<?php

declare(strict_types=1);

namespace App\Services\Budgets;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Services\Reports\BrandedReportLayout;

final class StrategicBudgetPdfDocument
{
    public function __construct(private readonly BrandedReportLayout $layout) {}

    /**
     * @param  array<string, mixed>  $budget
     */
    public function html(Client $client, array $budget): string
    {
        $businessName = $client->trading_name ?: $client->legal_name;
        $engagementLabel = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type->label()
            : str((string) $client->engagement_type)->replace('_', ' ')->title()->toString();
        $computed = (array) ($budget['computed'] ?? []);
        $analytics = (array) ($budget['analytics'] ?? []);
        $charts = (array) ($analytics['charts'] ?? []);
        $planSections = (array) ($budget['business_plan_sections'] ?? []);
        $annualForecast = (array) data_get($analytics, 'predictive.annual_forecast', []);

        $content = $this->layout->section(
            'Business plan',
            $this->planHtml($planSections),
            key: 'business-plan',
        );
        $content .= $this->layout->section(
            'Budget summary',
            $this->summaryHtml($computed).$this->annualForecastHtml($annualForecast),
            key: 'budget-summary',
        );
        $content .= $this->layout->section(
            'Insight charts',
            $this->chartsHtml($charts),
            key: 'insight-charts',
        );
        $content .= $this->layout->section(
            'Decision-ready insights',
            $this->insightHtml($analytics),
            key: 'decision-insights',
        );

        return $this->layout->document(
            title: ($budget['label'] ?? 'Business plan and budget').' - '.$businessName,
            templateKey: 'strategic-budget-document',
            documentTag: 'Client planning document',
            eyebrow: 'Future Shift Advisory',
            heading: (string) ($budget['label'] ?? 'Business plan and budget'),
            subheading: $businessName.' / '.$engagementLabel,
            meta: [
                'Plan status' => (string) ($budget['status_label'] ?? 'Draft'),
                'Readiness' => (string) ($budget['readiness_score'] ?? 0).'/100',
                'Forecast horizon' => (string) ($budget['horizon_months'] ?? 12).' months',
                'Financial evidence' => (string) data_get($budget, 'source_financials.count', 0).' files',
            ],
            contentHtml: $content,
            footer: 'Prepared for '.$businessName.' by Future Shift Advisory on '.now()->format('j M Y').'.',
            metaColumns: 4,
            extraCss: $this->chartCss(),
        );
    }

    /**
     * @param  array<int, mixed>  $sections
     */
    private function planHtml(array $sections): string
    {
        $html = '';

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $title = $this->escape($section['title'] ?? 'Plan section');
            $answer = trim((string) ($section['answer'] ?? ''));
            $html .= sprintf(
                '<div class="plan-section"><h3>%s</h3><p>%s</p></div>',
                $title,
                $this->escape($answer !== '' ? $answer : 'This section has not yet been completed.'),
            );
        }

