<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\PlanSection;
use Illuminate\Support\Str;

/**
 * Compares explicit statements with saved calculations. Never edits either source.
 *
 * @phpstan-type Source array{requirement:string,title:string,excerpt:string}
 * @phpstan-type Fact array{key:string,label:string,value:float,source:string,requirement:string}
 * @phpstan-type Issue array{key:string,title:string,detail:string,action:string,requirement:string,focus:string}
 * @phpstan-type Guide array{sources:list<Source>,facts:list<Fact>,issues:list<Issue>}
 * @phpstan-type Section array{requirement_key?:string,body?:string,title?:string}
 * @phpstan-type Row array{label?:string,amount?:float|int,quantity?:float|int,cadence?:string,month?:int,year?:int,classification?:string,monthly_schedule?:list<float|int|null>}
 * @phpstan-type Annual array{year?:int,net_profit_before_tax_percent?:float|int|null}
 * @phpstan-type Monthly array{month?:int,revenue?:float|int}
 * @phpstan-type Budget array{forecast_years?:int,assumptions?:array{opening_cash_balance?:float|int|null},monthly_fixed_costs?:list<Row>,revenue_forecast?:list<Row>,future_costs?:list<Row>,computed?:array{break_even_year?:int|null,annual_totals?:list<Annual>,monthly_series?:list<Monthly>}}
 */
final class PlanBudgetGuide
{
    /** @return Guide */
    public function forPlan(BusinessPlan $plan): array
    {
        $plan->loadMissing('sections', 'budgetRunway');
        $sections = $plan->sections->map(fn (PlanSection $section): array => [
            'requirement_key' => (string) data_get($section->metadata, 'requirement_key', ''),
            'title' => $section->title,
            'body' => (string) $section->body,
        ])->all();
        /** @var Budget $budget */
        $budget = $plan->budgetRunway?->only(['forecast_years', 'assumptions', 'monthly_fixed_costs', 'revenue_forecast', 'future_costs', 'computed']) ?? [];

        return $this->build($sections, $budget);
    }

