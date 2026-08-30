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
        $this->assertSame(4_200.0, $decision['required_additional_funding']);
        $this->assertSame(2, $decision['lowest_cash_month']);
    }

    public function test_unconfirmed_cadence_capacity_and_forecast_start_are_external_issue_warnings(): void
    {
        $budget = new EntrepreneurBudget([
            'status' => EntrepreneurBudget::STATUS_COMPLETE,
            'expected_runway_months' => 6,
            'computed' => [
                'opening_cash_balance' => 20_000,
                'total_funding' => 20_000,
                'total_launch_costs' => 0,
                'monthly_fixed_costs' => 1_000,
                'break_even_year' => 1,
                'cash_flow_positive_year' => 1,
                'assumptions' => ['company_tax_configured' => true],
                'monthly_detail' => [
                    ['month' => 1, 'cumulative_cash' => 39_000],
                ],
                'input_quality' => [
                    'unconfirmed_fixed_cost_cadences' => ['Annual registry fee'],
                    'unconfirmed_revenue_growth' => ['Advisory intensives'],
                    'revenue_without_capacity' => ['Advisory intensives'],
                    'missing_assumptions' => ['forecast_start_month'],
                ],
            ],
            'flags' => [],
        ]);

        $decision = (new BudgetFundingReadiness)->evaluate($budget);
        $warnings = implode("\n", $decision['warnings']);

        $this->assertFalse($decision['external_issue_ready']);
        $this->assertStringContainsString('billing cadence', $warnings);
        $this->assertStringContainsString('monthly or annual', $warnings);
        $this->assertStringContainsString('monthly capacity', $warnings);
        $this->assertStringContainsString('forecast start month', $warnings);
    }

    public function test_a_verified_external_request_can_be_ready_when_its_use_of_funds_matches_the_forecast(): void
    {
        $budget = new EntrepreneurBudget([
            'status' => EntrepreneurBudget::STATUS_COMPLETE,
            'expected_runway_months' => 6,
            'computed' => [
                'opening_cash_balance' => 0,
                'total_funding' => 20_000,
                'total_launch_costs' => 10_000,
                'monthly_fixed_costs' => 2_000,
                'break_even_year' => 1,
                'cash_flow_positive_year' => 1,
                'assumptions' => [
                    'company_tax_configured' => true,
                    'funding_position' => 'external_funding',
                    'funding_position_confirmed' => true,
                    'funding_request_purpose' => 'Launch costs, operating cover, and contingency',
                ],
                'monthly_detail' => [
                    ['month' => 1, 'cumulative_cash' => 5_000],
                    ['month' => 2, 'cumulative_cash' => 3_500],
                ],
                'input_quality' => [],
            ],
            'monthly_fixed_costs' => [[
                'label' => 'Core operating cost',
                'amount' => 2_000,
                'cadence' => 'monthly',
                'cadence_confirmed' => true,
            ]],
            'flags' => [],
        ]);

        $decision = (new BudgetFundingReadiness)->evaluate($budget);

        $this->assertGreaterThan(0, $decision['required_additional_funding']);
        $this->assertSame('External funding request', $decision['funding_position_label']);
        $this->assertTrue($decision['funding_position_aligned']);
        $this->assertTrue($decision['external_issue_ready']);
        $this->assertStringContainsString('external funding request', $decision['headline']);
    }
}
