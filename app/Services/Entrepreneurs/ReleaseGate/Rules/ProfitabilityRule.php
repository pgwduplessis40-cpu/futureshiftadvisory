<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;

final class ProfitabilityRule implements Rule
{
    public function id(): string
    {
        return 'PM-01';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $claims = $context->facts->all('plan.profitability');
        $checks = [Checks::currentClaims($claims)];
        foreach ($claims as $i => $claim) {
            $model = $context->facts->one('model.profitability', $claim->entity, $claim->period, $claim->scenario);
            if ($claim->number() === null || $model?->number() === null || $claim->basis === null
                || $model->basis !== $claim->basis || $model->unit !== $claim->unit) {
                $checks[] = Subcheck::missing('claim:'.$i, 'the profitability measure, period and matching model fact');

                continue;
            }
            $checks[] = Checks::result('claim:'.$i, $claim->number()->isEqualTo($model->number()),
                'The plan claims '.$claim->value.' '.$claim->unit.' for '.$claim->basis.'; the model reports '.$model->value.'.', [$claim, $model], precision: 'exact period');
        }

        return $checks;
    }
}
