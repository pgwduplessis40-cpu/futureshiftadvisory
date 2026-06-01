<?php

declare(strict_types=1);

namespace App\Services\Integration\StatsNz;

use App\Services\Integration\Exceptions\IntegrationDisabledException;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\Resilience\IntegrationResult;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\StatsNz\Contracts\StatsNzClient;
use Illuminate\Support\Facades\Config;

final class LiveStatsNzClient implements StatsNzClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly FakeStatsNzClient $fake,
        private readonly IntegrationActivationResolver $live,
        private readonly IntegrationCredentials $credentials,
        private readonly ManualStatsNzIndicatorSource $manualIndicators,
        private readonly SdmxJsonIndustryBenchmarkParser $parser,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function indicators(): array
    {
        return $this->manualIndicators->indicators();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function industryBenchmarks(): array
    {
        if (! $this->live->isLive('stats_nz')) {
            throw IntegrationDisabledException::forService('stats-nz');
        }

        $benchmarks = [];

        foreach ($this->datasets() as $dataset) {
            $result = $this->http->request(
                method: 'GET',
                service: 'stats-nz',
                endpoint: $this->datasetEndpoint($dataset),
                options: [
                    'headers' => $this->headers(),
                    'query' => $this->datasetQuery($dataset),
                ],
                cacheKey: 'integration:stats-nz:ade:'.(string) ($dataset['key'] ?? $dataset['resourceId'] ?? 'dataset'),
                fallback: fn (): array => ['benchmarks' => $this->fake->fallbackIndustryBenchmarks()],
            );

            $payload = is_array($result->data) ? $result->data : [];
            $records = is_array($payload['benchmarks'] ?? null)
                ? array_values(array_filter($payload['benchmarks'], 'is_array'))
                : $this->parser->parse($payload, $dataset);

            $benchmarks = [
                ...$benchmarks,
                ...array_map(
                    fn (array $benchmark): array => $this->withTransportMeta($result, $benchmark),
                    $records,
                ),
            ];
        }

        return $benchmarks;
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function datasets(): array
    {
        $datasets = Config::get('integrations.stats_nz.datasets', []);

        return is_array($datasets)
            ? array_values(array_filter($datasets, 'is_array'))
            : [];
    }

    /**
     * @param  array<string, mixed>  $dataset
     */
    private function datasetEndpoint(array $dataset): string
    {
        $resourceId = trim((string) ($dataset['resourceId'] ?? ''));
        $version = trim((string) ($dataset['version'] ?? '1.0'));
        $key = trim((string) ($dataset['sdmx_key'] ?? 'all'));

        return rtrim((string) Config::get('integrations.stats_nz.base_url'), '/')
            .'/data/STATSNZ,'.$resourceId.','.$version.'/'.$key;
    }

    /**
     * @return array<string, mixed>
     */
    private function headers(): array
    {
        return [
            'Accept' => 'application/vnd.sdmx.data+json;version=1.0.0',
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey(),
            'User-Agent' => 'futureshiftadvisory/1.0 (Language=php)',
        ];
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>
     */
    private function datasetQuery(array $dataset): array
    {
        return [
            'dimensionAtObservation' => (string) ($dataset['dimensionAtObservation'] ?? 'AllDimensions'),
            'detail' => 'full',
            'format' => 'jsondata',
        ];
    }

    private function subscriptionKey(): string
    {
        return (string) ($this->credentials->get('stats_nz', 'subscription_key') ?? '');
    }
}
