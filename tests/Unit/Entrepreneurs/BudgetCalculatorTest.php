<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Services\Entrepreneurs\BudgetCalculator;
use PHPUnit\Framework\TestCase;

final class BudgetCalculatorTest extends TestCase
{
    public function test_bank_loan_scenario_uses_interest_only_period_then_amortises_principal(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [],
            revenueForecast: [],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 1,
            fundingScenarios: [[
                'name' => 'Bank facility',
                'type' => 'bank_loan',
                'amount' => 12_000,
                'year' => 1,
                'interest_rate_percent' => 12,
                'term_years' => 1,
                'interest_only_months' => 2,
            ]],
        );

        $scenario = $computed['scenarios'][1];

        $this->assertSame(12_000.0, $scenario['monthly_detail'][0]['funding_inflow']);
        $this->assertSame(120.0, $scenario['monthly_detail'][0]['interest']);
        $this->assertSame(0.0, $scenario['monthly_detail'][0]['loan_principal']);
        $this->assertSame(120.0, $scenario['monthly_detail'][1]['interest']);
        $this->assertSame(0.0, $scenario['monthly_detail'][1]['loan_principal']);
        $this->assertEqualsWithDelta(120.0, $scenario['monthly_detail'][2]['interest'], 0.01);
        $this->assertEqualsWithDelta(1146.98, $scenario['monthly_detail'][2]['loan_principal'], 0.01);
        $this->assertEqualsWithDelta(108.53, $scenario['monthly_detail'][3]['interest'], 0.01);
        $this->assertEqualsWithDelta(1158.45, $scenario['monthly_detail'][3]['loan_principal'], 0.01);
    }

    public function test_year_two_revenue_uses_year_one_average_not_final_ramp_month(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [],
            revenueForecast: [[
                'label' => 'Subscriptions',
                'amount' => 1_000,
                'quantity' => 1,
                'month' => 1,
                'monthly_growth_percent' => 10,
            ]],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 2,
            assumptions: ['revenue_growth_percent' => 0],
        );

        $expectedYearOneAverage = array_sum(array_map(
            fn (int $elapsed): float => 1_000 * (1.1 ** $elapsed),
            range(0, 11),
        )) / 12;

        $month12Revenue = $computed['monthly_detail'][11]['revenue'];
        $month13Revenue = $computed['monthly_detail'][12]['revenue'];

        $this->assertEqualsWithDelta(round($expectedYearOneAverage, 2), $month13Revenue, 0.01);
        $this->assertGreaterThan($month13Revenue, $month12Revenue);
        $this->assertStringContainsString('month 13 can be lower than month 12', $computed['explanations']['year_two_revenue_basis']);
    }

    public function test_tax_carries_losses_forward_within_forecast_year(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [['label' => 'Rent', 'amount' => 1_000]],
            revenueForecast: [['label' => 'Sales', 'amount' => 3_000, 'month' => 7]],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 1,
            companyTaxRatePercent: 28,
        );

        $this->assertSame(-1_000.0, $computed['monthly_detail'][0]['net_profit_before_tax']);
        $this->assertSame(0.0, $computed['monthly_detail'][0]['tax']);
        $this->assertSame(2_000.0, $computed['monthly_detail'][6]['net_profit_before_tax']);
        $this->assertSame(0.0, $computed['monthly_detail'][6]['tax']);
        $this->assertSame(-4_000.0, $computed['monthly_detail'][6]['tax_loss_carried_forward']);
        $this->assertSame(560.0, $computed['monthly_detail'][9]['tax']);
        $this->assertStringContainsString('carrying earlier losses forward within the same forecast year', $computed['explanations']['tax_simplification']);
    }

    public function test_automatic_downside_scenarios_are_generated(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [['label' => 'Rent', 'amount' => 1_000]],
            revenueForecast: [['label' => 'Sales', 'amount' => 5_000]],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 1,
        );

        $scenarios = collect($computed['scenarios'])->keyBy('key');

        $this->assertTrue($scenarios->has('revenue_downside'));
        $this->assertTrue($scenarios->has('cost_upside'));
        $this->assertTrue($scenarios->has('combined_downside'));
        $this->assertSame(4_000.0, $scenarios['revenue_downside']['monthly_detail'][0]['revenue']);
        $this->assertSame(1_100.0, $scenarios['cost_upside']['monthly_detail'][0]['fixed_costs']);
        $this->assertSame(1_100.0, $scenarios['combined_downside']['monthly_detail'][0]['fixed_costs']);
        $this->assertStringContainsString('Automatic sensitivity scenarios', $computed['explanations']['automatic_scenarios']);
    }

    public function test_investor_scenario_discloses_equity_sold_and_founder_ownership(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [],
            revenueForecast: [],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 1,
            fundingScenarios: [[
                'name' => 'Seed investor',
                'type' => 'investor',
                'amount' => 50_000,
                'investor_equity_percent' => 20,
            ]],
        );

        $scenario = $computed['scenarios'][1];

        $this->assertSame(20.0, $scenario['equity_sold_percent']);
        $this->assertSame(80.0, $scenario['founder_ownership_percent']);
        $this->assertSame(20.0, $scenario['summary']['equity_sold_percent']);
        $this->assertStringContainsString('remaining founder ownership', $computed['explanations']['investor_equity']);
    }

    public function test_negative_growth_and_deflation_are_modelled(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [['label' => 'Rent', 'amount' => 1_000]],
            revenueForecast: [[
                'label' => 'Subscriptions',
                'amount' => 1_000,
                'month' => 1,
                'monthly_growth_percent' => -10,
            ]],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 2,
            assumptions: [
                'revenue_growth_percent' => -20,
                'cost_inflation_percent' => -10,
            ],
        );

        $expectedYearOneAverage = array_sum(array_map(
            fn (int $elapsed): float => 1_000 * (0.9 ** $elapsed),
            range(0, 11),
        )) / 12;

        $this->assertSame(900.0, $computed['monthly_detail'][1]['revenue']);
        $this->assertEqualsWithDelta(round($expectedYearOneAverage * 0.8, 2), $computed['monthly_detail'][12]['revenue'], 0.01);
        $this->assertSame(900.0, $computed['monthly_detail'][12]['fixed_costs']);
        $this->assertSame(-20.0, $computed['assumptions']['revenue_growth_percent']);
        $this->assertSame(-10.0, $computed['assumptions']['cost_inflation_percent']);
        $this->assertStringContainsString('downside and deflation cases are modelled', $computed['explanations']['downside_growth']);
    }

    public function test_launch_cost_month_is_honoured_in_cash_curve(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [['label' => 'Second fit-out payment', 'amount' => 5_000, 'month' => 3]],
            monthlyFixedCosts: [],
            revenueForecast: [],
            fundingSources: [['label' => 'Founder cash', 'amount' => 10_000]],
            expectedRunwayMonths: null,
            forecastYears: 1,
        );

        $this->assertSame(0.0, $computed['monthly_detail'][0]['launch_costs']);
        $this->assertSame(5_000.0, $computed['monthly_detail'][2]['launch_costs']);
        $this->assertSame(10_000.0, $computed['monthly_detail'][0]['cumulative_cash']);
        $this->assertSame(5_000.0, $computed['monthly_detail'][2]['cumulative_cash']);
        $this->assertSame(5_000.0, $computed['total_launch_costs']);
    }

    public function test_fixed_cost_start_month_is_honoured_in_monthly_simulation(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [['label' => 'Software subscription', 'amount' => 1_000, 'month' => 6]],
            revenueForecast: [],
            fundingSources: [['label' => 'Opening cash', 'amount' => 10_000, 'month' => 1]],
            expectedRunwayMonths: null,
            forecastYears: 1,
            assumptions: ['cost_inflation_percent' => 0],
        );

        $this->assertSame(0.0, $computed['monthly_detail'][0]['fixed_costs']);
        $this->assertSame(0.0, $computed['monthly_detail'][4]['fixed_costs']);
        $this->assertSame(1_000.0, $computed['monthly_detail'][5]['fixed_costs']);
    }

    public function test_runway_break_even_zero_runway_and_open_ended_edges(): void
    {
        $shortRunway = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [['label' => 'Rent', 'amount' => 1_000]],
            revenueForecast: [],
            fundingSources: [['label' => 'Founder cash', 'amount' => 10_000]],
            expectedRunwayMonths: null,
            forecastYears: 1,
        );

        $this->assertSame(10, $shortRunway['runway_months']);
        $this->assertFalse($shortRunway['runway_open_ended']);
        $this->assertFalse($shortRunway['break_even_reached']);
        $this->assertNull($shortRunway['break_even_month']);
        $this->assertNull($shortRunway['break_even_year']);

        $zeroRunway = $this->calculator()->compute(
            launchCosts: [['label' => 'Fit-out', 'amount' => 1_000]],
            monthlyFixedCosts: [],
            revenueForecast: [],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 1,
        );

        $this->assertSame(0, $zeroRunway['runway_months']);
        $this->assertFalse($zeroRunway['runway_open_ended']);

        $openEnded = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [],
            revenueForecast: [],
            fundingSources: [['label' => 'Founder cash', 'amount' => 5_000]],
            expectedRunwayMonths: null,
            forecastYears: 1,
        );

        $this->assertSame(12, $openEnded['runway_months']);
        $this->assertTrue($openEnded['runway_open_ended']);
    }

    public function test_empty_inputs_do_not_claim_open_ended_runway(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [],
            revenueForecast: [],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 1,
        );

        $this->assertSame(0, $computed['input_count']);
        $this->assertNull($computed['runway_months']);
        $this->assertFalse($computed['runway_open_ended']);
        $this->assertFalse($computed['break_even_reached']);
    }

    private function calculator(): BudgetCalculator
    {
        return new BudgetCalculator;
    }
}
