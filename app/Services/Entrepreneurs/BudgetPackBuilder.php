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
                'assumptions' => [],
                'explanations' => [],
                'summary' => [],
            ];
        }

        $computed = (array) ($budget->computed ?? []);
        $warnings = $this->warnings($budget, $computed);
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
        $fundingDecision = $this->fundingDecision($budget, $computed, $monthlyByYear, $scenarios, $warnings);

        return [
            'available' => true,
            'profile_name' => $profile->name,
            'plan_title' => $plan->title,
            'status' => $budget->status,
            'forecast_years' => $budget->forecast_years ?? data_get($computed, 'forecast_years', 3),
            'generated_at' => now()->toIso8601String(),
            'gst_exclusive' => (bool) data_get($computed, 'assumptions.gst_exclusive', true),
            'tax_configured' => (bool) data_get($computed, 'assumptions.company_tax_configured', false),
            'warnings' => $warnings,
            'summary' => [
                'break_even_month' => data_get($computed, 'break_even_month'),
                'break_even_year' => data_get($computed, 'break_even_year'),
                'first_profitable_year' => data_get($computed, 'first_profitable_year'),
                'cash_flow_positive_year' => data_get($computed, 'cash_flow_positive_year'),
                'runway_months' => data_get($computed, 'runway_months'),
                'runway_open_ended' => data_get($computed, 'runway_open_ended', false),
                'available_after_launch' => data_get($computed, 'available_after_launch', 0),
            ],
            'funding_decision' => $fundingDecision,
            'use_of_funds' => $this->useOfFunds($budget, $fundingDecision),
            'cash_story' => $this->cashStory($computed, $fundingDecision, $scenarios),
            'assumptions' => $this->assumptions((array) data_get($computed, 'assumptions', [])),
            'explanations' => data_get($computed, 'explanations', []),
            'annual_totals' => array_values((array) data_get($computed, 'annual_totals', [])),
            'monthly_by_year' => $monthlyByYear,
            'scenarios' => $scenarios,
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
        $annualRows = collect((array) $payload['annual_totals'])
            ->map(fn (array $row): string => $this->annualRowHtml($row))
            ->implode('');
        $summary = (array) ($payload['summary'] ?? []);
        $warnings = collect((array) ($payload['warnings'] ?? []))
            ->map(fn (string $warning): string => '<li>'.$this->escape($warning).'</li>')
            ->implode('');
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

        $generatedAt = now()->format('M j, Y g:i A');
        $decisionView = sprintf(
            <<<'HTML'
<article class="report-section decision-view">
<div class="decision-kicker">Funding decision view</div>
<h2>%s</h2>
<p class="decision-headline">%s</p>
<div class="summary">%s%s%s%s</div>
%s
</article>
HTML,
            $this->escape($decision['readiness_label'] ?? 'Funding readiness not calculated'),
            $this->escape($decision['headline'] ?? 'Complete the budget inputs before relying on this pack.'),
            $this->metricHtml('Lowest cash point', $this->moneyWithMonth($decision['lowest_cash'] ?? null, $decision['lowest_cash_month'] ?? null)),
            $this->metricHtml('Additional cash need', $this->money($decision['additional_funding_needed'] ?? 0)),
            $this->metricHtml('Funding gap / surplus', $this->signedMoney($decision['funding_gap_or_surplus'] ?? 0)),
            $this->metricHtml('Runway', $this->runwayText($summary)),
            $warnings === '' ? '' : '<div class="warning"><strong>Review before external issue</strong><ul>'.$warnings.'</ul></div>',
        );
        $cashStorySection = sprintf(
            <<<'HTML'
<article class="report-section cash-story">
<h2>Cash story</h2>
<div class="story">%s</div>
%s
</article>
HTML,
            $cashStory === '' ? '<p>No monthly cash forecast is available yet.</p>' : $cashStory,
            $cashChart,
        );
        $useOfFundsSection = sprintf(
            <<<'HTML'
<article class="report-section">
<h2>Use of funds</h2>
<p class="section-intro">Funding need is calculated from launch costs, an operating-cover buffer, contingency, and the cash already available in the forecast.</p>
<table class="decision-table">
<thead><tr><th>Item</th><th>Amount</th><th>Why it matters</th></tr></thead>
<tbody>%s</tbody>
</table>
</article>
HTML,
            $useOfFundsRows === '' ? '<tr><td colspan="3">No funding inputs saved.</td></tr>' : $useOfFundsRows,
        );
        $annualForecast = sprintf(
            <<<'HTML'
<article class="report-section annual-forecast">
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
        $assumptionsSection = sprintf(
            '<article class="report-section"><h2>Assumption quality</h2><table class="decision-table"><thead><tr><th>Assumption</th><th>Value</th><th>Basis</th><th>Review note</th></tr></thead><tbody>%s</tbody></table></article>',
            $assumptions === '' ? '<tr><td colspan="4">No assumptions saved.</td></tr>' : $assumptions,
        );
        $scenariosSection = sprintf(
            '<article class="report-section scenario-comparison"><h2>Scenario comparison</h2><table class="decision-table"><thead><tr><th>Scenario</th><th>Test</th><th>Break-even</th><th>Cash positive</th><th>Lowest cash</th><th>Cash value</th><th>Implication</th></tr></thead><tbody>%s</tbody></table></article>',
            $scenarioRows === '' ? '<tr><td colspan="7">No scenarios saved.</td></tr>' : $scenarioRows,
        );

        return $this->layout->document(
            title: 'Budget pack - '.$profile->name,
            templateKey: 'entrepreneur-budget-pack',
            documentTag: 'Budget pack',
            eyebrow: 'Entrepreneur business plan',
            heading: 'Budget pack',
            subheading: $profile->name.' - '.$plan->title,
            meta: [
                'Plan' => $plan->title,
                'Forecast' => ($payload['forecast_years'] ?? 3).' years',
                'Status' => $this->formatLabel((string) ($payload['status'] ?? 'not available')),
                'GST basis' => (bool) ($payload['gst_exclusive'] ?? true) ? 'GST exclusive' : 'GST inclusive',
            ],
            contentHtml: $decisionView.$cashStorySection.$useOfFundsSection.$scenariosSection.$assumptionsSection.$annualForecast.$monthlyPages,
            footer: 'Generated '.$generatedAt.' using Future Shift Advisory budget pack',
            snapshotTitle: 'Budget snapshot',
            metaColumns: 4,
            extraCss: $this->budgetPackCss(),
        );
    }

    public function fallbackPdf(EntrepreneurProfile $profile, BusinessPlan $plan): string
    {
        $payload = $this->payload($profile, $plan);
        $summary = (array) ($payload['summary'] ?? []);
        $decision = (array) ($payload['funding_decision'] ?? []);
        $blocks = [
            ['type' => 'meta', 'text' => 'Prepared by Future Shift Advisory'],
            ['type' => 'meta', 'text' => 'Founder: '.$profile->name],
            ['type' => 'meta', 'text' => 'Plan: '.$plan->title],
            ['type' => 'meta', 'text' => 'Status: '.$this->formatLabel((string) ($payload['status'] ?? 'not available'))],
            ['type' => 'spacer'],
            ['type' => 'section', 'text' => 'Funding decision view'],
            ['type' => 'paragraph', 'text' => ($decision['readiness_label'] ?? 'Funding readiness not calculated').'. '.($decision['headline'] ?? 'Complete the budget inputs before relying on this pack.')],
            [
                'type' => 'table',
                'headers' => ['Metric', 'Value'],
                'rows' => [
                    ['Lowest cash point', $this->moneyWithMonth($decision['lowest_cash'] ?? null, $decision['lowest_cash_month'] ?? null)],
                    ['Additional cash need', $this->money($decision['additional_funding_needed'] ?? 0)],
                    ['Funding gap / surplus', $this->signedMoney($decision['funding_gap_or_surplus'] ?? 0)],
                    ['Runway', $this->runwayText($summary)],
                ],
                'widths' => [1.2, 1],
            ],
        ];

        $warnings = (array) ($payload['warnings'] ?? []);
        if ($warnings !== []) {
            $blocks[] = ['type' => 'section', 'text' => 'Review before external issue'];
            $blocks[] = ['type' => 'bullets', 'items' => array_values(array_map('strval', $warnings))];
        }

        $cashStory = array_values(array_map('strval', (array) ($payload['cash_story'] ?? [])));
        if ($cashStory !== []) {
            $blocks[] = ['type' => 'section', 'text' => 'Cash story'];
            foreach ($cashStory as $line) {
                $blocks[] = ['type' => 'paragraph', 'text' => $line];
            }
        }

        $cashTrendChart = $this->fallbackCashTrendChart((array) ($payload['monthly_by_year'] ?? []));
        if ($cashTrendChart !== null) {
            $blocks[] = $cashTrendChart;
        }

        $useOfFunds = collect((array) ($payload['use_of_funds'] ?? []))
            ->map(fn (array $row): array => [
                (string) ($row['label'] ?? ''),
                (string) ($row['display_amount'] ?? $this->money($row['amount'] ?? 0)),
                (string) ($row['note'] ?? ''),
            ])
            ->values()
            ->all();
        $blocks[] = ['type' => 'section', 'text' => 'Use of funds'];
        $blocks[] = $useOfFunds === []
            ? ['type' => 'paragraph', 'text' => 'No funding inputs have been saved yet.']
            : [
                'type' => 'table',
                'headers' => ['Item', 'Amount', 'Why it matters'],
                'rows' => $useOfFunds,
                'widths' => [1.1, 0.9, 2],
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
                    $this->money($row['gross_profit'] ?? 0),
                    $this->money($row['fixed_costs'] ?? 0),
                    $this->money($row['net_cash_flow'] ?? 0),
                    $this->money($row['cumulative_cash'] ?? 0),
                ])
                ->values()
                ->all();

            if ($rows !== []) {
                $blocks[] = ['type' => 'section', 'text' => 'Year '.((string) ($year['year'] ?? '-')).' monthly detail'];
                $blocks[] = [
                    'type' => 'table',
                    'headers' => ['Month', 'Revenue', 'Gross profit', 'Fixed costs', 'Net cash flow', 'Cash'],
                    'rows' => $rows,
                    'widths' => [0.55, 1, 1, 1, 1, 1],
                ];
            }
        }

        return $this->fallbackPdf->renderStructured('Budget Pack - '.($profile->name ?: 'Entrepreneur'), $blocks);
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    private function scenarioPayload(array $scenario): array
    {
        $monthly = array_values((array) ($scenario['monthly_detail'] ?? []));
        $summary = (array) ($scenario['summary'] ?? []);
        $trough = $this->cashTrough($monthly);
        $additionalFunding = $this->additionalFundingNeeded($trough['value']);

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
     * @param  array<string, mixed>  $computed
     * @param  array<int, array<string, mixed>>  $monthlyByYear
     * @param  array<int, array<string, mixed>>  $scenarios
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function fundingDecision(
        EntrepreneurBudget $budget,
        array $computed,
        array $monthlyByYear,
        array $scenarios,
        array $warnings,
    ): array {
        $monthlyRows = collect($monthlyByYear)
            ->flatMap(fn (array $year): array => (array) ($year['rows'] ?? []))
            ->values()
            ->all();
        $trough = $this->cashTrough($monthlyRows);
        $additionalFunding = $this->additionalFundingNeeded($trough['value']);
        $availableFunding = round((float) ($computed['opening_cash_balance'] ?? 0) + (float) ($computed['total_funding'] ?? 0), 2);
        $launchCosts = round((float) ($computed['total_launch_costs'] ?? 0), 2);
        $monthlyFixedCosts = round((float) ($computed['monthly_fixed_costs'] ?? 0), 2);
        $operatingCoverMonths = $this->operatingCoverMonths($budget->expected_runway_months);
        $operatingCover = round($monthlyFixedCosts * $operatingCoverMonths, 2);
        $contingency = round(($launchCosts + $operatingCover) * 0.10, 2);
        $recommendedFundingTarget = round($launchCosts + $operatingCover + $contingency, 2);
        $fundingGapOrSurplus = round($availableFunding - $recommendedFundingTarget, 2);
        $riskReasons = [];

        if ($budget->status !== EntrepreneurBudget::STATUS_COMPLETE) {
            $riskReasons[] = 'Budget inputs are not complete.';
        }

        if ($additionalFunding > 0) {
            $riskReasons[] = 'The monthly cash curve falls below zero.';
        }

        if (data_get($computed, 'break_even_year') === null) {
            $riskReasons[] = 'Break-even is not visible in the forecast horizon.';
        }

        if (data_get($computed, 'cash_flow_positive_year') === null) {
            $riskReasons[] = 'Cumulative cash does not turn positive inside the forecast horizon.';
        }

        if ($warnings !== []) {
            $riskReasons[] = 'Open budget quality warnings need advisor review.';
        }

        if ($budget->status !== EntrepreneurBudget::STATUS_COMPLETE || $additionalFunding > 0 || data_get($computed, 'cash_flow_positive_year') === null) {
            $readinessLabel = 'Not ready for external issue';
            $readinessTone = 'high';
        } elseif ($warnings !== [] || data_get($computed, 'break_even_year') === null) {
            $readinessLabel = 'Needs advisor review';
            $readinessTone = 'medium';
        } else {
            $readinessLabel = 'Ready for advisor review';
            $readinessTone = 'good';
        }

        return [
            'readiness_label' => $readinessLabel,
            'readiness_tone' => $readinessTone,
            'headline' => $this->decisionHeadline($readinessLabel, $additionalFunding, $fundingGapOrSurplus),
            'lowest_cash_month' => $trough['month'],
            'lowest_cash' => $trough['value'],
            'additional_funding_needed' => $additionalFunding,
            'available_funding' => $availableFunding,
            'launch_costs' => $launchCosts,
            'monthly_fixed_costs' => $monthlyFixedCosts,
            'operating_cover_months' => $operatingCoverMonths,
            'operating_cover_amount' => $operatingCover,
            'contingency_amount' => $contingency,
            'recommended_funding_target' => $recommendedFundingTarget,
            'funding_gap_or_surplus' => $fundingGapOrSurplus,
            'risk_reasons' => $riskReasons,
            'scenario_count' => count($scenarios),
        ];
    }

    /**
     * @param  array<string, mixed>  $decision
     * @return array<int, array<string, mixed>>
     */
    private function useOfFunds(EntrepreneurBudget $budget, array $decision): array
    {
        $operatingCoverMonths = (int) ($decision['operating_cover_months'] ?? $this->operatingCoverMonths($budget->expected_runway_months));
        $gapOrSurplus = (float) ($decision['funding_gap_or_surplus'] ?? 0);

        return [
            [
                'label' => 'Launch costs',
                'amount' => (float) ($decision['launch_costs'] ?? 0),
                'note' => 'One-off setup costs entered in the budget.',
            ],
            [
                'label' => 'Operating cover',
                'amount' => (float) ($decision['operating_cover_amount'] ?? 0),
                'note' => $operatingCoverMonths.' months of fixed costs, aligned to lender-style runway checks.',
            ],
            [
                'label' => 'Contingency reserve',
                'amount' => (float) ($decision['contingency_amount'] ?? 0),
                'note' => '10% buffer on launch costs and operating cover for timing, pricing, and setup variance.',
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
                'label' => 'Funding gap / surplus',
                'amount' => $gapOrSurplus,
                'display_amount' => $this->signedMoney($gapOrSurplus),
                'note' => $gapOrSurplus >= 0
                    ? 'The recommended target is covered by current funding inputs.'
                    : 'Additional funding should be addressed before lender or investor issue.',
            ],
            [
                'label' => 'Minimum cash-curve cover',
                'amount' => (float) ($decision['additional_funding_needed'] ?? 0),
                'note' => 'Lowest extra cash required to stop the monthly forecast falling below zero.',
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
                $story[] = 'The base forecast reaches its lowest cash point in Month '.((int) $lowestMonth).' at '.$this->money($lowestCash).', so the plan needs at least '.$this->money($decision['additional_funding_needed'] ?? 0).' of cash cover before external issue.';
            } else {
                $story[] = 'The base forecast stays above zero, with the lowest cash point in Month '.((int) $lowestMonth).' at '.$this->money($lowestCash).'.';
            }
        }

        $story[] = 'Break-even is '.$this->yearValue(data_get($computed, 'break_even_year')).' and cash-positive timing is '.$this->yearValue(data_get($computed, 'cash_flow_positive_year')).'. These should be read alongside the monthly cash curve rather than the annual profit table alone.';

        $gapOrSurplus = (float) ($decision['funding_gap_or_surplus'] ?? 0);
        $story[] = $gapOrSurplus >= 0
            ? 'The recommended funding target is covered by current funding and opening cash, leaving a modelled surplus of '.$this->money($gapOrSurplus).'.'
            : 'The recommended funding target is short by '.$this->money(abs($gapOrSurplus)).' after launch costs, operating cover, and contingency are included.';

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
        $height = 260;
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
<div class="chart-legend">Cash -- teal&nbsp;&nbsp; Revenue -- gold</div>
</div>
<svg role="img" aria-label="Budget cash curve" viewBox="0 0 %s %s">
<line x1="%s" x2="%s" y1="%s" y2="%s" stroke="#17211b" stroke-opacity="0.28" stroke-dasharray="4 4"/>
%s%s%s
<polyline points="%s" fill="none" stroke="var(--chart-1)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
<polyline points="%s" fill="none" stroke="var(--chart-4)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
%s
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
            'cost_inflation_percent',
            'target_gross_profit_percent',
            'target_net_profit_before_tax_percent',
            'target_net_profit_after_tax_percent',
            'company_tax_rate_percent',
        ])
            ->map(fn (string $key): array => [
                'label' => (string) ($labels[$key] ?? $this->formatLabel($key)),
                'value' => ((float) ($assumptions[$key] ?? 0)).'%',
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
            'cost_inflation_percent' => 'Check against current supplier pricing, rent, wages, software, and CPI context.',
            'target_gross_profit_percent' => 'Check price, direct delivery cost, capacity, and product or service mix.',
            'target_net_profit_before_tax_percent' => 'Check whether overheads and owner capacity support the target.',
            'target_net_profit_after_tax_percent' => 'Check tax treatment and whether retained profit is realistic.',
            'company_tax_rate_percent' => 'Confirm reference data before relying on after-tax profit.',
            default => 'Check the source before external issue.',
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
     * @param  array<string, mixed>  $year
     */
    private function monthlyYearHtml(array $year, bool $firstMonthlyPage = false): string
    {
        $rows = collect((array) ($year['rows'] ?? []))
            ->map(fn (array $row): string => sprintf(
                '<tr><td>Month %s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->escape($row['month_in_year'] ?? ''),
                $this->money($row['revenue'] ?? 0),
                $this->money($row['variable_costs'] ?? 0),
                $this->money($row['gross_profit'] ?? 0),
                $this->money($row['fixed_costs'] ?? 0),
                $this->money($row['net_profit_after_tax'] ?? 0),
                $this->money($row['net_cash_flow'] ?? 0),
                $this->money($row['cumulative_cash'] ?? 0),
            ))
            ->implode('');
        $title = $firstMonthlyPage
            ? 'Appendix - Year '.((string) ($year['year'] ?? '-')).' monthly detail'
            : 'Year '.((string) ($year['year'] ?? '-')).' monthly detail';
        $intro = $firstMonthlyPage
            ? '<p class="section-intro">Monthly detail supports advisor review and lender queries. The main funding decision should be read from the preceding decision, scenario, and assumption sections.</p>'
            : '';

        return sprintf(
            '<article class="report-section page monthly-appendix"><h2>%s</h2>%s<table><thead><tr><th>Month</th><th>Revenue</th><th>Variable costs</th><th>Gross profit</th><th>Fixed costs</th><th>NPAT</th><th>Cash flow</th><th>Cumulative cash</th></tr></thead><tbody>%s</tbody></table></article>',
            $this->escape($title),
            $intro,
            $rows,
        );
    }

    /**
     * @param  array<string, mixed>  $computed
     * @return array<int, string>
     */
    private function warnings(EntrepreneurBudget $budget, array $computed): array
    {
        $warnings = collect((array) ($budget->flags ?? []))
            ->filter(fn (array $flag): bool => empty($flag['acknowledged_at']))
            ->map(fn (array $flag): string => (string) ($flag['title'] ?? 'Budget warning').': '.(string) ($flag['message'] ?? ''))
            ->values();

        if (! (bool) data_get($computed, 'assumptions.company_tax_configured', false)) {
            $warnings->push('Tax not configured: company tax is missing from Reference data, so after-tax profit is indicative only.');
        }

        if ($budget->status !== EntrepreneurBudget::STATUS_COMPLETE) {
            $warnings->push('Budget is not complete yet. It can be saved, but viability, scoring, and funding readiness may be affected.');
        }

        return $warnings->unique()->values()->all();
    }

    private function metricHtml(string $label, string $value): string
    {
        return sprintf(
            '<div class="metric"><span>%s</span><strong>%s</strong></div>',
            $this->escape($label),
            $this->escape($value),
        );
    }

    private function budgetPackCss(): string
    {
        return <<<'CSS'
:root { --chart-1: #0d7a7a; --chart-4: #b8860b; }
.decision-view { background: #f8fbfa; border-left-color: #0d7a7a; }
.decision-kicker { color: #0d7a7a; font-size: 9px; font-weight: 700; letter-spacing: 0; margin-bottom: 4px; text-transform: uppercase; }
.decision-headline { color: #34443c; font-size: 12px; margin: 0 0 10px; }
.summary { display: grid; gap: 8px; grid-template-columns: repeat(4, 1fr); margin: 12px 0; }
.metric { background: #fff; border: 1px solid #d8e2dc; padding: 8px; }
.metric span { color: #667085; display: block; font-size: 9px; font-weight: 700; text-transform: uppercase; }
.metric strong { display: block; font-size: 13px; margin-top: 2px; }
.section-intro { color: #667085; font-size: 10.5px; margin: 0 0 8px; }
.story p { margin: 0 0 7px; }
.decision-table th, .decision-table td { text-align: left; }
.decision-table th:nth-child(2), .decision-table td:nth-child(2) { text-align: right; white-space: nowrap; }
.scenario-comparison td:last-child { color: #34443c; }
.warning { background: #fff7e6; border: 1px solid #f3d08f; margin: 10px 0; padding: 8px 10px; }
.warning strong { display: block; margin-bottom: 4px; }
.warning ul { margin: 0; padding-left: 16px; }
.chart { border: 1px solid #d8e2dc; margin: 12px 0 14px; padding: 10px; page-break-inside: avoid; }
.chart-header { display: flex; gap: 12px; justify-content: space-between; margin-bottom: 6px; }
.chart-title { font-weight: 700; margin: 0; }
.chart-note { color: #667085; font-size: 10px; margin: 0; }
.chart-legend { color: #667085; font-size: 10px; white-space: nowrap; }
.chart svg { display: block; height: auto; width: 100%; }
.annual-forecast { break-inside: avoid; }
.monthly-appendix table { font-size: 10px; }
.page { break-before: page; }
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
    private function cashTrough(array $rows): array
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

    private function additionalFundingNeeded(mixed $lowestCash): float
    {
        if (! is_numeric($lowestCash)) {
            return 0.0;
        }

        return round(max(0.0, -((float) $lowestCash)), 2);
    }

    private function operatingCoverMonths(?int $expectedRunwayMonths): int
    {
        if ($expectedRunwayMonths === null || $expectedRunwayMonths <= 0) {
            return 6;
        }

        return min(12, max(6, $expectedRunwayMonths));
    }

    private function decisionHeadline(string $readinessLabel, float $additionalFunding, float $fundingGapOrSurplus): string
    {
        if ($readinessLabel === 'Not ready for external issue') {
            if ($additionalFunding > 0) {
                return 'The cash curve falls below zero, so the pack needs a funding action or revised assumptions before lender or investor issue.';
            }

            return 'The budget has unresolved readiness gaps before it should be issued externally.';
        }

        if ($readinessLabel === 'Needs advisor review') {
            return 'The base case is usable for discussion, but open quality warnings should be resolved before external issue.';
        }

        return $fundingGapOrSurplus >= 0
            ? 'The base case supports advisor review with current funding inputs covering the recommended funding target.'
            : 'The base case is close, but the recommended funding target still shows a gap to address.';
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
            return 'Requires funding cover and revised assumptions before external issue.';
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
