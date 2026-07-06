<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Services\Entrepreneurs\BudgetPackBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

final class BudgetPackBuilderTest extends TestCase
{
    public function test_budget_pack_html_contains_static_cash_curve_chart_with_markers(): void
    {
        $profile = new EntrepreneurProfile(['name' => 'Budget Founder']);
        $plan = new BusinessPlan(['title' => 'Budget runway plan']);
        $budget = new EntrepreneurBudget([
            'status' => EntrepreneurBudget::STATUS_COMPLETE,
            'forecast_years' => 1,
            'computed' => [
                'forecast_years' => 1,
                'break_even_month' => 2,
                'break_even_year' => 1,
                'first_profitable_year' => 1,
                'cash_flow_positive_year' => 1,
                'runway_months' => 3,
                'runway_open_ended' => false,
                'available_after_launch' => 4_000,
                'assumptions' => [
                    'gst_exclusive' => true,
                    'company_tax_configured' => true,
                    'company_tax_rate_percent' => 28,
                    'field_labels' => [],
                ],
                'annual_totals' => [[
                    'year' => 1,
                    'revenue' => 36_000,
                    'gross_profit' => 24_000,
                    'gross_profit_percent' => 66.67,
                    'fixed_costs' => 12_000,
                    'net_profit_before_tax' => 12_000,
                    'net_profit_before_tax_percent' => 33.33,
                    'tax' => 3_360,
                    'net_profit_after_tax' => 8_640,
                    'net_profit_after_tax_percent' => 24,
                    'ending_cash' => 12_640,
                ]],
                'monthly_detail' => [
                    ['month' => 1, 'month_in_year' => 1, 'year' => 1, 'revenue' => 1_000, 'variable_costs' => 0, 'gross_profit' => 1_000, 'fixed_costs' => 2_000, 'tax' => 0, 'net_profit_after_tax' => -1_000, 'net_cash_flow' => 3_000, 'cumulative_cash' => 3_000],
                    ['month' => 2, 'month_in_year' => 2, 'year' => 1, 'revenue' => 4_000, 'variable_costs' => 0, 'gross_profit' => 4_000, 'fixed_costs' => 2_000, 'tax' => 560, 'net_profit_after_tax' => 1_440, 'net_cash_flow' => 1_440, 'cumulative_cash' => 4_440],
                    ['month' => 3, 'month_in_year' => 3, 'year' => 1, 'revenue' => 4_000, 'variable_costs' => 0, 'gross_profit' => 4_000, 'fixed_costs' => 2_000, 'tax' => 560, 'net_profit_after_tax' => 1_440, 'net_cash_flow' => -500, 'cumulative_cash' => 3_940],
                ],
                'scenarios' => [],
                'explanations' => [],
            ],
            'flags' => [],
        ]);

        $plan->setRelation('budgetRunway', $budget);
        $plan->setRelation('sections', new EloquentCollection);

        $html = app(BudgetPackBuilder::class)->html($profile, $plan);

        $this->assertStringContainsString('<svg role="img" aria-label="Budget cash curve"', $html);
        $this->assertStringContainsString('Cash -- teal', $html);
        $this->assertStringContainsString('Revenue -- gold', $html);
        $this->assertStringContainsString('Break-even M2', $html);
        $this->assertStringContainsString('Runway M3', $html);
    }
}
