<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;

final class ScenarioInputs
{
    /** @return list<Subcheck> */
    public function compare(DocumentContext $context, string $scenario): array
    {
        $base = $context->facts->all('scenario.input', 'base');
        $coverage = $context->facts->one('scenario.input_inventory', scenario: $scenario);
        if ($base === [] || $coverage?->value !== 'complete' || ! $coverage->confirmed) {
            return [Subcheck::missing('input-inventory:'.$scenario, 'the complete baseline and changed-input inventory for '.$scenario)];
        }
        $checks = [];
        foreach ($base as $i => $input) {
            $actual = $context->facts->one('scenario.input', $input->entity, $input->period, $scenario);
            $factor = $context->facts->one('scenario.shock_multiplier', $input->entity, $input->period, $scenario);
            $delta = $context->facts->one('scenario.shock_delta', $input->entity, $input->period, $scenario);
            if ($actual === null || $input->sources === [] || $actual->sources === [] || $actual->unit !== $input->unit) {
                $checks[] = Subcheck::missing('input:'.$scenario.':'.$i, 'comparable sourced values for '.$input->entity);

                continue;
            }
            if ($factor === null && $delta === null) {
                $checks[] = Checks::result('input:'.$scenario.':'.$i, $actual->value === $input->value,
                    'Undeclared inputs stay at the shared baseline.', [$input, $actual]);

                continue;
            }
            if ($input->number() === null || $actual->number() === null
                || ($factor !== null && ($factor->number() === null || ! $factor->confirmed))
                || ($delta !== null && ($delta->number() === null || ! $delta->confirmed))) {
                $checks[] = Subcheck::missing('shock:'.$scenario.':'.$i, 'the confirmed numerical shock for '.$input->entity);

                continue;
            }
            $expected = $input->number()->multipliedBy($factor?->number() ?? 1)->plus($delta?->number() ?? 0);
            $checks[] = Checks::result('shock:'.$scenario.':'.$i, Checks::close($expected, $actual->number()),
                'The scenario input equals the baseline with its declared shock applied.', [$input, $actual, ...($factor === null ? [] : [$factor]), ...($delta === null ? [] : [$delta])]);
        }
        $actual = $context->facts->all('scenario.input', $scenario);
        $checks[] = Checks::result('input-count:'.$scenario, count($actual) === count($base), 'The scenario uses the complete same input inventory.', $actual);

        return $checks;
    }
}