        return $html !== '' ? $html : '<p class="muted">No business plan sections have been completed yet.</p>';
    }

    /**
     * @param  array<string, mixed>  $computed
     */
    private function summaryHtml(array $computed): string
    {
        $items = [
            ['Implementation costs', $this->currency($computed['total_launch_costs'] ?? 0)],
            ['Monthly operating costs', $this->currency($computed['monthly_fixed_costs'] ?? 0)],
            ['Funding available', $this->currency($computed['total_funding'] ?? 0)],
            ['Available after setup', $this->currency($computed['available_after_launch'] ?? 0)],
            ['Break-even', $this->year($computed['break_even_year'] ?? null)],
            ['Cash-flow positive', $this->year($computed['cash_flow_positive_year'] ?? null)],
        ];

        return '<div class="metric-grid">'.collect($items)->map(fn (array $item): string => sprintf(
            '<div><span>%s</span><strong>%s</strong></div>',
            $this->escape($item[0]),
            $this->escape($item[1]),
        ))->implode('').'</div>';
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    private function annualForecastHtml(array $rows): string
    {
        $rows = collect($rows)->filter(fn (mixed $row): bool => is_array($row))->values();

        if ($rows->isEmpty()) {
            return '';
        }

        $body = $rows->map(fn (array $row): string => sprintf(
            '<tr><td>Year %s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            $this->escape($row['year'] ?? ''),
            $this->escape($this->currency($row['revenue'] ?? 0)),
            $this->escape($this->currency($row['net_cash_flow'] ?? 0)),
            $this->escape($this->currency($row['ending_cash'] ?? 0)),
        ))->implode('');

        return '<h3 class="subheading">Annual forecast</h3><table><thead><tr><th>Period</th><th>Revenue</th><th>Net cash flow</th><th>Ending cash</th></tr></thead><tbody>'.$body.'</tbody></table>';
    }

    /**
     * @param  array<string, mixed>  $charts
     */
    private function chartsHtml(array $charts): string
    {
        return '<div class="chart-grid">'
            .$this->annualRevenueCostsChart((array) ($charts['annual_revenue_costs'] ?? []))
            .$this->marginChart((array) ($charts['margin_percentages'] ?? []))
            .$this->cashChart((array) ($charts['monthly_cash'] ?? []))
            .$this->scenarioChart((array) ($charts['scenario_comparison'] ?? []))
            .$this->confidenceChart((array) ($charts['confidence_mix'] ?? []))
            .'</div>';
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    private function annualRevenueCostsChart(array $rows): string
    {
        $rows = $this->chartRows($rows);
        $max = max([1.0, ...collect($rows)
            ->flatMap(fn (array $row): array => [(float) ($row['revenue'] ?? 0), (float) ($row['costs'] ?? 0)])
            ->all()]);
        $body = collect($rows)->map(function (array $row) use ($max): string {
            return '<div class="chart-row"><span>'.$this->escape($row['label'] ?? '').'</span><div class="chart-series">'
                .$this->bar((float) ($row['revenue'] ?? 0), $max, 'revenue', 'Revenue')
                .$this->bar((float) ($row['costs'] ?? 0), $max, 'costs', 'Costs')
                .'</div><strong>'.$this->escape($this->currency($row['net_cash_flow'] ?? 0)).'</strong></div>';
        })->implode('');

        return $this->chartCard('Revenue, costs and net cash', 'Green is revenue, gold is costs. The figure at right is net cash flow.', $body, 'Net cash flow');
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    private function marginChart(array $rows): string
    {
        $rows = $this->chartRows($rows);
        $values = collect($rows)->flatMap(fn (array $row): array => [
            abs((float) ($row['gross_profit_percent'] ?? 0)),
            abs((float) ($row['net_profit_before_tax_percent'] ?? 0)),
            abs((float) ($row['net_profit_after_tax_percent'] ?? 0)),
        ]);
        $max = max([1.0, ...$values->all()]);
        $body = collect($rows)->map(function (array $row) use ($max): string {
            return '<div class="chart-row"><span>'.$this->escape($row['label'] ?? '').'</span><div class="chart-series">'
                .$this->bar((float) ($row['gross_profit_percent'] ?? 0), $max, 'gross-profit', 'GP')
                .$this->bar((float) ($row['net_profit_before_tax_percent'] ?? 0), $max, 'before-tax', 'NPBT')
                .$this->bar((float) ($row['net_profit_after_tax_percent'] ?? 0), $max, 'after-tax', 'NPAT')
                .'</div><strong>'.$this->escape($this->percent($row['net_profit_after_tax_percent'] ?? 0)).'</strong></div>';
        })->implode('');

        return $this->chartCard('Profit margin story', 'GP, NPBT and NPAT show the profit retained at each stage. Red indicates a negative margin.', $body, 'NPAT');
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    private function cashChart(array $rows): string
    {
        $rows = $this->chartRows($rows);
        $sample = collect($rows)->filter(fn (array $row, int $index): bool => $index === 0 || $index === count($rows) - 1 || (($index + 1) % 3) === 0)->values();
        $max = max([1.0, ...$sample
            ->map(fn (array $row): float => abs((float) ($row['cumulative_cash'] ?? 0)))
            ->all()]);
        $body = $sample->map(fn (array $row): string => '<div class="chart-row"><span>'.$this->escape($row['label'] ?? '').'</span><div class="chart-series">'
            .$this->bar((float) ($row['cumulative_cash'] ?? 0), $max, 'cash', 'Cumulative cash').'</div><strong>'.$this->escape($this->currency($row['cumulative_cash'] ?? 0)).'</strong></div>')->implode('');

        return $this->chartCard('Cash available over time', 'Cumulative cash across the forecast horizon. The selected months keep the printed chart easy to scan.', $body, 'Cumulative cash', 'chart-wide');
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    private function scenarioChart(array $rows): string
    {
        $rows = $this->chartRows($rows);
        $max = max([1.0, ...collect($rows)
            ->map(fn (array $row): float => abs((float) ($row['ending_cash'] ?? 0)))
            ->all()]);
        $body = collect($rows)->map(function (array $row) use ($max): string {
            $runway = (bool) ($row['runway_open_ended'] ?? false) ? 'Open runway' : ((string) ($row['runway_months'] ?? 0).' months');

            return '<div class="chart-row"><span>'.$this->escape($row['name'] ?? '').'<small>'.$this->escape($runway).'</small></span><div class="chart-series">'
                .$this->bar((float) ($row['ending_cash'] ?? 0), $max, 'scenario', 'Ending cash').'</div><strong>'.$this->escape($this->currency($row['ending_cash'] ?? 0)).'</strong></div>';
        })->implode('');

        return $this->chartCard('Scenario sensitivity impact', 'Compares ending cash across the base case and automatic downside scenarios.', $body, 'Ending cash');
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    private function confidenceChart(array $rows): string
    {
        $rows = $this->chartRows($rows);
        $total = (float) collect($rows)->sum(fn (array $row): float => (float) ($row['value'] ?? 0));
        if ($total <= 0) {
            return $this->chartCard('Evidence confidence mix', 'No confidence rows have been recorded yet.', '<p class="muted">No confidence rows have been recorded yet.</p>', 'Confidence');
        }

        $segments = collect($rows)->map(function (array $row) use ($total): string {
            $label = (string) ($row['label'] ?? 'Unknown');
            $class = match ($label) {
                'Known' => 'known',
                'Estimate' => 'estimate',
                default => 'guess',
            };

            return sprintf('<span class="confidence-segment %s" style="width:%s%%"></span>', $class, $this->width((float) ($row['value'] ?? 0), $total));
        })->implode('');
        $body = '<div class="confidence-stack">'.$segments.'</div><div class="confidence-legend">'.collect($rows)->map(fn (array $row): string => sprintf(
            '<span><i class="%s"></i>%s: %s (%s)</span>',
            match ((string) ($row['label'] ?? '')) {
                'Known' => 'known',
                'Estimate' => 'estimate',
                default => 'guess',
            },
            $this->escape($row['label'] ?? ''),
            $this->escape($row['value'] ?? 0),
            $this->escape($this->percent(((float) ($row['value'] ?? 0) / $total) * 100)),
        ))->implode('').'</div>';

        return $this->chartCard('Evidence confidence mix', 'Shows how much of the planning model is supported by known evidence, estimates or guesses.', $body, 'Evidence');
    }

    /**
     * @param  array<string, mixed>  $analytics
     */
    private function insightHtml(array $analytics): string
    {
        $sections = [
            'Current position' => (array) ($analytics['descriptive'] ?? []),
            'Risks and diagnoses' => (array) ($analytics['diagnostic'] ?? []),
            'Forecast outlook' => (array) ($analytics['predictive'] ?? []),
            'Recommended actions' => (array) ($analytics['prescriptive'] ?? []),
        ];

        return '<div class="insight-grid">'.collect($sections)->map(function (array $readout, string $title): string {
            $findings = collect((array) ($readout['findings'] ?? []))
                ->filter(fn (mixed $finding): bool => is_string($finding) && $finding !== '')
                ->map(fn (string $finding): string => '<li>'.$this->escape($finding).'</li>')
                ->implode('');

            return '<div class="insight-card"><h3>'.$this->escape($title).'</h3><p>'.$this->escape($readout['summary'] ?? '').'</p>'
                .($findings !== '' ? '<ul>'.$findings.'</ul>' : '').'</div>';
        })->implode('').'</div>';
    }

    private function chartCard(string $title, string $description, string $body, string $valueLabel, string $class = ''): string
    {
        return '<section class="insight-chart '.$this->escape($class).'"><h3>'.$this->escape($title).'</h3><p>'.$this->escape($description).'</p><div class="chart-value-label">'.$this->escape($valueLabel).'</div>'.$body.'</section>';
    }

    private function bar(float $value, float $max, string $class, string $label): string
    {
        $tone = $value < 0 ? ' negative' : '';

        return '<div class="bar-row"><i>'.$this->escape($label).'</i><span class="bar-track"><b class="bar '.$this->escape($class.$tone).'" style="width:'.$this->width(abs($value), $max).'%"></b></span></div>';
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function chartRows(array $rows): array
    {
        return collect($rows)->filter(fn (mixed $row): bool => is_array($row))->values()->all();
    }

    private function width(float $value, float $max): string
    {
        return number_format(min(100, max(0, ($value / max(1.0, $max)) * 100)), 1, '.', '');
    }

    private function currency(mixed $value): string
    {
        return 'NZ$'.number_format((float) $value, 0);
    }

    private function percent(mixed $value): string
    {
        return number_format((float) $value, 1).'%';
    }

    private function year(mixed $value): string
    {
        return is_numeric($value) && (int) $value > 0 ? 'Year '.(int) $value : 'Not forecast';
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function chartCss(): string
    {
        return <<<'CSS'
.plan-section { border-top: 1px solid #e8e2d7; padding: 10px 0; }
.plan-section:first-child { border-top: 0; padding-top: 0; }
.plan-section h3, .subheading { color: #1c2f4a; font-size: 12px; margin: 0 0 4px; }
.plan-section p { margin: 0; white-space: pre-wrap; }
.metric-grid { display: grid; gap: 8px; grid-template-columns: repeat(3, 1fr); margin-bottom: 14px; }
.metric-grid div { background: #f8f5ee; border: 1px solid #ded6c7; padding: 8px; }
.metric-grid span { color: #667282; display: block; font-size: 8.5px; font-weight: 700; text-transform: uppercase; }
.metric-grid strong { color: #1c2f4a; display: block; font-size: 12px; margin-top: 3px; }
.chart-grid { display: grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.insight-chart { border: 1px solid #ded6c7; break-inside: avoid; padding: 10px; }
.insight-chart.chart-wide { grid-column: 1 / -1; }
.insight-chart h3 { color: #1c2f4a; font-size: 12px; margin: 0; }
.insight-chart > p { color: #667282; font-size: 9px; margin: 3px 0 8px; }
.chart-value-label { border-bottom: 1px solid #eee7db; color: #667282; font-size: 8px; margin: 0 0 6px; padding-bottom: 4px; text-align: right; }
.chart-row { align-items: center; display: grid; gap: 7px; grid-template-columns: 46px minmax(0, 1fr) 56px; margin-top: 7px; }
.chart-row > span { color: #1c2f4a; font-size: 8.5px; font-weight: 700; }
.chart-row > span small { color: #667282; display: block; font-size: 7.5px; font-weight: 400; }
.chart-row > strong { font-size: 8px; text-align: right; }
.chart-series { display: grid; gap: 3px; }
.bar-row { align-items: center; display: grid; gap: 4px; grid-template-columns: 29px minmax(0, 1fr); }
.bar-row i { color: #667282; font-size: 7.5px; font-style: normal; }
.bar-track { background: #edf0ed; display: block; height: 6px; overflow: hidden; }
.bar { display: block; height: 100%; }
.bar.revenue, .bar.gross-profit, .bar.cash, .bar.scenario { background: #16815f; }
.bar.costs, .bar.before-tax { background: #d29a12; }
.bar.after-tax { background: #1c5a7d; }
.bar.negative { background: #b54747; }
.confidence-stack { display: flex; height: 12px; margin: 12px 0; overflow: hidden; }
.confidence-segment.known, .confidence-legend i.known { background: #16815f; }
.confidence-segment.estimate, .confidence-legend i.estimate { background: #d29a12; }
.confidence-segment.guess, .confidence-legend i.guess { background: #b54747; }
.confidence-legend { display: grid; gap: 4px; }
.confidence-legend span { color: #34443c; font-size: 8.5px; }
.confidence-legend i { display: inline-block; height: 7px; margin-right: 5px; width: 7px; }
.insight-grid { display: grid; gap: 10px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.insight-card { border-top: 1px solid #ded6c7; padding-top: 8px; }
.insight-card h3 { color: #1c2f4a; font-size: 12px; margin: 0 0 4px; }
.insight-card p { margin: 0; }
.insight-card ul { margin: 6px 0 0; padding-left: 15px; }
.insight-card li { margin: 2px 0; }
CSS;
    }
}
