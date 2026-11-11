<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\Forecast\ActualBalances;
use App\Services\Entrepreneurs\Forecast\ActualMonth;
use App\Services\Entrepreneurs\Forecast\ActualProfit;
use App\Services\Entrepreneurs\Forecast\ActualSource;
use App\Services\Entrepreneurs\Forecast\AssetInput;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\FixedCost;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastEngineVersion;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use App\Services\Entrepreneurs\Forecast\TradeItem;
use App\Services\Entrepreneurs\ReleaseGate\CanonicalJson;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\ForecastModelFacts;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Rules\StressDimensionRule;
use App\Services\Entrepreneurs\ReleaseGate\SourceRecords;
use App\Services\Entrepreneurs\ReleaseGate\SourceVersion;
use PHPUnit\Framework\TestCase;

/**
 * The stress dimensions SS-02 reads.
 *
 * The rule has been registered since phase 2d and has never had a producer, so
 * it reported every dimension as not established on every plan. These tests
 * cover what it now receives, and in particular that each effective shock is
 * measured off the shocked model rather than copied from the request - a
 * requested figure reported as the applied one would pass the rule while
 * overstating the test, which is the one thing SS-02 exists to prevent.
 */
final class ScenarioFactsTest extends TestCase
{
    public function test_each_single_dimension_scenario_states_what_it_tests(): void
    {
        $facts = $this->dimensions();

        self::assertSame([
            'collections_delay' => 'collection_delay',
            'cost_upside' => 'cost_rate',
            'revenue_downside' => 'revenue_volume',
            'slow_start' => 'slow_start_volume',
        ], $this->byScenario($facts, 'scenario.dimension'));
    }

    /** The base case declares no shock, and the combined scenario restates the
     * three dimensions its parts already establish. Two records under one key
     * resolve to nothing, so a combined scenario emitting three dimensions
     * would take SS-02's reading of all three with it.
     */
    public function test_the_base_and_combined_scenarios_carry_no_dimension(): void
    {
        $scenarios = array_keys($this->byScenario($this->dimensions(), 'scenario.dimension'));

        self::assertNotContains('base', $scenarios);
        self::assertNotContains('combined_downside', $scenarios);
    }

    public function test_each_shock_is_requested_in_the_unit_its_dimension_is_measured_in(): void
    {
        $facts = $this->dimensions();

        self::assertSame([
            'collections_delay' => '30',
            'cost_upside' => '10.00',
            'revenue_downside' => '-20.00',
            'slow_start' => '-50.00',
        ], $this->byScenario($facts, 'scenario.requested_shock'));

        foreach ($this->factsFor($facts, 'scenario.requested_shock') as $fact) {
            $expected = $fact->scenario === 'collections_delay' ? 'days' : 'percent';
            self::assertSame($expected, $fact->unit, $fact->scenario.' is measured in the wrong unit.');
        }
    }

    /** Nothing caps a rate. A cost shock reaches every category it names in
     * full, so its effective size is its requested size — but it is measured
     * against the shocked model rather than assumed, which is what would catch
     * a category that quietly failed to scale.
     */
    public function test_the_cost_shock_is_measured_across_every_category_it_names(): void
    {
        self::assertSame('10.00', $this->byScenario($this->dimensions(), 'scenario.effective_shock')['cost_upside']);
    }

    public function test_a_collections_delay_that_moves_a_receipt_applies_in_full(): void
    {
        self::assertSame('30', $this->byScenario($this->dimensions(), 'scenario.effective_shock')['collections_delay']);
    }

    /** A delay with nothing eligible to move applies nothing, however many days
     * it asked for. Reporting 30 here would claim a test that never ran.
     */
    public function test_a_collections_delay_with_no_eligible_receipt_applies_nothing(): void
    {
        $facts = $this->dimensions($this->inputs(tradeItems: []));

        self::assertSame('0', $this->byScenario($facts, 'scenario.effective_shock')['collections_delay']);
        self::assertArrayHasKey('collections_delay', $this->byScenario($facts, 'scenario.shock_reduction_reason'));
    }

