<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Services\Storage\Hsm\HsmCiphertext;
use App\Services\Storage\Hsm\HsmClient;
use App\Services\Storage\Hsm\HsmKeyManager;
use App\Services\Storage\Hsm\SoftwareHsmClient;
use App\Services\Storage\Hsm\WrappedDataKey;
use App\Services\Storage\KeyEnvelope;
use App\Services\Storage\Pqc\PqcEnvelopeCipher;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class HsmKeyManagerTest extends TestCase
{
    public function test_software_hsm_wraps_and_unwraps_without_exporting_kek(): void
    {
        $client = new SoftwareHsmClient;
        $manager = new HsmKeyManager($client);

        $wrapped = $manager->wrapDataKey(str_repeat('K', 32), 'aad');

        $this->assertFalse(method_exists($client, 'exportKek'));
        $this->assertSame(str_repeat('K', 32), $manager->unwrapDataKey($wrapped, 'aad'));
        $this->assertNotSame(str_repeat('K', 32), $wrapped->ciphertext);
    }

    public function test_small_v2_payloads_use_hsm_direct_encryption(): void
    {
        $client = new RecordingHsmClient;
        $envelope = $this->envelopeFor($client);

        Config::set('crypto.pqc.enabled', true);
        Config::set('hsm.direct_secret_max_bytes', 4096);

        $ciphertext = $envelope->encrypt('small secret');
        $decoded = json_decode($ciphertext, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('hsm-direct', $decoded['body']['mode']);
        $this->assertSame('small secret', $envelope->decrypt($ciphertext));
        $this->assertSame(1, $client->directEncrypts);
        $this->assertSame(1, $client->directDecrypts);
        $this->assertSame(0, $client->wraps);
        $this->assertSame(0, $client->unwraps);
    }

    public function test_bulk_v2_payloads_use_hsm_wrapped_data_keys(): void
    {
        $client = new RecordingHsmClient;
        $envelope = $this->envelopeFor($client);
        $plaintext = str_repeat('bulk-payload-', 1024);

        Config::set('crypto.pqc.enabled', true);
        Config::set('hsm.direct_secret_max_bytes', 4);

        $ciphertext = $envelope->encrypt($plaintext);
        $decoded = json_decode($ciphertext, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('wrapped-dek', $decoded['body']['mode']);
        $this->assertSame($plaintext, $envelope->decrypt($ciphertext));
        $this->assertSame(0, $client->directEncrypts);
        $this->assertSame(0, $client->directDecrypts);
        $this->assertSame(1, $client->wraps);
        $this->assertSame(1, $client->unwraps);
    }

    public function test_rotation_updates_active_software_key_reference(): void
    {
        $manager = new HsmKeyManager(new SoftwareHsmClient);
        $before = $manager->activeKeyId();

        $after = $manager->rotateKek('test-hsm-key-v2');

        $this->assertNotSame($before, $after);
        $this->assertSame('test-hsm-key-v2', $manager->activeKeyId());
    }

    public function test_key_manager_zeroes_transient_key_material(): void
    {
        $manager = new HsmKeyManager(new SoftwareHsmClient);
        $key = str_repeat('Z', 32);

        $manager->zero($key);

        $this->assertNotSame(str_repeat('Z', 32), $key);
        $this->assertTrue($key === null || $key === '' || $key === str_repeat("\0", 32));
    }

    private function envelopeFor(HsmClient $client): KeyEnvelope
    {
        /** @var Encrypter $encrypter */
        $encrypter = app(Encrypter::class);

        return new KeyEnvelope(
            $encrypter,
            new PqcEnvelopeCipher(new HsmKeyManager($client)),
        );
    }
}

final class RecordingHsmClient implements HsmClient
{
    public int $wraps = 0;

    public int $unwraps = 0;

    public int $directEncrypts = 0;

    public int $directDecrypts = 0;

    private string $keyId = 'recording-key-v1';

    private SoftwareHsmClient $software;

    public function __construct()
    {
        $this->software = new SoftwareHsmClient;
    }

    public function driver(): string
    {
        return 'recording';
    }

    public function activeKeyId(): string
    {
        return $this->keyId;
    }

    public function wrapDataKey(string $plaintextDataKey, string $aad): WrappedDataKey
    {
        $this->wraps++;
        $wrapped = $this->software->wrapDataKey($plaintextDataKey, $aad);

        return new WrappedDataKey($wrapped->ciphertext, $wrapped->nonce, $wrapped->tag, $this->keyId, $this->driver());
    }

    public function unwrapDataKey(WrappedDataKey $wrappedDataKey, string $aad): string
    {
        $this->unwraps++;

        return $this->software->unwrapDataKey(
            new WrappedDataKey(
                $wrappedDataKey->ciphertext,
                $wrappedDataKey->nonce,
                $wrappedDataKey->tag,
                $wrappedDataKey->keyId,
                'software',
            ),
            $aad,
        );
    }

    public function supportsDirectSecretEncryption(): bool
    {
        return true;
    }

    public function encryptSmallSecret(string $plaintext, string $aad): HsmCiphertext
    {
        $this->directEncrypts++;
        $ciphertext = $this->software->encryptSmallSecret($plaintext, $aad);

        return new HsmCiphertext($ciphertext->ciphertext, $ciphertext->nonce, $ciphertext->tag, $this->keyId, $this->driver());
    }

    public function decryptSmallSecret(HsmCiphertext $ciphertext, string $aad): string
    {
        $this->directDecrypts++;

        return $this->software->decryptSmallSecret(
            new HsmCiphertext(
                $ciphertext->ciphertext,
                $ciphertext->nonce,
                $ciphertext->tag,
                $ciphertext->keyId,
                'software',
            ),
            $aad,
        );
    }

    public function rotateKek(?string $newKeyId = null): string
    {
        $this->keyId = $newKeyId ?: 'recording-key-v2';

        return $this->keyId;
    }
}
