<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\ReleaseGate\CheckStatus;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleCatalogue;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\RuleResult;
use App\Services\Entrepreneurs\ReleaseGate\Rules\AssetClassificationRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\CapacityRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\CommittedCostRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\FundingPositionRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\MarginRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\OpeningCashRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\OwnerPayRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\ProfitabilityRule;
use App\Services\Entrepreneurs\ReleaseGate\Severity;
use PHPUnit\Framework\TestCase;

final class PlanRulesTest extends TestCase
{
    private function evaluateRule(Rule $rule, array $facts): RuleResult
    {
        return new RuleResult(RuleCatalogue::get($rule->id()), true, $rule->evaluate(SyntheticCase::context($facts), new RuleConfiguration));
    }

    public function test_profitability_compares_the_same_measure_and_period(): void
    {
        $f = SyntheticCase::fact(...);
        $facts = [$f('plan.profitability', 2, unit: 'year', basis: 'annual_profit'), $f('model.profitability', 2, unit: 'year', basis: 'annual_profit')];
        self::assertSame(CheckStatus::Pass, $this->evaluateRule(new ProfitabilityRule, $facts)->status);
        self::assertSame(CheckStatus::Fail, $this->evaluateRule(new ProfitabilityRule, SyntheticCase::replace($facts, 'model.profitability', 1))->status);
        $facts[1] = $f('model.profitability', 2, unit: 'month', basis: 'monthly_cash');
        self::assertSame(CheckStatus::CannotVerify, $this->evaluateRule(new ProfitabilityRule, $facts)->status);
    }

    public function test_owner_pay_converts_frequency_but_does_not_compare_a_staged_average_to_a_current_rate(): void
    {
        $f = SyntheticCase::fact(...);
        $facts = [$f('plan.owner_pay', '550', unit: 'NZD/week', basis: 'current_rate'), $f('model.owner_pay', '2383.33333333', unit: 'NZD/month', basis: 'current_rate')];
        self::assertSame(CheckStatus::Pass, $this->evaluateRule(new OwnerPayRule, $facts)->status);
        $facts[1] = $f('model.owner_pay', '3698', unit: 'NZD/month', basis: 'staged_average');
        self::assertSame(CheckStatus::CannotVerify, $this->evaluateRule(new OwnerPayRule, $facts)->status);
    }

    public function test_different_cash_dates_require_a_complete_source_bridge(): void
    {
        $f = SyntheticCase::fact(...);
        $facts = [$f('plan.opening_cash', '1000', basis: 'bank-A:unrestricted', date: '2026-10-31'), $f('model.opening_cash', '1100', basis: 'bank-A:unrestricted')];
        self::assertSame(CheckStatus::CannotVerify, $this->evaluateRule(new OpeningCashRule, $facts)->status);
        $facts[] = $f('cash.bridge.coverage', 'complete', period: '2026-10-31/2026-11-01');
        $facts[] = $f('cash.bridge.movement', '100', period: '2026-10-31/2026-11-01');
        self::assertSame(CheckStatus::Pass, $this->evaluateRule(new OpeningCashRule, $facts)->status);
        self::assertSame(CheckStatus::Fail, $this->evaluateRule(new OpeningCashRule, SyntheticCase::replace($facts, 'cash.bridge.movement', '50'))->status);
    }

    public function test_capacity_uses_each_stream_instead_of_applying_one_offer_limit_to_aggregate_revenue(): void
    {
        $f = SyntheticCase::fact(...);
        $facts = [];
        foreach (['intensive' => ['3', '2000', '6000'], 'workshop' => ['7', '200', '1400']] as $entity => [$units, $price, $revenue]) {
            $facts = [...$facts, $f('stream.revenue', $revenue, $entity), $f('stream.units', $units, $entity, unit: 'deliveries'),
                $f('stream.capacity', $units, $entity, unit: 'deliveries'), $f('stream.price', $price, $entity, unit: 'NZD/deliveries'), $f('stream.membership', true, $entity)];
        }
        self::assertSame(CheckStatus::Pass, $this->evaluateRule(new CapacityRule, $facts)->status);
        self::assertSame(CheckStatus::Fail, $this->evaluateRule(new CapacityRule, SyntheticCase::replace($facts, 'stream.capacity', '2', 'intensive'))->status);
        self::assertSame(CheckStatus::CannotVerify, $this->evaluateRule(new CapacityRule, SyntheticCase::replace($facts, 'stream.price', null, 'workshop'))->status);
    }

