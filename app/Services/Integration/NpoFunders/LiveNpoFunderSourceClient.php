<?php

declare(strict_types=1);

namespace App\Services\Integration\NpoFunders;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\NpoFunders\Contracts\NpoFunderSourceClient;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;

final class LiveNpoFunderSourceClient implements NpoFunderSourceClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly FakeNpoFunderSourceClient $fake,
    ) {}

    public function fetch(string $source): array
    {
        $source = $this->normaliseSource($source);
        $configPath = "integrations.npo_funders.sources.{$source}";

        if (! (bool) Config::get("{$configPath}.live", false)) {
            throw IntegrationDisabledException::forService("npo-funders:{$source}");
        }

        $endpoint = rtrim((string) Config::get("{$configPath}.base_url"), '/')
            .'/'.ltrim((string) Config::get("{$configPath}.path", ''), '/');
        $apiKey = (string) Config::get("{$configPath}.api_key", '');
        $query = $apiKey === '' ? [] : ['api_key' => $apiKey];

        $result = $this->http->get(
            service: "npo-funders:{$source}",
            endpoint: $endpoint,
            query: $query,
            cacheKey: "integration:npo-funders:{$source}",
            fallback: fn (): array => $this->fake->fallbackSource($source),
        );

        $payload = is_array($result->data)
            ? $result->data
            : $this->fake->fallbackSource($source);

        return [
            ...$payload,
            'source' => (string) ($payload['source'] ?? $source),
            'source_badge' => $result->fromFallback
                ? 'stub_live_fallback'
                : ($result->fromCache ? 'cached' : 'live'),
            'degraded' => $result->fromFallback || (bool) ($payload['degraded'] ?? false),
            'correlation_id' => $result->correlationId,
        ];
    }

    private function normaliseSource(string $source): string
    {
        return strtolower(trim($source));
    }
}
