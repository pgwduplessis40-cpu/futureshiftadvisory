<?php

declare(strict_types=1);

namespace App\Services\Storage\Hsm;

use App\Services\Storage\Exceptions\InvalidEnvelopeException;

final readonly class WrappedDataKey
{
    public function __construct(
        public string $ciphertext,
        public string $nonce,
        public string $tag,
        public string $keyId,
        public string $driver,
    ) {}

    /**
     * @return array{encapsulated_key: string, wrap_nonce: string, wrap_tag: string, hsm_key_id: string, hsm_driver: string}
     */
    public function toEnvelope(): array
    {
        return [
            'encapsulated_key' => $this->ciphertext,
            'wrap_nonce' => $this->nonce,
            'wrap_tag' => $this->tag,
            'hsm_key_id' => $this->keyId,
            'hsm_driver' => $this->driver,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromEnvelope(array $body): self
    {
        foreach (['encapsulated_key', 'wrap_nonce', 'wrap_tag', 'hsm_key_id', 'hsm_driver'] as $field) {
            if (! isset($body[$field]) || ! is_string($body[$field]) || $body[$field] === '') {
                throw new InvalidEnvelopeException("V2 envelope missing required wrapped-key field: {$field}");
            }
        }

        return new self(
            ciphertext: $body['encapsulated_key'],
            nonce: $body['wrap_nonce'],
            tag: $body['wrap_tag'],
            keyId: $body['hsm_key_id'],
            driver: $body['hsm_driver'],
        );
    }
}
