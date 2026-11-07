<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\CheckStatus;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Severity;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;

final class MonthThirteenBridgeRule implements Rule
{
    private const COMPONENTS = ['bridge.growth', 'bridge.seasonality', 'bridge.capacity_change', 'bridge.start_stop'];

    public function id(): string
    {
        return 'MI-08';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $required = $context->source->requiredHorizon;
        foreach ($context->facts->all('scope.required_horizon') as $fact) {
            $horizon = $fact->number();
            if ($horizon !== null && $horizon->isGreaterThan($required)) {
                $required = $horizon->toInt();
            }
        }
        $claimed = array_values(array_filter($context->facts->all('stream.revenue'), fn (Fact $f): bool => $f->period === 'month:13'));
        if ($required < 13 && $claimed === []) {
            // Not applicable is a finding about scope, so it states the scope it
            // was proved from rather than the absence of month 13 data.
            return [new Subcheck('scope', CheckStatus::NotApplicable, Severity::Review,
                'The required scope ends at month '.$required.' and no month 13 revenue is claimed.',
                scopeReason: 'Required horizon of '.$required.' months with no month 13 claim or requirement.')];
        }
        $twelfths = array_values(array_filter($context->facts->all('stream.revenue'), fn (Fact $f): bool => $f->period === 'month:12'));
        if ($twelfths === []) {
            return [Subcheck::missing('month-12', 'month 12 revenue by stream to bridge from', Severity::Review)];
        }
        $checks = [];
        foreach ($twelfths as $i => $twelve) {
            $thirteen = $context->facts->one('stream.revenue', $twelve->entity, 'month:13', $twelve->scenario);
            if ($twelve->number() === null || $thirteen?->number() === null) {
                $checks[] = Subcheck::missing('month-13:'.$i, 'month 13 revenue for '.$twelve->entity, Severity::Review);

                continue;
            }
            $expected = $twelve->number();
            $parts = [];
            foreach (self::COMPONENTS as $key) {
                $part = $context->facts->one($key, $twelve->entity, 'month:13', $twelve->scenario);
                $number = $part?->number();
                if ($part === null || $number === null) {
                    $expected = null;
                    break;
                }
                $expected = $expected->plus($number);
                $parts[] = $part;
            }
            if ($expected === null) {
                $checks[] = Subcheck::missing('bridge:'.$i, 'growth, seasonality, capacity and starts/stops explaining '.$twelve->entity.' in month 13', Severity::Review);

                continue;
            }
            $checks[] = Checks::result('bridge:'.$i, Checks::close($expected, $thirteen->number()),
                $twelve->entity.' moves from '.$twelve->value.' in month 12 to '.$thirteen->value.' in month 13 through its stated components.',
                [$twelve, ...$parts, $thirteen], Severity::Review);
        }

        return $checks;
    }
}