    /**
     * SS-02's own contract, asserted as an invariant rather than against
     * numbers this test would have to predict: a shock that landed smaller
     * than it was asked for has to name what cut it, and one that landed in
     * full must not invent a reason.
     */
    public function test_a_volume_shock_that_lands_short_names_what_cut_it(): void
    {
        $facts = $this->dimensions();
        $requested = $this->byScenario($facts, 'scenario.requested_shock');
        $effective = $this->byScenario($facts, 'scenario.effective_shock');
        $reasons = $this->byScenario($facts, 'scenario.shock_reduction_reason');

        foreach (['revenue_downside', 'slow_start'] as $scenario) {
            self::assertNotNull($effective[$scenario] ?? null, $scenario.' states no applied size.');
            $short = abs((float) $effective[$scenario]) < abs((float) $requested[$scenario]);
            self::assertSame($short, array_key_exists($scenario, $reasons),
                $scenario.' must name a cap when it lands short, and only then.');
        }
    }

    /** A cost category holding a rate nobody has stated has no proportion to
     * report. An absent effective figure says so; a copied requested one would
     * report an untested dimension as tested.
     */
    public function test_an_unpriced_cost_leaves_the_applied_proportion_unstated(): void
    {
        $unpriced = new FixedCost('rent', 'lease', null, '1', 'monthly', '2026-02-10', '2026-06-10', 7, true);
        $facts = $this->dimensions($this->inputs(fixedCosts: [$unpriced]));

        self::assertNull($this->byScenario($facts, 'scenario.effective_shock')['cost_upside']);
        self::assertArrayHasKey('cost_upside', $this->byScenario($facts, 'scenario.shock_reduction_reason'));
    }

    /** The catalogue cannot build a volume shock without a stream to apply it
     * to. SS-02 then reports the dimensions as not established, which is the
     * truthful reading of a reconstruction with nothing to stress.
     */
    public function test_a_reconstruction_with_no_stream_states_no_dimensions(): void
    {
        self::assertSame([], $this->dimensions($this->inputs(streams: [])));
    }

    /** Actuals that name no entity establish no boundary for a shock to be
     * held behind, and a shock reaching a reviewed month is the one thing the
     * actuals protection exists to stop.
     */
    public function test_actuals_without_an_entity_scope_state_no_dimensions(): void
    {
        $anonymous = $this->actual(null);

        self::assertSame([], $this->dimensions($this->inputs(actualMonths: [$anonymous]), '2026-02-15'));
    }

    /**
     * An unresolved actuals review states no dimensions, and does not take the
     * rest of the capture with it.
     *
     * The window's own guard raises a LogicException from inside the shock
     * application. Reached from here that would travel out through the whole
     * fact capture, so every other producer's facts would vanish because the
     * actuals had not been reviewed — the gate would go quiet exactly when it
     * had the most to say.
     */
    public function test_an_unresolved_actuals_review_states_no_dimensions_without_silencing_the_capture(): void
    {
        $unreviewed = $this->inputs(actualMonths: [$this->actual('entity-1')]);

        $facts = $this->capture($unreviewed, '2026-02-15');

        self::assertSame([], $this->dimensions($unreviewed, '2026-02-15'));
        self::assertNotSame([], $facts, 'the rest of the reconstruction still has to reach the gate');
        self::assertContains('cash.opening', array_map(fn (Fact $fact): string => $fact->key, $facts));
    }

    /** The rule reached nothing but "dimensions missing" on every plan before
     * this producer existed. It now reads four of them.
     */
    public function test_the_rule_no_longer_reports_its_dimensions_as_unestablished(): void
    {
        $checks = (new StressDimensionRule)->evaluate(SyntheticCase::context($this->capture()), new RuleConfiguration);
        $ids = array_map(fn (object $check): string => $check->id, $checks);

        self::assertNotContains('dimensions', $ids, 'SS-02 still sees no stress dimensions at all.');
        foreach (['unit:0', 'unit:1', 'unit:2', 'unit:3'] as $id) {
            self::assertContains($id, $ids, 'SS-02 did not reach a unit check for every dimension.');
        }
    }

    /** @return list<Fact> */
    private function dimensions(?ForecastModelInputs $inputs = null, string $asOf = '2025-12-15'): array
    {
        return array_values(array_filter(
            $this->capture($inputs, $asOf),
            static fn (Fact $fact): bool => in_array($fact->key, ['scenario.dimension', 'scenario.interval',
                'scenario.requested_shock', 'scenario.effective_shock', 'scenario.shock_reduction_reason'], true),
        ));
    }

