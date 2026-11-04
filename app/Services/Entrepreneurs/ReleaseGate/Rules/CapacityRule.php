<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;

final class CapacityRule implements Rule
{
    public function id(): string
    {
        return 'PM-04';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $revenues = $context->facts->all('stream.revenue');
        if ($revenues === []) {
            return [Subcheck::missing('allocation', 'per-stream revenue, units, base membership and capacity schedules')];
        }
        $checks = [];
        foreach ($revenues as $i => $revenue) {
            $facts = [];
            foreach (['stream.units', 'stream.price', 'stream.capacity', 'stream.membership'] as $key) {
                $facts[$key] = $context->facts->one($key, $revenue->entity, $revenue->period, $revenue->scenario);
            }
            $units = $facts['stream.units'];
            $price = $facts['stream.price'];
            $capacity = $facts['stream.capacity'];
            if ($revenue->number() === null || $units?->number() === null || $price?->number() === null || $capacity?->number() === null
                || ! is_bool($facts['stream.membership']?->value) || $units->unit === null || $units->unit !== $capacity->unit
                || $revenue->unit === null || $price->unit !== $revenue->unit.'/'.$units->unit) {
                $checks[] = Subcheck::missing('stream:'.$i, 'unit-defined price, volume, capacity and membership for '.$revenue->entity.' '.$revenue->period);

                continue;
            }
            $checks[] = Checks::result('capacity:'.$i, ! $units->number()->isNegative() && ! $capacity->number()->isNegative() && $units->number()->isLessThanOrEqualTo($capacity->number()),
                $revenue->entity.' uses '.$units->value.' '.$units->unit.' against capacity '.$capacity->value.' in '.$revenue->period.'.', [$units, $capacity]);
            $checks[] = Checks::result('revenue:'.$i, Checks::close($price->number()->multipliedBy($units->number()), $revenue->number()),
                $revenue->entity.' revenue must equal its own price multiplied by delivery units.', [$price, $units, $revenue]);
            $checks[] = Checks::result('membership:'.$i, $facts['stream.membership']->value || $revenue->number()->isZero(),
                'A stream excluded from this scenario contributes no forecast revenue.', [$facts['stream.membership'], $revenue]);
        }

        return $checks;
    }
}
