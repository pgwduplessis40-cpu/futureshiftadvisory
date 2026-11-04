<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\BudgetMonthlySchedule;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class BudgetFacts
{
    /** @return list<Fact> */
    public function capture(SourceRecords $source): array
    {
        $facts = [];
        foreach ([
            'model.opening_cash' => 'opening_cash_balance', 'model.funding_total' => 'total_funding',
            'model.fixed_cost_display' => 'monthly_fixed_costs',
        ] as $key => $path) {
            $facts[] = $source->fact($key, 'budget.computed.'.$path, unit: str_contains($key, 'fixed_cost') ? 'NZD/month' : 'NZD');
        }
        $years = $source->value('budget.forecast_years');
        $facts[] = new Fact('model.horizon', is_numeric($years) ? (int) $years * 12 : null, unit: 'months', sources: [$source->reference('budget.forecast_years')]);
        $facts[] = $source->fact('model.profitability', 'budget.computed.break_even_year', unit: 'year', basis: 'annual_profit');
        $facts[] = $source->fact('plan.funding_position', 'budget.assumptions.funding_position', confirmed: $source->value('budget.assumptions.funding_position_confirmed') === true);
        $facts[] = $source->fact('model.external_request', 'budget.assumptions.external_funding_request_amount', unit: 'NZD');
        $opening = $source->value('budget.computed.opening_cash_balance');
        $funding = $source->value('budget.computed.total_funding');
        $available = is_numeric($opening) && is_numeric($funding) ? BigDecimal::of((string) $opening)->plus((string) $funding) : null;
        $facts[] = new Fact('model.available_funds', $available === null ? null : (string) $available, unit: 'NZD', sources: [$source->reference('budget.computed')]);
        $launch = $source->value('budget.computed.total_launch_costs');
        $fixed = $source->value('budget.computed.monthly_fixed_costs');
        $months = $source->value('budget.expected_runway_months');
        if ($available !== null && is_numeric($launch) && is_numeric($fixed)) {
            $cover = max(3, min(12, is_numeric($months) ? (int) $months : 6));
            $target = BigDecimal::of((string) $fixed)->multipliedBy($cover)->plus((string) $launch)->multipliedBy('1.10');
            $gap = $target->minus($available);
            $facts[] = new Fact('model.reserve_gap', $gap->isPositive() ? Checks::money($gap) : '0.00', unit: 'NZD', basis: 'static reserve policy', sources: [$source->reference('budget')]);
        }
        $closings = [];
        $scenarios = $source->rows('budget.computed.scenarios');
        // Historical rows can have only the base payload; preserve that evidence.
        if ($scenarios === []) {
            $scenarios = [['key' => 'base', 'monthly_detail' => $source->value('budget.computed.monthly_detail'), 'annual_totals' => $source->value('budget.computed.annual_totals')]];
        }
        foreach ($scenarios as $index => $scenario) {
            $key = is_string($scenario['key'] ?? null) ? $scenario['key'] : 'unknown:'.$index;
            $path = $source->rows('budget.computed.scenarios') === [] ? 'budget.computed' : 'budget.computed.scenarios.'.$index;
            $previous = $opening;
            foreach ($source->rows($path.'.monthly_detail') as $rowIndex => $row) {
                if (! is_numeric($row['month'] ?? null)) {
                    continue;
                }
                $period = 'month:'.(int) $row['month'];
                $rowPath = $path.'.monthly_detail.'.$rowIndex;
                $facts[] = new Fact('cash.opening', is_numeric($previous) ? (string) $previous : null, period: $period, scenario: $key, unit: 'NZD', sources: [$source->reference($rowPath)]);
                foreach (['cash.movement' => 'net_cash_flow', 'cash.closing' => 'cumulative_cash', 'cash.receipts' => 'cash_collected',
                    'model.revenue' => 'revenue', 'scenario.profit' => 'net_profit_before_tax', 'scenario.revenue' => 'revenue',
                    'scenario.variable_costs' => 'variable_costs', 'scenario.fixed_costs' => 'fixed_costs',
                    'scenario.depreciation' => 'depreciation', 'scenario.interest' => 'interest'] as $field => $column) {
                    $facts[] = $source->fact($field, $rowPath.'.'.$column, period: $period, scenario: $key, unit: 'NZD');
                }
                $previous = $row['cumulative_cash'] ?? null;
                if (is_numeric($previous)) {
                    $closings[$key][] = (string) $previous;
                }
            }
            foreach ($source->rows($path.'.annual_totals') as $annualIndex => $row) {
                if (! is_numeric($row['year'] ?? null)) {
                    continue;
                }
                $period = 'year:'.(int) $row['year'];
                $rowPath = $path.'.annual_totals.'.$annualIndex;
                foreach (['cash.annual_movement' => 'net_cash_flow', 'cash.annual_closing' => 'ending_cash', 'model.npbt_margin' => 'net_profit_before_tax_percent',
                    'tax.profit_display' => 'net_profit_before_tax', 'tax.expense_display' => 'tax'] as $field => $column) {
                    $facts[] = $source->fact($field, $rowPath.'.'.$column, period: $period, scenario: $key, unit: $field === 'model.npbt_margin' ? 'percent' : 'NZD');
                }
                $ratePath = $source->value('budget.computed.assumptions.company_tax_rate_percent') !== null
                    ? 'budget.computed.assumptions.company_tax_rate_percent' : 'budget.assumptions.company_tax_rate_percent';
                $facts[] = $source->fact('tax.rate_display', $ratePath, period: $period, scenario: $key, unit: 'percent');
            }
            if (isset($closings[$key])) {
                $balances = array_map(BigDecimal::of(...), $closings[$key]);
                usort($balances, fn (BigDecimal $a, BigDecimal $b): int => $a->compareTo($b));
                $minimum = $balances[0];
                $facts[] = new Fact('scenario.lowest_cash', (string) $minimum, scenario: $key, unit: 'NZD', sources: [$source->reference($path.'.monthly_detail')]);
                if ($key === 'base') {
                    $facts[] = new Fact('model.cash_deficit', $minimum->isNegative() ? (string) $minimum->negated() : '0', unit: 'NZD', sources: [$source->reference($path.'.monthly_detail')]);
                }
            }
        }
        $facts = [...$facts, ...$this->costs($source)];
        // Legacy funding rows do not store all commitment, account restriction
        // and availability fields. Never infer those from a label or a zero gap.
        foreach ($source->rows('budget.funding_sources') as $i => $row) {
            $amount = is_numeric($row['amount'] ?? null) && is_numeric($row['quantity'] ?? 1) ? BigDecimal::of((string) $row['amount'])->multipliedBy((string) ($row['quantity'] ?? 1)) : null;
            $facts[] = new Fact('funding.amount', $amount === null ? null : (string) $amount, entity: 'funding:'.$i, unit: 'NZD', sources: [$source->reference('budget.funding_sources.'.$i)]);
        }

        return $facts;
    }

    /** @return list<Fact> */
    private function costs(SourceRecords $source): array
    {
        $facts = [];
        $trace = BigDecimal::of(0);
        $hasTrace = false;
        $owner = BigDecimal::of(0);
        $hasOwner = false;
        foreach ($source->rows('budget.monthly_fixed_costs') as $i => $row) {
            if (! is_numeric($row['amount'] ?? null) || ! is_numeric($row['quantity'] ?? null)) {
                continue;
            }
            $factor = match ($row['cadence'] ?? null) {
                'weekly' => 52, 'fortnightly' => 26, 'monthly' => 12, 'quarterly' => 4, 'annual' => 1, default => null
            };
            if ($factor === null) {
                continue;
            }
            $schedule = BudgetMonthlySchedule::normalise($row['monthly_schedule'] ?? []);
            $average = BudgetMonthlySchedule::averageRate($schedule, (float) $row['amount']);
            $trace = $trace->plus(BigDecimal::of((string) $average)->multipliedBy((string) $row['quantity'])->multipliedBy($factor)->dividedBy(12, 8, RoundingMode::HalfUp));
            $hasTrace = true;
            if (preg_match('/owner|founder/i', (string) ($row['label'] ?? '')) === 1 && (int) ($row['month'] ?? 1) <= 1) {
                $current = BudgetMonthlySchedule::rate($schedule, 1, (float) $row['amount']);
                $owner = $owner->plus(BigDecimal::of((string) $current)->multipliedBy((string) $row['quantity'])->multipliedBy($factor)->dividedBy(12, 8, RoundingMode::HalfUp));
                $hasOwner = true;
            }
        }
        if ($hasTrace) {
            $facts[] = new Fact('cost.trace_display', (string) $trace, unit: 'NZD/month', sources: [$source->reference('budget.monthly_fixed_costs')]);
        }
        if ($hasOwner) {
            $start = $source->value('budget.assumptions.forecast_start_month');
            $date = is_string($start) && preg_match('/^\d{4}-\d{2}$/D', $start) === 1 ? $start.'-01' : $start;
            $facts[] = new Fact('model.owner_pay', (string) $owner, unit: 'NZD/month', basis: 'current_rate', asOf: FactValidation::date($date) ? $date : null, sources: [$source->reference('budget.monthly_fixed_costs')]);
        }
        foreach ($source->rows('budget.future_costs') as $i => $row) {
            $entity = 'future:'.$i;
            $period = is_numeric($row['year'] ?? null) ? 'year:'.(int) $row['year'] : 'unknown';
            $amount = is_numeric($row['amount'] ?? null) && is_numeric($row['quantity'] ?? 1) ? BigDecimal::of((string) $row['amount'])->multipliedBy((string) ($row['quantity'] ?? 1)) : null;
            $facts[] = new Fact('cost.amount', $amount === null ? null : (string) $amount, $entity, $period, unit: 'NZD', sources: [$source->reference('budget.future_costs.'.$i)]);
            if ($amount?->isPositive() && is_numeric($row['year'] ?? null)) {
                $facts[] = new Fact('scope.required_horizon', (int) $row['year'] * 12, $entity, $period, unit: 'months', sources: [$source->reference('budget.future_costs.'.$i)]);
            }
            $facts[] = $source->fact('cost.classification', 'budget.future_costs.'.$i.'.classification', $entity, $period);
            $asset = preg_match('/\b(vehicle|laptop|computer|phone|studio)\b/i', (string) ($row['label'] ?? '')) === 1
                && preg_match('/insurance|fuel|lease|rent|repair|maintenance/i', (string) ($row['label'] ?? '')) !== 1;
            $facts[] = new Fact('cost.asset_like', $asset, $entity, $period, sources: [$source->reference('budget.future_costs.'.$i)]);
        }

        return $facts;
    }
}
