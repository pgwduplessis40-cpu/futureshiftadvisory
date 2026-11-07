<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\FactValidation;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;
use Brick\Math\BigDecimal;

final class SharedCapacityRule implements Rule
{
    public function id(): string
    {
        return 'PM-11';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $budgets = $context->facts->all('capacity.available_hours');
        if ($budgets === []) {
            return [Subcheck::missing('time-budget', 'one shared founder time budget for each forecast month')];
        }
        $checks = [];
        /** @var array<string,BigDecimal> $founderHours */
        $founderHours = [];
        foreach ($context->facts->all('stream.units') as $i => $units) {
            $membership = $context->facts->one('stream.membership', $units->entity, scenario: $units->scenario);
            if (! is_bool($membership?->value)) {
                $checks[] = Subcheck::missing('membership:'.$i, 'explicit membership for '.$units->entity.' before its hours are counted');

                continue;
            }
            if ($membership->value === false) {
                continue;
            }
            $delivery = $context->facts->one('stream.delivery_hours_per_unit', $units->entity, scenario: $units->scenario);
            $preparation = $context->facts->one('stream.preparation_hours_per_unit', $units->entity, scenario: $units->scenario);
            $provider = $context->facts->one('capacity.provider', $units->entity, scenario: $units->scenario);
            // Without a unit-to-time conversion the hours a stream consumes are
            // unknown. Treating them as zero would let an unconverted stream
            // fit inside any time budget.
            if ($units->number() === null || $delivery?->number() === null || $preparation?->number() === null
                || ! is_string($provider?->value) || trim($provider->value) === '') {
                $checks[] = Subcheck::missing('conversion:'.$i, 'delivery and preparation hours per unit and the named provider for '.$units->entity);

                continue;
            }
            $hours = $delivery->number()->plus($preparation->number())->multipliedBy($units->number());
            if ($provider->value === 'founder') {
                $key = $units->scenario.':'.$units->period;
                $founderHours[$key] = ($founderHours[$key] ?? BigDecimal::of(0))->plus($hours);

                continue;
            }
            $capacity = $context->facts->one('provider.capacity_hours', $provider->value, $units->period, $units->scenario);
            $start = $context->facts->one('provider.start', $provider->value, $units->period, $units->scenario);
            $cost = $context->facts->one('provider.cost', $provider->value, $units->period, $units->scenario);
            $noCost = $context->facts->one('provider.no_cost_basis', $provider->value, $units->period, $units->scenario);
            $priced = $cost?->number() !== null
                ? $cost
                : (is_string($noCost?->value) && trim($noCost->value) !== '' && $noCost->confirmed ? $noCost : null);
            if ($capacity?->number() === null || ! FactValidation::date($start?->value) || $priced === null) {
                $checks[] = Subcheck::missing('provider:'.$i, 'capacity, a start date and either a cost or a documented no-cost basis for '.$provider->value);

                continue;
            }
            $checks[] = Checks::result('provider:'.$i, $hours->isLessThanOrEqualTo($capacity->number()),
                $provider->value.' delivers '.$hours.' hours for '.$units->entity.' in '.$units->period.' against a capacity of '.$capacity->value.'.',
                [$units, $capacity, $priced]);
        }
        foreach ($budgets as $j => $budget) {
            $key = $budget->scenario.':'.$budget->period;
            $used = $founderHours[$key] ?? BigDecimal::of(0);
            $checks[] = $budget->number() === null
                ? Subcheck::missing('budget:'.$j, 'the available founder hours for '.$budget->period)
                : Checks::result('budget:'.$j, $used->isLessThanOrEqualTo($budget->number()),
                    'Founder delivery and preparation across included streams takes '.$used.' hours in '.$budget->period.' against '.$budget->value.' available.',
                    [$budget]);
        }

        return $checks;
    }
}
