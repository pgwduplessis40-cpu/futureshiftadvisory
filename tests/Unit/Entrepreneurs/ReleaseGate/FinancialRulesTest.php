<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\ReleaseGate\CheckStatus;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleCatalogue;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\RuleResult;
use App\Services\Entrepreneurs\ReleaseGate\Rules\CashLedgerRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\FixedCostRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\FundingSourcesRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\HorizonRule;
use App\Services\Entrepreneurs\ReleaseGate\Rules\TaxRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FinancialRulesTest extends TestCase
{
    /** @param list<Fact> $facts */
    private function evaluateRule(Rule $rule, array $facts): RuleResult
    {
        return new RuleResult(RuleCatalogue::get($rule->id()), true, $rule->evaluate(SyntheticCase::context($facts), new RuleConfiguration));
    }

    public function test_f11_complete_funded_positive_cash_passes_without_inventing_a_deficit(): void
    {
        foreach ([new CashLedgerRule, new FundingSourcesRule] as $rule) {
            $result = $this->evaluateRule($rule, SyntheticCase::cash());
            self::assertSame(CheckStatus::Pass, $result->status, json_encode($result));
            self::assertNotEmpty($result->subchecks);
        }
    }

    public static function corruptCash(): iterable
    {
        yield 'wrong addition' => ['cash.closing', '1151', 'business', 'month:1', CheckStatus::Fail];
        yield 'broken continuity' => ['cash.opening', '1149', 'business', 'month:2', CheckStatus::Fail];
        yield 'wrong annual total' => ['cash.annual_movement', '201', 'business', 'year:1', CheckStatus::Fail];
        yield 'missing row is unknown' => ['cash.closing', null, 'business', 'month:1', CheckStatus::CannotVerify];
        yield 'invalid calendar date' => ['ledger.date', '2026-11-31', 'cost-1', 'month:1', CheckStatus::Fail];
        yield 'duplicate transaction across months' => ['ledger.source', 'cost-1', 'cost-2', 'month:2', CheckStatus::Fail];
        yield 'unclassified payment' => ['ledger.category', 'unknown', 'cost-1', 'month:1', CheckStatus::CannotVerify];
        yield 'fractional horizon is not truncated' => ['model.horizon', '1.5', 'business', 'current', CheckStatus::CannotVerify];
    }

    #[DataProvider('corruptCash')]
    public function test_cash_faults_cannot_pass(string $key, ?string $value, string $entity, string $period, CheckStatus $expected): void
    {
        $facts = SyntheticCase::replace(SyntheticCase::cash(), $key, $value, $entity, $period);
        self::assertSame($expected, $this->evaluateRule(new CashLedgerRule, $facts)->status);
    }

    public static function corruptFunding(): iterable
    {
        yield 'late funds used early' => ['funding.availability', '2026-12-01', CheckStatus::Fail];
        yield 'double counted opening balance' => ['funding.identity', 'bank-A:unrestricted', CheckStatus::Fail];
        yield 'restricted funding' => ['funding.restriction', 'restricted', CheckStatus::Fail];
        yield 'tentative commitment' => ['funding.commitment', 'proposed', CheckStatus::CannotVerify];
        yield 'missing source identity' => ['funding.identity', null, CheckStatus::CannotVerify];
    }

    #[DataProvider('corruptFunding')]
    public function test_f13_source_timing_and_identity_cannot_be_waived(string $key, ?string $value, CheckStatus $expected): void
    {
        self::assertSame($expected, $this->evaluateRule(new FundingSourcesRule, SyntheticCase::replace(SyntheticCase::cash(), $key, $value))->status);
    }

    public function test_fixed_cost_expense_cash_bridge_and_annual_total_are_independent(): void
    {
        $f = SyntheticCase::fact(...);
        $facts = [$f('model.horizon', 2), $f('cost.schedule_complete', true), $f('cost.annual_total', '200', period: 'year:1')];
        foreach ([1, 2] as $m) {
            foreach (['cost.rate' => '100', 'cost.quantity' => '1', 'cost.frequency' => 'monthly', 'cost.expense' => '100', 'cost.payment' => $m === 1 ? '120' : '80', 'cost.accrual_movement' => $m === 1 ? '-20' : '20'] as $key => $value) {
                $facts[] = $f($key, $value, 'rent', 'month:'.$m);
            }
            $facts[] = $f('model.fixed_cost', '100', period: 'month:'.$m);
        }
        self::assertSame(CheckStatus::Pass, $this->evaluateRule(new FixedCostRule, $facts)->status);
        self::assertSame(CheckStatus::Fail, $this->evaluateRule(new FixedCostRule, SyntheticCase::replace($facts, 'cost.payment', '90', period: 'month:2'))->status);
        self::assertSame(CheckStatus::Fail, $this->evaluateRule(new FixedCostRule, SyntheticCase::replace($facts, 'cost.annual_total', '201'))->status);
        self::assertSame(CheckStatus::CannotVerify, $this->evaluateRule(new FixedCostRule, SyntheticCase::replace($facts, 'model.horizon', 3))->status);
    }

    public function test_tax_carries_losses_without_refunds_and_keeps_cash_payment_separate(): void
    {
        $f = SyntheticCase::fact(...);
        $facts = [$f('model.horizon', 2), $f('tax.policy', 'loss_carry_forward'), $f('tax.opening_losses', '0'), $f('tax.rate', '28')];
        foreach ([1 => ['-100', '0', '100', '0'], 2 => ['200', '28', '0', '28']] as $m => [$profit, $expense, $losses, $payable]) {
            $facts[] = $f('ledger.coverage', 'complete', period: 'month:'.$m);
            foreach (['tax.profit' => $profit, 'tax.adjustment' => '0', 'tax.expense' => $expense, 'tax.closing_losses' => $losses, 'tax.payment' => '0', 'tax.payable_movement' => $payable] as $key => $value) {
                $facts[] = $f($key, $value, period: 'month:'.$m);
            }
        }
        self::assertSame(CheckStatus::Pass, $this->evaluateRule(new TaxRule, $facts)->status);
        self::assertSame(CheckStatus::Fail, $this->evaluateRule(new TaxRule, SyntheticCase::replace($facts, 'tax.expense', '-28', period: 'month:1'))->status);
        self::assertSame(CheckStatus::CannotVerify, $this->evaluateRule(new TaxRule, SyntheticCase::replace($facts, 'tax.profit', null, period: 'month:1'))->status);
    }

    public function test_f17_reducing_the_model_horizon_does_not_remove_the_required_scope(): void
    {
        $f = SyntheticCase::fact(...);
        self::assertSame(CheckStatus::Fail, $this->evaluateRule(new HorizonRule, [$f('model.horizon', 1), $f('scope.prior_required_horizon', 36)])->status);
        self::assertSame(CheckStatus::Pass, $this->evaluateRule(new HorizonRule, [$f('model.horizon', 36), $f('scope.required_horizon', 36)])->status);
    }
}
