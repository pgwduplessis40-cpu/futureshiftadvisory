<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\FactValidation;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;

final class HorizonRule implements Rule
{
    public function id(): string
    {
        return 'MI-06';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $model = $context->facts->one('model.horizon');
        $requirements = [...$context->facts->all('scope.required_horizon'), ...$context->facts->all('scope.prior_required_horizon')];
        $required = $context->source->requiredHorizon;
        foreach ($requirements as $claim) {
            if (FactValidation::horizon($claim) === null) {
                return [Subcheck::missing('scope', 'the period required by each financial claim')];
            }
            $required = max($required, $claim->number()->toInt());
        }
        if (FactValidation::horizon($model) === null || $required < 1) {
            return [Subcheck::missing('horizon', 'a non-empty model horizon and the retained required claim horizon')];
        }

        return [Checks::result('horizon', $model->number()->isGreaterThanOrEqualTo($required),
            'The model covers '.$model->value.' months; current claims and unresolved prior scope require '.$required.' months.', [$model, ...$requirements], precision: 'months')];
    }
}
