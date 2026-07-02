<?php

declare(strict_types=1);

namespace Tests\Unit\Budgets;

use App\Models\Client;
use App\Models\StrategicBudget;
use App\Services\Budgets\StrategicBudgetExcelExporter;
use App\Services\Budgets\StrategicBudgetService;
use Tests\TestCase;
use ZipArchive;

final class StrategicBudgetAnalyticsTest extends TestCase
{
    public function test_analytics_payload_exposes_budget_frameworks_and_charts(): void
    {
        $analytics = app(StrategicBudgetService::class)->analyticsPayload($this->budget());

        $this->assertStringContainsString('Plan coverage is 3/3 plan sections complete', $analytics['descriptive']['summary']);
        $this->assertSame(
            'Current budget view based on uploaded financial evidence and client-entered budget assumptions.',
            $analytics['descriptive']['explanation'],
        );
        $this->assertContains(
            'Plan goal: Grow recurring advisory revenue while protecting delivery capacity.',
            $analytics['descriptive']['findings'],
        );
        $this->assertSame(
            'Explains why the budget is strong, weak, incomplete, or risky.',
            $analytics['diagnostic']['explanation'],
        );
        $this->assertContains(
            'Missing assumptions: Some budget assumptions are still missing.',
            $analytics['diagnostic']['findings'],
        );
        $this->assertContains(
            'Plan risk noted: Runway is tight if implementation costs land before funding is confirmed.',
            $analytics['diagnostic']['findings'],
        );
        $this->assertSame(
            'Projects runway, break-even timing, cash-flow timing, and scenario outcomes.',
            $analytics['predictive']['explanation'],
        );
        $this->assertContains('Runway is 8 months.', $analytics['predictive']['findings']);
        $this->assertStringStartsWith('Next action:', $analytics['prescriptive']['summary']);
        $this->assertSame(
            'Turns budget signals into advisor/client actions before proposal reliance.',
            $analytics['prescriptive']['explanation'],
        );
        $this->assertContains(
            'Plan action priority to fund: Secure funding before large systems setup spend.',
            $analytics['prescriptive']['findings'],
        );

        $this->assertSame('Year 1', $analytics['charts']['annual_revenue_costs'][0]['label']);
        $this->assertSame(340000.0, $analytics['charts']['annual_revenue_costs'][0]['revenue']);
        $this->assertSame(312500.0, $analytics['charts']['annual_revenue_costs'][0]['costs']);
        $this->assertSame(64.7, $analytics['charts']['margin_percentages'][0]['gross_profit_percent']);
        $this->assertSame(6.9, $analytics['charts']['margin_percentages'][0]['net_profit_before_tax_percent']);
        $this->assertSame(6.9, $analytics['charts']['margin_percentages'][0]['net_profit_after_tax_percent']);
        $this->assertSame('Revenue growth %', $analytics['diagnostic']['missing_assumptions'][0]['label']);
        $this->assertSame('Known', $analytics['charts']['confidence_mix'][0]['label']);
        $this->assertSame('Base case', $analytics['charts']['scenario_comparison'][0]['name']);

        $actions = collect($analytics['prescriptive']['actions'])->pluck('action')->all();

        $this->assertContains('Complete growth, margin, inflation, and profit-target assumptions.', $actions);
        $this->assertContains('Confirm extra funding, delay implementation spend, or reduce launch costs.', $actions);
    }

    public function test_excel_export_contains_framework_sheets_forecasts_and_prescriptive_actions(): void
    {
        $exporter = app(StrategicBudgetExcelExporter::class);
        $workbook = $exporter->export($this->budget());

        $this->assertStringStartsWith('PK', $workbook);
        $this->assertStringEndsWith('.xlsx', $exporter->filename($this->budget()));

        $workbookXml = $this->xlsxPart($workbook, 'xl/workbook.xml');
        $this->assertStringContainsString('name="Descriptive"', $workbookXml);
        $this->assertStringContainsString('name="Diagnostic"', $workbookXml);
        $this->assertStringContainsString('name="Predictive"', $workbookXml);
        $this->assertStringContainsString('name="Prescriptive"', $workbookXml);
        $this->assertStringContainsString('name="Monthly Forecast"', $workbookXml);
        $this->assertStringContainsString('name="Annual Forecast"', $workbookXml);

        $descriptiveSheet = $this->xlsxPart($workbook, 'xl/worksheets/sheet2.xml');
        $diagnosticSheet = $this->xlsxPart($workbook, 'xl/worksheets/sheet3.xml');
        $prescriptiveSheet = $this->xlsxPart($workbook, 'xl/worksheets/sheet5.xml');
        $annualSheet = $this->xlsxPart($workbook, 'xl/worksheets/sheet7.xml');

        $this->assertStringContainsString('P&amp;L June.xlsx', $descriptiveSheet);
        $this->assertStringContainsString('Diagnoses', $diagnosticSheet);
        $this->assertStringContainsString('Confirm extra funding, delay implementation spend, or reduce launch costs.', $prescriptiveSheet);
        $this->assertStringContainsString('GP %', $annualSheet);
    }

