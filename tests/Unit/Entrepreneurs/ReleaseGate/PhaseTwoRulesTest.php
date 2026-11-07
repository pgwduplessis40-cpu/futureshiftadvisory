<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\ReleaseGate\CheckStatus;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\PhaseOneRules;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleCatalogue;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\RuleResult;
use App\Services\Entrepreneurs\ReleaseGate\Rules\ElapsedActualsRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\MonthThirteenBridgeRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\ProfitAllocationRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\RevenueRampRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\RunwayDefinitionRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\ScenarioFundingRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\SharedCapacityRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\StreamMembershipRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\StressDimensionRule;
use PHPUnit\Framework\TestCase;

/**
 * The nine phase-2 rules. These implementations are complete and tested here,
 * and deliberately absent from PhaseOneRules: registering them changes live
 * verdicts and is the separate, final step of slice 2E.
 */
final class PhaseTwoRulesTest extends TestCase
{
    private const IDS = ['PM-05', 'PM-06', 'PM-08', 'PM-10', 'PM-11', 'MI-07', 'MI-08', 'SS-02', 'SS-03'];

    /** @param list<Fact> $facts */
    private function evaluateRule(Rule $rule, array $facts, int $required = 2): RuleResult
    {
        return new RuleResult(RuleCatalogue::get($rule->id()), true, $rule->evaluate(SyntheticCase::context($facts, $required), new RuleConfiguration));
    }

    public function test_the_nine_rules_are_built_but_stay_out_of_the_evaluator(): void
    {
        $registered = array_map(fn (Rule $rule): string => $rule->id(), PhaseOneRules::all());
        self::assertCount(20, $registered, 'Slice 2E-2 adds implementations, not enabled rules.');
        foreach (self::IDS as $id) {
            self::assertNotContains($id, $registered, $id.' must not reach the evaluator before the rules are switched on.');
            self::assertSame(2, RuleCatalogue::get($id)->phase);
        }
    }

    public function test_each_rule_declares_the_facts_its_implementation_reads(): void
    {
        foreach ($this->passingCases() as $id => [$rule, $facts]) {
            $declared = RuleCatalogue::get($id)->inputs;
            self::assertNotSame(['plan.claims', 'budget.facts', 'evidence.records'], $declared, $id.' still carries the placeholder input list.');
            $read = array_values(array_unique(array_map(fn (Fact $fact): string => $fact->key, $facts)));
            self::assertSame([], array_values(array_diff($read, $declared)), $id.' reads facts it does not declare.');
            self::assertSame($rule->id(), $id);
        }
    }

    public function test_every_rule_passes_on_a_complete_case(): void
    {
        foreach ($this->passingCases() as $id => [$rule, $facts, $required]) {
            self::assertSame(CheckStatus::Pass, $this->evaluateRule($rule, $facts, $required)->status, $id.' must pass on a complete case.');
        }
    }