    /** @param list<Section> $sections
     * @param  Budget  $budget
     * @return Guide
     */
    public function build(array $sections, array $budget): array
    {
        $sources = [];
        $facts = [];
        $issues = [];
        $money = '([0-9][0-9,]*(?:\.[0-9]{1,2})?)';
        $patterns = [
            'opening_cash_balance' => ['Opening cash', '/(?:opening cash(?: balance)?|cash balance)\s*(?:is|of|:)?\s*(?:approximately |about )?\$'.$money.'/i'],
            'owner_weekly_pay' => ['Owner pay per week', '/(?:currently (?:allocate|pay)|current (?:owner.{0,15}pay|pay))\s*(?:is |of |: )?\$'.$money.'\s*(?:per |a |\/)?week/i'],
            'revenue_growth_percent' => ['Annual revenue growth (%)', '/(?:annual revenue growth|annual sales growth|revenue growth per year)\s*(?:is |of |: )?(\d+(?:\.\d+)?)\s*%/i'],
            'cost_inflation_percent' => ['Annual cost inflation (%)', '/(?:annual cost inflation|cost inflation|annual cost increase)\s*(?:is |of |: )?(\d+(?:\.\d+)?)\s*%/i'],
            'target_gross_profit_percent' => ['Gross profit target (%)', '/(?:target gross profit(?: margin)?|gross profit margin)\s*(?:is |of |: )?(?:approximately |about )?(\d+(?:\.\d+)?)\s*%/i'],
            'break_even_year' => ['Break-even year', '/break[ -]even[^.\n]{0,70}\byear\s+(\d+)/i'],
            'day_rate' => ['Price per delivery day', '/\$'.$money.'\s*(?:\+\s*GST\s*)?(?:per|a)\s+day/i'],
            'monthly_revenue_ceiling' => ['Monthly sales at capacity', '/\$[0-9,]+\s*[-–—]\s*\$'.$money.'\s*(?:\+\s*GST\s*)?(?:per|a)\s+month/iu'],
        ];
        foreach ($sections as $section) {
            $requirement = $section['requirement_key'] ?? '';
            if (! in_array($requirement, ['revenue-model', 'financial-assumptions', 'launch-funding', 'goals-objectives'], true)) {
                continue;
            }
            $text = trim(html_entity_decode(strip_tags(Str::markdown($section['body'] ?? '', ['html_input' => 'strip'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            if ($text === '') {
                continue;
            }
            $sources[] = ['requirement' => $requirement, 'title' => $section['title'] ?? $requirement, 'excerpt' => Str::limit(preg_replace('/\s+/u', ' ', $text) ?? $text, 260)];
            foreach ($patterns as $key => [$label, $pattern]) {
                preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $facts[] = ['key' => $key, 'label' => $label, 'value' => (float) str_replace(',', '', $match[1]), 'source' => $match[0], 'requirement' => $requirement];
                }
            }
            // An explicit amount in a dated monthly ramp is a claim, not a growth rate.
            preg_match_all('/\bMonth\s+([1-9]|1[0-2])\s*\(\s*\$'.$money.'/i', $text, $ramp, PREG_SET_ORDER);
            foreach ($ramp as $match) {
                $facts[] = ['key' => 'month_'.$match[1].'_revenue', 'label' => 'Month '.$match[1].' sales', 'value' => (float) str_replace(',', '', $match[2]), 'source' => $match[0].')', 'requirement' => $requirement];
            }
            if (preg_match('/(?:currently has|cash of)\s*(?:approximately |about )?\$'.$money.'\s+in (?:business|bank) accounts/i', $text, $cash) === 1) {
                $facts[] = ['key' => 'opening_cash_balance', 'label' => 'Opening cash', 'value' => (float) str_replace(',', '', $cash[1]), 'source' => $cash[0], 'requirement' => $requirement];
            }
            $this->compareRevenueScope($text, $budget, $issues);
        }
        $facts = array_values(array_unique($facts, SORT_REGULAR));
        $this->compareFacts($facts, $budget, $issues);
        foreach ($budget['future_costs'] ?? [] as $index => $cost) {
            if (($cost['year'] ?? 1) > ($budget['forecast_years'] ?? 3)) {
                $issues[] = $this->issue('horizon_'.$index, 'Include the planned future cost', ($cost['label'] ?? 'A future cost').' is in Year '.($cost['year'] ?? 1).', beyond the saved forecast.', 'Extend the budget horizon or change the timing to match the plan.', 'future-costs');
            }
            if (($cost['classification'] ?? 'operating') === 'operating' && preg_match('/\b(vehicle|laptop|computer)\b/i', $cost['label'] ?? '') === 1 && preg_match('/insurance|fuel|lease|rent|repair|maintenance/i', $cost['label'] ?? '') !== 1) {
                $issues[] = $this->issue('asset_'.$index, 'Check the asset purchase', ($cost['label'] ?? 'Equipment').' is recorded as a running cost.', 'For an asset purchase, select Capital and its useful life. Rename the line if it is a running expense.', 'future-costs');
            }
        }

        return ['sources' => $sources, 'facts' => $facts, 'issues' => array_values(collect($issues)->unique('key')->all())];
    }

    /** @param list<Fact> $facts
     * @param  Budget  $budget
     * @param  list<Issue>  $issues
     */
    private function compareFacts(array $facts, array $budget, array &$issues): void
    {
        foreach (collect($facts)->groupBy('key') as $key => $matches) {
            $fact = $matches->first();
            if ($matches->pluck('value')->unique()->count() > 1) {
                $issues[] = $this->issue($key, 'Choose one current '.$fact['label'], 'The saved plan states more than one value: '.$matches->pluck('value')->unique()->implode(', ').'.', 'Update the original plan section so it contains one current assumption.', 'budget-overview', $fact['requirement']);

                continue;
            }
            $actual = null;
            $focus = 'budget-overview';
            if ($key === 'opening_cash_balance') {
                $actual = $budget['assumptions']['opening_cash_balance'] ?? null;
                $focus = 'funding-sources';
            } elseif ($key === 'owner_weekly_pay') {
                $ownerRows = array_filter($budget['monthly_fixed_costs'] ?? [], fn (array $row): bool => preg_match('/owner|founder/i', $row['label'] ?? '') === 1 && ($row['month'] ?? 1) <= 1);
                if (count($ownerRows) > 0) {
                    $actual = array_sum(array_map(fn (array $row): float => BudgetMonthlySchedule::rate($row['monthly_schedule'] ?? [], 1, (float) ($row['amount'] ?? 0)) * (float) ($row['quantity'] ?? 1) * match ($row['cadence'] ?? 'monthly') {
                        'weekly' => 1, 'fortnightly' => 0.5, 'annual' => 1 / 52, 'quarterly' => 4 / 52, default => 12 / 52,
                    }, $ownerRows));
                }
                $focus = 'fixed-costs';
            } elseif ($key === 'break_even_year') {
                $actual = $budget['computed']['break_even_year'] ?? null;
            } elseif (preg_match('/^month_(\d+)_revenue$/', $key, $month) === 1) {
                $actual = collect($budget['computed']['monthly_series'] ?? [])->firstWhere('month', (int) $month[1])['revenue'] ?? null;
                $focus = 'revenue-forecast';
            } elseif ($key === 'monthly_revenue_ceiling') {
                $highest = collect($budget['computed']['monthly_series'] ?? [])->max('revenue');
                if (is_numeric($highest) && $highest > $fact['value'] + 1) {
                    $issues[] = $this->issue($key, 'Check sales against delivery capacity', 'The plan describes sales of up to $'.number_format($fact['value'], 2).' per month; the saved Year 1 forecast reaches $'.number_format((float) $highest, 2).'.', 'Check volumes and shared delivery capacity across all offers. Explain and cost any additional capacity in the plan.', 'revenue-forecast', $fact['requirement']);
                }
            }
            if (is_numeric($actual) && abs((float) $actual - $fact['value']) > 1) {
                $issues[] = $this->issue($key, 'Check '.$fact['label'], 'Plan: '.$fact['source'].'. Saved budget: '.number_format((float) $actual, 2).'.', 'Check the budget input against the plan. Correct the input or update the plan if the assumption has changed.', $focus, $fact['requirement']);
            } elseif ($key === 'break_even_year' && is_numeric($actual) && (int) $actual !== (int) $fact['value']) {
                $issues[] = $this->issue($key, 'Update the break-even explanation', 'The plan says Year '.$fact['value'].'; the saved forecast reaches break-even in Year '.$actual.'.', 'Use the calculated result in the plan. Change budget inputs only when the underlying assumptions change.', $focus, $fact['requirement']);
            }
        }
    }

    /** @param Budget $budget
     * @param  list<Issue>  $issues
     */
    private function compareRevenueScope(string $text, array $budget, array &$issues): void
    {
        if (preg_match('/([^.!?\n]{5,100}) is the (?:core|only) revenue stream used in the base forecast/i', $text, $base) !== 1 || preg_match('/excluded from the (?:conservative )?base (?:revenue )?forecast/i', $text) !== 1) {
            return;
        }
        $words = array_filter(preg_split('/\W+/u', strtolower($base[1])) ?: [], fn (string $word): bool => strlen($word) > 4 && ! in_array($word, ['business', 'creative'], true));
        $other = array_filter($budget['revenue_forecast'] ?? [], function (array $row) use ($words): bool {
            if (($row['amount'] ?? 0) * ($row['quantity'] ?? 1) <= 0) {
                return false;
            }
            foreach ($words as $word) {
                if (str_contains(strtolower($row['label'] ?? ''), rtrim($word, 's'))) {
                    return false;
                }
            }

            return $words !== [];
        });
        if ($other !== []) {
            $issues[] = $this->issue('revenue_scope', 'Choose the sales included in the base forecast', 'The plan excludes other offers, but the budget includes '.implode(', ', array_column($other, 'label')).'.', 'Remove excluded sales from the base budget, or update the plan and provide evidence for including them.', 'revenue-forecast', 'revenue-model');
        }
    }

    /** @return Issue */
    private function issue(string $key, string $title, string $detail, string $action, string $focus, string $requirement = 'financial-assumptions'): array
    {
        return compact('key', 'title', 'detail', 'action', 'requirement', 'focus');
    }
}
