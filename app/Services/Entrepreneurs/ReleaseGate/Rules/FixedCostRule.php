<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\FactValidation;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;
use Brick\Math\RoundingMode;

final class FixedCostRule implements Rule
{
    public function id(): string
    {
        return 'MI-03';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $checks = [];
        $trace = $context->facts->one('cost.trace_display');
        $display = $context->facts->one('model.fixed_cost_display');
        if ($trace?->number() !== null && $display?->number() !== null) {
            $checks[] = Checks::result('display-total', Checks::money($trace->number(), 0) === Checks::money($display->number(), 0),
                'Displayed fixed-cost trace and model totals agree at whole-dollar precision.', [$trace, $display], precision: '1');
        }
        $rates = $context->facts->all('cost.rate');
        if ($rates === []) {
            return [...$checks, Subcheck::missing('exact-schedule', 'unrounded fixed-cost rates, quantities, frequencies, monthly schedules and cash dates')];
        }
        $monthly = [];
        foreach ($rates as $i => $rate) {
            $qty = $context->facts->one('cost.quantity', $rate->entity, $rate->period);
            $cadence = $context->facts->one('cost.frequency', $rate->entity, $rate->period);
            $expense = $context->facts->one('cost.expense', $rate->entity, $rate->period);
            $factor = match ($cadence?->value) {
                'weekly' => '52', 'fortnightly' => '26', 'monthly' => '12', 'quarterly' => '4', 'annual' => '1', default => null,
            };
            if ($rate->number() === null || $qty?->number() === null || $expense?->number() === null || $factor === null) {
                $checks[] = Subcheck::missing('expense:'.$i, 'rate, parallel quantity, frequency and expense schedule for '.$rate->entity);

                continue;
            }
            $expected = $rate->number()->multipliedBy($qty->number())->multipliedBy($factor)->dividedBy(12, 8, RoundingMode::HalfUp);
            $checks[] = Checks::result('expense:'.$i, Checks::close($expected, $expense->number()),
                $rate->entity.' converts to '.$expected.' per month; recorded expense is '.$expense->value.'.', [$rate, $qty, $cadence, $expense]);
            $monthly[$rate->period][] = $expense;
            $payments = array_values(array_filter($context->facts->all('cost.payment'), fn (Fact $f): bool => $f->entity === $rate->entity && $f->period === $rate->period));
            $bridge = $context->facts->one('cost.accrual_movement', $rate->entity, $rate->period);
            $sum = Checks::sum($payments);
            if ($payments === [] || $sum === null || $bridge?->number() === null || array_any($payments, fn (Fact $f): bool => ! FactValidation::date($f->asOf) || $f->sources === [])) {
                $checks[] = Subcheck::missing('payments:'.$i, 'dated payments and the expense-to-cash accrual/prepayment bridge for '.$rate->entity);
            } else {
                $checks[] = Checks::result('payments:'.$i, Checks::close($sum->plus($bridge->number()), $expense->number()),
                    'Cash payments plus the recorded accrual/prepayment movement reconcile to expense.', [...$payments, $bridge, $expense]);
            }
        }
        foreach ($monthly as $period => $expenses) {
            $model = $context->facts->one('model.fixed_cost', period: $period);
            $sum = Checks::sum($expenses);
            $checks[] = $model?->number() === null || $sum === null
                ? Subcheck::missing('total:'.$period, 'the monthly fixed-cost model total')
                : Checks::result('total:'.$period, Checks::close($sum, $model->number()), 'Fixed-cost rows reconcile to the model total for '.$period.'.', [...$expenses, $model]);
        }
        $coverage = $context->facts->one('cost.schedule_complete');
        $modelRows = $context->facts->all('model.fixed_cost');
        $horizon = FactValidation::horizon($context->facts->one('model.horizon'));
        if ($coverage?->value !== true || ! $coverage->confirmed || ! FactValidation::monthlyCoverage($modelRows, $horizon) || count($monthly) !== $horizon) {
            $checks[] = Subcheck::missing('schedule-coverage', 'the complete required-horizon expense, annual-total and payment schedules');
        }
        if ($horizon !== null) {
            foreach (range(1, (int) ceil($horizon / 12)) as $year) {
                $annual = $context->facts->one('cost.annual_total', period: 'year:'.$year);
                $rows = array_values(array_filter($modelRows, fn (Fact $f): bool => preg_match('/^month:(\d+)$/D', $f->period, $m) === 1 && (int) ceil((int) $m[1] / 12) === $year));
                $sum = Checks::sum($rows);
                $checks[] = $annual?->number() === null || $sum === null || count($rows) !== min(12, $horizon - ($year - 1) * 12)
                    ? Subcheck::missing('annual:'.$year, 'the complete annual fixed-cost total and monthly rows')
                    : Checks::result('annual:'.$year, Checks::close($sum, $annual->number()), 'Annual fixed costs equal the monthly expense schedule.', [...$rows, $annual]);
            }
        }

        return $checks;
    }
}
