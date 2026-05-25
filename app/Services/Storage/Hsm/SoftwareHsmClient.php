<?php

declare(strict_types=1);

namespace App\Services\Storage\Hsm;

use App\Services\Storage\Exceptions\InvalidEnvelopeException;
use Illuminate\Support\Facades\Config;
use RuntimeException;

final class SoftwareHsmClient implements HsmClient
{
    private const CIPHER = 'aes-256-gcm';

    public function driver(): string
    {
        return 'software';
    }

    public function activeKeyId(): string
    {
        $configured = Config::get('hsm.key_id');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return 'software:'.substr(hash('sha256', $this->rootKey()), 0, 16);
    }

    public function wrapDataKey(string $plaintextDataKey, string $aad): WrappedDataKey
    {
        $sealed = $this->seal($plaintextDataKey, $aad, 'wrap');

        return new WrappedDataKey(
            ciphertext: $sealed['ciphertext'],
            nonce: $sealed['nonce'],
            tag: $sealed['tag'],
            keyId: $this->activeKeyId(),
            driver: $this->driver(),
        );
    }

    public function unwrapDataKey(WrappedDataKey $wrappedDataKey, string $aad): string
    {
        if ($wrappedDataKey->driver !== $this->driver()) {
            throw new InvalidEnvelopeException('Wrapped data key belongs to a different HSM driver.');
        }

        return $this->open(
            ciphertext: $wrappedDataKey->ciphertext,
            nonce: $wrappedDataKey->nonce,
            tag: $wrappedDataKey->tag,
            aad: $aad,
            label: 'wrap',
        );
    }

    public function supportsDirectSecretEncryption(): bool
    {
        return true;
    }

    public function encryptSmallSecret(string $plaintext, string $aad): HsmCiphertext
    {
        $sealed = $this->seal($plaintext, $aad, 'direct');

        return new HsmCiphertext(
            ciphertext: $sealed['ciphertext'],
            nonce: $sealed['nonce'],
            tag: $sealed['tag'],
            keyId: $this->activeKeyId(),
            driver: $this->driver(),
        );
    }

    public function decryptSmallSecret(HsmCiphertext $ciphertext, string $aad): string
    {
        if ($ciphertext->driver !== $this->driver()) {
            throw new InvalidEnvelopeException('HSM ciphertext belongs to a different HSM driver.');
        }

        return $this->open(
            ciphertext: $ciphertext->ciphertext,
            nonce: $ciphertext->nonce,
            tag: $ciphertext->tag,
            aad: $aad,
            label: 'direct',
        );
    }

    public function rotateKek(?string $newKeyId = null): string
    {
        $keyId = $newKeyId ?: 'software:'.substr(hash('sha256', random_bytes(32)), 0, 16);
        Config::set('hsm.key_id', $keyId);

        return $keyId;
    }

    /**
     * @return array{ciphertext: string, nonce: string, tag: string}
     */
    private function seal(string $plaintext, string $aad, string $label): array
    {
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->derivedKey($label), OPENSSL_RAW_DATA, $nonce, $tag, $aad, 16);

        if ($ciphertext === false) {
            throw new RuntimeException('Software HSM encryption failed.');
        }

        return [
            'ciphertext' => base64_encode($ciphertext),
            'nonce' => base64_encode($nonce),
            'tag' => base64_encode($tag),
        ];
    }

    private function open(string $ciphertext, string $nonce, string $tag, string $aad, string $label): string
    {
        $plaintext = openssl_decrypt(
            $this->decodeBase64($ciphertext, 'ciphertext'),
            self::CIPHER,
            $this->derivedKey($label),
            OPENSSL_RAW_DATA,
            $this->decodeBase64($nonce, 'nonce'),
            $this->decodeBase64($tag, 'tag'),
            $aad,
        );

        if ($plaintext === false) {
            throw new InvalidEnvelopeException('Software HSM decryption failed.');
        }

        return $plaintext;
    }

    private function derivedKey(string $label): string
    {
        return hash_hmac('sha256', 'fsa:hsm:'.$label, $this->rootKey(), true);
    }

    private function rootKey(): string
    {
        $configured = Config::get('hsm.software_key');
        if (is_string($configured) && $configured !== '') {
            return str_starts_with($configured, 'base64:')
                ? (base64_decode(substr($configured, 7), true) ?: $configured)
                : $configured;
        }

        $appKey = (string) Config::get('app.key', '');

        return str_starts_with($appKey, 'base64:')
            ? (base64_decode(substr($appKey, 7), true) ?: $appKey)
            : $appKey;
    }

    private function decodeBase64(string $value, string $field): string
    {
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            throw new InvalidEnvelopeException("Software HSM field is not valid base64: {$field}");
        }

        return $decoded;
    }
}