    /**
     * Captures the way the gate does, through the stored payload and back out
     * through the codec, so the producer is exercised on a model that survived
     * the round trip rather than on one built in memory.
     *
     * @return list<Fact>
     */
    private function capture(?ForecastModelInputs $inputs = null, string $asOf = '2025-12-15'): array
    {
        $inputs ??= $this->inputs();
        $records = ['plan_id' => 'p1', 'forecast_model' => [
            'engine_version' => ForecastEngineVersion::CURRENT,
            'drivers' => json_decode(CanonicalJson::encode($inputs->driversPayload()), true),
            'actuals' => json_decode(CanonicalJson::encode($inputs->actualsPayload()), true),
            'reserve_policy' => null,
        ]];

        return (new ForecastModelFacts)->capture(new SourceRecords(new SourceVersion(
            'p1', str_repeat('a', 64), str_repeat('b', 64), [], 0, $asOf, CanonicalJson::encode($records),
        )));
    }

    /**
     * @param  list<RevenueStream>|null  $streams
     * @param  list<TradeItem>|null  $tradeItems
     * @param  list<FixedCost>|null  $fixedCosts
     * @param  list<ActualMonth>|null  $actualMonths
     */
    private function inputs(?array $streams = null, ?array $tradeItems = null, ?array $fixedCosts = null,
        ?array $actualMonths = null): ForecastModelInputs
    {
        $calendar = new ForecastCalendar('2026-01', 6);
        $founders = [];
        foreach ($calendar->periods as $period) {
            $founders[$period] = new FounderMonth('120', '20', '15');
        }

        return new ForecastModelInputs(
            calendar: $calendar,
            streams: $streams ?? [$this->stream($calendar)],
            founderMonths: $founders,
            cashOpening: new CashOpening('5000', 'Bank statement to 31 December 2025', true),
            tradeItems: $tradeItems ?? [new TradeItem('inv-1', 'invoice', 'receivable', '2026-03-01', '1200', [
                new CashMovement('inv-1.balance', 'invoice', '2026-03-20', '1200', 'in', 'operating_receipt', 0, true),
            ])],
            assets: [new AssetInput('laptop', 'invoice', '3200', 'capital', 'fixed asset policy', true, '2026-02-10',
                '2026-02-01', 36, '200', 'straight_line_full_start_month', '0')],
            actualMonths: $actualMonths ?? [],
            fixedCosts: $fixedCosts ?? [new FixedCost('rent', 'lease', '1000', '1', 'monthly', '2026-02-10', '2026-06-10', 7, true)],
        );
    }

    /** A stream priced in every month, so the direct-cost base is a figure
     * rather than a gap in all six.
     */
    private function stream(ForecastCalendar $calendar): RevenueStream
    {
        $schedule = [];
        foreach ($calendar->periods as $period) {
            $schedule[$period] = new StreamMonth('4', '4', '4', '24', '2', 'Associate', '400', null);
        }

        return new RevenueStream('intensive', 'Intensive day', 'day', '1200', '2', '6', true, 'own_record', $schedule);
    }

    private function actual(?string $entityId): ActualMonth
    {
        $balances = fn (?string $cash): ActualBalances => new ActualBalances($cash, null, null, null, null, null);

        return new ActualMonth(
            period: '2026-01', state: 'trading',
            source: new ActualSource($entityId, 'manual', null, null, null, null, null, 'NZD', null, 'A. Advisor', '2026-02-05', null, true),
            profit: new ActualProfit(null, null, null, null, null, null, null),
            opening: $balances(null), closing: $balances('5000.00'),
            movements: null, movementAccounts: null, closingObligations: null, preTradingEvidence: null,
        );
    }

    /**
     * @param  list<Fact>  $facts
     * @return array<string,string|int|bool|null>
     */
    private function byScenario(array $facts, string $key): array
    {
        $values = [];
        foreach ($this->factsFor($facts, $key) as $fact) {
            $values[$fact->scenario] = $fact->value;
        }
        ksort($values);

        return $values;
    }

    /**
     * @param  list<Fact>  $facts
     * @return list<Fact>
     */
    private function factsFor(array $facts, string $key): array
    {
        return array_values(array_filter($facts, static fn (Fact $fact): bool => $fact->key === $key));
    }
}
