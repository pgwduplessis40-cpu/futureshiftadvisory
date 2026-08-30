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

    public function test_year_two_revenue_carries_forward_the_year_one_exit_run_rate_by_default(): void
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

        $month12Revenue = $computed['monthly_detail'][11]['revenue'];
        $month13Revenue = $computed['monthly_detail'][12]['revenue'];

        $this->assertEqualsWithDelta($month12Revenue, $month13Revenue, 0.01);
        $this->assertStringContainsString('exit run-rate', $computed['explanations']['year_two_revenue_basis']);
        $this->assertSame('exit_run_rate', $computed['year_two_revenue_bridge']['basis']);
        $this->assertEqualsWithDelta($month12Revenue, $computed['year_two_revenue_bridge']['month_12_revenue'], 0.01);
        $this->assertEqualsWithDelta($month13Revenue, $computed['year_two_revenue_bridge']['month_13_revenue'], 0.01);
        $this->assertFalse($computed['year_two_revenue_bridge']['material_drop']);
    }

    public function test_year_two_revenue_can_use_the_year_one_average_for_a_deliberate_seasonal_assumption(): void
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
            assumptions: [
                'revenue_growth_percent' => 0,
                'year_two_revenue_basis' => 'year_one_average',
            ],
        );

        $expectedYearOneAverage = array_sum(array_map(
            fn (int $elapsed): float => 1_000 * (1.1 ** $elapsed),
            range(0, 11),
        )) / 12;

        $this->assertEqualsWithDelta($expectedYearOneAverage, $computed['monthly_detail'][12]['revenue'], 0.01);
        $this->assertGreaterThan($computed['monthly_detail'][12]['revenue'], $computed['monthly_detail'][11]['revenue']);
        $this->assertSame('year_one_average', $computed['year_two_revenue_bridge']['basis']);
        $this->assertTrue($computed['year_two_revenue_bridge']['material_drop']);
    }

    public function test_annual_revenue_growth_does_not_compound_the_first_year_monthly_forecast(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [],
            revenueForecast: [[
                'label' => 'Subscriptions',
                'amount' => 1_000,
                'quantity' => 1,
                'month' => 1,
                'monthly_growth_percent' => 0,
            ]],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 2,
            assumptions: ['revenue_growth_percent' => 25],
        );

        $this->assertSame(1_000.0, $computed['monthly_detail'][0]['revenue']);
        $this->assertSame(1_000.0, $computed['monthly_detail'][11]['revenue']);
        $this->assertEqualsWithDelta(1_000 * (1.25 ** (1 / 12)), $computed['monthly_detail'][12]['revenue'], 0.01);
        $this->assertEqualsWithDelta(1_250.0, $computed['monthly_detail'][23]['revenue'], 0.01);
    }

    public function test_tax_carries_losses_forward_across_the_forecast(): void
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
        $this->assertStringContainsString('carrying losses forward through the forecast', $computed['explanations']['tax_simplification']);
    }

    public function test_fixed_cost_cadences_are_converted_to_a_monthly_equivalent(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [[
                'label' => 'Annual registry fee',
                'amount' => 1_200,
                'cadence' => 'annual',
                'cadence_confirmed' => true,
            ]],
            revenueForecast: [],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 1,
        );

        $this->assertSame(100.0, $computed['monthly_detail'][0]['fixed_costs']);
        $this->assertSame(100.0, $computed['monthly_fixed_costs']);
        $this->assertSame(100.0, $computed['base_scenario']['summary']['year_one_monthly_fixed_costs']);
    }

    public function test_revenue_capacity_caps_a_monthly_growth_forecast(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [],
            revenueForecast: [[
                'label' => 'Advisory intensives',
                'amount' => 1_500,
                'quantity' => 10,
                'month' => 1,
                'growth_percent' => 25,
                'growth_cadence' => 'monthly',
                'growth_cadence_confirmed' => true,
                'monthly_capacity_units' => 3,
                'capacity_confirmed' => true,
                'unit_label' => 'intensives',
            ]],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 2,
        );

        $this->assertSame(4_500.0, $computed['monthly_detail'][0]['revenue']);
        $this->assertSame(4_500.0, $computed['monthly_detail'][11]['revenue']);
        $this->assertSame(4_500.0, $computed['monthly_detail'][12]['revenue']);
        $this->assertSame([], $computed['input_quality']['revenue_without_capacity']);
    }

    public function test_tax_losses_offset_profit_after_a_year_boundary(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [['label' => 'Rent', 'amount' => 1_000]],
            revenueForecast: [['label' => 'Sales', 'amount' => 1_500, 'month' => 7]],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 2,
            companyTaxRatePercent: 28,
        );

        $this->assertSame(-3_000.0, $computed['monthly_detail'][11]['tax_loss_carried_forward']);
        $this->assertSame(0.0, $computed['monthly_detail'][12]['tax']);
        $this->assertSame(500.0, $computed['monthly_detail'][12]['tax_loss_used']);
        $this->assertSame(-2_500.0, $computed['monthly_detail'][12]['tax_loss_carried_forward']);
        $this->assertSame(140.0, $computed['monthly_detail'][18]['tax']);
    }

    public function test_capital_assets_are_cash_purchases_with_depreciation_not_operating_costs(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [],
            revenueForecast: [['label' => 'Sales', 'amount' => 5_000]],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 2,
            futureCosts: [[
                'label' => 'Workshop equipment',
                'amount' => 12_000,
                'year' => 2,
                'classification' => 'capital',
                'useful_life_years' => 3,
            ]],
        );

        $monthThirteen = $computed['monthly_detail'][12];

        $this->assertSame(0.0, $monthThirteen['fixed_costs']);
        $this->assertEqualsWithDelta(333.33, $monthThirteen['depreciation'], 0.01);
        $this->assertSame(12_000.0, $monthThirteen['capital_expenditure']);
        $this->assertEqualsWithDelta(-7_000.0, $monthThirteen['net_cash_flow'], 0.01);
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

        $this->assertSame(900.0, $computed['monthly_detail'][1]['revenue']);
        $this->assertEqualsWithDelta(
            $computed['monthly_detail'][11]['revenue'] * (0.8 ** (1 / 12)),
            $computed['monthly_detail'][12]['revenue'],
            0.01,
        );
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

    public function test_opening_cash_balance_starts_cash_curve_without_becoming_funding(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [['label' => 'Fit-out', 'amount' => 5_000, 'month' => 2]],
            monthlyFixedCosts: [],
            revenueForecast: [],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 1,
            assumptions: ['opening_cash_balance' => 12_000],
        );

        $this->assertSame(12_000.0, $computed['opening_cash_balance']);
        $this->assertSame(0.0, $computed['total_funding']);
        $this->assertSame(7_000.0, $computed['available_after_launch']);
        $this->assertSame(12_000.0, $computed['monthly_detail'][0]['cumulative_cash']);
        $this->assertSame(5_000.0, $computed['monthly_detail'][1]['launch_costs']);
        $this->assertSame(7_000.0, $computed['monthly_detail'][1]['cumulative_cash']);
    }

    public function test_working_capital_timing_delays_revenue_and_supplier_cash(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [],
            revenueForecast: [[
                'label' => 'Sales',
                'amount' => 3_000,
                'gross_profit_percent' => 50,
            ]],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 1,
            assumptions: [
                'debtor_days' => 30,
                'creditor_days' => 60,
            ],
        );

        $this->assertSame(30, $computed['assumptions']['working_capital_timing']['debtor_days']);
        $this->assertSame(1, $computed['assumptions']['working_capital_timing']['debtor_lag_months']);
        $this->assertSame(60, $computed['assumptions']['working_capital_timing']['creditor_days']);
        $this->assertSame(2, $computed['assumptions']['working_capital_timing']['creditor_lag_months']);
        $this->assertSame(0.0, $computed['monthly_detail'][0]['cash_collected']);
        $this->assertSame(0.0, $computed['monthly_detail'][0]['variable_costs_paid']);
        $this->assertSame(0.0, $computed['monthly_detail'][0]['net_cash_flow']);
        $this->assertSame(3_000.0, $computed['monthly_detail'][1]['cash_collected']);
        $this->assertSame(0.0, $computed['monthly_detail'][1]['variable_costs_paid']);
        $this->assertStringContainsString('Debtor days delay forecast revenue', $computed['explanations']['working_capital_timing']);
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

    public function test_contractor_capacity_is_costed_after_founder_capacity_is_used(): void
    {
        $computed = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [],
            revenueForecast: [[
                'label' => 'Advisory intensives',
                'amount' => 1_500,
                'quantity' => 5,
                'month' => 1,
                'monthly_capacity_units' => 5,
                'capacity_confirmed' => true,
                'founder_capacity_units' => 2,
                'contractor_unit_cost' => 500,
                'contractor_cost_confirmed' => true,
                'source_type' => 'signed_contract',
                'source_reference' => 'Signed delivery agreement',
                'source_confirmed' => true,
            ]],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 1,
        );

        $monthOne = $computed['monthly_detail'][0];

        $this->assertSame(7_500.0, $monthOne['revenue']);
        $this->assertSame(1_500.0, $monthOne['contractor_delivery_costs']);
        $this->assertSame(1_500.0, $monthOne['variable_costs']);
        $this->assertSame([], $computed['input_quality']['revenue_with_unpriced_contractors']);
    }

    public function test_verification_gaps_and_zero_loan_terms_are_normalised_for_the_external_issue_gate(): void
    {
        $scenarios = $this->calculator()->normaliseFundingScenarios([[
            'name' => 'Bank facility',
            'type' => 'bank_loan',
            'amount' => 12_000,
            'term_years' => 0,
            'interest_only_months' => 120,
        ]]);
        $computed = $this->calculator()->compute(
            launchCosts: [],
            monthlyFixedCosts: [['label' => 'Software', 'amount' => 100]],
            revenueForecast: [['label' => 'Advisory', 'amount' => 1_000, 'monthly_capacity_units' => 1, 'capacity_confirmed' => true]],
            fundingSources: [],
            expectedRunwayMonths: null,
            forecastYears: 1,
        );

        $this->assertSame(1, $scenarios[0]['term_years']);
        $this->assertSame(11, $scenarios[0]['interest_only_months']);
        $this->assertSame(['Software'], $computed['input_quality']['unverified_fixed_cost_sources']);
        $this->assertSame(['Advisory'], $computed['input_quality']['unverified_revenue_sources']);
        $this->assertContains('opening_cash_balance', $computed['input_quality']['unverified_cash_timing']);
        $this->assertTrue($computed['input_quality']['funding_position_unconfirmed']);
    }

    private function calculator(): BudgetCalculator
    {
        return new BudgetCalculator;
    }
}
