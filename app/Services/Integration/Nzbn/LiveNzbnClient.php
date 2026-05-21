<?php

declare(strict_types=1);

namespace App\Services\Integration\Nzbn;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Nzbn\Contracts\NzbnClient;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;

final class LiveNzbnClient implements NzbnClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly FakeNzbnClient $fake,
    ) {}

    public function lookupByNzbn(string $nzbn): array
    {
        if (! (bool) Config::get('integrations.nzbn.live', false)) {
            throw IntegrationDisabledException::forService('nzbn');
        }

        $apiKey = (string) Config::get('integrations.nzbn.api_key', '');
        $endpoint = $apiKey === ''
            ? 'fsa-disabled://nzbn/missing-api-key'
            : rtrim((string) Config::get('integrations.nzbn.base_url'), '/').'/entities/'.$nzbn;

        $result = $this->http->get(
            service: 'nzbn',
            endpoint: $endpoint,
            query: $apiKey === '' ? [] : ['api_key' => $apiKey],
            cacheKey: "integration:nzbn:{$nzbn}",
            fallback: fn (): array => $this->fake->fallbackLookupByNzbn($nzbn),
        );

        return is_array($result->data)
            ? [
                ...$result->data,
                'source_badge' => $result->fromFallback ? 'stub_live_fallback' : ($result->fromCache ? 'cached' : 'live'),
                'degraded' => $result->fromFallback,
                'correlation_id' => $result->correlationId,
            ]
            : $this->fake->fallbackLookupByNzbn($nzbn);
    }
}
