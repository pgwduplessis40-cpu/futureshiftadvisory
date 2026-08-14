<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Models\EntrepreneurBudget;
use App\Services\Entrepreneurs\BudgetFundingReadiness;
use PHPUnit\Framework\TestCase;

final class BudgetFundingReadinessTest extends TestCase
{
    public function test_complete_inputs_with_a_cash_trough_are_not_ready_for_external_issue(): void
    {
        $budget = new EntrepreneurBudget([
            'status' => EntrepreneurBudget::STATUS_COMPLETE,
            'expected_runway_months' => 6,
            'computed' => [
                'opening_cash_balance' => 0,
                'total_funding' => 20_000,
                'total_launch_costs' => 10_000,
                'monthly_fixed_costs' => 2_000,
                'break_even_year' => 2,
                'cash_flow_positive_year' => 2,
                'assumptions' => ['company_tax_configured' => true],
                'monthly_detail' => [
                    ['month' => 1, 'cumulative_cash' => 5_000],
                    ['month' => 2, 'cumulative_cash' => -3_500],
                ],
            ],
            'flags' => [],
        ]);

        $decision = (new BudgetFundingReadiness)->evaluate($budget);

        $this->assertSame('Complete', $decision['input_status_label']);
        $this->assertSame('Not ready for external issue', $decision['readiness_label']);
        $this->assertFalse($decision['external_issue_ready']);
        $this->assertSame(3_500.0, $decision['additional_funding_needed']);
        $this->assertSame(2, $decision['lowest_cash_month']);
    }
}
