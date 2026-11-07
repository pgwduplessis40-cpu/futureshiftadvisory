<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;

final class RevenueRampRule implements Rule
{
    public function id(): string
    {
        return 'PM-05';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $claims = $context->facts->all('plan.ramp');
        if ($claims === []) {
            return [Subcheck::missing('ramp', 'the current plan revenue schedule by stream and month')];
        }
        $checks = [];
        $planStart = $context->facts->one('plan.start_period');
        $modelStart = $context->facts->one('model.start_period');
        if (! is_string($planStart?->value) || ! is_string($modelStart?->value) || ! $planStart->confirmed || ! $modelStart->confirmed) {
            $checks[] = Subcheck::missing('start-period', 'the confirmed forecast start period on both the plan and the model');
        } else {
            // Two schedules read from different first months describe different
            // ramps even when every figure matches, so this is checked before
            // and separately from the month-by-month comparison below.
            $checks[] = Checks::result('start-period', $planStart->value === $modelStart->value,
                'The plan schedule and the model ramp begin in the same forecast period.', [$planStart, $modelStart]);
        }
        $tolerance = $configuration->threshold('cash_tolerance');
        foreach ($claims as $i => $claim) {
            $membership = $context->facts->one('stream.membership', $claim->entity, scenario: $claim->scenario);
            $model = $context->facts->one('model.ramp', $claim->entity, $claim->period, $claim->scenario);
            if ($claim->number() === null || $model?->number() === null || ! is_bool($membership?->value)
                || $claim->unit === null || $claim->unit !== $model->unit) {
                $checks[] = Subcheck::missing('ramp:'.$i, 'a plan and model ramp in the same unit with explicit membership for '.$claim->entity.' '.$claim->period);

                continue;
            }
            if ($membership->value === false) {
                // An excluded stream is not compared to the included ramp; it is
                // checked for contributing nothing to either side.
                $checks[] = Checks::result('excluded:'.$i, $claim->number()->isZero() && $model->number()->isZero(),
                    $claim->entity.' is excluded from this scenario and contributes no ramp revenue in '.$claim->period.'.', [$membership, $claim, $model]);

                continue;
            }
            $checks[] = Checks::result('ramp:'.$i, Checks::close($claim->number(), $model->number(), $tolerance),
                $claim->entity.' ramps to '.$claim->value.' in '.$claim->period.' on the plan against '.$model->value.' in the model.',
                [$claim, $model], precision: $tolerance);
        }

        return $checks;
    }
}
