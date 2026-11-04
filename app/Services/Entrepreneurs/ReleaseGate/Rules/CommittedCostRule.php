<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\CheckStatus;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Severity;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;

final class CommittedCostRule implements Rule
{
    public function id(): string
    {
        return 'PM-09';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $commitments = $context->facts->all('plan.commitment');
        if ($commitments === []) {
            return [Subcheck::missing('commitments', 'an inventory of plan commitments and cost mappings')];
        }
        $checks = [];
        foreach ($commitments as $i => $cost) {
            $scope = $context->facts->one('plan.commitment_in_scope', $cost->entity, $cost->period, $cost->scenario);
            $mapping = $context->facts->one('budget.cost_mapping', $cost->entity, $cost->period, $cost->scenario);
            if ($scope?->value === false && $scope->confirmed) {
                $checks[] = new Subcheck('cost:'.$i, CheckStatus::NotApplicable, Severity::Review,
                    'The current plan explicitly excludes or defers '.$cost->entity.'.', [$cost, $scope], scopeReason: 'Confirmed excluded commitment with updated plan scope.');

                continue;
            }
            if ($cost->number() === null || $scope?->value !== true || $cost->period === 'unknown') {
                $checks[] = Subcheck::missing('cost:'.$i, 'the amount, commitment scope and payment period for '.$cost->entity, Severity::Review);

                continue;
            }
            $severity = $cost->number()->isGreaterThanOrEqualTo($configuration->threshold('material_cost')) ? Severity::Block : Severity::Review;
            if ($mapping === null || $mapping->value === null) {
                $checks[] = Subcheck::missing('cost:'.$i, 'a budget/actuals mapping for '.$cost->entity.' at '.$cost->value, $severity);
            } elseif ($mapping->value === false || $mapping->value === '') {
                $checks[] = Checks::result('cost:'.$i, false,
                    'Map '.$cost->entity.' ('.$cost->value.' '.$cost->unit.') to an existing cost/actuals line and its payment period.', [$cost, $scope, $mapping], $severity);
            } else {
                $mapped = $context->facts->one('budget.mapped_cost', $cost->entity, $cost->period, $cost->scenario);
                $checks[] = $mapped?->number() === null || ! $scope->confirmed || $mapped->sources === []
                    ? Subcheck::missing('cost:'.$i, 'the sourced amount and payment period of the mapped cost', $severity)
                    : Checks::result('cost:'.$i, $mapped->unit === $cost->unit && Checks::close($mapped->number(), $cost->number()),
                        'The commitment maps to the same amount, currency and payment period without a duplicate cost.', [$cost, $scope, $mapping, $mapped], $severity);
            }
        }

        return $checks;
    }
}
