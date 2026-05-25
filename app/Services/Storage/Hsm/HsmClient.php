<?php

declare(strict_types=1);

namespace App\Services\Storage\Hsm;

interface HsmClient
{
    public function driver(): string;

    public function activeKeyId(): string;

    public function wrapDataKey(string $plaintextDataKey, string $aad): WrappedDataKey;

    public function unwrapDataKey(WrappedDataKey $wrappedDataKey, string $aad): string;

    public function supportsDirectSecretEncryption(): bool;

    public function encryptSmallSecret(string $plaintext, string $aad): HsmCiphertext;

    public function decryptSmallSecret(HsmCiphertext $ciphertext, string $aad): string;

    public function rotateKek(?string $newKeyId = null): string;
}