    public function test_margin_target_variance_requires_an_explanation_without_changing_profit(): void
    {
        $f = SyntheticCase::fact(...);
        $facts = [$f('plan.margin_min', '20', period: 'mature'), $f('plan.margin_max', '30', period: 'mature'), $f('model.npbt_margin', '57.1', period: 'year:1')];
        self::assertSame(CheckStatus::Fail, $this->evaluateRule(new MarginRule, $facts)->status);
        $facts[] = $f('plan.margin_explanation', 'The mature target includes the later staffing costs; the Year 1 calculation remains 57.1%.', period: 'mature');
        self::assertSame(CheckStatus::Pass, $this->evaluateRule(new MarginRule, $facts)->status);
        self::assertSame('57.1', $facts[2]->value);
    }

    public function test_material_committed_costs_need_a_sourced_mapping_with_the_same_amount_and_period(): void
    {
        $f = SyntheticCase::fact(...);
        $facts = [$f('plan.commitment', '1750', 'content', 'month:1'), $f('plan.commitment_in_scope', true, 'content', 'month:1'), $f('budget.cost_mapping', 'launch:1', 'content', 'month:1')];
        self::assertSame(CheckStatus::CannotVerify, $this->evaluateRule(new CommittedCostRule, $facts)->status);
        $facts[] = $f('budget.mapped_cost', '1750', 'content', 'month:1');
        self::assertSame(CheckStatus::Pass, $this->evaluateRule(new CommittedCostRule, $facts)->status);
        $missing = $this->evaluateRule(new CommittedCostRule, SyntheticCase::replace($facts, 'budget.cost_mapping', false));
        self::assertSame(CheckStatus::Fail, $missing->status);
        self::assertSame(Severity::Block, $missing->subchecks[0]->severity);
        $unknown = $this->evaluateRule(new CommittedCostRule, SyntheticCase::replace($facts, 'plan.commitment', null));
        self::assertSame(CheckStatus::CannotVerify, $unknown->status);
        self::assertSame(Severity::Review, $unknown->subchecks[0]->severity);
        self::assertSame(CheckStatus::NotApplicable, $this->evaluateRule(new CommittedCostRule, SyntheticCase::replace($facts, 'plan.commitment_in_scope', false))->status);
    }

    public function test_asset_review_uses_policy_and_the_reserve_gap_remains_a_review(): void
    {
        $f = SyntheticCase::fact(...);
        $asset = [$f('cost.amount', '18000', 'vehicle'), $f('cost.asset_like', true, 'vehicle'), $f('cost.classification', 'operating', 'vehicle')];
        self::assertSame(CheckStatus::Fail, $this->evaluateRule(new AssetClassificationRule, $asset)->status);
        $asset[] = $f('cost.policy', 'Accountant reviewed the classification, useful life and cash timing.', 'vehicle');
        self::assertSame(CheckStatus::Pass, $this->evaluateRule(new AssetClassificationRule, $asset)->status);
        $facts = [...SyntheticCase::cash(), $f('plan.funding_position', 'self_funded'), $f('model.cash_deficit', '0'), $f('model.reserve_gap', '232.73')];
        $result = $this->evaluateRule(new FundingPositionRule, $facts);
        self::assertSame(CheckStatus::Fail, $result->status);
        self::assertCount(1, array_filter($result->subchecks, fn ($s) => $s->status->unresolved()));
        self::assertSame(Severity::Review, $result->subchecks[array_key_last($result->subchecks)]->severity);
    }
}
