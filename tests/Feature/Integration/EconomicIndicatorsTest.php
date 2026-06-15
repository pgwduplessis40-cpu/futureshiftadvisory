<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\AccountingConnection;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EconomicIndicator;
use App\Models\ExchangeRate;
use App\Models\FinancialSnapshot;
use App\Models\IntegrationCall;
use App\Models\LearningUpdate;
use App\Models\LearningUpdateImplementation;
use App\Models\User;
use App\Services\EconomicData\EconomicIndicatorRefresher;
use App\Services\Integration\Mbie\FakeMbieClient;
use App\Services\Integration\Mbie\FallbackMbieClient;
use App\Services\Integration\Mbie\LiveMbieClient;
use App\Services\Integration\Rbnz\Contracts\RbnzClient;
use App\Services\Integration\Rbnz\FakeRbnzClient;
use App\Services\Integration\Rbnz\FallbackRbnzClient;
use App\Services\Integration\Rbnz\LiveRbnzClient;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Resilience\RetryPolicy;
use App\Services\Integration\StatsNz\Contracts\StatsNzClient;
use App\Services\Integration\StatsNz\FakeStatsNzClient;
use App\Services\Integration\StatsNz\FallbackStatsNzClient;
use App\Services\Integration\StatsNz\LiveStatsNzClient;
use App\Services\Integration\StatsNz\ManualStatsNzIndicatorSource;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EconomicIndicatorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Cache::flush();
        Config::set('integrations.rbnz.live', false);
        Config::set('integrations.mbie.live', false);
        Config::set('integrations.stats_nz.live', false);
        Config::set('integrations.retry.attempts', 1);
        Config::set('integrations.retry.base_delay_ms', 0);
        Config::set('integrations.retry.max_delay_ms', 0);
        $this->forgetEconomicClients();
    }

    public function test_fixture_feed_refreshes_deterministic_economic_indicators(): void
    {
        $this->artisan('economic-indicators:refresh', [
            '--fetched-at' => '2026-05-22T03:30:00+00:00',
        ])->assertSuccessful();

        $ocr = EconomicIndicator::query()->where('indicator', EconomicIndicator::OCR)->firstOrFail();
        $this->assertSame('Official Cash Rate', $ocr->label);
        $this->assertSame(5.5, $ocr->value);
        $this->assertSame('percent', $ocr->unit);
        $this->assertSame('rbnz', $ocr->source);
        $this->assertSame('stub', $ocr->source_badge);
        $this->assertFalse($ocr->degraded);
        $this->assertSame('2026-05-20', $ocr->period_date?->toDateString());

        $this->assertDatabaseHas('economic_indicators', [
            'indicator' => EconomicIndicator::MINIMUM_WAGE,
            'source' => 'mbie',
            'source_badge' => 'stub',
        ]);
        $this->assertDatabaseHas('economic_indicators', [
            'indicator' => EconomicIndicator::GDP_QUARTERLY,
            'source' => 'stats_nz',
            'source_badge' => 'stub',
            'value' => 0.7,
        ]);
        $this->assertDatabaseHas('economic_indicators', [
            'indicator' => EconomicIndicator::UNEMPLOYMENT_RATE,
            'source' => 'stats_nz',
            'source_badge' => 'stub',
            'value' => 4.3,
        ]);

        $rate = ExchangeRate::query()
            ->where('base_currency', 'NZD')
            ->where('quote_currency', 'USD')
            ->firstOrFail();
        $this->assertSame(0.6123, $rate->rate);
        $this->assertSame('stub', $rate->source_badge);

        $this->assertDatabaseCount('economic_indicators', 6);
        $this->assertDatabaseCount('exchange_rates', 2);
        $this->assertDatabaseHas('learning_layer_runs', [
            'layer_id' => EconomicIndicatorRefresher::LAYER_ID,
            'candidates_created' => 0,
        ]);
    }

    public function test_live_mode_with_config_credentials_degrades_through_resilience_layer(): void
    {
        Config::set('integrations.rbnz.live', true);
        Config::set('integrations.mbie.live', true);
        Config::set('integrations.mbie.api_key', 'mbie-test-key');
        $this->forgetEconomicClients();

        Http::fake(fn () => Http::response(['error' => 'missing credential'], 401));

        app(EconomicIndicatorRefresher::class)->refresh(now());

        $ocr = EconomicIndicator::query()->where('indicator', EconomicIndicator::OCR)->firstOrFail();
        $this->assertSame('stub_live_fallback', $ocr->source_badge);
        $this->assertTrue($ocr->degraded);
        $this->assertNotNull($ocr->correlation_id);

        Http::assertSentCount(3);
        foreach (['rbnz', 'mbie'] as $service) {
            $this->assertDatabaseHas('integration_calls', [
                'service' => $service,
                'status' => IntegrationCall::STATUS_FAILURE,
                'attempt' => 1,
            ]);
            $this->assertDatabaseHas('integration_calls', [
                'service' => $service,
                'status' => IntegrationCall::STATUS_FALLBACK,
                'attempt' => 1,
            ]);
        }
    }

    public function test_rbnz_live_mode_uses_approved_website_agent_without_api_key(): void
    {
        Config::set('integrations.rbnz.live', true);
        Config::set('integrations.mbie.live', false);
        Config::set('integrations.rbnz.user_agent', 'rbnz-approved-agent/rsd-58801');
        $this->forgetEconomicClients();

        Http::fake([
            'https://www.rbnz.govt.nz/monetary-policy/about-monetary-policy/the-official-cash-rate' => Http::response(
                '<h3>Official Cash Rate</h3><div>2.25 %</div><p>Updated: 2:00pm, 27 May 2026</p><p>Next update: 2:00pm, 08 Jul 2026</p>',
                200,
            ),
            'https://www.rbnz.govt.nz/' => Http::response(
                '<a>Exchange Rates USD 0.58020 GBP 0.43345 AUD 0.82810 EUR 0.50215 CAD 0.80845 JPY 93.11920 Updated: 11 Jun 2026</a>',
                200,
            ),
        ]);

        app(EconomicIndicatorRefresher::class)->refresh(now());

        $ocr = EconomicIndicator::query()->where('indicator', EconomicIndicator::OCR)->firstOrFail();
        $this->assertSame(2.25, $ocr->value);
        $this->assertSame('2026-05-27', $ocr->period_date?->toDateString());
        $this->assertSame('live', $ocr->source_badge);
        $this->assertSame('approved_website_agent', $ocr->payload['source_mode'] ?? null);

        $rate = ExchangeRate::query()
            ->where('base_currency', 'NZD')
            ->where('quote_currency', 'USD')
            ->firstOrFail();
        $this->assertSame(0.5802, $rate->rate);
        $this->assertSame('2026-06-11', $rate->rate_date?->toDateString());
        $this->assertSame('live', $rate->source_badge);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->hasHeader('User-Agent', 'rbnz-approved-agent/rsd-58801')
            && ! str_contains($request->url(), 'api_key='));
    }

    public function test_rbnz_approved_agent_refresh_is_scheduled_daily(): void
    {
        Artisan::call('schedule:list');

        $this->assertMatchesRegularExpression(
            '/30\s+3\s+\*\s+\*\s+\*\s+php artisan economic-indicators:refresh/',
            Artisan::output(),
        );
    }

    public function test_refresh_is_idempotent_for_same_source_periods(): void
    {
        $fetchedAt = now();

        app(EconomicIndicatorRefresher::class)->refresh($fetchedAt);
        app(EconomicIndicatorRefresher::class)->refresh($fetchedAt->copy()->addHour());

        $this->assertDatabaseCount('economic_indicators', 6);
        $this->assertDatabaseCount('exchange_rates', 2);
        $this->assertDatabaseCount('learning_updates', 0);
        $this->assertDatabaseCount('learning_layer_runs', 2);
    }

    public function test_ocr_change_queues_pv_discount_rate_candidate_without_auto_apply(): void
    {
        app(EconomicIndicatorRefresher::class)->refresh(now()->subDay());
        $this->app->instance(RbnzClient::class, new ChangedOcrRbnzClient);

        app(EconomicIndicatorRefresher::class)->refresh(now());
        app(EconomicIndicatorRefresher::class)->refresh(now()->addHour());

        $candidate = LearningUpdate::query()->firstOrFail();
        $this->assertSame(EconomicIndicatorRefresher::LAYER_ID, $candidate->layer_id);
        $this->assertSame(LearningUpdate::STATUS_DETECTED, $candidate->status);
        $this->assertSame('economic_indicator_auto_update', $candidate->source['type']);
        $this->assertSame('review_pv_discount_rate_assumptions', $candidate->proposed_change['action']);
        $this->assertFalse($candidate->proposed_change['automatic_application']);
        $this->assertSame(5.5, $candidate->evidence['previous_value']);
        $this->assertEquals(6.0, $candidate->evidence['current_value']);
        $this->assertDatabaseCount('learning_updates', 1);
        $this->assertSame(0, LearningUpdateImplementation::query()->count());
    }

    public function test_stale_older_ocr_feed_does_not_queue_pv_discount_rate_candidate(): void
    {
        EconomicIndicator::query()->create([
            'indicator' => EconomicIndicator::OCR,
            'label' => 'Official Cash Rate',
            'value' => 2.25,
            'unit' => 'percent',
            'period_date' => '2026-06-11',
            'source' => 'rbnz',
            'source_badge' => 'live',
            'degraded' => false,
            'fetched_at' => now()->subHour(),
            'payload' => ['source_mode' => 'approved_website_agent'],
        ]);

        app(EconomicIndicatorRefresher::class)->refresh(now());

        $this->assertDatabaseHas('economic_indicators', [
            'indicator' => EconomicIndicator::OCR,
            'source' => 'rbnz',
            'period_date' => '2026-05-20',
        ]);
        $this->assertDatabaseCount('learning_updates', 0);
    }

    public function test_advisor_dashboard_shows_latest_values_and_change_alerts(): void
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => 'economic-dashboard@example.test',
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $client = $this->clientFor($advisor, 'Economic Exposure Limited');
        $this->snapshot($client, ['metrics' => ['interest_bearing_debt' => 120000]]);

        app(EconomicIndicatorRefresher::class)->refresh(now()->subDay());
        $this->app->instance(RbnzClient::class, new ChangedOcrRbnzClient);
        app(EconomicIndicatorRefresher::class)->refresh(now());

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('economicIndicators.summary.indicators', 6)
                ->where('economicIndicators.summary.exchange_rates', 2)
                ->where('economicIndicators.summary.change_alerts', 1)
                ->where('economicIndicators.indicators.0.indicator', EconomicIndicator::OCR)
                ->where('economicIndicators.indicators.0.value', 6)
                ->where('economicIndicators.indicators.0.previous_value', 5.5)
                ->where('economicIndicators.indicators.0.change_pct', 9.09)
                ->where('economicIndicators.indicators.0.direction', 'up')
                ->where('economicIndicators.indicators.0.exposure.exposed_count', 1)
                ->where('economicIndicators.indicators.0.exposure.unknown_count', 0)
                ->where('economicIndicators.indicators.0.exposure.drill_url', route('advisor.clients.index', ['exposed_to' => 'ocr'], absolute: false))
                ->where('economicIndicators.exchange_rates.0.base_currency', 'NZD')
                ->where('economicIndicators.exchange_rates', function ($rates): bool {
                    $usd = $rates->firstWhere('quote_currency', 'USD');

                    return $usd !== null
                        && $usd['previous_rate'] === 0.6123
                        && $usd['direction'] === 'down'
                        && $usd['exposure']['supported'] === false
                        && $usd['exposure']['drill_url'] === null;
                })
                ->where('economicIndicators.alerts.0.summary', 'OCR changed from 5.50% to 6.00%; review PV discount-rate assumptions.'));
    }

    private function clientFor(User $advisor, string $name): Client
    {
        $client = Client::query()->create([
            'engagement_type' => 'standard_advisory',
            'nzbn' => '9429000000800',
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => ['standard_advisory'],
        ]);

        return $client;
    }

    /**
     * @param  array{balance_sheet?:array<string, mixed>, cash_flow?:array<string, mixed>, metrics?:array<string, mixed>}  $payload
     */
    private function snapshot(Client $client, array $payload): FinancialSnapshot
    {
        $connection = AccountingConnection::query()->create([
            'client_id' => $client->getKey(),
            'provider' => AccountingConnection::PROVIDER_XERO,
            'external_tenant_id' => 'economic-exposure-test',
            'status' => AccountingConnection::STATUS_CONNECTED,
            'token_envelope' => 'encrypted-token',
            'connected_at' => now(),
        ]);

        return FinancialSnapshot::query()->create([
            'client_id' => $client->getKey(),
            'accounting_connection_id' => $connection->getKey(),
            'provider' => AccountingConnection::PROVIDER_XERO,
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'source' => 'xero',
            'source_badge' => 'fixture',
            'degraded' => false,
            'profit_and_loss' => [],
            'balance_sheet' => $payload['balance_sheet'] ?? [],
            'cash_flow' => $payload['cash_flow'] ?? [],
            'metrics' => $payload['metrics'] ?? [],
            'pulled_at' => now(),
        ]);
    }

    private function forgetEconomicClients(): void
    {
        foreach ([
            RbnzClient::class,
            FakeRbnzClient::class,
            LiveRbnzClient::class,
            FallbackRbnzClient::class,
            FakeMbieClient::class,
            LiveMbieClient::class,
            FallbackMbieClient::class,
            StatsNzClient::class,
            FakeStatsNzClient::class,
            LiveStatsNzClient::class,
            FallbackStatsNzClient::class,
            ManualStatsNzIndicatorSource::class,
            RetryPolicy::class,
            ResilientHttp::class,
            EconomicIndicatorRefresher::class,
        ] as $abstract) {
            app()->forgetInstance($abstract);
        }
    }
}

final class ChangedOcrRbnzClient implements RbnzClient
{
    public function ocr(): array
    {
        return [
            'indicator' => EconomicIndicator::OCR,
            'label' => 'Official Cash Rate',
            'value' => 6.0,
            'unit' => 'percent',
            'period_date' => '2026-06-01',
            'source' => 'rbnz',
            'source_badge' => 'stub',
            'degraded' => false,
            'payload' => ['series' => 'OCR'],
        ];
    }

    public function exchangeRates(): array
    {
        return [
            [
                'base_currency' => 'NZD',
                'quote_currency' => 'USD',
                'rate' => 0.6001,
                'rate_date' => '2026-06-01',
                'source' => 'rbnz',
                'source_badge' => 'stub',
                'degraded' => false,
            ],
            [
                'base_currency' => 'NZD',
                'quote_currency' => 'AUD',
                'rate' => 0.9202,
                'rate_date' => '2026-06-01',
                'source' => 'rbnz',
                'source_badge' => 'stub',
                'degraded' => false,
            ],
        ];
    }
}
