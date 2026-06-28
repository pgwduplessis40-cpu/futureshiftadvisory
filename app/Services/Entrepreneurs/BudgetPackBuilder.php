<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use Illuminate\Support\Collection;

final class BudgetPackBuilder
{
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
                'break_even_year' => data_get($computed, 'break_even_year'),
                'first_profitable_year' => data_get($computed, 'first_profitable_year'),
                'cash_flow_positive_year' => data_get($computed, 'cash_flow_positive_year'),
                'runway_months' => data_get($computed, 'runway_months'),
                'runway_open_ended' => data_get($computed, 'runway_open_ended', false),
                'available_after_launch' => data_get($computed, 'available_after_launch', 0),
            ],
            'assumptions' => $this->assumptions((array) data_get($computed, 'assumptions', [])),
            'explanations' => data_get($computed, 'explanations', []),
            'annual_totals' => array_values((array) data_get($computed, 'annual_totals', [])),
            'monthly_by_year' => collect((array) data_get($computed, 'monthly_detail', []))
                ->groupBy('year')
                ->map(fn (Collection $rows, int|string $year): array => [
                    'year' => (int) $year,
                    'rows' => $rows->values()->all(),
                ])
                ->values()
                ->all(),
            'scenarios' => collect((array) data_get($computed, 'scenarios', []))
                ->map(fn (array $scenario): array => [
                    'key' => $scenario['key'] ?? null,
                    'name' => $scenario['name'] ?? 'Scenario',
                    'type' => $scenario['type'] ?? 'base',
                    'summary' => $scenario['summary'] ?? [],
                    'annual_totals' => $scenario['annual_totals'] ?? [],
                ])
                ->values()
                ->all(),
            'active_flags' => collect((array) ($budget->flags ?? []))
                ->filter(fn (array $flag): bool => empty($flag['acknowledged_at']))
                ->values()
                ->all(),
        ];
    }

    public function html(EntrepreneurProfile $profile, BusinessPlan $plan): string
    {
        $payload = $this->payload($profile, $plan);
        $annualRows = collect((array) $payload['annual_totals'])
            ->map(fn (array $row): string => $this->annualRowHtml($row))
            ->implode('');
        $summary = (array) ($payload['summary'] ?? []);
        $warnings = collect((array) ($payload['warnings'] ?? []))
            ->map(fn (string $warning): string => '<li>'.$this->escape($warning).'</li>')
            ->implode('');
        $assumptions = collect((array) ($payload['assumptions'] ?? []))
            ->map(fn (array $row): string => sprintf(
                '<tr><td>%s</td><td>%s</td></tr>',
                $this->escape($row['label'] ?? ''),
                $this->escape($row['value'] ?? ''),
            ))
            ->implode('');
        $monthlyPages = collect((array) ($payload['monthly_by_year'] ?? []))
            ->map(fn (array $year): string => $this->monthlyYearHtml($year))
            ->implode('');
        $scenarioRows = collect((array) ($payload['scenarios'] ?? []))
            ->map(fn (array $scenario): string => sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $this->escape($scenario['name'] ?? 'Scenario'),
                $this->escape($this->formatLabel((string) ($scenario['type'] ?? 'base'))),
                $this->escape($this->yearValue(data_get($scenario, 'summary.break_even_year'))),
                $this->escape($this->yearValue(data_get($scenario, 'summary.cash_flow_positive_year'))),
            ))
            ->implode('');

        return sprintf(
            <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>%s</title>
<style>
body { color: #17211b; font-family: Arial, sans-serif; font-size: 11px; line-height: 1.5; margin: 0; }
h1 { font-size: 22px; margin: 0 0 4px; }
h2 { color: #214f44; font-size: 15px; margin: 0 0 8px; }
p { margin: 0 0 8px; }
.brand { border-bottom: 2px solid #2f6f5e; margin-bottom: 14px; padding-bottom: 10px; }
.summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 12px 0; }
.metric { border: 1px solid #d8e2dc; padding: 8px; }
.metric span { color: #667085; display: block; font-size: 9px; text-transform: uppercase; }
.metric strong { display: block; font-size: 13px; margin-top: 2px; }
.warning { background: #fff7e6; border: 1px solid #f3d08f; margin: 10px 0; padding: 8px 10px; }
.warning ul { margin: 0; padding-left: 16px; }
table { border-collapse: collapse; margin: 8px 0 14px; width: 100%; }
th, td { border: 1px solid #d8e2dc; padding: 5px 6px; text-align: right; vertical-align: top; }
th:first-child, td:first-child { text-align: left; }
th { background: #f5f8f6; color: #34443c; font-size: 10px; }
.note { color: #667085; font-size: 10px; }
.page { break-before: page; }
</style>
</head>
<body>
<header class="brand">
<h1>Budget pack</h1>
<p>Future Shift Advisory</p>
<p>%s · %s</p>
<p>Generated %s · GST exclusive by default</p>
</header>
<section>
<h2>Headline finance view</h2>
<div class="summary">%s%s%s</div>
%s
<table>
<thead><tr><th>Year</th><th>Revenue</th><th>Gross profit</th><th>GP %%</th><th>Fixed costs</th><th>Net profit before tax</th><th>NPBT %%</th><th>Tax</th><th>Net profit after tax</th><th>Ending cash</th></tr></thead>
<tbody>%s</tbody>
</table>
<p class="note">Break-even means the first year where net profit before tax is zero or positive. Cash-flow-positive means cumulative cash becomes zero or positive after startup losses and funding movements.</p>
</section>
<section>
<h2>Assumptions used</h2>
<table><tbody>%s</tbody></table>
</section>
<section>
<h2>Funding scenarios</h2>
<table><thead><tr><th>Scenario</th><th>Type</th><th>Break-even</th><th>Cash positive</th></tr></thead><tbody>%s</tbody></table>
</section>
%s
</body>
</html>
HTML,
            $this->escape('Budget pack - '.$profile->name),
            $this->escape($profile->name),
            $this->escape($plan->title),
            $this->escape(now()->format('M j, Y g:i A')),
            $this->metricHtml('Break-even year', $this->yearValue($summary['break_even_year'] ?? null)),
            $this->metricHtml('Profit year', $this->yearValue($summary['first_profitable_year'] ?? null)),
            $this->metricHtml('Cash-flow-positive year', $this->yearValue($summary['cash_flow_positive_year'] ?? null)),
            $warnings === '' ? '' : '<div class="warning"><ul>'.$warnings.'</ul></div>',
            $annualRows,
            $assumptions,
            $scenarioRows === '' ? '<tr><td colspan="4">No scenarios saved.</td></tr>' : $scenarioRows,
            $monthlyPages,
        );
    }

    /**
     * @param  array<string, mixed>  $assumptions
     * @return array<int, array{label:string,value:string}>
     */
    private function assumptions(array $assumptions): array
    {
        $labels = (array) ($assumptions['field_labels'] ?? []);

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
            ])
            ->values()
            ->all();
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
    private function monthlyYearHtml(array $year): string
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

        return sprintf(
            '<section class="page"><h2>Year %s monthly detail</h2><table><thead><tr><th>Month</th><th>Revenue</th><th>Variable costs</th><th>Gross profit</th><th>Fixed costs</th><th>NPAT</th><th>Cash flow</th><th>Cumulative cash</th></tr></thead><tbody>%s</tbody></table></section>',
            $this->escape($year['year'] ?? ''),
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

    private function money(mixed $value): string
    {
        return '$'.number_format((float) $value, 0);
    }

    private function percent(mixed $value): string
    {
        return $value === null ? '-' : number_format((float) $value, 1).'%';
    }

    private function yearValue(mixed $year): string
    {
        return is_numeric($year) ? 'Year '.((int) $year) : 'Not reached';
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
