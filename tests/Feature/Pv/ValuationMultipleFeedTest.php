<?php

declare(strict_types=1);

namespace Tests\Feature\Pv;

use App\Models\LearningUpdate;
use App\Models\LearningUpdateImplementation;
use App\Models\ValuationMultiple;
use App\Services\Integration\Mbie\Contracts\MbieClient;
use App\Services\Pv\ValuationMultipleProvider;
use App\Services\Pv\ValuationMultipleRefresher;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class ValuationMultipleFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_lookup_returns_low_mid_high_by_industry(): void
    {
        $this->artisan('valuation-multiples:refresh', [
            '--fetched-at' => '2026-07-01T04:00:00+00:00',
        ])->assertSuccessful();

        $range = app(ValuationMultipleProvider::class)->rangeFor(
            industryCode: 'M6962',
            metric: ValuationMultiple::METRIC_EBITDA,
            source: ValuationMultiple::SOURCE_MBIE,
        );

        $this->assertNotNull($range);
        $this->assertSame('M6962', $range['industry_code']);
        $this->assertSame(ValuationMultiple::METRIC_EBITDA, $range['metric']);
        $this->assertSame(3.2, $range['multiple_low']);
        $this->assertSame(4.1, $range['multiple_mid']);
        $this->assertSame(5.0, $range['multiple_high']);
        $this->assertSame(ValuationMultiple::SOURCE_MBIE, $range['source']);
        $this->assertSame('2026Q2', $range['quarter']);
        $this->assertStringStartsWith('valuation_multiple:', $range['source_reference']);
        $this->assertDatabaseCount('valuation_multiples', 3);
    }

    public function test_quarterly_refresh_supersedes_prior_rows(): void
    {
        app(ValuationMultipleRefresher::class)->refresh(Carbon::parse('2026-07-01T04:00:00+00:00'));
        $previous = ValuationMultiple::query()
            ->where('industry_code', 'M6962')
            ->where('metric', ValuationMultiple::METRIC_EBITDA)
            ->where('source', ValuationMultiple::SOURCE_MBIE)
            ->firstOrFail();

        $this->bindMbieMultiples([
            [
                'industry_code' => 'M6962',
                'industry_label' => 'Management advice and related consulting services',
                'metric' => ValuationMultiple::METRIC_EBITDA,
                'multiple_low' => 3.5,
                'multiple_mid' => 4.3,
                'multiple_high' => 5.4,
                'source' => ValuationMultiple::SOURCE_MBIE,
                'quarter' => '2026Q3',
                'source_badge' => 'stub',
                'degraded' => false,
            ],
        ]);

        app(ValuationMultipleRefresher::class)->refresh(Carbon::parse('2026-10-01T04:00:00+00:00'), '2026Q3');

        $previous->refresh();
        $latest = ValuationMultiple::query()
            ->where('industry_code', 'M6962')
            ->where('metric', ValuationMultiple::METRIC_EBITDA)
            ->where('source', ValuationMultiple::SOURCE_MBIE)
            ->whereNull('superseded_at')
            ->firstOrFail();

        $this->assertNotNull($previous->superseded_at);
        $this->assertNotSame($previous->id, $latest->id);
        $this->assertSame('2026Q3', $latest->quarter);
        $this->assertSame(3.5, $latest->multiple_low);
        $this->assertSame(4.3, $latest->multiple_mid);
        $this->assertSame(5.4, $latest->multiple_high);
        $this->assertSame(1, ValuationMultiple::query()
            ->where('industry_code', 'M6962')
            ->where('metric', ValuationMultiple::METRIC_EBITDA)
            ->where('source', ValuationMultiple::SOURCE_MBIE)
            ->whereNull('superseded_at')
            ->count());
    }

    public function test_refresh_queues_governed_candidate_without_auto_apply(): void
    {
        $this->artisan('valuation-multiples:refresh', [
            '--fetched-at' => '2026-07-01T04:00:00+00:00',
        ])->assertSuccessful();

        $this->artisan('valuation-multiples:refresh', [
            '--fetched-at' => '2026-07-01T05:00:00+00:00',
        ])->assertSuccessful();

        $candidate = LearningUpdate::query()
            ->where('layer_id', ValuationMultipleRefresher::LAYER_ID)
            ->where('source->type', 'valuation_multiple_refresh')
            ->firstOrFail();

        $this->assertSame(LearningUpdate::STATUS_DETECTED, $candidate->status);
        $this->assertSame('review_valuation_multiple_assumptions', $candidate->proposed_change['action']);
        $this->assertFalse($candidate->proposed_change['automatic_application']);
        $this->assertSame('2026Q2', $candidate->source['quarter']);
        $this->assertSame(3, $candidate->evidence['multiples_created']);
        $this->assertSame([ValuationMultiple::SOURCE_MBIE, ValuationMultiple::SOURCE_NZ_BUSINESS_BROKERS], $candidate->evidence['sources']);
        $this->assertDatabaseCount('learning_updates', 1);
        $this->assertDatabaseCount('learning_update_implementations', 0);
        $this->assertSame(0, LearningUpdateImplementation::query()->count());
        $this->assertDatabaseHas('learning_layer_runs', [
            'layer_id' => ValuationMultipleRefresher::LAYER_ID,
            'candidates_created' => 1,
        ]);
        $this->assertDatabaseHas('learning_layer_runs', [
            'layer_id' => ValuationMultipleRefresher::LAYER_ID,
            'candidates_created' => 0,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $multiples
     */
    private function bindMbieMultiples(array $multiples): void
    {
        $this->app->instance(MbieClient::class, new ChangedValuationMultipleMbieClient($multiples));
        $this->app->forgetInstance(ValuationMultipleRefresher::class);
    }
}

final class ChangedValuationMultipleMbieClient implements MbieClient
{
    /**
     * @param  array<int, array<string, mixed>>  $multiples
     */
    public function __construct(private readonly array $multiples) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function wageRates(): array
    {
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function valuationMultiples(): array
    {
        return $this->multiples;
    }
}
