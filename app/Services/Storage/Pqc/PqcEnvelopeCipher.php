<?php

declare(strict_types=1);

namespace App\Services\Storage\Pqc;

use App\Services\Storage\Exceptions\InvalidEnvelopeException;
use App\Services\Storage\Hsm\HsmCiphertext;
use App\Services\Storage\Hsm\HsmKeyManager;
use App\Services\Storage\Hsm\SoftwareHsmClient;
use App\Services\Storage\Hsm\WrappedDataKey;
use Illuminate\Support\Facades\Config;
use JsonException;
use RuntimeException;

final class PqcEnvelopeCipher
{
    public const KEM_ALG = 'ml-kem-1024';

    public const SIGNATURE_ALG = 'ml-dsa-87';

    private const CIPHER = 'aes-256-gcm';

    private const MODE_HSM_DIRECT = 'hsm-direct';

    private const MODE_WRAPPED_DEK = 'wrapped-dek';

    private readonly HsmKeyManager $hsm;

    public function __construct(?HsmKeyManager $hsm = null)
    {
        $this->hsm = $hsm ?? new HsmKeyManager(new SoftwareHsmClient);
    }

    /**
     * @return array<string, mixed>
     */
    public function encrypt(string $plaintext, int $version, string $alg, string $kid): array
    {
        $aad = $this->aad($version, $alg, $kid);
        $body = $this->shouldUseHsmDirect($plaintext)
            ? $this->encryptDirect($plaintext, $aad)
            : $this->encryptWithWrappedDek($plaintext, $aad);
        $body['provider'] = $this->hsm->driver();
        $body['kem_alg'] = self::KEM_ALG;
        $body['sig_alg'] = self::SIGNATURE_ALG;
        $body['signature'] = $this->sign($version, $alg, $kid, $body);

        return $body;
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    public function decrypt(array $envelope): string
    {
        $body = $envelope['body'] ?? null;
        if (! is_array($body)) {
            throw new InvalidEnvelopeException('V2 envelope body must be an object.');
        }

        foreach (['mode', 'provider', 'kem_alg', 'sig_alg', 'signature'] as $required) {
            if (! array_key_exists($required, $body)) {
                throw new InvalidEnvelopeException("V2 envelope missing required body field: {$required}");
            }
        }

        if ($body['kem_alg'] !== self::KEM_ALG || $body['sig_alg'] !== self::SIGNATURE_ALG) {
            throw new InvalidEnvelopeException('V2 envelope uses unsupported PQC metadata.');
        }

        $signature = (string) $body['signature'];
        $unsignedBody = $body;
        unset($unsignedBody['signature']);

        $expected = $this->sign(
            (int) $envelope['v'],
            (string) $envelope['alg'],
            (string) $envelope['kid'],
            $unsignedBody,
        );

        if (! hash_equals($expected, $signature)) {
            throw new InvalidEnvelopeException('V2 envelope signature verification failed.');
        }

        $aad = $this->aad((int) $envelope['v'], (string) $envelope['alg'], (string) $envelope['kid']);

        return match ($body['mode']) {
            self::MODE_HSM_DIRECT => $this->hsm->decryptSmallSecret(HsmCiphertext::fromEnvelope($body), $aad),
            self::MODE_WRAPPED_DEK => $this->decryptWithWrappedDek($body, $aad),
            default => throw new InvalidEnvelopeException('V2 envelope uses an unsupported HSM mode.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function encryptDirect(string $plaintext, string $aad): array
    {
        return [
            'mode' => self::MODE_HSM_DIRECT,
            ...$this->hsm->encryptSmallSecret($plaintext, $aad)->toEnvelope(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function encryptWithWrappedDek(string $plaintext, string $aad): array
    {
        $dek = random_bytes(32);
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $dek, OPENSSL_RAW_DATA, $nonce, $tag, $aad, 16);

        if ($ciphertext === false) {
            $this->hsm->zero($dek);

            throw new RuntimeException('Failed to encrypt v2 envelope body.');
        }

        $wrapped = $this->hsm->wrapDataKey($dek, $aad);
        $this->hsm->zero($dek);

        return [
            'mode' => self::MODE_WRAPPED_DEK,
            ...$wrapped->toEnvelope(),
            'nonce' => base64_encode($nonce),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function decryptWithWrappedDek(array $body, string $aad): string
    {
        foreach (['ciphertext', 'nonce', 'tag'] as $field) {
            if (! isset($body[$field]) || ! is_string($body[$field]) || $body[$field] === '') {
                throw new InvalidEnvelopeException("V2 envelope missing required wrapped-DEK body field: {$field}");
            }
        }

        $dek = $this->hsm->unwrapDataKey(WrappedDataKey::fromEnvelope($body), $aad);

        $plaintext = openssl_decrypt(
            $this->decodeBase64((string) $body['ciphertext'], 'ciphertext'),
            self::CIPHER,
            $dek,
            OPENSSL_RAW_DATA,
            $this->decodeBase64((string) $body['nonce'], 'nonce'),
            $this->decodeBase64((string) $body['tag'], 'tag'),
            $aad,
        );
        $this->hsm->zero($dek);

        if ($plaintext === false) {
            throw new InvalidEnvelopeException('Failed to decrypt v2 envelope body.');
        }

        return $plaintext;
    }

    private function shouldUseHsmDirect(string $plaintext): bool
    {
        $threshold = max(0, (int) Config::get('hsm.direct_secret_max_bytes', 4096));

        return $this->hsm->supportsDirectSecretEncryption()
            && strlen($plaintext) <= $threshold;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function sign(int $version, string $alg, string $kid, array $body): string
    {
        return base64_encode(hash_hmac(
            'sha384',
            $this->canonicalJson([
                'v' => $version,
                'alg' => $alg,
                'kid' => $kid,
                'body' => $body,
            ]),
            $this->softwareSigningKey(),
            true,
        ));
    }

    private function aad(int $version, string $alg, string $kid): string
    {
        return $this->canonicalJson([
            'v' => $version,
            'alg' => $alg,
            'kid' => $kid,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function canonicalJson(array $payload): string
    {
        $normalised = $this->sortKeys($payload);

        try {
            return json_encode($normalised, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new InvalidEnvelopeException(
                'Failed to canonicalise v2 envelope payload: '.$e->getMessage(),
                previous: $e,
            );
        }
    }

    private function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortKeys($item);
        }

        return $value;
    }

    private function softwareSigningKey(): string
    {
        return hash_hmac('sha256', 'fsa:pqc-envelope:sign', $this->appKeyMaterial(), true);
    }

    private function appKeyMaterial(): string
    {
        $appKey = (string) Config::get('app.key', '');

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);

            return $decoded === false ? '' : $decoded;
        }

        return $appKey;
    }

    private function decodeBase64(string $value, string $field): string
    {
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            throw new InvalidEnvelopeException("V2 envelope field is not valid base64: {$field}");
        }

        return $decoded;
    }
}
