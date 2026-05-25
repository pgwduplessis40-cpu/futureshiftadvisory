<?php

declare(strict_types=1);

namespace App\Services\Storage\Hsm;

final readonly class HsmKeyManager
{
    public function __construct(private HsmClient $client) {}

    public function driver(): string
    {
        return $this->client->driver();
    }

    public function activeKeyId(): string
    {
        return $this->client->activeKeyId();
    }

    public function wrapDataKey(string $plaintextDataKey, string $aad): WrappedDataKey
    {
        return $this->client->wrapDataKey($plaintextDataKey, $aad);
    }

    public function unwrapDataKey(WrappedDataKey $wrappedDataKey, string $aad): string
    {
        return $this->client->unwrapDataKey($wrappedDataKey, $aad);
    }

    public function supportsDirectSecretEncryption(): bool
    {
        return $this->client->supportsDirectSecretEncryption();
    }

    public function encryptSmallSecret(string $plaintext, string $aad): HsmCiphertext
    {
        return $this->client->encryptSmallSecret($plaintext, $aad);
    }

    public function decryptSmallSecret(HsmCiphertext $ciphertext, string $aad): string
    {
        return $this->client->decryptSmallSecret($ciphertext, $aad);
    }

    public function rotateKek(?string $newKeyId = null): string
    {
        return $this->client->rotateKek($newKeyId);
    }

    public function zero(string &$keyMaterial): void
    {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($keyMaterial);

            return;
        }

        $keyMaterial = str_repeat("\0", strlen($keyMaterial));
    }
}
