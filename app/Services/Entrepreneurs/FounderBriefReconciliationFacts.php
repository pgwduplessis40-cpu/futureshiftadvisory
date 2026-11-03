<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Services\Entrepreneurs\FounderBriefPages as Page;

final class FounderBriefReconciliationFacts
{
    public function __construct(private readonly BudgetFundingReadiness $funding) {}

    /** @return array<string,list<string>> */
    public function build(BusinessPlan $plan): array
    {
        $budget = $plan->budgetRunway;
        if ($budget === null) {
            return array_fill_keys(array_keys(FounderBriefReconciliationTopics::all()), ['No saved Budget is available.']);
        }
        $computed = (array) $budget->computed;
        $annual = (array) ($computed['annual_totals'] ?? []);
        $monthly = collect((array) ($computed['monthly_detail'] ?? []));
        $revenue = collect((array) $budget->revenue_forecast);
        $fixed = collect((array) $budget->monthly_fixed_costs);
        $future = collect((array) $budget->future_costs);
        $decision = $this->funding->evaluate($budget);
        $costs = $fixed->map(fn (array $row): string => $this->rate($row))->values()->all();
        $commitments = collect((array) $budget->launch_costs)->map(fn (array $row): string => (string) ($row['label'] ?? 'Unnamed cost').': '.Page::money((float) ($row['amount'] ?? 0) * (float) ($row['quantity'] ?? 1)).', once in Month '.($row['month'] ?? 1))->values()->all();
        $futureFacts = $future->map(fn (array $row): string => (string) ($row['label'] ?? 'Unnamed cost').': '.Page::money((float) ($row['amount'] ?? 0) * (float) ($row['quantity'] ?? 1)).'; '.FutureCostSchedule::timingLabel($row).'; '.(($row['classification'] ?? 'operating') === 'capital' ? 'capital asset, useful life '.($row['useful_life_years'] ?? 3).' years' : 'operating cost'))->values()->all();
        $forecast = [
            'Saved forecast start: '.(string) data_get($computed, 'assumptions.forecast_start_month', 'Not recorded').'. Annual result columns: '.count($annual).'.',
            'Budget horizon setting: '.($budget->forecast_years ?? 'Not recorded').' years.',
        ];

        return [
            'revenue_scope' => $revenue->map(fn (array $row): string => $this->rate($row))->values()->all() ?: ['No revenue rows saved.'],
            'ramp_capacity' => [
                ...$monthly->take(3)->map(fn (array $row): string => 'Month '.($row['month'] ?? '?').' revenue: '.Page::money($row['revenue'] ?? null))->values()->all(),
                ...$revenue->map(fn (array $row): string => (string) ($row['label'] ?? 'Revenue').': total monthly capacity '.($row['monthly_capacity_units'] ?? 'not recorded').' '.($row['unit_label'] ?? 'units').'; founder capacity '.($row['founder_capacity_units'] ?? 'not recorded').'; growth '.($row['growth_percent'] ?? 0).'% '.($row['growth_cadence'] ?? 'monthly'))->values()->all(),
                ...$forecast,
                'Debtor days: '.data_get($computed, 'assumptions.debtor_days', 'Not recorded').'. Check the cash-collection schedule against any deposit terms.',
            ],
            'profitability' => [
                'Calculated break-even: Month '.($computed['break_even_month'] ?? 'not reached').'; Year '.($computed['break_even_year'] ?? 'not reached').'.',
                ...collect($annual)->map(fn (array $row): string => 'Year '.($row['year'] ?? '?').': revenue '.Page::money($row['revenue'] ?? null).'; pre-tax profit '.Page::money($row['net_profit_before_tax'] ?? null).'; pre-tax margin '.($row['net_profit_before_tax_percent'] ?? 'not available').'%')->values()->all(),
            ],
            'owner_pay' => ['Monthly fixed-cost model base: '.Page::money($computed['monthly_fixed_costs'] ?? null).'.', ...$costs],
            'cash_funding' => [
                'Opening cash: '.Page::money($computed['opening_cash_balance'] ?? null).'; saved funding: '.Page::money($computed['total_funding'] ?? null).'.',
                'Declared funding position: '.(string) ($decision['funding_position_label'] ?? 'Not confirmed').'.',
                'Lowest forecast cash: '.Page::money($decision['lowest_cash'] ?? null).'; cash-deficit cover: '.Page::money($decision['additional_funding_needed'] ?? null).'.',
                'Planned funding target: '.Page::money($decision['recommended_funding_target'] ?? null).'; available funding and opening cash: '.Page::money($decision['available_funding'] ?? null).'; required additional funding: '.Page::money($decision['required_additional_funding'] ?? null).'.',
            ],
            'forecast_period' => [...$forecast, ...$futureFacts],
            'commitments' => [...$commitments, ...$futureFacts, ...$costs],
            'asset_treatment' => $futureFacts ?: ['No later-year cost rows saved.'],
            'distributions' => [
                ...collect($annual)->map(fn (array $row): string => 'Year '.($row['year'] ?? '?').': after-tax profit '.Page::money($row['net_profit_after_tax'] ?? null).'; closing cash '.Page::money($row['ending_cash'] ?? null).'.')->values()->all(),
                'Giving or distribution percentages in narrative text do not create Budget payments. Identify each scheduled payment and its Budget treatment, or explain that the allocation is conditional.',
            ],
            'evidence' => [
                'Supporting files linked to plan responses: '.$plan->sections->flatMap(fn ($section): array => (array) $section->attached_document_ids)->unique()->count().'.',
                ...$revenue->merge($fixed)->map(fn (array $row): string => (string) ($row['label'] ?? 'Budget row').': source '.($row['source_type'] ?? 'not recorded').'; reference '.($row['source_reference'] ?? 'not recorded').'; '.(! empty($row['source_confirmed']) ? 'confirmed' : 'unconfirmed'))->values()->all(),
            ],
            'narrative' => ['Use the calculated results above to reconcile the written financial narrative. Saving this review does not edit the plan, recalculate the Budget or regenerate its executive summary.'],
        ];
    }

    /** @param array{label?:string,amount?:float|int,quantity?:float|int,cadence?:string,month?:int} $row */
    private function rate(array $row): string
    {
        return ($row['label'] ?? 'Unnamed row').': '.Page::money($row['amount'] ?? null).' x '.($row['quantity'] ?? 1).', '.($row['cadence'] ?? 'monthly').', from Month '.($row['month'] ?? 1).'.';
    }
}
