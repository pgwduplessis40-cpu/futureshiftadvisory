<?php

declare(strict_types=1);

namespace App\Services\Storage\Hsm;

use App\Services\Storage\Exceptions\InvalidEnvelopeException;

final readonly class HsmCiphertext
{
    public function __construct(
        public string $ciphertext,
        public string $nonce,
        public string $tag,
        public string $keyId,
        public string $driver,
    ) {}

    /**
     * @return array{ciphertext: string, nonce: string, tag: string, hsm_key_id: string, hsm_driver: string}
     */
    public function toEnvelope(): array
    {
        return [
            'ciphertext' => $this->ciphertext,
            'nonce' => $this->nonce,
            'tag' => $this->tag,
            'hsm_key_id' => $this->keyId,
            'hsm_driver' => $this->driver,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromEnvelope(array $body): self
    {
        foreach (['ciphertext', 'nonce', 'tag', 'hsm_key_id', 'hsm_driver'] as $field) {
            if (! isset($body[$field]) || ! is_string($body[$field]) || $body[$field] === '') {
                throw new InvalidEnvelopeException("V2 envelope missing required HSM ciphertext field: {$field}");
            }
        }

        return new self(
            ciphertext: $body['ciphertext'],
            nonce: $body['nonce'],
            tag: $body['tag'],
            keyId: $body['hsm_key_id'],
            driver: $body['hsm_driver'],
        );
    }
}
