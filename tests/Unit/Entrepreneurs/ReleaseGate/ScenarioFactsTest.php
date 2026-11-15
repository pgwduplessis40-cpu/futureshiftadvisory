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
use App\Services\Entrepreneurs\Forecast\FundingRequest;
use App\Services\Entrepreneurs\Forecast\ReservePolicy;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\ScenarioRunner;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastEngineVersion;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use App\Services\Entrepreneurs\Forecast\TaxCashPlan;
use App\Services\Entrepreneurs\Forecast\TaxPeriod;
use App\Services\Entrepreneurs\Forecast\TaxPolicy;
use App\Services\Entrepreneurs\Forecast\TradeItem;
use App\Services\Entrepreneurs\ReleaseGate\CanonicalJson;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\ForecastModelFacts;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Rules\ScenarioFundingRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\ScenarioRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\StressDimensionRule;
use App\Services\Entrepreneurs\ReleaseGate\ScenarioFacts;
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

    /** Each shocked scenario states the months SS-01 reconciles its trough
     * against. Without them the rule can only report the curve missing.
     */
    public function test_each_shocked_scenario_states_its_own_cash_curve(): void
    {
        $facts = $this->capture();
        $scenarios = [];
        foreach ($facts as $fact) {
            if ($fact->key === 'cash.closing') {
                $scenarios[$fact->scenario][] = $fact->period;
            }
        }

        self::assertArrayHasKey('cost_upside', $scenarios);
        self::assertArrayHasKey('collections_delay', $scenarios);
        self::assertSame($scenarios['base'], $scenarios['cost_upside'],
            'A scenario curve covers the same months as the base curve it is compared with.');
    }

    /** The base curve is stated once, by the reconstruction. A second record
     * under the same key resolves to nothing and would take MI-01's reading of
     * the base curve with it.
     */
    public function test_the_base_curve_is_not_restated_by_the_scenario_run(): void
    {
        $periods = [];
        foreach ($this->capture() as $fact) {
            if ($fact->key === 'cash.closing' && $fact->scenario === 'base') {
                $periods[] = $fact->period;
            }
        }

        self::assertSame(array_values(array_unique($periods)), $periods,
            'The base curve is stated twice, which resolves every one of its months to nothing.');
    }

    /** The trough has to be the minimum of the curve it is checked against,
     * not the ledger's own lowest cash, which tracks the balance after every
     * movement and can dip below every month end.
     */
    public function test_a_scenario_trough_is_the_minimum_of_its_own_month_end_closings(): void
    {
        $facts = $this->capture();
        $closings = [];
        $troughs = [];
        foreach ($facts as $fact) {
            if ($fact->key === 'cash.closing' && $fact->value !== null) {
                $closings[$fact->scenario][] = (float) $fact->value;
            }
            if ($fact->key === 'scenario.lowest_cash') {
                $troughs[$fact->scenario] = (float) $fact->value;
            }
        }

        self::assertArrayHasKey('cost_upside', $troughs);
        foreach ($troughs as $scenario => $trough) {
            self::assertArrayHasKey($scenario, $closings, $scenario.' reports a trough with no curve behind it.');
            self::assertSame(min($closings[$scenario]), $trough, $scenario.' reports a trough its own months do not reach.');
        }
    }

    /**
     * The decomposition has to close, or SS-01's reconciliation is testing a
     * rounding rather than the figures.
     */
    public function test_a_scenario_profit_equals_its_own_five_published_parts(): void
    {
        $parts = [];
        foreach ($this->capture() as $fact) {
            if (str_starts_with($fact->key, 'scenario.') && $fact->scenario !== 'base') {
                $parts[$fact->scenario][$fact->period][substr($fact->key, 9)] = $fact->value;
            }
        }

        $checked = 0;
        foreach ($parts as $scenario => $periods) {
            foreach ($periods as $period => $line) {
                if (! isset($line['profit'])) {
                    continue;
                }
                foreach (['revenue', 'variable_costs', 'fixed_costs', 'depreciation', 'interest'] as $name) {
                    self::assertArrayHasKey($name, $line, $scenario.' '.$period.' publishes a profit without its '.$name.'.');
                }
                $expected = (float) $line['revenue'] - (float) $line['variable_costs']
                    - (float) $line['fixed_costs'] - (float) $line['depreciation'] - (float) $line['interest'];
                self::assertEqualsWithDelta($expected, (float) $line['profit'], 0.005,
                    $scenario.' '.$period.' profit does not equal the parts it publishes.');
                $checked++;
            }
        }

        self::assertGreaterThan(0, $checked, 'No scenario stated a profit decomposition at all.');
    }

    /**
     * Every scenario claims the same baseline, which is the evidence that none
     * of them was built from a different model.
     *
     * The identity is the unshocked reconstruction's. Hashing the shocked
     * inputs would give each scenario its own and fail the rule's comparison
     * by construction.
     */
    public function test_every_scenario_claims_the_same_unshocked_baseline(): void
    {
        $baselines = [];
        $shocks = [];
        foreach ($this->capture() as $fact) {
            if ($fact->key === 'scenario.baseline') {
                $baselines[$fact->scenario] = $fact->value;
            }
            if ($fact->key === 'scenario.shocks') {
                $shocks[$fact->scenario] = $fact->value;
            }
        }

        self::assertArrayHasKey('base', $baselines);
        self::assertArrayHasKey('cost_upside', $baselines);
        self::assertCount(1, array_unique(array_values($baselines)),
            'A scenario claiming a different baseline was built from a different model.');
        self::assertSame('[]', $shocks['base'], 'The base case declares no shock, and says so.');
        self::assertStringContainsString('cost_rate_factor', (string) $shocks['cost_upside'],
            'A scenario has to declare what it changed.');
    }

    /** A baseline that does not move when the model does would pass the rule's
     * comparison while proving nothing about which model was used.
     */
    public function test_the_baseline_identity_follows_the_reconstruction(): void
    {
        $first = ScenarioFacts::baseline($this->inputs());
        $changed = ScenarioFacts::baseline($this->inputs(fixedCosts: [
            new FixedCost('rent', 'lease', '2000', '1', 'monthly', '2026-02-10', '2026-06-10', 7, true),
        ]));

        self::assertNotSame($first, $changed);
        self::assertSame($first, ScenarioFacts::baseline($this->inputs()), 'The same model has to hash the same.');
    }

    /**
     * A shock changes trading, not the tax the business is subject to.
     *
     * The scenario's four are read back off its own shocked reconstruction
     * rather than copied from the base one, so the rule checks that rather
     * than asserting it.
     */
    public function test_every_scenario_retains_the_tax_arrangement_of_the_base_case(): void
    {
        $arrangements = [];
        foreach ($this->capture($this->taxed()) as $fact) {
            if (in_array($fact->key, ['tax.policy', 'tax.rate', 'tax.opening_losses', 'tax.payment_policy'], true)) {
                $arrangements[$fact->scenario][$fact->key] = $fact->value;
            }
        }

        self::assertArrayHasKey('base', $arrangements);
        self::assertArrayHasKey('cost_upside', $arrangements);
        self::assertSame('none_due', $arrangements['base']['tax.payment_policy'],
            'The payment policy is the scope the cash plan states, and nothing stated it before.');
        foreach ($arrangements as $scenario => $arrangement) {
            self::assertSame($arrangements['base'], $arrangement,
                $scenario.' changed the tax arrangement, which no shock does.');
        }
    }

    /** All four or none. A payment schedule for a tax nobody has established
     * the basis of states the timing of a figure that has none.
     */
    public function test_a_reconstruction_with_no_tax_policy_states_no_arrangement(): void
    {
        $keys = [];
        foreach ($this->capture() as $fact) {
            $keys[] = $fact->key;
        }

        self::assertNotContains('tax.payment_policy', $keys);
        self::assertNotContains('tax.policy', $keys);
    }

    /**
     * The entries are the scenario's own, not the base case's relabelled.
     *
     * A collections delay moves a receipt's date, so the dated line under that
     * scenario has to move with it. Copying the base entries would leave the
     * scenario reconciling a movement its own lines do not explain.
     */
    public function test_a_delayed_scenario_states_its_own_moved_receipt(): void
    {
        $dates = [];
        foreach ($this->capture() as $fact) {
            if ($fact->key === 'ledger.date' && $fact->entity === 'inv-1.balance') {
                $dates[$fact->scenario] = $fact->value;
            }
        }

        self::assertSame('2026-03-20', $dates['base'] ?? null);
        self::assertArrayHasKey('collections_delay', $dates);
        self::assertNotSame($dates['base'], $dates['collections_delay'],
            'The delayed scenario reports the receipt on the day the base case does.');
        self::assertSame('2026-04-19', $dates['collections_delay'], 'Thirty days later than the base case.');
    }

    /** Every entry a scenario states carries the classification, date and
     * source the reconciliation needs; a bare amount reconciles nothing.
     */
    public function test_every_scenario_entry_is_classified_dated_and_sourced(): void
    {
        $entries = [];
        foreach ($this->capture() as $fact) {
            if (in_array($fact->key, ['ledger.amount', 'ledger.category', 'ledger.date', 'ledger.source'], true)
                && $fact->scenario === 'cost_upside') {
                $entries[$fact->entity][] = $fact->key;
            }
        }

        self::assertNotSame([], $entries);
        foreach ($entries as $entity => $keys) {
            foreach (['ledger.amount', 'ledger.category', 'ledger.date', 'ledger.source'] as $key) {
                self::assertContains($key, $keys, $entity.' is stated without its '.$key.'.');
            }
        }
    }

    /** The rule's own arithmetic: the classified lines have to sum to the
     * movement the curve reports for that month.
     */
    public function test_the_rule_reconciles_a_scenario_month_to_its_own_entries(): void
    {
        $checks = (new ScenarioRule)->evaluate($this->gateContext(), new RuleConfiguration);
        $resolved = [];
        foreach ($checks as $check) {
            if (preg_match('/^cost_upside:ledger:month:\d+$/D', $check->id) === 1 && ! $check->status->unresolved()) {
                $resolved[] = $check->id;
            }
        }

        self::assertNotSame([], $resolved, 'No scenario month reconciled to the entries behind it.');
    }

    /** Each scenario carries its own chain, not a relabelled copy of the base
     * case's.
     */
    public function test_each_scenario_states_its_own_tax_chain(): void
    {
        $months = [];
        foreach ($this->capture($this->taxed()) as $fact) {
            if (in_array($fact->key, ['tax.profit', 'tax.expense', 'tax.closing_losses'], true)) {
                $months[$fact->scenario][$fact->key][] = $fact->value;
            }
        }

        self::assertArrayHasKey('base', $months);
        foreach (['revenue_downside', 'cost_upside', 'combined_downside'] as $scenario) {
            self::assertArrayHasKey($scenario, $months, $scenario.' states no tax chain.');
            foreach (['tax.profit', 'tax.expense', 'tax.closing_losses'] as $key) {
                self::assertArrayHasKey($key, $months[$scenario], $scenario.' states no '.$key.'.');
            }
        }
    }

    /**
     * The reason a scenario needs its own chain at all.
     *
     * A revenue downside earns less, so it owes less. A chain copied from the
     * base case would have every scenario owing the same tax on different
     * profits, which is the error this exists to prevent.
     */
    public function test_a_revenue_downside_owes_less_tax_than_the_base_case(): void
    {
        $owed = [];
        foreach ($this->capture($this->taxed()) as $fact) {
            if ($fact->key === 'tax.expense' && $fact->value !== null) {
                $owed[$fact->scenario] = ($owed[$fact->scenario] ?? 0) + (float) $fact->value;
            }
        }

        self::assertArrayHasKey('base', $owed);
        self::assertArrayHasKey('revenue_downside', $owed);
        self::assertGreaterThan(0.0, $owed['base'], 'The base case owes nothing, so the comparison proves nothing.');
        self::assertLessThan($owed['base'], $owed['revenue_downside'],
            'A downside that owes the same tax as the base case is running the base case chain.');
    }

    /** SS-01 re-runs the whole tax rule against each scenario's chain, and
     * could only report it unestablished while nothing stated one.
     */
    public function test_the_rule_reaches_the_scenario_tax_checks(): void
    {
        $checks = (new ScenarioRule)->evaluate($this->gateContext($this->taxed()), new RuleConfiguration);
        $decided = [];
        foreach ($checks as $check) {
            if (str_contains($check->id, ':tax:') && ! $check->status->unresolved()) {
                $decided[] = $check->id;
            }
        }

        self::assertNotSame([], $decided, 'SS-01 decided none of its per-scenario tax checks.');
    }

    /** The inventory claims to be complete, so it has to cover every value a
     * shock can reach: the three volume fields and the provider unit cost on
     * each stream month, each fixed cost's rate, each asset's cost.
     */
    public function test_the_inventory_covers_every_value_a_shock_can_reach(): void
    {
        $entities = [];
        $inventory = [];
        foreach ($this->capture() as $fact) {
            if ($fact->key === 'scenario.input' && $fact->scenario === 'base') {
                $entities[$fact->entity] = true;
            }
            if ($fact->key === 'scenario.input_inventory') {
                $inventory[$fact->scenario] = $fact->value;
            }
        }

        foreach (['booked_units', 'delivery_units', 'invoiced_units', 'provider_unit_cost'] as $field) {
            self::assertArrayHasKey('stream:intensive.'.$field, $entities, $field.' is outside the inventory.');
        }
        self::assertArrayHasKey('fixed_cost:rent.rate', $entities);
        self::assertArrayHasKey('asset:laptop.cost', $entities);
        self::assertSame('complete', $inventory['base'] ?? null);
        self::assertSame('complete', $inventory['cost_upside'] ?? null);
    }

    /**
     * The declared multiplier against the applied value.
     *
     * The multiplier comes from the definition and the value from the shocked
     * model, so the comparison tests the engine. Dividing one by the other
     * would agree with itself however the shock had been applied.
     */
    public function test_a_shocked_value_is_the_baseline_with_its_declared_multiplier_applied(): void
    {
        $values = [];
        $multipliers = [];
        foreach ($this->capture() as $fact) {
            if ($fact->key === 'scenario.input' && $fact->entity === 'fixed_cost:rent.rate') {
                $values[$fact->scenario] = $fact->value;
            }
            if ($fact->key === 'scenario.shock_multiplier' && $fact->entity === 'fixed_cost:rent.rate') {
                $multipliers[$fact->scenario] = $fact->value;
            }
        }

        self::assertSame('1000', $values['base']);
        self::assertSame('1.1', $multipliers['cost_upside'] ?? null, 'The declared factor is the one the catalogue set.');
        self::assertEqualsWithDelta(
            (float) $values['base'] * (float) $multipliers['cost_upside'],
            (float) $values['cost_upside'],
            0.005,
            'The shocked rate is not the baseline with its declared factor applied.',
        );
    }

    /** An undeclared input stays at the shared baseline, and the rule compares
     * the two as strings, so they have to match exactly rather than merely
     * being equal as numbers.
     */
    public function test_an_unshocked_value_is_byte_identical_to_the_baseline(): void
    {
        $values = [];
        $shocked = [];
        foreach ($this->capture() as $fact) {
            if ($fact->key === 'scenario.input' && $fact->entity === 'asset:laptop.cost') {
                $values[$fact->scenario] = $fact->value;
            }
            if ($fact->key === 'scenario.shock_multiplier' && $fact->entity === 'asset:laptop.cost') {
                $shocked[$fact->scenario] = true;
            }
        }

        self::assertArrayNotHasKey('collections_delay', $shocked, 'A delay declares nothing against capex.');
        self::assertSame($values['base'], $values['collections_delay'],
            'An undeclared input moved, or moved only in how it is written.');
    }

    /** SS-01 reported the inventory unestablished on every scenario while
     * nothing stated one.
     */
    public function test_the_rule_reaches_its_input_comparison(): void
    {
        $checks = (new ScenarioRule)->evaluate($this->gateContext(), new RuleConfiguration);
        $ids = array_map(fn (object $check): string => $check->id, $checks);
        $decided = [];
        foreach ($checks as $check) {
            if (! $check->status->unresolved()) {
                $decided[] = $check->id;
            }
        }

        self::assertSame([], array_values(array_filter($ids,
            fn (string $id): bool => str_starts_with($id, 'input-inventory:'))),
            'A scenario stating a complete inventory has it established.');
        self::assertNotSame([], array_filter($decided, fn (string $id): bool => str_starts_with($id, 'shock:')),
            'SS-01 decided none of its declared-shock comparisons.');
        self::assertNotSame([], array_filter($decided, fn (string $id): bool => str_starts_with($id, 'input-count:')),
            'SS-01 did not decide that the scenarios use the same inventory.');
    }

    /**
     * SS-03 sizes a funding ask against these, and reads a deficit whose
     * basis differs from the shared one as missing evidence rather than as a
     * mismatch. So every scenario's has to carry the same footing the base
     * case's does.
     */
    public function test_every_scenario_states_its_deficit_and_reserve_gap_on_the_shared_basis(): void
    {
        $bases = [];
        $deficits = [];
        $gaps = [];
        $shared = null;
        foreach ($this->capture($this->funded()) as $fact) {
            if ($fact->key === 'funding.basis') {
                $shared = $fact->value;
            }
            if ($fact->key === 'scenario.cash_deficit') {
                $deficits[$fact->scenario] = $fact->value;
                $bases[] = $fact->basis;
            }
            if ($fact->key === 'scenario.reserve_gap') {
                $gaps[$fact->scenario] = $fact->value;
                $bases[] = $fact->basis;
            }
        }

        self::assertSame(ScenarioRunner::SCOPE, $shared);
        self::assertArrayHasKey('base', $deficits);
        self::assertArrayHasKey('cost_upside', $deficits);
        self::assertArrayHasKey('cost_upside', $gaps);
        self::assertSame([$shared], array_values(array_unique($bases)),
            'A measure on another footing is a different number, and the rule reads it as missing.');
    }

    /** The base case is stated once. A second record under the same
     * coordinate resolves to nothing, which would lose its metrics rather
     * than add to them.
     */
    public function test_the_base_metrics_are_not_stated_twice(): void
    {
        $counts = [];
        foreach ($this->capture($this->funded()) as $fact) {
            if (in_array($fact->key, ['scenario.cash_deficit', 'scenario.reserve_gap', 'funding.basis'], true)) {
                $counts[$fact->key.':'.$fact->scenario] = ($counts[$fact->key.':'.$fact->scenario] ?? 0) + 1;
            }
        }

        foreach ($counts as $coordinate => $count) {
            self::assertSame(1, $count, $coordinate.' is stated more than once, so it resolves to nothing.');
        }
    }

    /** The run already decides which scenario each measure peaks in. Naming
     * it is what lets the rule check the claim instead of recomputing it.
     */
    public function test_the_worst_scenario_for_each_measure_is_named(): void
    {
        $named = [];
        $deficits = [];
        foreach ($this->capture($this->funded()) as $fact) {
            if (in_array($fact->key, ['funding.worst_deficit_scenario', 'funding.worst_reserve_scenario'], true)) {
                $named[$fact->key] = $fact->value;
            }
            if ($fact->key === 'scenario.cash_deficit' && $fact->value !== null) {
                $deficits[$fact->scenario] = (float) $fact->value;
            }
        }

        self::assertArrayHasKey('funding.worst_deficit_scenario', $named);
        self::assertContains($named['funding.worst_deficit_scenario'], array_keys($deficits));
        self::assertSame(max($deficits), $deficits[$named['funding.worst_deficit_scenario']],
            'The named worst deficit scenario is not the one with the highest deficit.');
    }

    /** SS-03 could only ever report its scenarios as not established. It now
     * reaches the comparisons it was written to make.
     */
    public function test_the_funding_rule_reaches_its_worst_scenario_comparisons(): void
    {
        $checks = (new ScenarioFundingRule)->evaluate($this->gateContext($this->funded()), new RuleConfiguration);
        $ids = array_map(fn (object $check): string => $check->id, $checks);
        $decided = [];
        foreach ($checks as $check) {
            if (! $check->status->unresolved()) {
                $decided[] = $check->id;
            }
        }

        self::assertNotContains('scenarios', $ids, 'SS-03 still sees no scenarios at all.');
        self::assertNotContains('basis', $ids, 'SS-03 still cannot read the basis the scenarios share.');
        self::assertContains('worst:deficit', $decided, 'SS-03 did not decide which scenario has the worst deficit.');
        self::assertContains('worst:reserve', $decided, 'SS-03 did not decide which scenario has the worst reserve gap.');
    }

    /**
     * The check the whole request slice exists to enable.
     *
     * SS-03 holds the amount against the requirement it names, less the
     * non-external sources applied first. The expected figure is read back off
     * the facts rather than written in here, because hard-coding it would
     * test this fixture's arithmetic instead of the rule's.
     */
    public function test_the_funding_rule_checks_a_request_against_the_requirement_it_names(): void
    {
        $deficits = [];
        foreach ($this->capture($this->funded()) as $fact) {
            if ($fact->key === 'scenario.cash_deficit' && $fact->value !== null) {
                $deficits[$fact->scenario] = (float) $fact->value;
            }
        }
        self::assertArrayHasKey('combined_downside', $deficits);

        $nonExternal = 500.0;
        $sized = $this->asking((string) ($deficits['combined_downside'] - $nonExternal), (string) $nonExternal);
        $checks = (new ScenarioFundingRule)->evaluate($this->gateContext($sized), new RuleConfiguration);

        $request = null;
        foreach ($checks as $check) {
            if ($check->id === 'request') {
                $request = $check;
            }
        }

        self::assertNotNull($request, 'SS-03 never reached the request check.');
        self::assertFalse($request->status->unresolved(),
            'SS-03 still reports the request unestablished with every statement behind it present.');
    }

    /** The non-external total is subtracted once. A request already net of it
     * would be reduced twice and understate what is needed, so a request
     * sized at the full requirement has to fail.
     */
    public function test_a_request_that_ignores_the_non_external_sources_is_demonstrated(): void
    {
        $deficits = [];
        foreach ($this->capture($this->funded()) as $fact) {
            if ($fact->key === 'scenario.cash_deficit' && $fact->value !== null) {
                $deficits[$fact->scenario] = (float) $fact->value;
            }
        }

        $full = $this->asking((string) $deficits['combined_downside'], '500');
        $checks = (new ScenarioFundingRule)->evaluate($this->gateContext($full), new RuleConfiguration);

        $failed = [];
        foreach ($checks as $check) {
            if ($check->id === 'request' && $check->status->value === 'fail') {
                $failed[] = $check->id;
            }
        }

        self::assertSame(['request'], $failed,
            'A request ignoring the sources applied first was not demonstrated.');
    }

    /**
     * A reserve shortfall with no month running out of cash is a decision to
     * run thinner than policy, not an automatic need to borrow.
     *
     * SS-03 lets it stand but not go unsaid, so the disclosure is the one
     * thing it requires on the no-request path. The preconditions are
     * asserted first: without them the rule returns no checks at all and the
     * test would pass on silence.
     */
    public function test_a_reserve_only_shortfall_with_no_request_is_disclosed(): void
    {
        $facts = $this->capture($this->disclosing());
        $deficits = [];
        $gaps = [];
        foreach ($facts as $fact) {
            if ($fact->key === 'scenario.cash_deficit' && $fact->value !== null) {
                $deficits[$fact->scenario] = (float) $fact->value;
            }
            if ($fact->key === 'scenario.reserve_gap' && $fact->value !== null) {
                $gaps[$fact->scenario] = (float) $fact->value;
            }
        }

        self::assertNotSame([], $deficits);
        self::assertSame(0.0, max($deficits), 'A month short of cash puts this on the borrowing path, not the disclosure one.');
        self::assertGreaterThan(0.0, max($gaps), 'With no reserve shortfall there is nothing to disclose.');

        $checks = (new ScenarioFundingRule)->evaluate($this->gateContext($this->disclosing()), new RuleConfiguration);
        $disclosure = null;
        foreach ($checks as $check) {
            if ($check->id === 'disclosure') {
                $disclosure = $check;
            }
        }

        self::assertNotNull($disclosure, 'SS-03 never asked for the disclosure.');
        self::assertFalse($disclosure->status->unresolved(),
            'The shortfall is disclosed, so the check is decided rather than reported missing.');
    }

    /** SS-01 could only ever report its scenarios as not established, because
     * nothing stated any. It now reaches the reconciliation itself.
     */
    public function test_the_rule_reaches_its_scenario_reconciliation(): void
    {
        $checks = (new ScenarioRule)->evaluate($this->gateContext(), new RuleConfiguration);
        $ids = array_map(fn (object $check): string => $check->id, $checks);
        // Resolution, not mere presence: a missing subcheck carries the same
        // id prefix as a decided one, so asserting the prefix exists would
        // pass on the gap it is meant to prove is gone.
        $resolved = [];
        foreach ($checks as $check) {
            if (! $check->status->unresolved()) {
                $resolved[] = $check->id;
            }
        }

        self::assertNotContains('scenarios', $ids, 'SS-01 still sees no scenarios at all.');
        self::assertNotSame([], array_filter($resolved, fn (string $id): bool => str_starts_with($id, 'extremum:')),
            'SS-01 did not decide the check that reconciles a trough to its curve.');
        self::assertNotSame([], array_filter($resolved, fn (string $id): bool => str_starts_with($id, 'baseline:')),
            'SS-01 did not decide the check that holds each scenario to the shared baseline.');
        self::assertSame([], array_values(array_filter($ids, fn (string $id): bool => str_starts_with($id, 'inputs:'))),
            'A scenario stating its baseline and shocks has its inputs established.');
    }

    /**
     * The captured facts as a rule sees them.
     *
     * `model.horizon` comes from the plan's budget rather than from the
     * reconstruction, and the cash-ledger checks refuse to run without it, so
     * a context built from reconstruction facts alone would report every
     * scenario month unverifiable for a reason that has nothing to do with
     * the scenarios.
     */
    private function gateContext(?ForecastModelInputs $inputs = null): DocumentContext
    {
        return SyntheticCase::context([
            ...$this->capture($inputs),
            SyntheticCase::fact('model.horizon', 6, 'business', 'current', 'base', 'months'),
        ], 6);
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
            // Carried through rather than nulled. The funding measures have
            // nothing to measure against without it, so a fixture that sets a
            // policy and a capture that drops it would report every deficit
            // unstated and look like the engine withholding them.
            'reserve_policy' => $inputs->reservePolicyPayload() === null
                ? null
                : json_decode(CanonicalJson::encode($inputs->reservePolicyPayload()), true),
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

    /**
     * The same reconstruction with a reserve policy on it.
     *
     * Without one the funding measures have nothing to measure against, so
     * every scenario's deficit and shortfall are unstated and there is no
     * maximum for the run to name.
     */
    private function funded(): ForecastModelInputs
    {
        $base = $this->inputs();

        return new ForecastModelInputs(
            calendar: $base->calendar,
            streams: $base->streams,
            founderMonths: $base->founderMonths,
            cashOpening: $base->cashOpening,
            tradeItems: $base->tradeItems,
            assets: $base->assets,
            actualMonths: $base->actualMonths,
            reservePolicy: new ReservePolicy('1000', 3, false, null, '0.10',
                'three months of fixed costs excluding owner pay'),
            fixedCosts: $base->fixedCosts,
        );
    }

    /** The funded reconstruction with an external ask recorded against it. */
    private function asking(string $amount, string $nonExternal): ForecastModelInputs
    {
        $base = $this->funded();

        return new ForecastModelInputs(
            calendar: $base->calendar,
            streams: $base->streams,
            founderMonths: $base->founderMonths,
            cashOpening: $base->cashOpening,
            tradeItems: $base->tradeItems,
            assets: $base->assets,
            actualMonths: $base->actualMonths,
            reservePolicy: $base->reservePolicy,
            fixedCosts: $base->fixedCosts,
            fundingRequest: new FundingRequest($amount, '2026-05-01', 'cash_deficit', 'combined_downside',
                $nonExternal, null),
        );
    }

    /**
     * Enough opening cash that no month runs out, but less held than the
     * reserve policy asks for, and nothing requested.
     *
     * The request records only the disclosure: an amount of none is what puts
     * this on the no-request path, and the disclosure is what the rule then
     * requires.
     */
    private function disclosing(): ForecastModelInputs
    {
        $base = $this->funded();

        return new ForecastModelInputs(
            calendar: $base->calendar,
            streams: $base->streams,
            founderMonths: $base->founderMonths,
            cashOpening: new CashOpening('10000', 'Bank statement to 31 December 2025', true),
            tradeItems: $base->tradeItems,
            assets: $base->assets,
            actualMonths: $base->actualMonths,
            reservePolicy: $base->reservePolicy,
            fixedCosts: $base->fixedCosts,
            fundingRequest: new FundingRequest(null, null, null, null, null,
                'Reserve target accepted as short for two quarters; board minute 2026-04-18.'),
        );
    }

    /** The same reconstruction with a tax arrangement recorded on it. */
    private function taxed(): ForecastModelInputs
    {
        $base = $this->inputs();

        return new ForecastModelInputs(
            calendar: $base->calendar,
            streams: $base->streams,
            founderMonths: $base->founderMonths,
            cashOpening: $base->cashOpening,
            tradeItems: $base->tradeItems,
            assets: $base->assets,
            taxPolicy: new TaxPolicy('company', '28', true, 'IR policy note', 'advisor review', '2026.1'),
            // Without a period covering the calendar every month's adjustment
            // is unstated, and the chain withholds the expense behind it.
            taxPeriods: [new TaxPeriod('2026-01-01', '2026-12-31', null, '0')],
            actualMonths: $base->actualMonths,
            openingEligibleLosses: '1500',
            fixedCosts: $base->fixedCosts,
            taxCashPlan: new TaxCashPlan('none_due', 'none_due', 'IR account review', 'IR account review', []),
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