    public function test_revenue_ramp_compares_the_same_streams_from_the_same_first_month(): void
    {
        [$rule, $facts] = $this->passingCases()['PM-05'];
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'model.ramp', '5000', 'intensive'))->status);
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'model.start_period', '2026-02'))->status);
        // An excluded stream contributing revenue is a defect, not a difference.
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'model.ramp', '900', 'upside'))->status);
        self::assertSame(CheckStatus::CannotVerify, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'stream.membership', null, 'intensive'))->status);
    }

    public function test_stream_membership_totals_only_included_streams_and_refuses_to_guess_a_flag(): void
    {
        [$rule, $facts] = $this->passingCases()['PM-06'];
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'model.base_revenue', '14000'))->status);
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'plan.stream_membership', true, 'upside'))->status);
        // A stream with no flag is unresolved; it is not quietly excluded.
        self::assertSame(CheckStatus::CannotVerify, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'stream.membership', null, 'workshop'))->status);
    }

    public function test_profit_allocation_accepts_a_visible_exclusion_but_not_a_reinvestment_counted_twice(): void
    {
        [$rule, $facts] = $this->passingCases()['PM-08'];
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'allocation.duplicate_line', true, 'reinvestment'))->status);
        self::assertSame(CheckStatus::CannotVerify, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'allocation.trigger', null, 'giving'))->status);
        $excluded = array_values(array_filter(
            SyntheticCase::replace($facts, 'allocation.policy', 'excluded', 'giving'),
            fn (Fact $fact): bool => $fact->entity !== 'giving' || $fact->key === 'allocation.policy',
        ));
        self::assertSame(CheckStatus::Pass, $this->evaluateRule($rule, $excluded)->status, 'An excluded policy needs no amount, only a visible statement.');
    }

    public function test_runway_claims_are_compared_only_with_a_model_runway_on_the_same_terms(): void
    {
        [$rule, $facts] = $this->passingCases()['PM-10'];
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'model.runway_months', '4'))->status);
        self::assertSame(CheckStatus::CannotVerify, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'runway.includes_receipts', false, 'forecast_cash'))->status);
        $f = SyntheticCase::fact(...);
        $noSales = [$f('runway.months', '3', 'claim-2', unit: 'months', basis: 'essential_cost_no_sales'),
            $f('runway.start_cash', '9000', 'claim-2'), $f('runway.includes_receipts', true, 'claim-2', unit: null),
            $f('runway.includes_owner_pay', false, 'claim-2', unit: null),
            $f('model.runway_months', '3', 'essential_cost_no_sales', unit: 'months', basis: 'essential_cost_no_sales'),
            $f('runway.includes_receipts', true, 'essential_cost_no_sales', unit: null),
            $f('runway.includes_owner_pay', false, 'essential_cost_no_sales', unit: null)];
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, $noSales)->status, 'A no-sales runway cannot count future receipts.');
    }

    public function test_shared_capacity_fits_one_time_budget_and_prices_additional_delivery(): void
    {
        [$rule, $facts] = $this->passingCases()['PM-11'];
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'capacity.available_hours', '20'))->status);
        // Hours that cannot be derived are unknown, never zero.
        self::assertSame(CheckStatus::CannotVerify, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'stream.delivery_hours_per_unit', null, 'intensive'))->status);
        $f = SyntheticCase::fact(...);
        $contracted = [...SyntheticCase::replace($facts, 'capacity.provider', 'contractor-a', 'intensive'),
            $f('provider.capacity_hours', '40', 'contractor-a', 'month:1', unit: 'hours'),
            $f('provider.start', '2026-02-01', 'contractor-a', 'month:1', unit: null),
            $f('provider.cost', '800', 'contractor-a', 'month:1')];
        self::assertSame(CheckStatus::Pass, $this->evaluateRule($rule, $contracted)->status);
        self::assertSame(CheckStatus::CannotVerify, $this->evaluateRule($rule, array_values(array_filter($contracted,
            fn (Fact $fact): bool => $fact->key !== 'provider.cost')))->status, 'Additional delivery needs a cost or a documented no-cost basis.');
    }

    public function test_elapsed_actuals_require_a_record_a_distinct_label_and_a_checked_handover(): void
    {
        [$rule, $facts] = $this->passingCases()['MI-07'];
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'forecast.opening_cash', '9000'))->status);
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'period.label', 'forecast', period: 'month:2'))->status);
        self::assertSame(CheckStatus::CannotVerify, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'actuals.status', null, period: 'month:2'))->status);
        // Pre-trading is an answer an elapsed month is allowed to give.
        $preTrading = SyntheticCase::replace(SyntheticCase::replace($facts, 'actuals.status', 'pre_trading', period: 'month:1'), 'actuals.closing_cash', null, period: 'month:1');
        self::assertSame(CheckStatus::Pass, $this->evaluateRule($rule, $preTrading)->status);
    }

    public function test_month_thirteen_is_bridged_by_stream_or_is_proved_out_of_scope(): void
    {
        [$rule, $facts] = $this->passingCases()['MI-08'];
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'stream.revenue', '6000', 'intensive', 'month:13'), 13)->status);
        $noBridge = array_values(array_filter($facts, fn (Fact $fact): bool => ! str_starts_with($fact->key, 'bridge.')));
        self::assertSame(CheckStatus::CannotVerify, $this->evaluateRule($rule, $noBridge, 13)->status);
        $twelveOnly = array_values(array_filter($facts, fn (Fact $fact): bool => $fact->period === 'month:12'));
        $result = $this->evaluateRule($rule, $twelveOnly, 12);
        self::assertSame(CheckStatus::NotApplicable, $result->status, 'A scope that never reaches month 13 is out of scope, not unverifiable.');
        self::assertNotNull($result->subchecks[0]->scopeReason);
    }

    public function test_stress_dimensions_are_measured_in_their_own_unit_and_reported_at_their_effective_size(): void
    {
        [$rule, $facts] = $this->passingCases()['SS-02'];
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'scenario.effective_shock', '0'))->status);
        $f = SyntheticCase::fact(...);
        $delay = fn (string $unit): array => [$f('scenario.dimension', 'collection_delay', 'business', 'current', 'late', null),
            $f('scenario.interval', 'month:4-6', 'business', 'current', 'late', null),
            $f('scenario.requested_shock', '30', 'business', 'current', 'late', $unit),
            $f('scenario.effective_shock', '30', 'business', 'current', 'late', $unit)];
        self::assertSame(CheckStatus::Pass, $this->evaluateRule($rule, $delay('days'))->status);
        // A collections delay measured as a percentage is the wrong test.
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, $delay('percent'))->status);
        $capped = SyntheticCase::replace($facts, 'scenario.effective_shock', '-10');
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, $capped)->status, 'A shock cut short must name what cut it.');
        self::assertSame(CheckStatus::Pass, $this->evaluateRule($rule, [...$capped,
            $f('scenario.shock_reduction_reason', 'capacity cap on the intensive stream', 'business', 'current', 'downside', null)])->status);
    }

    public function test_scenario_funding_sizes_a_request_once_and_discloses_a_reserve_only_gap(): void
    {
        [$rule, $facts] = $this->passingCases()['SS-03'];
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'model.external_request', '2000'))->status,
            'A request sized at the gross requirement subtracts the named sources twice.');
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'funding.worst_deficit_scenario', 'base'))->status);
        self::assertSame(CheckStatus::CannotVerify, $this->evaluateRule($rule, SyntheticCase::replace($facts, 'funding.basis', null))->status);
        $f = SyntheticCase::fact(...);
        $reserveOnly = [$f('scenario.lowest_cash', '500', 'business', 'current', 'base'),
            $f('funding.basis', 'declared monthly cash basis', unit: null),
            $f('scenario.cash_deficit', '0', 'business', 'current', 'base', basis: 'declared monthly cash basis'),
            $f('scenario.reserve_gap', '1500', 'business', 'current', 'base', basis: 'declared monthly cash basis'),
            $f('funding.worst_deficit_scenario', 'base', unit: null), $f('funding.worst_reserve_scenario', 'base', unit: null)];
        self::assertSame(CheckStatus::Fail, $this->evaluateRule($rule, $reserveOnly)->status,
            'A reserve shortfall with no request and no disclosure is silently carried.');
        self::assertSame(CheckStatus::Pass, $this->evaluateRule($rule, [...$reserveOnly,
            $f('funding.reserve_only_disclosure', 'The board accepted a thinner reserve for two quarters.', unit: null)])->status);
    }

    /** @return array<string,array{0:Rule,1:list<Fact>,2:int}> */
    private function passingCases(): array
    {
        $f = SyntheticCase::fact(...);

        $ramp = [$f('plan.start_period', '2026-01', unit: null), $f('model.start_period', '2026-01', unit: null),
            $f('stream.membership', true, 'intensive', unit: null), $f('stream.membership', false, 'upside', unit: null),
            $f('plan.ramp', '4000', 'intensive', 'month:1'), $f('model.ramp', '4000', 'intensive', 'month:1'),
            $f('plan.ramp', '0', 'upside', 'month:1'), $f('model.ramp', '0', 'upside', 'month:1')];

        $membership = [$f('stream.revenue', '4000', 'intensive', 'month:1'), $f('stream.revenue', '1000', 'workshop', 'month:1'),
            $f('stream.revenue', '9000', 'upside', 'month:1'), $f('model.base_revenue', '5000', period: 'month:1')];
        foreach (['intensive' => true, 'workshop' => true, 'upside' => false] as $entity => $included) {
            $membership[] = $f('stream.membership', $included, $entity, unit: null);
            $membership[] = $f('plan.stream_membership', $included, $entity, unit: null);
        }

        $allocation = [];
        foreach (['giving', 'reinvestment', 'owner_distribution'] as $policy) {
            $allocation = [...$allocation, $f('allocation.policy', 'included', $policy, unit: null), $f('allocation.amount', '500', $policy),
                $f('allocation.trigger', 'monthly net profit above 2000', $policy, unit: null),
                $f('allocation.schedule', 'complete', $policy, unit: null), $f('allocation.duplicate_line', false, $policy, unit: null)];
        }

        $runway = [$f('runway.months', '8', 'claim-1', unit: 'months', basis: 'forecast_cash'), $f('runway.start_cash', '24000', 'claim-1'),
            $f('runway.includes_receipts', true, 'claim-1', unit: null), $f('runway.includes_owner_pay', true, 'claim-1', unit: null),
            $f('model.runway_months', '8', 'forecast_cash', unit: 'months', basis: 'forecast_cash'),
            $f('runway.includes_receipts', true, 'forecast_cash', unit: null), $f('runway.includes_owner_pay', true, 'forecast_cash', unit: null)];

        $capacity = [$f('capacity.available_hours', '120', period: 'month:1', unit: 'hours'),
            $f('stream.units', '4', 'intensive', 'month:1', unit: 'deliveries'), $f('stream.membership', true, 'intensive', unit: null),
            $f('stream.delivery_hours_per_unit', '6', 'intensive', unit: 'hours'),
            $f('stream.preparation_hours_per_unit', '2', 'intensive', unit: 'hours'),
            $f('capacity.provider', 'founder', 'intensive', unit: null)];

        $actuals = [$f('actuals.as_of', '2026-03-15', unit: null), $f('model.start_date', '2026-01-01', unit: null),
            $f('forecast.opening_cash', '6000', period: 'month:3'), $f('period.label', 'forecast', period: 'month:3', unit: null)];
        foreach ([1 => '5000', 2 => '6000'] as $month => $closing) {
            $actuals = [...$actuals, $f('actuals.status', 'reconciled', period: 'month:'.$month, unit: null),
                $f('actuals.closing_cash', $closing, period: 'month:'.$month), $f('period.label', 'actual', period: 'month:'.$month, unit: null)];
        }

        $bridge = [$f('stream.revenue', '5000', 'intensive', 'month:12'), $f('stream.revenue', '5600', 'intensive', 'month:13'),
            $f('bridge.growth', '300', 'intensive', 'month:13'), $f('bridge.seasonality', '200', 'intensive', 'month:13'),
            $f('bridge.capacity_change', '100', 'intensive', 'month:13'), $f('bridge.start_stop', '0', 'intensive', 'month:13')];

        $stress = [$f('scenario.dimension', 'revenue_volume', 'business', 'current', 'downside', null),
            $f('scenario.interval', 'month:1-12', 'business', 'current', 'downside', null),
            $f('scenario.requested_shock', '-20', 'business', 'current', 'downside', 'percent'),
            $f('scenario.effective_shock', '-20', 'business', 'current', 'downside', 'percent')];

        $basis = 'declared monthly cash basis';
        $funding = [$f('funding.basis', $basis, unit: null), $f('funding.worst_deficit_scenario', 'downside', unit: null),
            $f('funding.worst_reserve_scenario', 'downside', unit: null), $f('model.external_request', '1500'),
            $f('funding.request_metric', 'cash_deficit', unit: null), $f('funding.request_basis_scenario', 'downside', unit: null),
            $f('funding.non_external_total', '500')];
        foreach (['base' => ['500', '0', '1500'], 'downside' => ['-2000', '2000', '3000']] as $scenario => [$lowest, $deficit, $reserve]) {
            $funding = [...$funding, $f('scenario.lowest_cash', $lowest, 'business', 'current', $scenario),
                $f('scenario.cash_deficit', $deficit, 'business', 'current', $scenario, basis: $basis),
                $f('scenario.reserve_gap', $reserve, 'business', 'current', $scenario, basis: $basis)];
        }

        return [
            'PM-05' => [new RevenueRampRule, $ramp, 2],
            'PM-06' => [new StreamMembershipRule, $membership, 2],
            'PM-08' => [new ProfitAllocationRule, $allocation, 2],
            'PM-10' => [new RunwayDefinitionRule, $runway, 2],
            'PM-11' => [new SharedCapacityRule, $capacity, 2],
            'MI-07' => [new ElapsedActualsRule, $actuals, 2],
            'MI-08' => [new MonthThirteenBridgeRule, $bridge, 13],
            'SS-02' => [new StressDimensionRule, $stress, 2],
            'SS-03' => [new ScenarioFundingRule, $funding, 2],
        ];
    }
}
