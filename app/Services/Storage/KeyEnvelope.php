<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Services\Storage\Exceptions\InvalidEnvelopeException;
use App\Services\Storage\Exceptions\UnsupportedEnvelopeVersionException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Config;
use JsonException;

/**
 * Tagged encryption envelope.
 *
 * Phase 1 implementation wraps Laravel's default encrypter (AES-256-CBC over
 * APP_KEY) inside a versioned envelope:
 *
 *   {"v":1,"alg":"aes-256-laravel","kid":"<hash>","body":"<base64-ciphertext>"}
 *
 * The envelope is the single sanctioned way to persist any "encrypted at
 * rest" payload (secure file storage, MFA secrets, signed PDF audit hashes,
 * encrypted JSONB columns). Direct calls to Crypt::encryptString are
 * forbidden by convention; everything goes through KeyEnvelope so that the
 * Phase 4 PQC swap-in (CRYSTALS-Kyber + AES-256-GCM) is a single-file
 * change that decrypts every historical envelope while writing new ones in
 * the post-quantum format.
 *
 * @see PLAN.md section 7.1 - cross-cutting foundations
 * @see docs/architecture/security-decisions.md SD-01, SD-02
 * @see docs/architecture/key-envelope.md
 */
final class KeyEnvelope
{
    /** Current envelope version produced by encrypt(). */
    public const CURRENT_VERSION = 1;

    /** Algorithm tag for v1 envelopes. */
    public const ALG_V1 = 'aes-256-laravel';

    public function __construct(private readonly Encrypter $encrypter) {}

    /**
     * Encrypt a plaintext string and return the JSON envelope.
     */
    public function encrypt(string $plaintext): string
    {
        $envelope = [
            'v' => self::CURRENT_VERSION,
            'alg' => self::ALG_V1,
            'kid' => $this->currentKeyId(),
            'body' => $this->encrypter->encryptString($plaintext),
        ];

        try {
            return json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            throw new InvalidEnvelopeException(
                'Failed to encode envelope JSON: '.$e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Decrypt a JSON envelope produced by any supported version of this
     * service. The version dispatch is the seam where PQC envelopes will
     * be added without disturbing historical AES-only envelopes.
     */
    public function decrypt(string $envelope): string
    {
        $parsed = $this->parse($envelope);

        return match ((int) ($parsed['v'] ?? 0)) {
            1 => $this->encrypter->decryptString((string) $parsed['body']),
            default => throw new UnsupportedEnvelopeVersionException(
                sprintf('Unsupported envelope version: %s', $parsed['v'] ?? 'null')
            ),
        };
    }

    /**
     * Inspect an envelope without decrypting it. Useful for diagnostics
     * and for future migrations that need to bulk-rewrap envelopes after a
     * key rotation or PQC upgrade.
     *
     * @return array{v:int, alg:string, kid:string}
     */
    public function inspect(string $envelope): array
    {
        $parsed = $this->parse($envelope);

        return [
            'v' => (int) ($parsed['v'] ?? 0),
            'alg' => (string) ($parsed['alg'] ?? ''),
            'kid' => (string) ($parsed['kid'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parse(string $envelope): array
    {
        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($envelope, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidEnvelopeException(
                'Envelope is not valid JSON: '.$e->getMessage(),
                previous: $e,
            );
        }

        foreach (['v', 'alg', 'body'] as $required) {
            if (! array_key_exists($required, $decoded)) {
                throw new InvalidEnvelopeException("Envelope missing required field: {$required}");
            }
        }

        return $decoded;
    }

    /**
     * Stable identifier for the active key. The body is the SHA-256 hash of
     * the raw APP_KEY, prefixed with the algorithm tag. Storing the kid in
     * each envelope lets future key rotations decrypt historical data
     * with the right key without scanning the whole table.
     */
    private function currentKeyId(): string
    {
        $appKey = (string) Config::get('app.key', '');

        if ($appKey === '') {
            return self::ALG_V1.':no-key';
        }

        $material = str_starts_with($appKey, 'base64:')
            ? base64_decode(substr($appKey, 7), true) ?: ''
            : $appKey;

        return self::ALG_V1.':'.substr(hash('sha256', $material), 0, 16);
    }
}
