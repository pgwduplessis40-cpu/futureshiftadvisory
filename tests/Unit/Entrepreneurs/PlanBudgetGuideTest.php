<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Services\Entrepreneurs\BudgetCalculator;
use App\Services\Entrepreneurs\PlanBudgetCoherence;
use App\Services\Entrepreneurs\PlanBudgetGuide;
use Tests\TestCase;

final class PlanBudgetGuideTest extends TestCase
{
    public function test_assessment_routes_the_same_monthly_ramp_difference_to_the_budget_input(): void
    {
        $result = (new PlanBudgetCoherence)->evaluate([
            'phases' => [['sections' => [[
                'requirement_key' => 'financial-assumptions', 'title' => 'Financial assumptions',
                'body' => 'Month 1 ($3,000). Opening cash is $7,300. The business is self-funded.',
            ]]]],
            'budget' => ['status' => 'complete', 'assessment_evidence' => [
                'assumptions' => ['opening_cash_balance' => 7300],
                'computed' => ['monthly_series' => [['month' => 1, 'revenue' => 12250]]],
            ]],
        ]);
        $finding = collect($result['findings'])->firstWhere('input_key', 'sales_forecast');
        $this->assertNotNull($finding);
        $this->assertStringContainsString('Month 1 ($3,000)', $finding['message']);
        $this->assertStringContainsString('12,250.00', $finding['message']);
        $this->assertNotNull(collect($result['actions'])->firstWhere('key', 'sales_forecast'));
        $this->assertFalse($result['approval_available']);
    }

    public function test_specific_plan_claims_produce_specific_input_questions(): void
    {
        $guide = (new PlanBudgetGuide)->build([[
            'requirement_key' => 'financial-assumptions', 'title' => 'Financial assumptions',
            'body' => 'Opening cash is $7,300. I currently allocate $550 per week to owner pay. Month 1 ($3,000). Break-even is projected in Year 2.',
        ]], [
            'assumptions' => ['opening_cash_balance' => 7531],
            'monthly_fixed_costs' => [['label' => 'Owner pay', 'amount' => 875, 'cadence' => 'weekly']],
            'computed' => ['break_even_year' => 1, 'monthly_series' => [['month' => 1, 'revenue' => 12250]]],
        ]);
        $this->assertEqualsCanonicalizing(['opening_cash_balance', 'owner_weekly_pay', 'month_1_revenue', 'break_even_year'], array_column($guide['issues'], 'key'));
        $this->assertSame('funding-sources', $guide['issues'][0]['focus']);
        $this->assertStringContainsString('7,531.00', $guide['issues'][0]['detail']);
    }

    public function test_corrected_inputs_clear_questions_without_a_second_approval_form(): void
    {
        $guide = (new PlanBudgetGuide)->build([[
            'requirement_key' => 'financial-assumptions',
            'body' => 'Opening cash is $7,300. I currently allocate $550 per week to owner pay. Month 1 ($3,000). Break-even is projected in Year 1.',
        ]], [
            'assumptions' => ['opening_cash_balance' => 7300],
            'monthly_fixed_costs' => [['label' => 'Owner pay', 'amount' => 550, 'cadence' => 'weekly']],
            'computed' => ['break_even_year' => 1, 'monthly_series' => [['month' => 1, 'revenue' => 3000]]],
        ]);
        $this->assertSame([], $guide['issues']);
    }

    public function test_conflicting_plan_values_are_not_silently_selected(): void
    {
        $guide = (new PlanBudgetGuide)->build([[
            'requirement_key' => 'financial-assumptions',
            'body' => 'Opening cash: $7,300. UPDATED Opening cash: $7,531. After-tax profits: 50% for giving. We do not need a loan or grant.',
        ]], []);
        $this->assertCount(2, $guide['facts']);
        $this->assertSame('opening_cash_balance', $guide['issues'][0]['key']);
        $this->assertStringContainsString('one current assumption', $guide['issues'][0]['action']);
    }

    public function test_excluded_offers_and_costs_beyond_the_forecast_are_visible(): void
    {
        $guide = (new PlanBudgetGuide)->build([[
            'requirement_key' => 'revenue-model',
            'body' => 'The Creative Business Intensive is the core revenue stream used in the base forecast. Other offers have been excluded from the conservative base revenue forecast.',
        ]], [
            'forecast_years' => 1,
            'revenue_forecast' => [['label' => 'Intensives', 'amount' => 1500], ['label' => 'Workshops', 'amount' => 750]],
            'future_costs' => [['label' => 'Vehicle purchase', 'amount' => 18000, 'year' => 2, 'classification' => 'operating']],
        ]);
        $this->assertEqualsCanonicalizing(['revenue_scope', 'horizon_0', 'asset_0'], array_column($guide['issues'], 'key'));
        $this->assertStringContainsString('Workshops', $guide['issues'][0]['detail']);
        $this->assertStringNotContainsString('Intensives', $guide['issues'][0]['detail']);
    }

    public function test_explicit_monthly_ramp_and_pay_schedule_flow_through_cash_and_later_years(): void
    {
        $calculator = new BudgetCalculator;
        $computed = $calculator->compute(
            launchCosts: [],
            monthlyFixedCosts: [['label' => 'Owner pay', 'amount' => 550, 'cadence' => 'weekly', 'monthly_schedule' => [550, 550, 875, 875, 875, 875, 1200, 1200, 1200, 1200, 1200, 1500]]],
            revenueForecast: [['label' => 'Intensives', 'amount' => 1500, 'quantity' => 2, 'month' => 1, 'growth_percent' => 25, 'monthly_capacity_units' => 8, 'monthly_schedule' => [2, 4, 6, 6, 6, 6, 6, 6, 6, 6, 6, 8]]],
            fundingSources: [], expectedRunwayMonths: null, forecastYears: 3,
            assumptions: ['opening_cash_balance' => 7300, 'revenue_growth_percent' => 0, 'cost_inflation_percent' => 0],
        );
        $months = $computed['monthly_detail'];
        $this->assertSame([3000.0, 6000.0, 9000.0], array_column(array_slice($months, 0, 3), 'revenue'));
        $this->assertEqualsWithDelta(550 * 52 / 12, $months[0]['fixed_costs'], 0.01);
        $this->assertEqualsWithDelta(875 * 52 / 12, $months[2]['fixed_costs'], 0.01);
        $this->assertEqualsWithDelta(1500 * 52 / 12, $months[12]['fixed_costs'], 0.01);
        $this->assertSame($months[11]['revenue'], $months[12]['revenue']);
        $this->assertSame(12000.0, $months[12]['revenue']);
        $this->assertCount(3, $computed['annual_totals']);
        $this->assertEqualsWithDelta(7300 + $months[0]['net_cash_flow'], $months[0]['cumulative_cash'], 0.01);
    }

    public function test_zero_sales_override_and_average_basis_are_preserved(): void
    {
        $computed = (new BudgetCalculator)->compute([], [], [[
            'label' => 'Orders', 'amount' => 100, 'quantity' => 2, 'month' => 1,
            'monthly_schedule' => [0, 0, 3],
        ]], [], null, forecastYears: 2, assumptions: ['revenue_growth_percent' => 0, 'year_two_revenue_basis' => 'year_one_average']);
        $this->assertSame(0.0, $computed['monthly_detail'][0]['revenue']);
        $this->assertSame(300.0, $computed['monthly_detail'][2]['revenue']);
        $this->assertEqualsWithDelta(175, $computed['monthly_detail'][12]['revenue'], 0.01);
    }
}
