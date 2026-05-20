<?php

declare(strict_types=1);

namespace App\Services\Integration\Resilience;

use Illuminate\Http\Client\Response;

final readonly class IntegrationResult
{
    public function __construct(
        public string $status,
        public int $statusCode,
        public mixed $data,
        public ?string $body,
        public string $correlationId,
        public bool $fromCache = false,
        public bool $fromFallback = false,
    ) {}

    public static function fromResponse(Response $response, string $correlationId): self
    {
        $json = $response->json();

        return new self(
            status: 'success',
            statusCode: $response->status(),
            data: $json ?? $response->body(),
            body: $response->body(),
            correlationId: $correlationId,
        );
    }

    public static function cached(mixed $cached, string $correlationId): self
    {
        $payload = is_array($cached) ? $cached : ['json' => $cached];

        return new self(
            status: 'cached',
            statusCode: (int) ($payload['status_code'] ?? 200),
            data: $payload['json'] ?? $payload['body'] ?? $cached,
            body: isset($payload['body']) ? (string) $payload['body'] : null,
            correlationId: $correlationId,
            fromCache: true,
        );
    }

    public static function fallback(mixed $data, string $correlationId, int $statusCode = 503): self
    {
        return new self(
            status: 'fallback',
            statusCode: $statusCode,
            data: $data,
            body: is_string($data) ? $data : null,
            correlationId: $correlationId,
            fromFallback: true,
        );
    }

    public function successful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if (! is_array($this->data)) {
            return $key === null ? null : $default;
        }

        return $key === null ? $this->data : data_get($this->data, $key, $default);
    }
}
