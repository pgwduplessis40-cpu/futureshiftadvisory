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
use Illuminate\Support\Str;

final class LiveStatsNzClient implements StatsNzClient
{
    public function __construct(
        private readonly ResilientHttp $http,
        private readonly FakeStatsNzClient $fake,
        private readonly IntegrationActivationResolver $live,
        private readonly IntegrationCredentials $credentials,
        private readonly ManualStatsNzIndicatorSource $manualIndicators,
        private readonly SdmxJsonIndustryBenchmarkParser $parser,
        private readonly SdmxJsonIndicatorParser $indicatorParser,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function indicators(): array
    {
        if (! $this->live->isLive('stats_nz')) {
            throw IntegrationDisabledException::forService('stats-nz');
        }

        $datasets = $this->indicatorDatasets();
        if ($datasets === []) {
            $manualIndicators = $this->manualIndicators->indicators();

            return $manualIndicators === []
                ? $this->fake->fallbackIndicators()
                : $manualIndicators;
        }

        $indicators = [];

        foreach ($datasets as $dataset) {
            $result = $this->http->request(
                method: 'GET',
                service: 'stats-nz',
                endpoint: $this->datasetEndpoint($dataset),
                options: [
                    'headers' => $this->headers(),
                    'query' => $this->datasetQuery($dataset),
                ],
                cacheKey: 'integration:stats-nz:ade:indicator:'.(string) ($dataset['key'] ?? $dataset['indicator'] ?? $dataset['resourceId'] ?? 'dataset'),
                fallback: fn (): array => ['indicators' => $this->fallbackIndicatorsFor($dataset)],
            );

            $payload = is_array($result->data) ? $result->data : [];
            $records = is_array($payload['indicators'] ?? null)
                ? array_values(array_filter($payload['indicators'], 'is_array'))
                : $this->indicatorParser->parse($payload, $dataset);

            $indicators = [
                ...$indicators,
                ...array_map(
                    fn (array $indicator): array => $this->withTransportMeta($result, $this->withIndicatorDefaults($indicator, $dataset)),
                    $records,
                ),
            ];
        }

        return $indicators;
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
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>
     */
    private function withIndicatorDefaults(array $record, array $dataset): array
    {
        $indicator = (string) ($record['indicator'] ?? $dataset['indicator'] ?? $dataset['key'] ?? '');

        return [
            ...$record,
            'indicator' => $indicator,
            'label' => (string) ($record['label'] ?? $dataset['label'] ?? Str::headline($indicator)),
            'value' => (float) ($record['value'] ?? 0),
            'unit' => (string) ($record['unit'] ?? $dataset['unit'] ?? 'value'),
            'source' => (string) ($record['source'] ?? 'stats_nz'),
            'payload' => [
                ...((array) ($record['payload'] ?? [])),
                'dataset_key' => (string) ($dataset['key'] ?? $indicator),
                'resource_id' => (string) ($dataset['resourceId'] ?? ''),
                'version' => (string) ($dataset['version'] ?? '1.0'),
            ],
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
     * @return array<int, array<string, mixed>>
     */
    private function indicatorDatasets(): array
    {
        $datasets = Config::get('integrations.stats_nz.indicator_datasets', []);

        if (! is_array($datasets)) {
            return [];
        }

        return array_values(array_filter(
            $datasets,
            fn (mixed $dataset): bool => is_array($dataset)
                && trim((string) ($dataset['resourceId'] ?? '')) !== ''
                && trim((string) ($dataset['indicator'] ?? $dataset['key'] ?? '')) !== '',
        ));
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return array<int, array<string, mixed>>
     */
    private function fallbackIndicatorsFor(array $dataset): array
    {
        $indicator = (string) ($dataset['indicator'] ?? $dataset['key'] ?? '');

        return array_values(array_filter(
            $this->fake->fallbackIndicators(),
            fn (array $record): bool => $indicator === '' || (string) ($record['indicator'] ?? '') === $indicator,
        ));
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
