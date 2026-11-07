<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Severity;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;

final class StressDimensionRule implements Rule
{
    /** Each stress dimension and the unit its shock is necessarily measured in. */
    private const DIMENSIONS = [
        'revenue_volume' => 'percent',
        'cost_rate' => 'percent',
        'slow_start_volume' => 'percent',
        'collection_delay' => 'days',
    ];

    public function id(): string
    {
        return 'SS-02';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $dimensions = $context->facts->all('scenario.dimension');
        if ($dimensions === []) {
            return [Subcheck::missing('dimensions', 'the tested input dimension and interval for each scenario', Severity::Review)];
        }
        $checks = [];
        foreach ($dimensions as $i => $dimension) {
            $name = $dimension->scenario;
            $interval = $context->facts->one('scenario.interval', scenario: $name);
            $requested = $context->facts->one('scenario.requested_shock', scenario: $name);
            $effective = $context->facts->one('scenario.effective_shock', scenario: $name);
            if (! is_string($dimension->value) || ! array_key_exists($dimension->value, self::DIMENSIONS) || ! $dimension->confirmed
                || ! is_string($interval?->value) || trim($interval->value) === ''
                || $requested?->number() === null || $effective?->number() === null) {
                $checks[] = Subcheck::missing('dimension:'.$i, 'a named dimension, its interval and both the requested and effective shocks for '.$name, Severity::Review);

                continue;
            }
            // The unit is what prevents a percentage revenue test being reported
            // against a collections-delay scenario, whose shock is a number of
            // days and cannot be read as a proportion of anything.
            $unit = self::DIMENSIONS[$dimension->value];
            $checks[] = Checks::result('unit:'.$i, $requested->unit === $unit && $effective->unit === $unit,
                $name.' tests '.$dimension->value.' over '.$interval->value.', which is measured in '.$unit.'.',
                [$dimension, $interval, $requested, $effective], Severity::Review);
            $checks[] = Checks::result('ineffective:'.$i, ! $effective->number()->isZero(),
                $name.' applies an effective shock of '.$effective->value.' after capacity caps and actuals protection.',
                [$requested, $effective], Severity::Review);
            if (! $effective->number()->abs()->isLessThan($requested->number()->abs())) {
                continue;
            }
            // A stress the model could not apply in full is not a failure, but
            // reporting it at its requested size would overstate the test.
            $reason = $context->facts->one('scenario.shock_reduction_reason', scenario: $name);
            $checks[] = Checks::result('undersized:'.$i, is_string($reason?->value) && trim($reason->value) !== '' && $reason->confirmed,
                $name.' requested '.$requested->value.' and applied '.$effective->value.'; the reduction names the cap or protection that caused it.',
                [$requested, $effective, ...($reason === null ? [] : [$reason])], Severity::Review);
        }

        return $checks;
    }
}
