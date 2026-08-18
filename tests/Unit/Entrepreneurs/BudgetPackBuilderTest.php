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
                    ['month' => 12, 'month_in_year' => 12, 'year' => 1, 'revenue' => 8_000, 'variable_costs' => 0, 'gross_profit' => 8_000, 'fixed_costs' => 2_000, 'tax' => 1_680, 'net_profit_after_tax' => 4_320, 'net_cash_flow' => 4_320, 'cumulative_cash' => 8_260],
                    ['month' => 13, 'month_in_year' => 1, 'year' => 2, 'revenue' => 8_000, 'variable_costs' => 0, 'gross_profit' => 8_000, 'fixed_costs' => 2_000, 'tax' => 1_680, 'net_profit_after_tax' => 4_320, 'net_cash_flow' => 4_320, 'cumulative_cash' => 12_580],
                ],
                'year_two_revenue_bridge' => [
                    'basis' => 'exit_run_rate',
                    'basis_label' => 'Year 1 exit run-rate',
                    'month_12_revenue' => 8_000,
                    'month_13_revenue' => 8_000,
                    'year_one_average_monthly_revenue' => 4_750,
                    'change_amount' => 0,
                    'change_percent' => 0,
                    'material_drop' => false,
                    'explanation' => 'Month 13 carries forward the Year 1 exit run-rate.',
                ],
                'monthly_fixed_costs' => 2_000,
                'scenarios' => [],
                'explanations' => [],
            ],
            'monthly_fixed_costs' => [
                ['label' => 'Studio rent', 'amount' => 2_000, 'quantity' => 1, 'month' => 1, 'confidence' => 'known'],
            ],
            'flags' => [],
        ]);

        $plan->setRelation('budgetRunway', $budget);
        $plan->setRelation('sections', new EloquentCollection);

        $html = app(BudgetPackBuilder::class)->html($profile, $plan);

        $this->assertStringContainsString('Funding position', $html);
        $this->assertStringContainsString('Funding build-up', $html);
        $this->assertStringContainsString('Assumption quality', $html);
        $this->assertStringContainsString('Scenario comparison', $html);
        $this->assertStringContainsString('Appendix - Year 1 monthly detail', $html);
        $this->assertStringContainsString('<svg role="img" aria-label="Budget cash curve"', $html);
        $this->assertStringContainsString('legend-dot-cash', $html);
        $this->assertStringContainsString('legend-dot-revenue', $html);
        $this->assertStringContainsString('Founder - Budget Founder', $html);
        $this->assertStringNotContainsString('Budget inputs', $html);
        $this->assertStringContainsString('Internal draft - not for external issue', $html);
        $this->assertStringContainsString('Monthly fixed-cost trace', $html);
        $this->assertStringContainsString('Saved fixed-cost rows total $2,000 per month; the model base used for funding calculations is $2,000 per month.', $html);
        $this->assertStringContainsString('Traces to saved fixed-cost row.', $html);
        $this->assertStringContainsString('Year 2 revenue bridge', $html);
        $this->assertStringContainsString('Month 13 carries forward the Year 1 exit run-rate.', $html);
        $this->assertStringContainsString('Later-year cost trace', $html);
        $this->assertStringContainsString('Lowest cash M1', $html);
        $this->assertStringContainsString('Break-even M2', $html);
        $this->assertStringContainsString('Runway M3', $html);
        $this->assertStringContainsString('class="summary funding-summary"', $html);
        $this->assertStringContainsString('class="report-section funding-build-up page"', $html);
        $this->assertStringContainsString('class="report-section annual-forecast page"', $html);
        $this->assertStringContainsString('class="report-section assumption-quality page"', $html);
        $this->assertStringContainsString('.funding-build-up { break-before: page; }', $html);
    }

    public function test_budget_pack_html_flags_fixed_cost_mismatch_and_owner_compensation_rows(): void
    {
        $profile = new EntrepreneurProfile(['name' => 'Budget Founder']);
        $plan = new BusinessPlan(['title' => 'Budget runway plan']);
        $budget = new EntrepreneurBudget([
            'status' => EntrepreneurBudget::STATUS_COMPLETE,
            'forecast_years' => 1,
            'computed' => [
                'forecast_years' => 1,
                'break_even_year' => 1,
                'cash_flow_positive_year' => 1,
                'runway_months' => 6,
                'monthly_fixed_costs' => 51_573,
                'assumptions' => [
                    'gst_exclusive' => true,
                    'company_tax_configured' => true,
                    'company_tax_rate_percent' => 28,
                    'field_labels' => [],
                ],
                'monthly_detail' => [
                    ['month' => 1, 'month_in_year' => 1, 'year' => 1, 'revenue' => 80_000, 'fixed_costs' => 51_573, 'net_cash_flow' => 20_000, 'cumulative_cash' => 20_000],
                ],
                'scenarios' => [],
                'annual_totals' => [],
            ],
            'monthly_fixed_costs' => [
                ['label' => 'Software subscriptions', 'amount' => 8_400, 'quantity' => 1, 'month' => 1, 'confidence' => 'known'],
                ['label' => 'Owners Compensation- current $550 wk $45,500', 'amount' => 0, 'quantity' => 1, 'month' => 1, 'confidence' => 'estimate'],
            ],
            'flags' => [],
        ]);

        $plan->setRelation('budgetRunway', $budget);
        $plan->setRelation('sections', new EloquentCollection);

        $html = app(BudgetPackBuilder::class)->html($profile, $plan);

        $this->assertStringContainsString('Fixed-cost reconciliation warning', $html);
        $this->assertStringContainsString('Add the missing rows or relabel the table', $html);
        $this->assertStringContainsString('Owner compensation - current', $html);
        $this->assertStringContainsString('Clarify whether the saved amount is weekly, monthly, or annual', $html);
    }

    public function test_budget_pack_fallback_pdf_is_structured_without_browser_renderer(): void
    {
        $profile = new EntrepreneurProfile(['name' => 'Budget Founder']);
        $plan = new BusinessPlan(['title' => 'Budget runway plan']);
        $budget = new EntrepreneurBudget([
            'status' => EntrepreneurBudget::STATUS_COMPLETE,
            'forecast_years' => 1,
            'computed' => [
                'forecast_years' => 1,
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
                    'net_profit_after_tax' => 8_640,
                    'ending_cash' => 12_640,
                ]],
                'monthly_detail' => [
                    ['month' => 1, 'month_in_year' => 1, 'year' => 1, 'revenue' => 1_000, 'gross_profit' => 1_000, 'fixed_costs' => 2_000, 'net_cash_flow' => 3_000, 'cumulative_cash' => 3_000],
                    ['month' => 2, 'month_in_year' => 2, 'year' => 1, 'revenue' => 4_000, 'gross_profit' => 4_000, 'fixed_costs' => 2_000, 'net_cash_flow' => 1_440, 'cumulative_cash' => 4_440],
                    ['month' => 3, 'month_in_year' => 3, 'year' => 1, 'revenue' => 4_000, 'gross_profit' => 4_000, 'fixed_costs' => 2_000, 'net_cash_flow' => -500, 'cumulative_cash' => 3_940],
                ],
                'scenarios' => [],
                'explanations' => [],
            ],
            'flags' => [],
        ]);

        $plan->setRelation('budgetRunway', $budget);
        $plan->setRelation('sections', new EloquentCollection);

        $pdf = app(BudgetPackBuilder::class)->fallbackPdf($profile, $plan);

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('Budget Pack', $pdf);
        $this->assertStringContainsString('Founder - Budget Founder', $pdf);
        $this->assertStringContainsString('BUDGET PACK', $pdf);
        $this->assertStringContainsString('Funding position', $pdf);
        $this->assertStringContainsString('Funding build-up', $pdf);
        $this->assertStringContainsString('Assumption quality', $pdf);
        $this->assertStringContainsString('Scenario comparison', $pdf);
        $this->assertStringContainsString('Cash and revenue trend', $pdf);
        $this->assertStringContainsString('Annual forecast profile', $pdf);
        $this->assertStringNotContainsString('FALLBACK PDF', $pdf);
        $this->assertStringNotContainsString('Fallback rendering', $pdf);
        $this->assertStringContainsString('INTERNAL DRAFT - NOT FOR EXTERNAL ISSUE', $pdf);
    }
}
