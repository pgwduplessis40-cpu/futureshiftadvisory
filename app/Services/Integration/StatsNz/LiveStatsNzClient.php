<?php

declare(strict_types=1);

namespace App\Services\Integration\StatsNz;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\Resilience\IntegrationResult;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\StatsNz\Contracts\StatsNzClient;
use Illuminate\Support\Facades\Config;

final class LiveStatsNzClient implements StatsNzClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly FakeStatsNzClient $fake,
    ) {}

    public function indicators(): array
    {
        if (! (bool) Config::get('integrations.stats_nz.live', false)) {
            throw IntegrationDisabledException::forService('stats-nz');
        }

        $result = $this->http->get(
            service: 'stats-nz',
            endpoint: $this->endpoint(),
            query: $this->query(),
            cacheKey: 'integration:stats-nz:economic-indicators',
            fallback: fn (): array => $this->fake->fallbackIndicators(),
        );

        $payload = is_array($result->data) ? $result->data : $this->fake->fallbackIndicators();
        $indicators = array_is_list($payload) ? $payload : (array) ($payload['indicators'] ?? []);

        return array_values(array_map(
            fn (array $indicator): array => $this->withTransportMeta($result, $indicator),
            array_filter($indicators, 'is_array'),
        ));
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function withTransportMeta(IntegrationResult $result, array $record): array
    {
        return [
            ...$record,
            'source' => (string) ($record['source'] ?? 'stats_nz'),
            'source_badge' => $result->fromFallback ? 'stub_live_fallback' : ($result->fromCache ? 'cached' : 'live'),
            'degraded' => $result->fromFallback || (bool) ($record['degraded'] ?? false),
            'correlation_id' => $result->correlationId,
        ];
    }

    private function endpoint(): string
    {
        $apiKey = (string) Config::get('integrations.stats_nz.api_key', '');

        return $apiKey === ''
            ? 'fsa-disabled://stats-nz/missing-api-key/economic-indicators'
            : rtrim((string) Config::get('integrations.stats_nz.base_url'), '/').'/economic-indicators';
    }

    /**
     * @return array<string, mixed>
     */
    private function query(): array
    {
        $apiKey = (string) Config::get('integrations.stats_nz.api_key', '');

        return $apiKey === '' ? [] : ['api_key' => $apiKey];
    }
}
