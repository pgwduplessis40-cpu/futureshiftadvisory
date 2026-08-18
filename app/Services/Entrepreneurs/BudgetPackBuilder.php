<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Services\Pdf\SimpleTextPdf;
use App\Services\Reports\BrandedReportLayout;
use Illuminate\Support\Collection;

final class BudgetPackBuilder
{
    public function __construct(
        private readonly BrandedReportLayout $layout,
        private readonly SimpleTextPdf $fallbackPdf,
        private readonly BudgetFundingReadiness $readiness,
        private readonly BusinessPlanIdentity $identity,
        private readonly EntrepreneurDocumentTemplate $templates,
        private readonly PlanIssueReadiness $issueReadiness,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(EntrepreneurProfile $profile, BusinessPlan $plan): array
    {
        $plan->loadMissing('budgetRunway', 'sections');
        $budget = $plan->budgetRunway;

        if (! $budget instanceof EntrepreneurBudget) {
            return [
                'available' => false,
                'profile_name' => $profile->name,
                'plan_title' => $plan->title,
                'warnings' => ['No saved budget is available yet.'],
                'funding_decision' => null,
                'use_of_funds' => [],
                'cash_story' => [],
                'annual_totals' => [],
                'monthly_by_year' => [],
                'scenarios' => [],
                'fixed_costs' => [],
                'assumptions' => [],
                'explanations' => [],
                'summary' => [],
            ];
        }

        $computed = (array) ($budget->computed ?? []);
        $fundingDecision = $this->readiness->evaluate($budget);
        $monthlyByYear = collect((array) data_get($computed, 'monthly_detail', []))
            ->groupBy('year')
            ->map(fn (Collection $rows, int|string $year): array => [
                'year' => (int) $year,
                'rows' => $rows->values()->all(),
            ])
            ->values()
            ->all();
        $scenarios = collect((array) data_get($computed, 'scenarios', []))
            ->map(fn (array $scenario): array => $this->scenarioPayload($scenario))
            ->values()
            ->all();

        return [
            'available' => true,
            'profile_name' => $profile->name,
            'plan_title' => $plan->title,
            'status' => $budget->status,
            'forecast_years' => $budget->forecast_years ?? data_get($computed, 'forecast_years', 3),
            'generated_at' => now()->toIso8601String(),
            'gst_exclusive' => (bool) data_get($computed, 'assumptions.gst_exclusive', true),
            'forecast_start_month' => data_get($computed, 'assumptions.forecast_start_month'),
            'tax_configured' => (bool) data_get($computed, 'assumptions.company_tax_configured', false),
            'warnings' => (array) ($fundingDecision['warnings'] ?? []),
            'summary' => [
                'break_even_month' => data_get($computed, 'break_even_month'),
                'break_even_year' => data_get($computed, 'break_even_year'),
                'first_profitable_year' => data_get($computed, 'first_profitable_year'),
                'cash_flow_positive_year' => data_get($computed, 'cash_flow_positive_year'),
                'runway_months' => data_get($computed, 'runway_months'),
                'runway_open_ended' => data_get($computed, 'runway_open_ended', false),
                'available_after_launch' => data_get($computed, 'available_after_launch', 0),
                'opening_cash_balance' => data_get($computed, 'opening_cash_balance', 0),
            ],
            'funding_decision' => $fundingDecision,
            'use_of_funds' => $this->useOfFunds($budget, $fundingDecision),
            'cash_story' => $this->cashStory($computed, $fundingDecision, $scenarios),
            'assumptions' => $this->assumptions((array) data_get($computed, 'assumptions', [])),
            'explanations' => data_get($computed, 'explanations', []),
            'year_two_revenue_bridge' => (array) data_get($computed, 'year_two_revenue_bridge', []),
            'annual_totals' => array_values((array) data_get($computed, 'annual_totals', [])),
            'monthly_by_year' => $monthlyByYear,
            'scenarios' => $scenarios,
            'fixed_costs' => $this->fixedCosts($budget),
            'fixed_cost_reconciliation' => $this->fixedCostReconciliation($budget, $computed),
            'future_costs' => $this->futureCosts($budget),
            'active_flags' => collect((array) ($budget->flags ?? []))
                ->filter(fn (array $flag): bool => empty($flag['acknowledged_at']))
                ->values()
                ->all(),
        ];
    }

    public function html(EntrepreneurProfile $profile, BusinessPlan $plan): string
    {
        $payload = $this->payload($profile, $plan);
        $decision = (array) ($payload['funding_decision'] ?? []);
        $issueReadiness = $this->issueReadiness->evaluate($plan);
        $draftNotice = $this->draftNoticeHtml($issueReadiness);
        $annualRows = collect((array) $payload['annual_totals'])
            ->map(fn (array $row): string => $this->annualRowHtml($row))
            ->implode('');
        $financialBridgeRows = collect((array) $payload['annual_totals'])
            ->map(fn (array $row): string => $this->financialBridgeRowHtml($row))
            ->implode('');
        $summary = (array) ($payload['summary'] ?? []);
        $assumptions = collect((array) ($payload['assumptions'] ?? []))
            ->map(fn (array $row): string => sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->escape($row['label'] ?? ''),
                $this->escape($row['value'] ?? ''),
                $this->escape($row['basis'] ?? ''),
                $this->escape($row['review_note'] ?? ''),
            ))
            ->implode('');
        $monthlyPages = collect((array) ($payload['monthly_by_year'] ?? []))
            ->map(fn (array $year, int $index): string => $this->monthlyYearHtml($year, $index === 0))
            ->implode('');
        $useOfFundsRows = collect((array) ($payload['use_of_funds'] ?? []))
            ->map(fn (array $row): string => sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->escape($row['label'] ?? ''),
                $this->escape($row['display_amount'] ?? $this->money($row['amount'] ?? 0)),
                $this->escape($row['note'] ?? ''),
            ))
            ->implode('');
        $fixedCostRows = collect((array) ($payload['fixed_costs'] ?? []))
            ->map(fn (array $row): string => sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->escape($row['label'] ?? ''),
                $this->money($row['entered_amount'] ?? 0),
                $this->escape($row['cadence_label'] ?? ''),
                $this->money($row['monthly_amount'] ?? 0),
                $this->escape($row['start_month_label'] ?? ''),
                $this->escape($row['review_note'] ?? ''),
            ))
            ->implode('');
        $futureCostRows = collect((array) ($payload['future_costs'] ?? []))
            ->map(fn (array $row): string => sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->escape($row['label'] ?? ''),
                $this->money($row['amount'] ?? 0),
                $this->escape($row['timing'] ?? ''),
                $this->escape($row['classification'] ?? ''),
                $this->escape($this->formatLabel((string) ($row['confidence'] ?? 'estimate'))),
            ))
            ->implode('');
        $scenarioRows = collect((array) ($payload['scenarios'] ?? []))
            ->map(fn (array $scenario): string => sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->escape($scenario['name'] ?? 'Scenario'),
                $this->escape($scenario['sensitivity_label'] ?? $this->formatLabel((string) ($scenario['type'] ?? 'base'))),
                $this->escape($this->yearValue(data_get($scenario, 'summary.break_even_year'))),
                $this->escape($this->yearValue(data_get($scenario, 'summary.cash_flow_positive_year'))),
                $this->escape($this->monthValue($scenario['lowest_cash_month'] ?? null)),
                is_numeric($scenario['lowest_cash'] ?? null) ? $this->money($scenario['lowest_cash']) : 'Not calculated',
                $this->escape($scenario['implication'] ?? ''),
            ))
            ->implode('');
        $cashChart = $this->cashChartHtml((array) ($payload['monthly_by_year'] ?? []), $summary);
        $cashStory = collect((array) ($payload['cash_story'] ?? []))
            ->map(fn (string $line): string => '<p>'.$this->escape($line).'</p>')
            ->implode('');
        $revenueBridgeSection = $this->yearTwoRevenueBridgeHtml((array) ($payload['year_two_revenue_bridge'] ?? []));
        $fixedCostReconciliation = (array) ($payload['fixed_cost_reconciliation'] ?? []);
        $fixedCostReconciliationNote = $this->fixedCostReconciliationHtml($fixedCostReconciliation);
        $generatedAt = now()->format('M j, Y g:i A');
        $decisionView = sprintf(
            <<<'HTML'
<article class="report-section decision-view">
<div class="decision-kicker">Funding position</div>
<h2>%s</h2>
<p class="decision-headline">%s</p>
<div class="summary funding-summary">%s%s%s%s</div>
</article>
HTML,
            'Funding position',
            $this->escape($this->fundingPositionHeadline($decision)),
            $this->metricHtml('Lowest cash point', $this->moneyWithMonth($decision['lowest_cash'] ?? null, $decision['lowest_cash_month'] ?? null)),
            $this->metricHtml('Required additional funding', $this->money($decision['required_additional_funding'] ?? 0)),
            $this->metricHtml('Funding available', $this->money($decision['available_funding'] ?? 0)),
            $this->metricHtml('Runway', $this->runwayText($summary)),
        );
        $cashStorySection = sprintf(
            <<<'HTML'
<article class="report-section cash-story">
<h2>Cash and downside checks</h2>
<div class="story">%s</div>
%s
</article>
HTML,
            $cashStory === '' ? '<p>No monthly cash forecast is available yet.</p>' : $cashStory,
            $cashChart,
        );
        $useOfFundsSection = sprintf(
            <<<'HTML'
<article class="report-section funding-build-up page">
<h2>Funding build-up</h2>
<p class="section-intro">The funding position is built from planned one-off costs, an operating-cover buffer, contingency, and cash already available in the forecast.</p>
<table class="decision-table">
<thead><tr><th>Item</th><th>Amount</th><th>Why it matters</th></tr></thead>
<tbody>%s</tbody>
</table>
</article>
HTML,
            $useOfFundsRows === '' ? '<tr><td colspan="3">No funding inputs saved.</td></tr>' : $useOfFundsRows,
        );
        $fixedCostsSection = sprintf(
            '<article class="report-section"><h2>Monthly fixed-cost trace</h2><p class="section-intro">Each row shows the entered amount, billing cadence, and monthly equivalent used by the model. The converted rows total %s per month; the model base is %s per month.</p>%s<table class="decision-table"><thead><tr><th>Cost item</th><th>Entered amount</th><th>Cadence</th><th>Monthly equivalent</th><th>Starts</th><th>Review note</th></tr></thead><tbody>%s</tbody></table></article>',
            $this->money($fixedCostReconciliation['listed_total'] ?? 0),
            $this->money($fixedCostReconciliation['model_base'] ?? ($decision['monthly_fixed_costs'] ?? 0)),
            $fixedCostReconciliationNote,
            $fixedCostRows === '' ? '<tr><td colspan="6">No monthly fixed costs saved.</td></tr>' : $fixedCostRows,
        );
        $futureCostsSection = sprintf(
            '<article class="report-section"><h2>Later-year cost trace</h2><p class="section-intro">Operating costs affect profit when incurred. Capital assets are paid in cash at purchase and depreciated over their stated useful life.</p><table class="decision-table"><thead><tr><th>Cost item</th><th>Amount</th><th>Timing</th><th>Treatment</th><th>Confidence</th></tr></thead><tbody>%s</tbody></table></article>',
            $futureCostRows === '' ? '<tr><td colspan="5">No later-year costs saved.</td></tr>' : $futureCostRows,
        );
        $annualForecast = sprintf(
            <<<'HTML'
<article class="report-section annual-forecast page">
<h2>Annual forecast</h2>
<table>
<thead><tr><th>Year</th><th>Revenue</th><th>Gross profit</th><th>GP %%</th><th>Fixed costs</th><th>Net profit before tax</th><th>NPBT %%</th><th>Tax</th><th>Net profit after tax</th><th>Ending cash</th></tr></thead>
<tbody>%s</tbody>
</table>
<p class="note">Break-even means the first year where net profit before tax is zero or positive. Cash-flow-positive means cumulative cash becomes zero or positive after startup losses and funding movements.</p>
</article>
HTML,
            $annualRows === '' ? '<tr><td colspan="10">No annual forecast saved.</td></tr>' : $annualRows,
        );
        $openingCash = (float) data_get($payload, 'summary.opening_cash_balance', 0);
        $financialBridgeSection = sprintf(
            <<<'HTML'
<article class="report-section financial-bridge">
<h2>Profit and cash reconciliation</h2>
<p class="section-intro">Opening cash is carried separately from Month 1 cash movement. Amounts are NZD and GST exclusive; GST timing is not a substitute for a separate GST provision schedule.</p>
<p class="note">Opening cash balance: %s</p>
<table class="decision-table">
<thead><tr><th>Year</th><th>NPBT</th><th>Loss used</th><th>Tax</th><th>Depreciation</th><th>Capex</th><th>Net cash flow</th><th>Closing cash</th></tr></thead>
<tbody>%s</tbody>
</table>
</article>
HTML,
            $this->money($openingCash),
            $financialBridgeRows === '' ? '<tr><td colspan="8">No annual forecast saved.</td></tr>' : $financialBridgeRows,
        );
        $assumptionsSection = sprintf(
            '<article class="report-section assumption-quality page"><h2>Assumption quality</h2><table class="decision-table"><thead><tr><th>Assumption</th><th>Value</th><th>Basis</th><th>Review note</th></tr></thead><tbody>%s</tbody></table></article>',
            $assumptions === '' ? '<tr><td colspan="4">No assumptions saved.</td></tr>' : $assumptions,
        );
        $scenariosSection = sprintf(
            '<article class="report-section scenario-comparison"><h2>Scenario comparison</h2><table class="decision-table"><thead><tr><th>Scenario</th><th>Test</th><th>Break-even</th><th>Cash positive</th><th>Lowest cash</th><th>Cash value</th><th>Implication</th></tr></thead><tbody>%s</tbody></table></article>',
            $scenarioRows === '' ? '<tr><td colspan="7">No scenarios saved.</td></tr>' : $scenarioRows,
        );

        $template = $this->templates->budgetPack();
        $businessName = $this->identity->businessName($profile, $plan);
        $title = 'Budget Pack'.($businessName === null ? '' : ' - '.$businessName);

        return $this->layout->document(
            title: $title,
            templateKey: $template?->getKey() ?? EntrepreneurDocumentTemplate::BUDGET_PACK,
            documentTag: 'Budget pack',
            eyebrow: 'Lender-readiness financial forecast',
            heading: $title,
            subheading: 'Founder - '.$profile->name,
            meta: [
                'Prepared' => $generatedAt,
                'Version' => (bool) ($issueReadiness['external_issue_ready'] ?? false) ? 'External issue ready' : 'Internal draft',
                'Currency' => 'NZD',
                'GST basis' => 'GST exclusive',
                'Forecast starts' => (string) ($payload['forecast_start_month'] ?: 'Not set'),
            ],
            contentHtml: $draftNotice.$decisionView.$cashStorySection.$revenueBridgeSection.$useOfFundsSection.$fixedCostsSection.$futureCostsSection.$scenariosSection.$assumptionsSection.$annualForecast.$financialBridgeSection.$monthlyPages,
            footer: $this->footerText('Generated '.$generatedAt.' using Future Shift Advisory budget pack', $issueReadiness),
            snapshotTitle: 'Document details',
            template: $template,
            extraCss: $this->budgetPackCss(),
        );
    }

    public function fallbackPdf(EntrepreneurProfile $profile, BusinessPlan $plan): string
    {
        $payload = $this->payload($profile, $plan);
        $summary = (array) ($payload['summary'] ?? []);
        $decision = (array) ($payload['funding_decision'] ?? []);
        $issueReadiness = $this->issueReadiness->evaluate($plan);
        $businessName = $this->identity->businessName($profile, $plan);
        $title = 'Budget Pack'.($businessName === null ? '' : ' - '.$businessName);
        $blocks = [
            [
                'type' => 'cover',
                'document_tag' => 'Budget pack',
                'title' => $title,
                'subtitle' => 'Founder - '.$profile->name.((bool) ($issueReadiness['external_issue_ready'] ?? false) ? '' : ' | INTERNAL DRAFT - NOT FOR EXTERNAL ISSUE'),
            ],
            ['type' => 'page_break'],
            ['type' => 'section', 'text' => 'Funding position'],
            ['type' => 'paragraph', 'text' => $this->fundingPositionHeadline($decision)],
            [
                'type' => 'table',
                'headers' => ['Metric', 'Value'],
                'rows' => [
                    ['Lowest cash point', $this->moneyWithMonth($decision['lowest_cash'] ?? null, $decision['lowest_cash_month'] ?? null)],
                    ['Required additional funding', $this->money($decision['required_additional_funding'] ?? 0)],
                    ['Funding available', $this->money($decision['available_funding'] ?? 0)],
                    ['Runway', $this->runwayText($summary)],
                ],
                'widths' => [1.2, 1],
            ],
        ];

        $cashStory = array_values(array_map('strval', (array) ($payload['cash_story'] ?? [])));
        if ($cashStory !== []) {
            $blocks[] = ['type' => 'section', 'text' => 'Cash and downside checks'];
            foreach ($cashStory as $line) {
                $blocks[] = ['type' => 'paragraph', 'text' => $line];
            }
        }

        $cashTrendChart = $this->fallbackCashTrendChart((array) ($payload['monthly_by_year'] ?? []));
        if ($cashTrendChart !== null) {
            $blocks[] = $cashTrendChart;
        }

        $bridgeBlocks = $this->yearTwoRevenueBridgeBlocks((array) ($payload['year_two_revenue_bridge'] ?? []));
        foreach ($bridgeBlocks as $block) {
            $blocks[] = $block;
        }

        $useOfFunds = collect((array) ($payload['use_of_funds'] ?? []))
            ->map(fn (array $row): array => [
                (string) ($row['label'] ?? ''),
                (string) ($row['display_amount'] ?? $this->money($row['amount'] ?? 0)),
                (string) ($row['note'] ?? ''),
            ])
            ->values()
            ->all();
        $blocks[] = ['type' => 'section', 'text' => 'Funding build-up'];
        $blocks[] = $useOfFunds === []
            ? ['type' => 'paragraph', 'text' => 'No funding inputs have been saved yet.']
            : [
                'type' => 'table',
                'headers' => ['Item', 'Amount', 'Why it matters'],
                'rows' => $useOfFunds,
                'widths' => [1.1, 0.9, 2],
            ];

        $fixedCosts = collect((array) ($payload['fixed_costs'] ?? []))
            ->map(fn (array $row): array => [
                (string) ($row['label'] ?? ''),
                $this->money($row['entered_amount'] ?? 0),
                (string) ($row['cadence_label'] ?? ''),
                $this->money($row['monthly_amount'] ?? 0),
                (string) ($row['start_month_label'] ?? ''),
                (string) ($row['review_note'] ?? ''),
            ])
            ->values()
            ->all();
        $fixedCostReconciliation = (array) ($payload['fixed_cost_reconciliation'] ?? []);
        $blocks[] = ['type' => 'section', 'text' => 'Monthly fixed-cost trace'];
        $blocks[] = [
            'type' => 'paragraph',
            'text' => 'The converted fixed-cost rows total '.$this->money($fixedCostReconciliation['listed_total'] ?? 0).' per month; the model base used for funding calculations is '.$this->money($fixedCostReconciliation['model_base'] ?? 0).' per month.',
        ];
        if (! (bool) ($fixedCostReconciliation['reconciled'] ?? true)) {
            $blocks[] = [
                'type' => 'callout',
                'title' => 'Fixed-cost reconciliation warning',
                'text' => (string) ($fixedCostReconciliation['message'] ?? 'The itemised fixed-cost trace does not reconcile to the model base.'),
            ];
        }
        $blocks[] = $fixedCosts === []
            ? ['type' => 'paragraph', 'text' => 'No monthly fixed costs have been saved yet.']
            : [
                'type' => 'table',
                'headers' => ['Cost item', 'Entered', 'Cadence', 'Monthly equivalent', 'Starts', 'Review note'],
                'rows' => $fixedCosts,
                'widths' => [1.15, 0.7, 0.75, 0.85, 0.55, 1.25],
            ];

        $futureCosts = collect((array) ($payload['future_costs'] ?? []))
            ->map(fn (array $row): array => [
                (string) ($row['label'] ?? ''),
                $this->money($row['amount'] ?? 0),
                (string) ($row['timing'] ?? ''),
                (string) ($row['classification'] ?? ''),
                $this->formatLabel((string) ($row['confidence'] ?? 'estimate')),
            ])
            ->values()
            ->all();
        $blocks[] = ['type' => 'section', 'text' => 'Later-year cost trace'];
        $blocks[] = $futureCosts === []
            ? ['type' => 'paragraph', 'text' => 'No later-year costs have been saved yet.']
            : [
                'type' => 'table',
                'headers' => ['Cost item', 'Amount', 'Timing', 'Treatment', 'Confidence'],
                'rows' => $futureCosts,
                'widths' => [1.25, 0.75, 0.9, 1.15, 0.65],
            ];

        $scenarios = collect((array) ($payload['scenarios'] ?? []))
            ->map(fn (array $scenario): array => [
                (string) ($scenario['name'] ?? 'Scenario'),
                (string) ($scenario['sensitivity_label'] ?? $this->formatLabel((string) ($scenario['type'] ?? 'base'))),
                $this->yearValue(data_get($scenario, 'summary.break_even_year')),
                $this->yearValue(data_get($scenario, 'summary.cash_flow_positive_year')),
                $this->moneyWithMonth($scenario['lowest_cash'] ?? null, $scenario['lowest_cash_month'] ?? null),
                $this->money($scenario['additional_funding_needed'] ?? 0),
            ])
            ->values()
            ->all();
        $blocks[] = ['type' => 'section', 'text' => 'Scenario comparison'];
        $blocks[] = $scenarios === []
            ? ['type' => 'paragraph', 'text' => 'No scenarios have been saved yet.']
            : [
                'type' => 'table',
                'headers' => ['Scenario', 'Test', 'Break-even', 'Cash positive', 'Lowest cash', 'Cash need'],
                'rows' => $scenarios,
                'widths' => [1.25, 1, 0.8, 0.9, 1.1, 0.9],
            ];

        $annual = collect((array) ($payload['annual_totals'] ?? []))
            ->map(fn (array $row): array => [
                (string) ($row['year'] ?? '-'),
                $this->money($row['revenue'] ?? 0),
                $this->money($row['gross_profit'] ?? 0),
                $this->percent($row['gross_profit_percent'] ?? null),
                $this->money($row['fixed_costs'] ?? 0),
                $this->money($row['net_profit_after_tax'] ?? 0),
                $this->money($row['ending_cash'] ?? 0),
            ])
            ->values()
            ->all();

        $blocks[] = ['type' => 'page_break'];
        $annualChart = $this->fallbackAnnualForecastChart((array) ($payload['annual_totals'] ?? []));
        if ($annualChart !== null) {
            $blocks[] = $annualChart;
        }

        $blocks[] = ['type' => 'section', 'text' => 'Annual forecast'];
        $blocks[] = $annual === []
            ? ['type' => 'paragraph', 'text' => 'No annual forecast has been saved yet.']
            : [
                'type' => 'table',
                'headers' => ['Year', 'Revenue', 'Gross profit', 'GP %', 'Fixed costs', 'NPAT', 'Ending cash'],
                'rows' => $annual,
                'widths' => [0.55, 1, 1, 0.65, 1, 1, 1],
            ];

        $financialBridge = collect((array) ($payload['annual_totals'] ?? []))
            ->map(fn (array $row): array => [
                'Year '.((string) ($row['year'] ?? '-')),
                $this->money($row['net_profit_before_tax'] ?? 0),
                $this->money($row['tax_loss_used'] ?? 0),
                $this->money($row['tax'] ?? 0),
                $this->money($row['depreciation'] ?? 0),
                $this->money($row['capital_expenditure'] ?? 0),
                $this->money($row['net_cash_flow'] ?? 0),
                $this->money($row['ending_cash'] ?? 0),
            ])
            ->values()
            ->all();
        $blocks[] = ['type' => 'section', 'text' => 'Profit and cash reconciliation'];
        $blocks[] = ['type' => 'paragraph', 'text' => 'Opening cash balance: '.$this->money(data_get($payload, 'summary.opening_cash_balance', 0)).'. All figures are NZD and GST exclusive.'];
        $blocks[] = $financialBridge === []
            ? ['type' => 'paragraph', 'text' => 'No annual forecast has been saved yet.']
            : [
                'type' => 'table',
                'headers' => ['Year', 'NPBT', 'Loss used', 'Tax', 'Depn', 'Capex', 'Net cash', 'Closing cash'],
                'rows' => $financialBridge,
                'widths' => [0.55, 0.85, 0.8, 0.7, 0.75, 0.75, 0.9, 0.9],
            ];

        $blocks[] = ['type' => 'page_break'];
        $assumptions = collect((array) ($payload['assumptions'] ?? []))
            ->map(fn (array $row): array => [
                (string) ($row['label'] ?? ''),
                (string) ($row['value'] ?? ''),
                (string) ($row['basis'] ?? ''),
                (string) ($row['review_note'] ?? ''),
            ])
            ->filter()
            ->values()
            ->all();
        $blocks[] = ['type' => 'section', 'text' => 'Assumption quality'];
        $blocks[] = $assumptions === []
            ? ['type' => 'paragraph', 'text' => 'No assumptions have been saved yet.']
            : [
                'type' => 'table',
                'headers' => ['Assumption', 'Value', 'Basis', 'Review note'],
                'rows' => $assumptions,
                'widths' => [1.1, 0.65, 1.05, 1.8],
            ];

        foreach ((array) ($payload['monthly_by_year'] ?? []) as $year) {
            $rows = collect((array) ($year['rows'] ?? []))
                ->map(fn (array $row): array => [
                    (string) ($row['month_in_year'] ?? '-'),
                    $this->money($row['revenue'] ?? 0),
                    $this->money($row['cash_collected'] ?? 0),
                    $this->money($row['gross_profit'] ?? 0),
                    $this->money($row['fixed_costs'] ?? 0),
                    $this->money($row['tax'] ?? 0),
                    $this->money($row['net_cash_flow'] ?? 0),
                    $this->money($row['cumulative_cash'] ?? 0),
                ])
                ->values()
                ->all();

            if ($rows !== []) {
                $blocks[] = ['type' => 'page_break'];
                $blocks[] = ['type' => 'section', 'text' => 'Year '.((string) ($year['year'] ?? '-')).' monthly detail'];
                $blocks[] = [
                    'type' => 'table',
                    'headers' => ['Month', 'Revenue', 'Cash collected', 'Gross profit', 'Fixed costs', 'Tax', 'Net cash flow', 'Cash'],
                    'rows' => $rows,
                    'widths' => [0.5, 0.8, 0.85, 0.8, 0.75, 0.6, 0.9, 0.8],
                ];
            }
        }

        return $this->fallbackPdf->renderStructured(
            'Budget Pack',
            $blocks,
            $this->draftFooterNote($issueReadiness),
        );
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    private function scenarioPayload(array $scenario): array
    {
        $monthly = array_values((array) ($scenario['monthly_detail'] ?? []));
        $summary = (array) ($scenario['summary'] ?? []);
        $trough = $this->scenarioCashTrough($monthly);
        $additionalFunding = $this->scenarioAdditionalFundingNeeded($trough['value']);

        return [
            'key' => $scenario['key'] ?? null,
            'name' => $scenario['name'] ?? 'Scenario',
            'type' => $scenario['type'] ?? 'base',
            'sensitivity_label' => $this->sensitivityLabel($scenario),
            'summary' => $summary,
            'annual_totals' => $scenario['annual_totals'] ?? [],
            'lowest_cash_month' => $trough['month'],
            'lowest_cash' => $trough['value'],
            'additional_funding_needed' => $additionalFunding,
            'implication' => $this->scenarioImplication($scenario, $summary, $additionalFunding),
        ];
    }

    /**
     * @return array<int, array{label:string,entered_amount:float,cadence_label:string,monthly_amount:float,start_month_label:string,confidence:string,review_note:string}>
     */
    private function fixedCosts(EntrepreneurBudget $budget): array
    {
        return collect((array) ($budget->monthly_fixed_costs ?? []))
            ->map(function (array $row): array {
                $month = max(1, (int) ($row['month'] ?? 1));
                $label = (string) ($row['label'] ?? 'Unlabelled cost');

                return [
                    'label' => $this->fixedCostDisplayLabel($label),
                    'entered_amount' => round((float) ($row['amount'] ?? 0) * (float) ($row['quantity'] ?? 1), 2),
                    'cadence_label' => $this->cadenceLabel((string) ($row['cadence'] ?? 'monthly'), (bool) ($row['cadence_confirmed'] ?? false)),
                    'monthly_amount' => round($this->monthlyEquivalent($row), 2),
                    'start_month_label' => 'Month '.$month,
                    'confidence' => (string) ($row['confidence'] ?? 'estimate'),
                    'review_note' => $this->fixedCostReviewNote($label, $row),
                ];
            })
            ->sortByDesc('monthly_amount')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $computed
     * @return array{listed_total:float,model_base:float,difference:float,reconciled:bool,message:string}
     */
    private function fixedCostReconciliation(EntrepreneurBudget $budget, array $computed): array
    {
        $listedTotal = collect((array) ($budget->monthly_fixed_costs ?? []))
            ->sum(fn (array $row): float => $this->monthlyEquivalent($row));
        $modelBase = (float) ($computed['monthly_fixed_costs'] ?? data_get($computed, 'base_scenario.summary.year_one_monthly_fixed_costs', 0));
        $difference = round($modelBase - $listedTotal, 2);
        $reconciled = abs($difference) < 1.0;

        return [
            'listed_total' => round($listedTotal, 2),
            'model_base' => round($modelBase, 2),
            'difference' => $difference,
            'reconciled' => $reconciled,
            'message' => $reconciled
                ? 'The converted fixed-cost trace reconciles to the model base.'
                : 'The converted fixed-cost trace differs from the model base by '.$this->money($difference).'. Add the missing rows or correct a cost cadence before external issue.',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function fixedCostReviewNote(string $label, array $row): string
    {
        if ($this->isAmbiguousOwnerCompensation($label)) {
            return 'Clarify whether the saved amount is weekly, monthly, or annual before external issue.';
        }

        $amount = (float) ($row['amount'] ?? 0) * (float) ($row['quantity'] ?? 1);
        if ($amount <= 0) {
            return 'Amount needs confirmation.';
        }

        if (! (bool) ($row['cadence_confirmed'] ?? false)) {
            return 'Confirm the saved billing cadence before external issue.';
        }

        return 'Traces to saved fixed-cost row and converted monthly equivalent.';
    }

    private function fixedCostDisplayLabel(string $label): string
    {
        if ($this->isAmbiguousOwnerCompensation($label)) {
            return 'Owner compensation - current';
        }

        return trim($label) === '' ? 'Unlabelled cost' : trim($label);
    }

    private function isAmbiguousOwnerCompensation(string $label): bool
    {
        $normalised = strtolower($label);

        return preg_match('/owners?\s+compensation|owner\s+draw|founder\s+pay|founder\s+salary/', $normalised) === 1
            && preg_match_all('/\$?\d[\d,]*(?:\.\d+)?\s*(?:wk|week|weekly|pa|p\.a\.|annual|annually|year|yr)?/i', $label) >= 2;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function monthlyEquivalent(array $row): float
    {
        $amount = (float) ($row['amount'] ?? 0) * (float) ($row['quantity'] ?? 1);

        return match ($row['cadence'] ?? 'monthly') {
            'weekly' => $amount * (52 / 12),
            'fortnightly' => $amount * (26 / 12),
            'quarterly' => $amount / 3,
            'annual' => $amount / 12,
            default => $amount,
        };
    }

    private function cadenceLabel(string $cadence, bool $confirmed): string
    {
        $label = match ($cadence) {
            'weekly' => 'Weekly',
            'fortnightly' => 'Fortnightly',
            'quarterly' => 'Quarterly',
            'annual' => 'Annual',
            default => 'Monthly',
        };

        return $confirmed ? $label : $label.' - confirm';
    }

    /**
     * @return array<int, array{label:string,amount:float,timing:string,classification:string,confidence:string}>
     */
    private function futureCosts(EntrepreneurBudget $budget): array
    {
        return collect((array) ($budget->future_costs ?? []))
            ->map(function (array $row): array {
                $year = max(2, (int) ($row['year'] ?? 2));
                $recurring = (bool) ($row['recurring'] ?? false);
                $classification = ($row['classification'] ?? 'operating') === 'capital' ? 'capital' : 'operating';

                return [
                    'label' => (string) ($row['label'] ?? 'Unlabelled later-year cost'),
                    'amount' => round((float) ($row['amount'] ?? 0) * (float) ($row['quantity'] ?? 1), 2),
                    'timing' => $recurring
                        ? 'From Year '.$year.', monthly'
                        : 'Year '.$year.', Month 1 once',
                    'classification' => $classification === 'capital'
                        ? 'Capital asset; depreciated over '.max(1, (int) ($row['useful_life_years'] ?? 3)).' years'
                        : 'Operating cost',
                    'confidence' => (string) ($row['confidence'] ?? 'estimate'),
                ];
            })
            ->sortBy('timing')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $decision
     * @return array<int, array<string, mixed>>
     */
    private function useOfFunds(EntrepreneurBudget $budget, array $decision): array
    {
        $operatingCoverMonths = (int) ($decision['operating_cover_months'] ?? 0);

        return [
            [
                'label' => 'Planned one-off costs',
                'amount' => (float) ($decision['launch_costs'] ?? 0),
                'note' => 'One-off costs entered in the budget, whether for setup, replacement, or growth.',
            ],
            [
                'label' => 'Operating cover',
                'amount' => (float) ($decision['operating_cover_amount'] ?? 0),
                'note' => $operatingCoverMonths.' months of fixed costs, aligned to lender-style runway checks.',
            ],
            [
                'label' => 'Contingency reserve',
                'amount' => (float) ($decision['contingency_amount'] ?? 0),
                'note' => '10% buffer on planned one-off costs and operating cover for timing and pricing variance.',
            ],
            [
                'label' => 'Recommended funding target',
                'amount' => (float) ($decision['recommended_funding_target'] ?? 0),
                'note' => 'Funding target before comparing against cash already available.',
            ],
            [
                'label' => 'Funding and opening cash available',
                'amount' => (float) ($decision['available_funding'] ?? 0),
                'note' => 'Saved funding sources plus opening cash balance in the forecast.',
            ],
            [
                'label' => 'Required additional funding',
                'amount' => (float) ($decision['required_additional_funding'] ?? 0),
                'note' => 'The single funding action figure: the larger of the planned funding-target shortfall and the monthly cash-curve deficit.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $computed
     * @param  array<string, mixed>  $decision
     * @param  array<int, array<string, mixed>>  $scenarios
     * @return array<int, string>
     */
    private function cashStory(array $computed, array $decision, array $scenarios): array
    {
        $story = [];
        $lowestCash = $decision['lowest_cash'] ?? null;
        $lowestMonth = $decision['lowest_cash_month'] ?? null;

        if (is_numeric($lowestCash) && is_numeric($lowestMonth)) {
            if ((float) $lowestCash < 0) {
                $story[] = 'The base forecast reaches its lowest cash point in Month '.((int) $lowestMonth).' at '.$this->money($lowestCash).'. The funding position reflects this cash-curve deficit alongside the planned funding buffer.';
            } else {
                $story[] = 'The base forecast stays above zero, with the lowest cash point in Month '.((int) $lowestMonth).' at '.$this->money($lowestCash).'.';
            }
        }

        $story[] = 'Break-even is '.$this->yearValue(data_get($computed, 'break_even_year')).' and cash-positive timing is '.$this->yearValue(data_get($computed, 'cash_flow_positive_year')).'. These should be read alongside the monthly cash curve rather than the annual profit table alone.';

        $downside = collect($scenarios)
            ->filter(fn (array $scenario): bool => (string) ($scenario['type'] ?? '') === 'sensitivity')
            ->sortByDesc(fn (array $scenario): float => (float) ($scenario['additional_funding_needed'] ?? 0))
            ->first();

        if (is_array($downside)) {
            $story[] = 'The hardest downside test is '.$downside['name'].': '.$this->yearValue(data_get($downside, 'summary.cash_flow_positive_year')).' cash-positive with '.$this->money($downside['additional_funding_needed'] ?? 0).' of extra cash cover indicated.';
        }

        return $story;
    }

    /**
     * @param  array<string, mixed>  $bridge
     */
    private function yearTwoRevenueBridgeHtml(array $bridge): string
    {
        if (! is_numeric($bridge['month_13_revenue'] ?? null)) {
            return '';
        }

        $warning = (bool) ($bridge['material_drop'] ?? false)
            ? '<div class="warning"><strong>Revenue continuity warning</strong><p>Month 13 falls materially below Month 12. This is acceptable only if the Year 1 average is an intentional seasonal or averaged basis and the plan explains it.</p></div>'
            : '';

        return sprintf(
            <<<'HTML'
<article class="report-section revenue-bridge">
<h2>Year 2 revenue bridge</h2>
<p class="section-intro">%s</p>
%s
<table class="decision-table">
<thead><tr><th>Bridge point</th><th>Revenue</th><th>Reader note</th></tr></thead>
<tbody>
<tr><td>Month 12 revenue</td><td>%s</td><td>Year 1 exit run-rate visible in the monthly forecast.</td></tr>
<tr><td>Year 1 average monthly revenue</td><td>%s</td><td>Shown for comparison when an averaged or seasonal basis is selected.</td></tr>
<tr><td>Month 13 revenue</td><td>%s</td><td>Uses %s.</td></tr>
<tr><td>Month 13 change from Month 12</td><td>%s</td><td>%s</td></tr>
</tbody>
</table>
</article>
HTML,
            $this->escape((string) ($bridge['explanation'] ?? 'Month 13 revenue is bridged from the selected Year 2 basis.')),
            $warning,
            $this->money($bridge['month_12_revenue'] ?? 0),
            $this->money($bridge['year_one_average_monthly_revenue'] ?? 0),
            $this->money($bridge['month_13_revenue'] ?? 0),
            $this->escape((string) ($bridge['basis_label'] ?? 'the selected basis')),
            $this->signedMoney($bridge['change_amount'] ?? 0),
            $this->escape(is_numeric($bridge['change_percent'] ?? null) ? number_format((float) $bridge['change_percent'], 1).'% from Month 12.' : 'Change not calculated.'),
        );
    }

    /**
     * @param  array<string, mixed>  $bridge
     * @return array<int, array<string, mixed>>
     */
    private function yearTwoRevenueBridgeBlocks(array $bridge): array
    {
        if (! is_numeric($bridge['month_13_revenue'] ?? null)) {
            return [];
        }

        $blocks = [
            ['type' => 'section', 'text' => 'Year 2 revenue bridge'],
            ['type' => 'paragraph', 'text' => (string) ($bridge['explanation'] ?? 'Month 13 revenue is bridged from the selected Year 2 basis.')],
        ];

        if ((bool) ($bridge['material_drop'] ?? false)) {
            $blocks[] = [
                'type' => 'callout',
                'title' => 'Revenue continuity warning',
                'text' => 'Month 13 falls materially below Month 12. This is acceptable only if the Year 1 average is an intentional seasonal or averaged basis and the plan explains it.',
            ];
        }

        $blocks[] = [
            'type' => 'table',
            'headers' => ['Bridge point', 'Revenue', 'Reader note'],
            'rows' => [
                ['Month 12 revenue', $this->money($bridge['month_12_revenue'] ?? 0), 'Year 1 exit run-rate visible in the monthly forecast.'],
                ['Year 1 average monthly revenue', $this->money($bridge['year_one_average_monthly_revenue'] ?? 0), 'Comparison point for averaged or seasonal basis.'],
                ['Month 13 revenue', $this->money($bridge['month_13_revenue'] ?? 0), 'Uses '.(string) ($bridge['basis_label'] ?? 'the selected basis').'.'],
                ['Month 13 change from Month 12', $this->signedMoney($bridge['change_amount'] ?? 0), is_numeric($bridge['change_percent'] ?? null) ? number_format((float) $bridge['change_percent'], 1).'% from Month 12.' : 'Change not calculated.'],
            ],
            'widths' => [1.25, 0.8, 1.8],
        ];

        return $blocks;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>  $summary
     */
    private function financeSummary(array $payload, array $decision, array $summary): string
    {
        $lowestCash = $decision['lowest_cash'] ?? null;
        $lowestMonth = $decision['lowest_cash_month'] ?? null;
        $requiredAdditionalFunding = (float) ($decision['required_additional_funding'] ?? 0);
        $scenarioCount = (int) ($decision['scenario_count'] ?? count((array) ($payload['scenarios'] ?? [])));
        $sentences = [];

        if (is_numeric($lowestCash) && is_numeric($lowestMonth)) {
            $sentences[] = 'The cash low point is '.$this->money($lowestCash).' in Month '.((int) $lowestMonth).', which is the first lender-read check because annual profit can hide short-term cash pressure.';
        }

        if ($requiredAdditionalFunding > 0) {
            $sentences[] = 'The forecast requires '.$this->money($requiredAdditionalFunding).' of additional funding or equivalent assumption changes before external issue.';
        }

        $sentences[] = $requiredAdditionalFunding === 0.0
            ? 'Current funding inputs cover the planned one-off costs, operating-cover, contingency, and monthly cash curve.'
            : 'The funding build-up and cash curve should be read together so this remains one clear funding decision.';

        $sentences[] = 'Break-even is '.$this->yearValue($summary['break_even_year'] ?? null).', cash-positive timing is '.$this->yearValue($summary['cash_flow_positive_year'] ?? null).', and runway is '.$this->runwayText($summary).'.';

        if ($scenarioCount > 0) {
            $sentences[] = $scenarioCount.' sensitivity scenario'.($scenarioCount === 1 ? '' : 's').' should be read alongside the base case so the funding ask is not based only on the central forecast.';
        }

        return $sentences === []
            ? 'This pack explains the forecast cash position, funding need, and key financial assumptions.'
            : implode(' ', $sentences);
    }

    /**
     * @param  array<string, mixed>  $decision
     */
    private function fundingPositionHeadline(array $decision): string
    {
        $requiredAdditionalFunding = (float) ($decision['required_additional_funding'] ?? 0);

        if ($requiredAdditionalFunding > 0) {
            return 'Required additional funding is '.$this->money($requiredAdditionalFunding).'. This single figure covers whichever is larger: the planned funding-target shortfall or the cash-curve deficit.';
        }

        return 'Current funding and opening cash cover the planned one-off costs, operating cover, contingency, and the monthly cash curve.';
    }

    /**
     * @param  array<int, array<string, mixed>>  $monthlyByYear
     * @return array<string, mixed>|null
     */
    private function fallbackCashTrendChart(array $monthlyByYear): ?array
    {
        $rows = collect($monthlyByYear)
            ->flatMap(fn (array $year): array => (array) ($year['rows'] ?? []))
            ->filter(fn (mixed $row): bool => is_array($row) && is_numeric($row['month'] ?? null))
            ->take(36)
            ->values();

        if ($rows->count() < 2) {
            return null;
        }

        return [
            'type' => 'line_chart',
            'title' => 'Cash and revenue trend',
            'note' => 'Monthly revenue and cumulative cash across the saved forecast.',
            'x_labels' => $rows
                ->map(fn (array $row): string => 'M'.((string) ($row['month'] ?? '')))
                ->all(),
            'series' => [
                [
                    'label' => 'Revenue',
                    'values' => $rows
                        ->map(fn (array $row): float => (float) ($row['revenue'] ?? 0))
                        ->all(),
                    'color' => [184, 134, 11],
                ],
                [
                    'label' => 'Cash',
                    'values' => $rows
                        ->map(fn (array $row): float => (float) ($row['cumulative_cash'] ?? 0))
                        ->all(),
                    'color' => [13, 122, 122],
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $annualTotals
     * @return array<string, mixed>|null
     */
    private function fallbackAnnualForecastChart(array $annualTotals): ?array
    {
        $rows = collect($annualTotals)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->take(5)
            ->values();

        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'type' => 'bar_chart',
            'title' => 'Annual forecast profile',
            'note' => 'Revenue, gross profit, fixed costs, and ending cash by year.',
            'x_labels' => $rows
                ->map(fn (array $row): string => 'Y'.((string) ($row['year'] ?? '')))
                ->all(),
            'series' => [
                [
                    'label' => 'Revenue',
                    'values' => $rows
                        ->map(fn (array $row): float => (float) ($row['revenue'] ?? 0))
                        ->all(),
                    'color' => [13, 122, 122],
                ],
                [
                    'label' => 'GP',
                    'values' => $rows
                        ->map(fn (array $row): float => (float) ($row['gross_profit'] ?? 0))
                        ->all(),
                    'color' => [95, 151, 135],
                ],
                [
                    'label' => 'Costs',
                    'values' => $rows
                        ->map(fn (array $row): float => (float) ($row['fixed_costs'] ?? 0))
                        ->all(),
                    'color' => [28, 47, 74],
                ],
                [
                    'label' => 'Cash',
                    'values' => $rows
                        ->map(fn (array $row): float => (float) ($row['ending_cash'] ?? 0))
                        ->all(),
                    'color' => [184, 134, 11],
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $monthlyByYear
     * @param  array<string, mixed>  $summary
     */
    private function cashChartHtml(array $monthlyByYear, array $summary): string
    {
        $points = collect($monthlyByYear)
            ->flatMap(fn (array $year): array => (array) ($year['rows'] ?? []))
            ->filter(fn (mixed $row): bool => is_array($row) && is_numeric($row['month'] ?? null))
            ->take(60)
            ->values()
            ->all();

        if ($points === []) {
            return '';
        }

        $width = 720;
        $height = 285;
        $top = 22;
        $right = 72;
        $bottom = 42;
        $left = 68;
        $plotWidth = $width - $left - $right;
        $plotHeight = $height - $top - $bottom;
        $cashValues = array_map(fn (array $point): float => (float) ($point['cumulative_cash'] ?? 0), $points);
        $revenueValues = array_map(fn (array $point): float => (float) ($point['revenue'] ?? 0), $points);
        $cashMin = min(array_merge([0.0], $cashValues));
        $cashMax = max(array_merge([0.0], $cashValues));
        $cashRange = $cashMax === $cashMin ? 1.0 : $cashMax - $cashMin;
        $revenueMax = max(array_merge([1.0], $revenueValues));
        $pointCount = count($points);
        $x = fn (int $index): float => $pointCount === 1
            ? $left + ($plotWidth / 2)
            : $left + (($index / max(1, $pointCount - 1)) * $plotWidth);
        $cashY = fn (float $value): float => $top + ((($cashMax - $value) / $cashRange) * $plotHeight);
        $revenueY = fn (float $value): float => $top + ((1 - ($value / $revenueMax)) * $plotHeight);
        $cashPoints = [];
        $revenuePoints = [];

        foreach ($points as $index => $point) {
            $cashPoints[] = $this->svgNumber($x($index)).','.$this->svgNumber($cashY((float) ($point['cumulative_cash'] ?? 0)));
            $revenuePoints[] = $this->svgNumber($x($index)).','.$this->svgNumber($revenueY((float) ($point['revenue'] ?? 0)));
        }
        $troughIndex = 0;
        foreach ($points as $index => $point) {
            if ((float) ($point['cumulative_cash'] ?? 0) < (float) ($points[$troughIndex]['cumulative_cash'] ?? 0)) {
                $troughIndex = $index;
            }
        }
        $troughPoint = $points[$troughIndex];
        $troughX = $x($troughIndex);
        $troughY = $cashY((float) ($troughPoint['cumulative_cash'] ?? 0));
        $troughMonth = (int) ($troughPoint['month'] ?? ($troughIndex + 1));
        $troughHtml = sprintf(
            '<g><circle cx="%s" cy="%s" r="5" fill="#b42318" stroke="#ffffff" stroke-width="2"/><text x="%s" y="%s" text-anchor="middle" fill="#b42318" font-size="11" font-weight="700">Lowest cash M%s</text></g>',
            $this->svgNumber($troughX),
            $this->svgNumber($troughY),
            $this->svgNumber($troughX),
            $this->svgNumber(max($top + 13, $troughY - 10)),
            $troughMonth,
        );

        $cashTicks = collect($this->valueTicks($cashMin, $cashMax))
            ->map(fn (float $value): string => sprintf(
                '<g><line x1="%s" x2="%s" y1="%s" y2="%s" stroke="#17211b" stroke-opacity="0.08"/><text x="%s" y="%s" text-anchor="end" fill="#667085" font-size="11">%s</text></g>',
                $left,
                $left + $plotWidth,
                $this->svgNumber($cashY($value)),
                $this->svgNumber($cashY($value)),
                $left - 10,
                $this->svgNumber($cashY($value) + 4),
                $this->escape($this->moneyShort($value)),
            ))
            ->implode('');
        $revenueTicks = collect($this->valueTicks(0.0, $revenueMax))
            ->map(fn (float $value): string => sprintf(
                '<text x="%s" y="%s" fill="#667085" font-size="11">%s</text>',
                $left + $plotWidth + 10,
                $this->svgNumber($revenueY($value) + 4),
                $this->escape($this->moneyShort($value)),
            ))
            ->implode('');
        $xTicks = collect($this->tickIndexes($pointCount))
            ->map(function (int $index) use ($points, $x, $top, $plotHeight, $height): string {
                $month = (int) ($points[$index]['month'] ?? ($index + 1));

                return sprintf(
                    '<g><line x1="%s" x2="%s" y1="%s" y2="%s" stroke="#17211b" stroke-opacity="0.06"/><text x="%s" y="%s" text-anchor="middle" fill="#667085" font-size="11">M%s</text></g>',
                    $this->svgNumber($x($index)),
                    $this->svgNumber($x($index)),
                    $top,
                    $top + $plotHeight,
                    $this->svgNumber($x($index)),
                    $height - 14,
                    $month,
                );
            })
            ->implode('');
        $markers = $this->chartMarkers($points, $summary);
        $markerHtml = collect($markers)
            ->map(fn (array $marker, int $index): string => sprintf(
                '<g><line x1="%s" x2="%s" y1="%s" y2="%s" stroke="#17211b" stroke-opacity="0.36" stroke-dasharray="3 5"/><text x="%s" y="%s" text-anchor="middle" fill="#17211b" font-size="11" font-weight="700">%s</text></g>',
                $this->svgNumber($x((int) $marker['index'])),
                $this->svgNumber($x((int) $marker['index'])),
                $top,
                $top + $plotHeight,
                $this->svgNumber($x((int) $marker['index'])),
                $top + 13 + ($index * 15),
                $this->escape($marker['label']),
            ))
            ->implode('');

        return sprintf(
            <<<'HTML'
<div class="chart">
<div class="chart-header">
<div><p class="chart-title">Budget cash curve</p><p class="chart-note">Cumulative cash and revenue use separate scales so funding does not flatten the sales curve.</p></div>
<div class="chart-legend"><span><i class="legend-dot legend-dot-cash"></i>Cash</span><span><i class="legend-dot legend-dot-revenue"></i>Revenue</span></div>
</div>
<svg role="img" aria-label="Budget cash curve" viewBox="0 0 %s %s">
<line x1="%s" x2="%s" y1="%s" y2="%s" stroke="#17211b" stroke-opacity="0.28" stroke-dasharray="4 4"/>
%s%s%s
<polyline points="%s" fill="none" stroke="var(--chart-1)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
<polyline points="%s" fill="none" stroke="var(--chart-4)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
%s%s
<text x="%s" y="%s" fill="#667085" font-size="11">Cash axis</text>
<text x="%s" y="%s" text-anchor="end" fill="#667085" font-size="11">Revenue axis</text>
</svg>
</div>
HTML,
            $width,
            $height,
            $left,
            $left + $plotWidth,
            $this->svgNumber($cashY(0.0)),
            $this->svgNumber($cashY(0.0)),
            $cashTicks,
            $revenueTicks,
            $xTicks,
            implode(' ', $cashPoints),
            implode(' ', $revenuePoints),
            $troughHtml,
            $markerHtml,
            $left,
            $height - 1,
            $left + $plotWidth,
            $height - 1,
        );
    }

    /**
     * @param  array<string, mixed>  $assumptions
     * @return array<int, array{label:string,value:string,basis:string,review_note:string,provided:bool}>
     */
    private function assumptions(array $assumptions): array
    {
        $labels = [
            'company_tax_rate_percent' => 'Company tax rate',
            ...(array) ($assumptions['field_labels'] ?? []),
        ];
        $provided = array_values((array) ($assumptions['provided_fields'] ?? []));
        $missing = array_values((array) ($assumptions['missing_fields'] ?? []));

        return collect([
            'revenue_growth_percent',
            'year_two_revenue_basis',
            'forecast_start_month',
            'opening_cash_balance',
            'debtor_days',
            'creditor_days',
            'cost_inflation_percent',
            'target_gross_profit_percent',
            'target_net_profit_before_tax_percent',
            'target_net_profit_after_tax_percent',
            'company_tax_rate_percent',
        ])
            ->map(fn (string $key): array => [
                'label' => (string) ($labels[$key] ?? $this->formatLabel($key)),
                'value' => $key === 'year_two_revenue_basis'
                    ? (($assumptions[$key] ?? 'exit_run_rate') === 'year_one_average'
                        ? 'Year 1 average monthly revenue'
                        : 'Year 1 exit run-rate')
                    : ($key === 'forecast_start_month'
                        ? ((string) ($assumptions[$key] ?? '') ?: 'Not set')
                        : (in_array($key, ['opening_cash_balance'], true)
                            ? $this->money($assumptions[$key] ?? 0)
                            : (in_array($key, ['debtor_days', 'creditor_days'], true)
                                ? ((int) ($assumptions[$key] ?? 0)).' days'
                                : ((float) ($assumptions[$key] ?? 0)).'%'))),
                'basis' => $this->assumptionBasis($key, $assumptions, $provided, $missing),
                'review_note' => $this->assumptionReviewNote($key),
                'provided' => ! in_array($key, $missing, true),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $assumptions
     * @param  array<int, mixed>  $provided
     * @param  array<int, mixed>  $missing
     */
    private function assumptionBasis(string $key, array $assumptions, array $provided, array $missing): string
    {
        if (in_array($key, $missing, true)) {
            return 'Missing input';
        }

        if ($key === 'company_tax_rate_percent') {
            return (bool) ($assumptions['company_tax_configured'] ?? false)
                ? 'Reference data'
                : 'Not configured';
        }

        if ($key === 'year_two_revenue_basis') {
            return 'Founder/advisor selection';
        }

        if (in_array($key, ['forecast_start_month', 'opening_cash_balance', 'debtor_days', 'creditor_days'], true)) {
            return in_array($key, $provided, true) ? 'Founder/advisor input' : 'Missing input';
        }

        if (in_array($key, $provided, true)) {
            return 'Founder/advisor input';
        }

        if ($key === 'cost_inflation_percent' && is_numeric($assumptions[$key] ?? null)) {
            return 'Reference/default input';
        }

        return 'Model default';
    }

    private function assumptionReviewNote(string $key): string
    {
        return match ($key) {
            'revenue_growth_percent' => 'Check against pipeline, pricing tests, signed work, or demand evidence.',
            'year_two_revenue_basis' => 'Use the Year 1 average only for an intentional seasonal or averaged forecast.',
            'forecast_start_month' => 'Use the real first forecast month so monthly cash and written milestones reconcile.',
            'opening_cash_balance' => 'Keep opening cash separate from Month 1 cash movement and funding inflows.',
            'debtor_days' => 'Match deposit and invoice collection timing; zero means cash is collected in the same month.',
            'creditor_days' => 'Match supplier-payment timing; zero means direct costs are paid in the same month.',
            'cost_inflation_percent' => 'Check against current supplier pricing, rent, wages, software, and CPI context.',
            'target_gross_profit_percent' => 'Check price, direct delivery cost, capacity, and product or service mix.',
            'target_net_profit_before_tax_percent' => 'Check whether overheads and owner capacity support the target.',
            'target_net_profit_after_tax_percent' => 'Check tax treatment and whether retained profit is realistic.',
            'company_tax_rate_percent' => 'Confirm reference data before relying on after-tax profit.',
            default => 'Check the source before relying on the forecast.',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function annualRowHtml(array $row): string
    {
        return sprintf(
            '<tr><td>Year %s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            $this->escape($row['year'] ?? ''),
            $this->money($row['revenue'] ?? 0),
            $this->money($row['gross_profit'] ?? 0),
            $this->percent($row['gross_profit_percent'] ?? null),
            $this->money($row['fixed_costs'] ?? 0),
            $this->money($row['net_profit_before_tax'] ?? 0),
            $this->percent($row['net_profit_before_tax_percent'] ?? null),
            $this->money($row['tax'] ?? 0),
            $this->money($row['net_profit_after_tax'] ?? 0),
            $this->money($row['ending_cash'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function financialBridgeRowHtml(array $row): string
    {
        return sprintf(
            '<tr><td>Year %s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            $this->escape($row['year'] ?? ''),
            $this->money($row['net_profit_before_tax'] ?? 0),
            $this->money($row['tax_loss_used'] ?? 0),
            $this->money($row['tax'] ?? 0),
            $this->money($row['depreciation'] ?? 0),
            $this->money($row['capital_expenditure'] ?? 0),
            $this->money($row['net_cash_flow'] ?? 0),
            $this->money($row['ending_cash'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>  $year
     */
    private function monthlyYearHtml(array $year, bool $firstMonthlyPage = false): string
    {
        $yearRows = collect((array) ($year['rows'] ?? []));
        $lowestCash = $yearRows
            ->filter(fn (array $row): bool => is_numeric($row['cumulative_cash'] ?? null))
            ->min(fn (array $row): float => (float) $row['cumulative_cash']);
        $rows = $yearRows
            ->map(function (array $row) use ($lowestCash): string {
                $isTrough = $lowestCash !== null
                    && is_numeric($row['cumulative_cash'] ?? null)
                    && (float) $row['cumulative_cash'] === (float) $lowestCash;

                return sprintf(
                    '<tr%s><td>Month %s%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                    $isTrough ? ' class="cash-trough-row"' : '',
                    $this->escape($row['month_in_year'] ?? ''),
                    $isTrough ? ' <span class="cash-trough-label">Lowest cash</span>' : '',
                    $this->money($row['revenue'] ?? 0),
                    $this->money($row['cash_collected'] ?? 0),
                    $this->money($row['variable_costs'] ?? 0),
                    $this->money($row['gross_profit'] ?? 0),
                    $this->money($row['fixed_costs'] ?? 0),
                    $this->money($row['tax'] ?? 0),
                    $this->money($row['net_profit_after_tax'] ?? 0),
                    $this->money($row['net_cash_flow'] ?? 0),
                    $this->money($row['cumulative_cash'] ?? 0),
                );
            })
            ->implode('');
        $title = $firstMonthlyPage
            ? 'Appendix - Year '.((string) ($year['year'] ?? '-')).' monthly detail'
            : 'Year '.((string) ($year['year'] ?? '-')).' monthly detail';
        $intro = $firstMonthlyPage
            ? '<p class="section-intro">Monthly detail supports advisor review and lender queries. The main funding decision should be read from the preceding decision, scenario, and assumption sections.</p>'
            : '';

        return sprintf(
            '<article class="report-section page monthly-appendix"><h2>%s</h2>%s<table><thead><tr><th>Month</th><th>Revenue</th><th>Cash collected</th><th>Variable costs</th><th>Gross profit</th><th>Fixed costs</th><th>Tax</th><th>NPAT</th><th>Cash flow</th><th>Cumulative cash</th></tr></thead><tbody>%s</tbody></table></article>',
            $this->escape($title),
            $intro,
            $rows,
        );
    }

    private function metricHtml(string $label, string $value): string
    {
        return sprintf(
            '<div class="metric"><span>%s</span><strong>%s</strong></div>',
            $this->escape($label),
            $this->escape($value),
        );
    }

    /**
     * @param  array<string, mixed>  $reconciliation
     */
    private function fixedCostReconciliationHtml(array $reconciliation): string
    {
        if ((bool) ($reconciliation['reconciled'] ?? true)) {
            return '<p class="trace-ok">'.$this->escape((string) ($reconciliation['message'] ?? 'The itemised fixed-cost trace reconciles to the model base.')).'</p>';
        }

        return '<div class="warning"><strong>Fixed-cost reconciliation warning</strong><p>'.$this->escape((string) ($reconciliation['message'] ?? 'The itemised fixed-cost trace does not reconcile to the model base.')).'</p></div>';
    }

    /**
     * @param  array<string, mixed>  $issueReadiness
     */
    private function footerText(string $base, array $issueReadiness): string
    {
        $note = $this->draftFooterNote($issueReadiness);

        return $note === '' ? $base : $base.' | '.$note;
    }

    /**
     * @param  array<string, mixed>  $issueReadiness
     */
    private function draftFooterNote(array $issueReadiness): string
    {
        return (bool) ($issueReadiness['external_issue_ready'] ?? false)
            ? ''
            : 'INTERNAL DRAFT - NOT FOR EXTERNAL ISSUE';
    }

    /**
     * @param  array<string, mixed>  $issueReadiness
     */
    private function draftNoticeHtml(array $issueReadiness): string
    {
        if ((bool) ($issueReadiness['external_issue_ready'] ?? false)) {
            return '';
        }

        $reasons = collect((array) ($issueReadiness['reasons'] ?? []))
            ->take(4)
            ->map(fn (string $reason): string => '<li>'.$this->escape($reason).'</li>')
            ->implode('');

        return '<div class="internal-draft-watermark">INTERNAL DRAFT</div><article class="report-section external-issue-warning"><h2>Internal draft - not for external issue</h2><p>Resolve the listed readiness items before sharing this document with a lender, investor, or other external audience.</p>'.($reasons === '' ? '' : '<ul>'.$reasons.'</ul>').'</article>';
    }

    private function budgetPackCss(): string
    {
        return <<<'CSS'
:root { --chart-1: #0d7a7a; --chart-4: #b8860b; }
.report-content { display: block; }
.report-content .report-section { margin: 0 0 18px; }
.report-content .report-section.page { margin-top: 0; }
.report-hero { background: #fff; border: 0; border-left: 0; break-after: page; margin: 68px 0 0; min-height: 430px; padding: 0; }
.report-hero .eyebrow:empty { display: none; }
.report-hero h1 { font-size: 31px; margin: 0 0 12px; }
.report-hero p { color: #39465a; font-size: 16px; }
.report-footer { bottom: -10mm; left: 0; position: fixed; right: 0; }
.finance-summary { background: #f8f5ee; border-left-color: #b8860b; break-after: page; margin-top: 62px; min-height: 365px; padding: 28px 30px; }
.finance-summary h2 { font-size: 25px; margin-bottom: 15px; }
.finance-summary p { color: #34443c; font-size: 13px; line-height: 1.75; margin: 0; max-width: 74ch; }
.finance-summary-copy { max-width: 76ch; }
.decision-view { background: #f8fbfa; border-left-color: #0d7a7a; padding: 18px 20px; }
.decision-kicker { color: #0d7a7a; font-size: 9px; font-weight: 700; letter-spacing: 0; margin-bottom: 6px; text-transform: uppercase; }
.decision-headline { color: #34443c; font-size: 13px; line-height: 1.6; margin: 0 0 14px; max-width: 82ch; }
.summary { display: grid; gap: 12px; grid-template-columns: repeat(2, 1fr); margin: 14px 0 2px; }
.metric { background: #fff; border: 1px solid #cfded8; min-height: 62px; padding: 11px 12px; }
.metric span { color: #667085; display: block; font-size: 9.5px; font-weight: 700; text-transform: uppercase; }
.metric strong { display: block; font-size: 15px; line-height: 1.25; margin-top: 5px; }
.section-intro { color: #667085; font-size: 11px; line-height: 1.55; margin: 0 0 12px; max-width: 86ch; }
.cash-story { padding: 17px 20px 18px; }
.story { margin-bottom: 14px; }
.story p { font-size: 11.5px; line-height: 1.62; margin: 0 0 8px; max-width: 92ch; }
.funding-build-up { break-before: page; }
.decision-table { font-size: 10.5px; line-height: 1.45; margin-top: 12px; }
.decision-table th, .decision-table td { padding: 7px 8px; text-align: left; }
.decision-table th:nth-child(2), .decision-table td:nth-child(2) { text-align: right; white-space: nowrap; }
.scenario-comparison td:last-child { color: #34443c; }
.warning { background: #fff7e6; border: 1px solid #f3d08f; margin: 10px 0; padding: 8px 10px; }
.warning strong { display: block; margin-bottom: 4px; }
.warning p { margin: 0; }
.warning ul { margin: 0; padding-left: 16px; }
.trace-ok { color: #176b4d; font-size: 10.5px; margin: 0 0 10px; }
.chart { border: 1px solid #cfded8; margin: 16px 0 2px; padding: 13px 14px 12px; page-break-inside: avoid; }
.chart-header { align-items: flex-start; display: flex; gap: 16px; justify-content: space-between; margin-bottom: 10px; }
.chart-title { font-size: 12px; font-weight: 700; margin: 0 0 2px; }
.chart-note { color: #667085; font-size: 10.5px; line-height: 1.45; margin: 0; max-width: 60ch; }
.chart-legend { align-items: center; color: #667085; display: flex; font-size: 10px; gap: 12px; white-space: nowrap; }
.chart-legend span { align-items: center; display: inline-flex; gap: 5px; }
.legend-dot { display: inline-block; height: 8px; width: 8px; }
.legend-dot-cash { background: var(--chart-1); }
.legend-dot-revenue { background: var(--chart-4); }
.chart svg { display: block; height: auto; width: 100%; }
.annual-forecast { break-inside: avoid; }
.monthly-appendix table { font-size: 10px; }
.cash-trough-row { background: #fff1f0; }
.cash-trough-label { color: #b42318; font-size: 8px; font-weight: 700; text-transform: uppercase; }
.page { break-before: page; }
.internal-draft-watermark { color: #b42318; font-size: 42px; font-weight: 700; left: 19%; letter-spacing: 0; opacity: 0.11; pointer-events: none; position: fixed; top: 46%; transform: rotate(-28deg); z-index: 0; }
.external-issue-warning { background: #fff1f0; border-left-color: #b42318; margin-bottom: 16px; }
.external-issue-warning h2 { color: #8a1c16; }
.external-issue-warning p { margin: 0 0 8px; }
.external-issue-warning ul { margin: 0; padding-left: 18px; }
CSS;
    }

    /**
     * @return array<int, int>
     */
    private function tickIndexes(int $length): array
    {
        if ($length <= 1) {
            return [0];
        }

        return collect([
            0,
            (int) floor(($length - 1) * 0.25),
            (int) floor(($length - 1) * 0.5),
            (int) floor(($length - 1) * 0.75),
            $length - 1,
        ])
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, float>
     */
    private function valueTicks(float $min, float $max): array
    {
        if ($min === $max) {
            return [$min];
        }

        return collect([$min, $min + (($max - $min) / 2), $max])
            ->map(fn (float $value): float => round($value))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $points
     * @param  array<string, mixed>  $summary
     * @return array<int, array{index:int,label:string}>
     */
    private function chartMarkers(array $points, array $summary): array
    {
        $markers = [];
        $breakEven = $this->markerForMonth($points, $summary['break_even_month'] ?? null, 'Break-even');

        if ($breakEven !== null) {
            $markers[] = $breakEven;
        }

        if ((bool) ($summary['runway_open_ended'] ?? false)) {
            $last = $points[array_key_last($points)] ?? null;
            $month = is_array($last) ? (int) ($last['month'] ?? count($points)) : count($points);
            $markers[] = [
                'index' => max(0, count($points) - 1),
                'label' => 'Runway > M'.$month,
            ];

            return $markers;
        }

        $runway = $this->markerForMonth($points, $summary['runway_months'] ?? null, 'Runway');
        if ($runway !== null) {
            $markers[] = $runway;
        }

        return $markers;
    }

    /**
     * @param  array<int, array<string, mixed>>  $points
     * @return array{index:int,label:string}|null
     */
    private function markerForMonth(array $points, mixed $month, string $label): ?array
    {
        if (! is_numeric($month)) {
            return null;
        }

        $month = (int) $month;
        foreach ($points as $index => $point) {
            if ((int) ($point['month'] ?? 0) >= $month) {
                return [
                    'index' => (int) $index,
                    'label' => $label.' M'.$month,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{month:int|null,value:float|null}
     */
    private function scenarioCashTrough(array $rows): array
    {
        $lowest = null;

        foreach ($rows as $row) {
            if (! is_array($row) || ! is_numeric($row['cumulative_cash'] ?? null)) {
                continue;
            }

            if ($lowest === null || (float) $row['cumulative_cash'] < (float) $lowest['cumulative_cash']) {
                $lowest = $row;
            }
        }

        if (! is_array($lowest)) {
            return ['month' => null, 'value' => null];
        }

        return [
            'month' => is_numeric($lowest['month'] ?? null) ? (int) $lowest['month'] : null,
            'value' => round((float) ($lowest['cumulative_cash'] ?? 0), 2),
        ];
    }

    private function scenarioAdditionalFundingNeeded(mixed $lowestCash): float
    {
        if (! is_numeric($lowestCash)) {
            return 0.0;
        }

        return round(max(0.0, -((float) $lowestCash)), 2);
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    private function sensitivityLabel(array $scenario): string
    {
        $type = (string) ($scenario['type'] ?? 'base');

        if ($type !== 'sensitivity') {
            return $this->formatLabel($type);
        }

        $revenueMultiplier = is_numeric($scenario['revenue_multiplier'] ?? null)
            ? (float) $scenario['revenue_multiplier']
            : 1.0;
        $costMultiplier = is_numeric($scenario['cost_multiplier'] ?? null)
            ? (float) $scenario['cost_multiplier']
            : 1.0;
        $parts = [];

        if ($revenueMultiplier !== 1.0) {
            $parts[] = 'Revenue '.($revenueMultiplier < 1 ? '-' : '+').number_format(abs(1 - $revenueMultiplier) * 100, 0).'%';
        }

        if ($costMultiplier !== 1.0) {
            $parts[] = 'Costs '.($costMultiplier < 1 ? '-' : '+').number_format(abs($costMultiplier - 1) * 100, 0).'%';
        }

        return $parts === [] ? 'Sensitivity' : implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $summary
     */
    private function scenarioImplication(array $scenario, array $summary, float $additionalFunding): string
    {
        if ($additionalFunding > 0 && data_get($summary, 'cash_flow_positive_year') === null) {
            return 'Requires funding cover and revised assumptions before planned launch.';
        }

        if ($additionalFunding > 0) {
            return 'Shows a cash trough that needs funding cover.';
        }

        if (data_get($summary, 'break_even_year') === null) {
            return 'Break-even is not visible; review revenue, margin, and fixed costs.';
        }

        if (data_get($summary, 'cash_flow_positive_year') === null) {
            return 'Cash-positive timing is not visible inside the forecast.';
        }

        return (string) ($scenario['type'] ?? '') === 'sensitivity'
            ? 'Still holds under this downside test.'
            : 'Base case supports the current funding story.';
    }

    private function money(mixed $value): string
    {
        $amount = (float) $value;
        $sign = $amount < 0 ? '-' : '';

        return $sign.'$'.number_format(abs($amount), 0);
    }

    private function signedMoney(mixed $value): string
    {
        $amount = (float) $value;

        if ($amount > 0) {
            return '+'.$this->money($amount);
        }

        return $this->money($amount);
    }

    private function monthValue(mixed $month): string
    {
        return is_numeric($month) ? 'Month '.((int) $month) : 'Not calculated';
    }

    private function moneyWithMonth(mixed $value, mixed $month): string
    {
        if (! is_numeric($value)) {
            return 'Not calculated';
        }

        return $this->money($value).' in '.$this->monthValue($month);
    }

    private function moneyShort(float $value): string
    {
        $sign = $value < 0 ? '-' : '';
        $absolute = abs($value);

        if ($absolute >= 1_000_000) {
            return $sign.'$'.number_format($absolute / 1_000_000, 1).'m';
        }

        if ($absolute >= 1_000) {
            return $sign.'$'.number_format(round($absolute / 1_000), 0).'k';
        }

        return $sign.'$'.number_format(round($absolute), 0);
    }

    private function svgNumber(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private function percent(mixed $value): string
    {
        return $value === null ? '-' : number_format((float) $value, 1).'%';
    }

    private function yearValue(mixed $year): string
    {
        return is_numeric($year) ? 'Year '.((int) $year) : 'Not reached';
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function runwayText(array $summary): string
    {
        $months = $summary['runway_months'] ?? null;

        if (! is_numeric($months)) {
            return 'not calculated';
        }

        return (bool) ($summary['runway_open_ended'] ?? false)
            ? 'more than '.((int) $months).' months'
            : ((int) $months).' months';
    }

    private function formatLabel(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->toString();
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
