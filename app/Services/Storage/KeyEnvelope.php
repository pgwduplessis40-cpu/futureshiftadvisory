<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Services\Storage\Exceptions\InvalidEnvelopeException;
use App\Services\Storage\Exceptions\UnsupportedEnvelopeVersionException;
use App\Services\Storage\Pqc\PqcEnvelopeCipher;
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
    /** Current default envelope version produced by encrypt() before FEATURE_PQC cutover. */
    public const CURRENT_VERSION = self::VERSION_V1;

    public const VERSION_V1 = 1;

    public const VERSION_V2 = 2;

    /** Algorithm tag for v1 envelopes. */
    public const ALG_V1 = 'aes-256-laravel';

    /** Algorithm tag for v2 envelopes. */
    public const ALG_V2 = 'ml-kem-1024+aes-256-gcm';

    private readonly PqcEnvelopeCipher $pqcCipher;

    public function __construct(
        private readonly Encrypter $encrypter,
        ?PqcEnvelopeCipher $pqcCipher = null,
    ) {
        $this->pqcCipher = $pqcCipher ?? new PqcEnvelopeCipher;
    }

    /**
     * Encrypt a plaintext string and return the JSON envelope.
     */
    public function encrypt(string $plaintext): string
    {
        return $this->encryptForVersion($plaintext, $this->writeVersion());
    }

    /**
     * Encrypt a plaintext string for a specific supported envelope version.
     */
    public function encryptForVersion(string $plaintext, int $version): string
    {
        $envelope = [
            'v' => $version,
            'alg' => $this->algorithmForVersion($version),
            'kid' => $this->currentKeyId($version),
        ];

        $envelope['body'] = match ($version) {
            self::VERSION_V1 => $this->encrypter->encryptString($plaintext),
            self::VERSION_V2 => $this->pqcCipher->encrypt(
                $plaintext,
                self::VERSION_V2,
                self::ALG_V2,
                $envelope['kid'],
            ),
            default => throw new UnsupportedEnvelopeVersionException(
                sprintf('Unsupported envelope version for encryption: %d', $version)
            ),
        };

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
        $this->assertSupportedEnvelopeMetadata($parsed);

        return match ((int) ($parsed['v'] ?? 0)) {
            self::VERSION_V1 => $this->encrypter->decryptString((string) $parsed['body']),
            self::VERSION_V2 => $this->pqcCipher->decrypt($parsed),
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
        $this->assertSupportedEnvelopeMetadata($parsed);

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

        foreach (['v', 'alg', 'kid', 'body'] as $required) {
            if (! array_key_exists($required, $decoded)) {
                throw new InvalidEnvelopeException("Envelope missing required field: {$required}");
            }
        }

        return $decoded;
    }

    public function writeVersion(): int
    {
        return (bool) Config::get('crypto.pqc.enabled', false)
            ? self::VERSION_V2
            : self::VERSION_V1;
    }

    public function algorithmForVersion(int $version): string
    {
        return match ($version) {
            self::VERSION_V1 => self::ALG_V1,
            self::VERSION_V2 => self::ALG_V2,
            default => throw new UnsupportedEnvelopeVersionException(
                sprintf('Unsupported envelope version: %d', $version)
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function assertSupportedEnvelopeMetadata(array $parsed): void
    {
        $version = (int) ($parsed['v'] ?? 0);
        $alg = (string) ($parsed['alg'] ?? '');

        if ($version === self::VERSION_V1 && $alg === self::ALG_V1) {
            return;
        }

        if ($version === self::VERSION_V2 && $alg === self::ALG_V2) {
            return;
        }

        if (in_array($version, [self::VERSION_V1, self::VERSION_V2], true)) {
            throw new UnsupportedEnvelopeVersionException(
                sprintf('Envelope version/algorithm mismatch: v%d cannot use %s', $version, $alg)
            );
        }

        throw new UnsupportedEnvelopeVersionException(
            sprintf('Unsupported envelope version: %s', $parsed['v'] ?? 'null')
        );
    }

    /**
     * Stable identifier for the active key. The body is the SHA-256 hash of
     * the raw APP_KEY, prefixed with the algorithm tag. Storing the kid in
     * each envelope lets future key rotations decrypt historical data
     * with the right key without scanning the whole table.
     */
    private function currentKeyId(int $version): string
    {
        $alg = $this->algorithmForVersion($version);
        $configuredKeyId = Config::get('crypto.pqc.key_id');
        if ($version === self::VERSION_V2 && is_string($configuredKeyId) && $configuredKeyId !== '') {
            return $alg.':'.$configuredKeyId;
        }

        $appKey = (string) Config::get('app.key', '');

        if ($appKey === '') {
            return $alg.':no-key';
        }

        $material = str_starts_with($appKey, 'base64:')
            ? base64_decode(substr($appKey, 7), true) ?: ''
            : $appKey;

        return $alg.':'.substr(hash('sha256', $material), 0, 16);
    }
}
