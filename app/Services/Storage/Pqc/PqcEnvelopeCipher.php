<?php

declare(strict_types=1);

namespace App\Services\Storage\Pqc;

use App\Services\Storage\Exceptions\InvalidEnvelopeException;
use Illuminate\Support\Facades\Config;
use JsonException;
use RuntimeException;

final class PqcEnvelopeCipher
{
    public const KEM_ALG = 'ml-kem-1024';

    public const SIGNATURE_ALG = 'ml-dsa-87';

    private const CIPHER = 'aes-256-gcm';

    /**
     * @return array<string, mixed>
     */
    public function encrypt(string $plaintext, int $version, string $alg, string $kid): array
    {
        $dek = random_bytes(32);
        $nonce = random_bytes(12);
        $tag = '';
        $aad = $this->aad($version, $alg, $kid);
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $dek, OPENSSL_RAW_DATA, $nonce, $tag, $aad, 16);

        if ($ciphertext === false) {
            $this->zero($dek);

            throw new RuntimeException('Failed to encrypt v2 envelope body.');
        }

        $wrapped = $this->wrapDek($dek, $aad);
        $this->zero($dek);

        $body = [
            'provider' => (string) Config::get('crypto.pqc.provider', 'software'),
            'kem_alg' => self::KEM_ALG,
            'sig_alg' => self::SIGNATURE_ALG,
            'encapsulated_key' => $wrapped['ciphertext'],
            'wrap_nonce' => $wrapped['nonce'],
            'wrap_tag' => $wrapped['tag'],
            'nonce' => base64_encode($nonce),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
        ];
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

        foreach ([
            'kem_alg',
            'sig_alg',
            'encapsulated_key',
            'wrap_nonce',
            'wrap_tag',
            'nonce',
            'tag',
            'ciphertext',
            'signature',
        ] as $required) {
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
        $dek = $this->unwrapDek(
            ciphertext: $this->decodeBase64((string) $body['encapsulated_key'], 'encapsulated_key'),
            nonce: $this->decodeBase64((string) $body['wrap_nonce'], 'wrap_nonce'),
            tag: $this->decodeBase64((string) $body['wrap_tag'], 'wrap_tag'),
            aad: $aad,
        );

        $plaintext = openssl_decrypt(
            $this->decodeBase64((string) $body['ciphertext'], 'ciphertext'),
            self::CIPHER,
            $dek,
            OPENSSL_RAW_DATA,
            $this->decodeBase64((string) $body['nonce'], 'nonce'),
            $this->decodeBase64((string) $body['tag'], 'tag'),
            $aad,
        );
        $this->zero($dek);

        if ($plaintext === false) {
            throw new InvalidEnvelopeException('Failed to decrypt v2 envelope body.');
        }

        return $plaintext;
    }

    /**
     * @return array{ciphertext: string, nonce: string, tag: string}
     */
    private function wrapDek(string $dek, string $aad): array
    {
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($dek, self::CIPHER, $this->softwareWrappingKey(), OPENSSL_RAW_DATA, $nonce, $tag, $aad, 16);

        if ($ciphertext === false) {
            throw new RuntimeException('Failed to wrap v2 envelope data key.');
        }

        return [
            'ciphertext' => base64_encode($ciphertext),
            'nonce' => base64_encode($nonce),
            'tag' => base64_encode($tag),
        ];
    }

    private function unwrapDek(string $ciphertext, string $nonce, string $tag, string $aad): string
    {
        $dek = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->softwareWrappingKey(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad,
        );

        if ($dek === false || strlen($dek) !== 32) {
            throw new InvalidEnvelopeException('Failed to unwrap v2 envelope data key.');
        }

        return $dek;
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

    private function softwareWrappingKey(): string
    {
        return hash_hmac('sha256', 'fsa:pqc-envelope:wrap', $this->appKeyMaterial(), true);
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

    private function zero(string &$value): void
    {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($value);

            return;
        }

        $value = str_repeat("\0", strlen($value));
    }
}
