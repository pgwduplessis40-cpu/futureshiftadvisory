<?php

declare(strict_types=1);

namespace App\Services\Storage\Hsm;

use RuntimeException;

final readonly class UnsupportedHsmClient implements HsmClient
{
    public function __construct(private string $driverName) {}

    public function driver(): string
    {
        return $this->driverName;
    }

    public function activeKeyId(): string
    {
        return $this->driverName.':unconfigured';
    }

    public function wrapDataKey(string $plaintextDataKey, string $aad): WrappedDataKey
    {
        throw $this->exception();
    }

    public function unwrapDataKey(WrappedDataKey $wrappedDataKey, string $aad): string
    {
        throw $this->exception();
    }

    public function supportsDirectSecretEncryption(): bool
    {
        return false;
    }

    public function encryptSmallSecret(string $plaintext, string $aad): HsmCiphertext
    {
        throw $this->exception();
    }

    public function decryptSmallSecret(HsmCiphertext $ciphertext, string $aad): string
    {
        throw $this->exception();
    }

    public function rotateKek(?string $newKeyId = null): string
    {
        throw $this->exception();
    }

    private function exception(): RuntimeException
    {
        return new RuntimeException("HSM driver [{$this->driverName}] is not bound in this environment.");
    }
}