    private function budget(): StrategicBudget
    {
        $budget = new StrategicBudget([
            'id' => 'budget-1',
            'client_id' => 'client-1',
            'label' => 'Strategic Advisory Budget',
            'pathway' => StrategicBudget::PATHWAY_ADVISORY,
            'status' => StrategicBudget::STATUS_CLIENT_WORKING_DRAFT,
            'horizon_months' => 24,
            'source_financials' => [
                'unlocked' => true,
                'count' => 2,
                'items' => [
                    [
                        'filename' => 'P&L June.xlsx',
                        'detected_as' => 'profit and loss',
                        'uploaded_at' => '2026-06-30T10:00:00+12:00',
                    ],
                ],
            ],
            'assumptions' => [
                'cost_inflation_percent' => 4,
                'target_gross_profit_percent' => 55,
            ],
            'business_plan_sections' => [
                [
                    'key' => 'goals',
                    'title' => 'Goals',
                    'prompt' => '',
                    'answer' => 'Grow recurring advisory revenue while protecting delivery capacity.',
                ],
                [
                    'key' => 'risks',
                    'title' => 'Risks',
                    'prompt' => '',
                    'answer' => 'Runway is tight if implementation costs land before funding is confirmed.',
                ],
                [
                    'key' => 'action_priorities',
                    'title' => 'Action priorities',
                    'prompt' => '',
                    'answer' => 'Secure funding before large systems setup spend.',
                ],
            ],
            'implementation_costs' => [
                ['label' => 'Systems setup', 'amount' => 180000, 'quantity' => 1],
            ],
            'monthly_fixed_costs' => [
                ['label' => 'Operations', 'amount' => 9500, 'quantity' => 1],
            ],
            'future_costs' => [
                ['label' => 'Year two project', 'amount' => 30000, 'quantity' => 1],
            ],
            'computed' => [
                'total_funding' => 110000,
                'available_after_launch' => -70000,
                'runway_months' => 8,
                'runway_open_ended' => false,
                'break_even_year' => 2,
                'cash_flow_positive_year' => 2,
                'missing_assumptions' => ['revenue_growth_percent'],
                'assumptions' => [
                    'field_labels' => [
                        'revenue_growth_percent' => 'Revenue growth %',
                    ],
                ],
                'annual_totals' => [
                    [
                        'year' => 1,
                        'revenue' => 340000,
                        'variable_costs' => 120000,
                        'fixed_costs' => 90000,
                        'interest' => 6500,
                        'tax' => 0,
                        'loan_principal' => 16000,
                        'funding_inflow' => 110000,
                        'launch_costs' => 80000,
                        'gross_profit' => 220000,
                        'net_profit_before_tax' => 23500,
                        'net_profit_after_tax' => 23500,
                        'net_cash_flow' => 37500,
                        'ending_cash' => 37500,
                    ],
                    [
                        'year' => 2,
                        'revenue' => 460000,
                        'variable_costs' => 150000,
                        'fixed_costs' => 112000,
                        'interest' => 4200,
                        'tax' => 38000,
                        'loan_principal' => 22000,
                        'funding_inflow' => 0,
                        'launch_costs' => 0,
                        'gross_profit' => 310000,
                        'net_profit_before_tax' => 193800,
                        'net_profit_after_tax' => 155800,
                        'net_cash_flow' => 133800,
                        'ending_cash' => 171300,
                    ],
                ],
                'monthly_detail' => [
                    [
                        'month' => 1,
                        'year' => 1,
                        'revenue' => 22000,
                        'variable_costs' => 9000,
                        'fixed_costs' => 7500,
                        'interest' => 500,
                        'tax' => 0,
                        'loan_principal' => 1000,
                        'funding_inflow' => 110000,
                        'launch_costs' => 80000,
                        'net_profit_after_tax' => 5000,
                        'net_cash_flow' => 34000,
                        'cumulative_cash' => 34000,
                    ],
                    [
                        'month' => 2,
                        'year' => 1,
                        'revenue' => 26000,
                        'variable_costs' => 10000,
                        'fixed_costs' => 7500,
                        'interest' => 500,
                        'tax' => 0,
                        'loan_principal' => 1000,
                        'funding_inflow' => 0,
                        'launch_costs' => 0,
                        'net_profit_after_tax' => 8000,
                        'net_cash_flow' => 7000,
                        'cumulative_cash' => 41000,
                    ],
                ],
                'scenarios' => [
                    [
                        'key' => 'base',
                        'name' => 'Base case',
                        'type' => 'base',
                        'summary' => [
                            'runway_months' => 8,
                            'runway_open_ended' => false,
                            'break_even_year' => 2,
                            'cash_flow_positive_year' => 2,
                            'total_funding' => 110000,
                        ],
                        'annual_totals' => [
                            ['ending_cash' => 171300],
                        ],
                    ],
                ],
            ],
            'confidence' => [
                'score' => 52,
                'row_confidence' => [
                    'known' => 2,
                    'estimate' => 3,
                    'guess' => 1,
                    'total' => 6,
                ],
            ],
            'flags' => [
                [
                    'key' => 'missing_assumptions',
                    'title' => 'Missing assumptions',
                    'severity' => 'medium',
                    'message' => 'Some budget assumptions are still missing.',
                ],
            ],
        ]);
        $budget->setRelation('client', new Client([
            'id' => 'client-1',
            'legal_name' => 'Acme Advisory Limited',
            'trading_name' => 'Acme & Co',
        ]));

        return $budget;
    }

    private function xlsxPart(string $workbook, string $part): string
    {
        $path = tempnam(sys_get_temp_dir(), 'budget-export-test-');
        $this->assertIsString($path);
        file_put_contents($path, $workbook);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $contents = $zip->getFromName($part);
        $zip->close();
        @unlink($path);

        $this->assertIsString($contents);

        return $contents;
    }
}
