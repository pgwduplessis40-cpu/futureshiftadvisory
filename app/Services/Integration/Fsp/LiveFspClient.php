<?php

declare(strict_types=1);

namespace App\Services\Integration\Fsp;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Fsp\Contracts\FspClient;
use App\Services\Integration\Resilience\ResilientHttp;
use Illuminate\Support\Facades\Config;

final class LiveFspClient implements FspClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly FakeFspClient $fake,
    ) {}

    public function lookup(string $fspNumber): array
    {
        $fspNumber = strtoupper(trim($fspNumber));

        if (! (bool) Config::get('integrations.fsp.live', false)) {
            throw IntegrationDisabledException::forService('fsp');
        }

        $apiKey = (string) Config::get('integrations.fsp.api_key', '');
        $endpoint = $apiKey === ''
            ? 'fsa-disabled://fsp/missing-api-key'
            : rtrim((string) Config::get('integrations.fsp.base_url'), '/').'/providers/'.$fspNumber;

        $result = $this->http->get(
            service: 'fsp',
            endpoint: $endpoint,
            query: $apiKey === '' ? [] : ['api_key' => $apiKey],
            cacheKey: "integration:fsp:{$fspNumber}",
            fallback: fn (): array => $this->fake->fallbackLookup($fspNumber),
        );

        return is_array($result->data)
            ? [
                ...$result->data,
                'fsp_number' => (string) ($result->data['fsp_number'] ?? $fspNumber),
                'source_badge' => $result->fromFallback ? 'stub_live_fallback' : ($result->fromCache ? 'cached' : 'live'),
                'degraded' => $result->fromFallback,
                'correlation_id' => $result->correlationId,
            ]
            : $this->fake->fallbackLookup($fspNumber);
    }
}
