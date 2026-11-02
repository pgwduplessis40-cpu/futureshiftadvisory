<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\EntrepreneurBudget;
use App\Services\Entrepreneurs\FounderBriefPages as Page;

/** @phpstan-import-type BriefPage from FounderBriefPages */
final class FounderBriefFinancials
{
    /** @return list<BriefPage> */
    public function build(mixed $payload, ?EntrepreneurBudget $budget): array
    {
        $data = (array) $payload;
        $decision = (array) ($data['funding_decision'] ?? []);
        $annual = array_slice((array) ($data['annual_totals'] ?? []), 0, 3);
        $annualRows = [];
        foreach ([
            'revenue' => 'Revenue', 'gross_profit' => 'Gross profit', 'fixed_costs' => 'Running costs',
            'net_profit_before_tax' => 'Profit before tax', 'tax' => 'Estimated tax',
            'net_profit_after_tax' => 'Profit after tax', 'capital_expenditure' => 'Capital expenditure',
            'net_cash_flow' => 'Net cash movement', 'ending_cash' => 'Closing cash',
        ] as $key => $label) {
            $annualRows[] = [$label, ...array_map(fn (array $row): string => Page::money($row[$key] ?? null), $annual)];
        }
        $years = array_map(fn (array $row): string => 'Year '.($row['year'] ?? '-'), $annual);
        $monthly = collect((array) ($data['monthly_by_year'] ?? []))->firstWhere('year', 1);
        $monthlyRows = collect((array) data_get($monthly, 'rows', []))->take(12)->map(fn (array $row): array => [
            'Month '.($row['month_in_year'] ?? '-'), Page::money($row['revenue'] ?? null),
            Page::money($row['cash_collected'] ?? null), Page::money($row['net_cash_flow'] ?? null),
            Page::money($row['cumulative_cash'] ?? null),
        ])->values()->all();
        $fundingRows = collect((array) ($data['use_of_funds'] ?? []))->take(6)->map(fn (array $row): array => [
            Page::excerpt((string) ($row['label'] ?? ''), 60), Page::money($row['amount'] ?? null),
        ])->values()->all();
        $fixed = collect((array) ($data['fixed_costs'] ?? []))->sortByDesc('monthly_amount')->values();
        $fixedRows = $fixed->take(10)->map(fn (array $row): array => [
            Page::excerpt((string) ($row['label'] ?? ''), 55), Page::money($row['rate_amount'] ?? null),
            (string) ($row['cadence_label'] ?? 'Not recorded'), Page::money($row['monthly_amount'] ?? null),
        ])->values()->all();
        if ($fixed->count() > 10) {
            $fixedRows[] = [($fixed->count() - 10).' other running costs', '-', 'Mixed', Page::money($fixed->slice(10)->sum('monthly_amount'))];
        }
        if ($fixed->isNotEmpty()) {
            $fixedRows[] = ['Total monthly equivalent', '-', '-', Page::money($fixed->sum('monthly_amount'))];
        }
        $launch = collect((array) ($budget->launch_costs ?? []));
        $launchRows = $launch->take(5)->map(fn (array $row): array => [
            Page::excerpt((string) ($row['label'] ?? 'Unlabelled cost'), 55),
            Page::money((float) ($row['amount'] ?? 0) * (float) ($row['quantity'] ?? 1)),
            'Month '.max(1, (int) ($row['month'] ?? 1)).' - once',
        ])->values()->all();
        if ($launch->count() > 5) {
            $launchRows[] = [($launch->count() - 5).' other one-off items', Page::money($launch->slice(5)->sum(fn (array $row): float => (float) ($row['amount'] ?? 0) * (float) ($row['quantity'] ?? 1))), 'See Budget Pack'];
        }
        $future = collect((array) ($data['future_costs'] ?? []));
        $futureRows = $future->take(5)->map(fn (array $row): array => [
            Page::excerpt((string) ($row['label'] ?? ''), 55), Page::money($row['amount'] ?? null),
            Page::excerpt((string) ($row['timing'] ?? 'Not recorded'), 65),
        ])->values()->all();
        $scenarioRows = collect((array) ($data['scenarios'] ?? []))->take(4)->map(fn (array $row): array => [
            Page::excerpt((string) ($row['name'] ?? 'Scenario'), 65), Page::money($row['lowest_cash'] ?? null),
            Page::money($row['additional_funding_needed'] ?? null),
        ])->values()->all();
        $assumptions = collect((array) ($data['assumptions'] ?? []))->take(5)->map(fn (array $row): string => Page::excerpt((string) ($row['label'] ?? ''), 55).': '.Page::excerpt((string) ($row['value'] ?? 'Not recorded'), 80)
        )->values()->all();
        $available = (bool) ($data['available'] ?? false);
        $currency = 'NZD; '.((bool) ($data['gst_exclusive'] ?? true) ? 'GST exclusive' : 'GST inclusive').'. Figures are from the saved Budget forecast.';
        $cashStory = array_map(fn (mixed $line): string => Page::excerpt((string) $line, 320), array_slice((array) ($data['cash_story'] ?? []), 0, 2));

        return [
            Page::page('Three-year financial outlook', [
                $currency,
                (bool) ($data['tax_configured'] ?? false) ? 'Tax uses the configured Budget assumptions.' : 'Company tax is not configured; any zero tax forecast must not be read as a tax exemption.',
                'Profit and cash differ because of payment timing, funding and capital spending. Closing cash includes the saved opening balance. Full calculations are in the Budget Pack.',
            ], [Page::table('Forecast results', ['Measure', ...($years ?: ['Forecast'])], $annual === [] ? [] : $annualRows)]),
            Page::page('Year 1 monthly cash flow', [
                $currency,
                'Opening cash: '.Page::money(data_get($data, 'summary.opening_cash_balance')).'. Net cash movement includes the payments and funding scheduled in the Budget; closing cash is the running balance.',
                ...$cashStory,
            ], [Page::table('Monthly forecast', ['Month', 'Revenue', 'Collected', 'Net movement', 'Closing cash'], $monthlyRows)]),
            Page::page('Funding and use of funds', [
                $available ? Page::excerpt((string) ($decision['headline'] ?? 'Funding position not confirmed.'), 450) : 'No saved Budget is available.',
                'Declared funding position: '.(string) ($decision['funding_position_label'] ?? 'Not confirmed').'.',
                'The required additional funding is the Budget model result. An advisor release decision does not change this amount. Funding already available and the planned operating buffer are shown separately.',
            ], [Page::table('Funding requirement', ['Measure', 'Amount'], $fundingRows)]),
            Page::page('Regular running costs', [
                'Saved rates and payment frequency are shown alongside their monthly equivalents. These are recurring costs; one-off payments are on the next page.',
                'The largest ten monthly equivalents are listed individually; any remaining lines are grouped into the other-cost total. Monthly equivalents include saved quantities. The annual forecast applies start dates, growth and other Budget assumptions.',
            ], [Page::table('Recurring cost schedule', ['Cost', 'Rate', 'Frequency', 'Monthly equivalent'], $fixedRows)]),
            Page::page('One-off costs and future commitments', [
                'One-off amounts include quantity and are paid once in the indicated forecast month. Future amounts are per scheduled payment; repeat timing is shown separately. These amounts are not monthly running costs.',
                'Schedules show up to five items per category. '.($future->count() > 5 ? ($future->count() - 5).' further future items are in the full Budget Pack. ' : '').'Read the complete Budget Pack for all line items, confidence and assumptions.',
            ], [
                Page::table('One-off payments', ['Item', 'Total payment', 'When paid'], $launchRows),
                Page::table('Future costs and renewals', ['Item', 'Per payment', 'Timing'], $futureRows),
            ]),
            Page::page('Assumptions and downside sensitivity', [
                'Sensitivity scenarios show how the saved forecast responds to changed assumptions. Additional funding is the modelled cash cover for each scenario.',
                ...$assumptions,
                'This is an extract of the saved assumptions and first four scenarios. Review the full Budget Pack before relying on a funding decision.',
            ], [Page::table('Scenario comparison', ['Scenario', 'Lowest cash', 'Additional funding'], $scenarioRows)]),
        ];
    }
}
