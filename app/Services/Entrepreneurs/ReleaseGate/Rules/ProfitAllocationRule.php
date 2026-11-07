<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Severity;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;

final class ProfitAllocationRule implements Rule
{
    private const POLICIES = ['giving', 'reinvestment', 'owner_distribution'];

    private const STATES = ['included', 'excluded', 'conditional'];

    public function id(): string
    {
        return 'PM-08';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $checks = [];
        foreach (self::POLICIES as $name) {
            $label = str_replace('_', ' ', $name);
            $policy = $context->facts->one('allocation.policy', $name);
            if (! is_string($policy?->value) || ! in_array($policy->value, self::STATES, true) || ! $policy->confirmed) {
                $checks[] = Subcheck::missing('policy:'.$name, 'an explicit included, excluded or conditional statement for '.$label, Severity::Review);

                continue;
            }
            if ($policy->value !== 'included') {
                // Excluding a policy is a legitimate answer, but only when the
                // reader can see it. An unsourced exclusion is indistinguishable
                // from an omission.
                $checks[] = Checks::result('policy:'.$name, $policy->sources !== [],
                    'The '.$label.' policy is recorded as '.$policy->value.' where the reader can see it.', [$policy], Severity::Review);

                continue;
            }
            $amount = $context->facts->one('allocation.amount', $name);
            $trigger = $context->facts->one('allocation.trigger', $name);
            $schedule = $context->facts->one('allocation.schedule', $name);
            $duplicate = $context->facts->one('allocation.duplicate_line', $name);
            if ($amount?->number() === null || ! is_string($trigger?->value) || trim($trigger->value) === ''
                || $schedule?->value !== 'complete' || ! is_bool($duplicate?->value)) {
                $checks[] = Subcheck::missing('terms:'.$name, 'the amount, trigger, cash schedule and cost-line overlap for '.$label, Severity::Review);

                continue;
            }
            $checks[] = Checks::result('terms:'.$name, ! $amount->number()->isNegative(),
                'The '.$label.' policy allocates '.$amount->value.' on the trigger "'.$trigger->value.'" with a cash schedule.',
                [$amount, $trigger, $schedule], Severity::Review);
            // Reinvestment already carried in a cost or capex line is spent once
            // in the model and a second time in the allocation narrative.
            $checks[] = Checks::result('double-count:'.$name, $duplicate->value === false,
                'The '.$label.' allocation is not already represented in a cost or capex line.', [$duplicate, $amount], Severity::Review);
        }

        return $checks;
    }
}
