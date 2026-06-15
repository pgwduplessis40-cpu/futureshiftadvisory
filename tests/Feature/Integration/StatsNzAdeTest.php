<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\EconomicIndicator;
use App\Models\User;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Integration\IntegrationCredentials;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Resilience\RetryPolicy;
use App\Services\Integration\StatsNz\Contracts\StatsNzClient;
use App\Services\Integration\StatsNz\FakeStatsNzClient;
use App\Services\Integration\StatsNz\FallbackStatsNzClient;
use App\Services\Integration\StatsNz\LiveStatsNzClient;
use App\Services\Integration\StatsNz\ManualStatsNzIndicatorSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class StatsNzAdeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Config::set('integrations.retry.attempts', 1);
        Config::set('integrations.retry.base_delay_ms', 0);
        Config::set('integrations.retry.max_delay_ms', 0);
        Config::set('integrations.stats_nz.base_url', 'https://api.data.stats.govt.nz/rest');
        Config::set('integrations.stats_nz.datasets', [$this->datasetConfig()]);
        Config::set('integrations.stats_nz.indicator_datasets', []);
        $this->forgetStatsNzClients();
    }

    public function test_live_industry_benchmarks_use_vault_key_and_explicit_sdmx_headers(): void
    {
        Config::set('integrations.stats_nz.live', false);
        Config::set('integrations.stats_nz.api_key', 'env-key-should-not-be-used');
        $admin = User::factory()->superAdmin()->create();

        app(IntegrationCredentials::class)->set('stats_nz', 'subscription_key', 'vaulted-stats-key', $admin);
        app(IntegrationActivationResolver::class)->activate('stats_nz', $admin);
        $this->forgetStatsNzClients();

        Http::fake([
            'api.data.stats.govt.nz/*' => Http::response($this->fixturePayload(), 200),
        ]);

        $records = app(StatsNzClient::class)->industryBenchmarks();

        $this->assertSame('live', $records[0]['source_badge']);
        $this->assertFalse($records[0]['degraded']);
        $this->assertSame('A', $records[2]['industry_code']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/rest/data/STATSNZ,BD_BD_001,1.0/all')
                && str_contains($request->url(), 'dimensionAtObservation=AllDimensions')
                && str_contains($request->url(), 'format=jsondata')
                && $request->hasHeader('Ocp-Apim-Subscription-Key', 'vaulted-stats-key')
                && $request->hasHeader('Accept', 'application/vnd.sdmx.data+json;version=1.0.0');
        });
    }

    public function test_revoked_stats_nz_credential_uses_degraded_fixture_without_live_call(): void
    {
        Config::set('integrations.stats_nz.live', true);
        Config::set('integrations.stats_nz.api_key', 'env-stats-key');
        $admin = User::factory()->superAdmin()->create();

        $credentials = app(IntegrationCredentials::class);
        $credentials->set('stats_nz', 'subscription_key', 'vaulted-stats-key', $admin);
        $credentials->revoke('stats_nz', 'subscription_key', $admin);
        $this->forgetStatsNzClients();

        Http::fake();

        $records = app(StatsNzClient::class)->industryBenchmarks();

        Http::assertNothingSent();
        $this->assertNotEmpty($records);
        $this->assertSame('stub_live_fallback', $records[0]['source_badge']);
        $this->assertTrue($records[0]['degraded']);
        $this->assertFalse(app(IntegrationActivationResolver::class)->isLive('stats_nz'));
    }

    public function test_disabled_macro_indicators_use_fixture_feed_without_live_call(): void
    {
        Config::set('integrations.stats_nz.live', false);
        $this->forgetStatsNzClients();
        Http::fake();

        $records = app(StatsNzClient::class)->indicators();

        Http::assertNothingSent();
        $this->assertCount(3, $records);
        $this->assertSame(EconomicIndicator::GDP_QUARTERLY, $records[1]['indicator']);
        $this->assertSame(0.7, $records[1]['value']);
        $this->assertSame(EconomicIndicator::UNEMPLOYMENT_RATE, $records[2]['indicator']);
        $this->assertSame(4.3, $records[2]['value']);
        $this->assertSame('stub', $records[2]['source_badge']);
    }

    public function test_live_macro_indicators_use_vault_key_and_explicit_ade_dataset_config(): void
    {
        Config::set('integrations.stats_nz.live', false);
        Config::set('integrations.stats_nz.api_key', 'env-key-should-not-be-used');
        Config::set('integrations.stats_nz.indicator_datasets', [[
            'key' => 'gdp_quarterly',
            'indicator' => EconomicIndicator::GDP_QUARTERLY,
            'label' => 'Gross Domestic Product quarterly change',
            'resourceId' => 'GDP_TEST',
            'version' => '1.0',
            'sdmx_key' => 'all',
            'dimensionAtObservation' => 'AllDimensions',
            'unit' => 'percent',
        ]]);
        $admin = User::factory()->superAdmin()->create();

        app(IntegrationCredentials::class)->set('stats_nz', 'subscription_key', 'vaulted-stats-key', $admin);
        app(IntegrationActivationResolver::class)->activate('stats_nz', $admin);
        $this->forgetStatsNzClients();

        Http::fake([
            'api.data.stats.govt.nz/*' => Http::response([
                'indicators' => [[
                    'indicator' => EconomicIndicator::GDP_QUARTERLY,
                    'label' => 'Gross Domestic Product quarterly change',
                    'value' => 0.2,
                    'unit' => 'percent',
                    'period_date' => '2026-06-30',
                    'source' => 'stats_nz',
                    'payload' => ['series' => 'GDP_TEST'],
                ]],
            ], 200),
        ]);

        $records = app(StatsNzClient::class)->indicators();

        $this->assertCount(1, $records);
        $this->assertSame(EconomicIndicator::GDP_QUARTERLY, $records[0]['indicator']);
        $this->assertSame(0.2, $records[0]['value']);
        $this->assertSame('live', $records[0]['source_badge']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/rest/data/STATSNZ,GDP_TEST,1.0/all')
                && str_contains($request->url(), 'dimensionAtObservation=AllDimensions')
                && $request->hasHeader('Ocp-Apim-Subscription-Key', 'vaulted-stats-key')
                && $request->hasHeader('Accept', 'application/vnd.sdmx.data+json;version=1.0.0');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function datasetConfig(): array
    {
        return [
            'key' => 'business_demography_enterprises_by_industry_size',
            'label' => 'Business demography - enterprises by industry and size',
            'resourceId' => 'BD_BD_001',
            'version' => '1.0',
            'sdmx_key' => 'all',
            'dimension_key' => 'ANZSIC06',
            'metric_dimension_key' => 'MEASURE',
            'dimensionAtObservation' => 'AllDimensions',
            'unit' => 'count',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fixturePayload(): array
    {
        $contents = file_get_contents(base_path('database/fixtures/integration/stats-nz-industry-benchmarks.json'));
        $decoded = json_decode((string) $contents, true, flags: JSON_THROW_ON_ERROR);

        return $decoded['current']['payload'];
    }

    private function forgetStatsNzClients(): void
    {
        foreach ([
            StatsNzClient::class,
            FakeStatsNzClient::class,
            LiveStatsNzClient::class,
            FallbackStatsNzClient::class,
            ManualStatsNzIndicatorSource::class,
            RetryPolicy::class,
            ResilientHttp::class,
        ] as $abstract) {
            app()->forgetInstance($abstract);
        }
    }
}
