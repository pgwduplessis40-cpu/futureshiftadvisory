<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Severity;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;

final class MarginRule implements Rule
{
    public function id(): string
    {
        return 'PM-07';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $minimums = $context->facts->all('plan.margin_min');
        $models = $context->facts->all('model.npbt_margin');
        if ($minimums === [] || $models === []) {
            return [Subcheck::missing('target', 'a named-period NPBT target and calculated margin', Severity::Review)];
        }
        $checks = [];
        foreach ($minimums as $i => $minimum) {
            $maximum = $context->facts->one('plan.margin_max', $minimum->entity, $minimum->period, $minimum->scenario);
            $model = $context->facts->one('model.npbt_margin', $minimum->entity, $minimum->period, $minimum->scenario);
            $explanation = $context->facts->one('plan.margin_explanation', $minimum->entity, $minimum->period, $minimum->scenario);
            if ($minimum->number() === null || $maximum?->number() === null) {
                $checks[] = Subcheck::missing('target:'.$i, 'the margin target range', Severity::Review);

                continue;
            }
            $explained = is_string($explanation?->value) && mb_strlen(trim($explanation->value)) >= 15 && $explanation->confirmed;
            $aligned = $model?->number() !== null && $model->number()->isGreaterThanOrEqualTo($minimum->number())
                && $model->number()->isLessThanOrEqualTo($maximum->number());
            $checks[] = Checks::result('variance:'.$i, $aligned || $explained,
                'Explain the '.$minimum->value.'-'.$maximum->value.'% '.$minimum->period.' target against the model margin; retain the calculated result.',
                [$minimum, $maximum, ...($model === null ? $models : [$model])], Severity::Review, '0.1%');
        }

        return $checks;
    }
}
