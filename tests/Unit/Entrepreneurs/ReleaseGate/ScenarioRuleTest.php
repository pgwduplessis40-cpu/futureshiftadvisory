<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\ReleaseGate\CheckStatus;
use App\Services\Entrepreneurs\ReleaseGate\RuleCatalogue;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\RuleResult;
use App\Services\Entrepreneurs\ReleaseGate\Rules\ScenarioRule;
use PHPUnit\Framework\TestCase;

final class ScenarioRuleTest extends TestCase
{
    public function test_f12_reconciled_downside_cash_can_exceed_base_cash(): void
    {
        $facts = $this->facts();
        $checks = (new ScenarioRule)->evaluate(SyntheticCase::context($facts, 1), new RuleConfiguration);
        $result = new RuleResult(RuleCatalogue::get('SS-01'), true, $checks);
        self::assertSame(CheckStatus::Pass, $result->status, json_encode($result));
        $facts = SyntheticCase::replace($facts, 'scenario.input', '1700', 'sales', 'month:1');
        $checks = (new ScenarioRule)->evaluate(SyntheticCase::context($facts, 1), new RuleConfiguration);
        self::assertSame(CheckStatus::Fail, (new RuleResult(RuleCatalogue::get('SS-01'), true, $checks))->status);
    }

    private function facts(): array
    {
        $f = SyntheticCase::fact(...);
        $facts = [$f('model.horizon', 1), $f('model.opening_cash', '10000')];
        // Receipts lag revenue. Under the same synthetic tax payment policy,
        // the downside expense stays payable this month. No cash ordering rule.
        foreach (['base' => ['2000', '1000', '560', '440', '10440'], 'downside' => ['1600', '800', '0', '800', '10800']] as $scenario => [$revenue, $receipts, $taxPaid, $movement, $closing]) {
            foreach (['tax.policy' => 'loss_carry_forward', 'tax.opening_losses' => '0', 'tax.rate' => '28', 'tax.payment_policy' => 'Synthetic: expense at least 500 paid this month; otherwise remains payable.'] as $key => $value) {
                $facts[] = $f($key, $value, scenario: $scenario);
            }
            foreach (['tax.profit' => $revenue, 'tax.adjustment' => '0', 'tax.expense' => $scenario === 'base' ? '560' : '448', 'tax.closing_losses' => '0', 'tax.payment' => $taxPaid, 'tax.payable_movement' => $scenario === 'base' ? '0' : '448'] as $key => $value) {
                $facts[] = $f($key, $value, period: 'month:1', scenario: $scenario);
            }
            foreach (['scenario.baseline' => 'same-source-version', 'scenario.shocks' => $scenario === 'base' ? 'none' : 'sales x 0.8', 'scenario.input_inventory' => 'complete', 'scenario.lowest_cash' => $closing] as $key => $value) {
                $facts[] = $f($key, $value, scenario: $scenario);
            }
            $facts[] = $f('scenario.input', $revenue, 'sales', 'month:1', $scenario);
            if ($scenario === 'downside') {
                $facts[] = $f('scenario.shock_multiplier', '0.8', 'sales', 'month:1', $scenario);
            }
            foreach (['cash.opening' => '10000', 'cash.movement' => $movement, 'cash.closing' => $closing, 'ledger.coverage' => 'complete', 'cash.period_start' => '2026-11-01', 'scenario.profit' => $revenue, 'scenario.revenue' => $revenue, 'scenario.variable_costs' => '0', 'scenario.fixed_costs' => '0', 'scenario.depreciation' => '0', 'scenario.interest' => '0'] as $key => $value) {
                $facts[] = $f($key, $value, period: 'month:1', scenario: $scenario);
            }
            $facts[] = $f('cash.annual_movement', $movement, period: 'year:1', scenario: $scenario);
            $facts[] = $f('cash.annual_closing', $closing, period: 'year:1', scenario: $scenario);
            foreach (['receipts' => [$receipts, 'customer_receipts'], 'tax' => [$taxPaid, 'cash_tax']] as $entity => [$amount, $category]) {
                foreach (['ledger.amount' => $amount, 'ledger.category' => $category, 'ledger.date' => '2026-11-30', 'ledger.source' => $scenario.':'.$entity] as $key => $value) {
                    $facts[] = $f($key, $value, $entity, 'month:1', $scenario);
                }
            }
        }

        return $facts;
    }
}
