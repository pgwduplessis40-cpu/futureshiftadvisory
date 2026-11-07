<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate\Rules;

use App\Services\Entrepreneurs\ReleaseGate\Checks;
use App\Services\Entrepreneurs\ReleaseGate\DocumentContext;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\FactValidation;
use App\Services\Entrepreneurs\ReleaseGate\Rule;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;
use Brick\Math\BigDecimal;

final class ScenarioFundingRule implements Rule
{
    public function id(): string
    {
        return 'SS-03';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $scenarios = $context->facts->all('scenario.lowest_cash');
        if ($scenarios === []) {
            return [Subcheck::missing('scenarios', 'every required scenario with its cash deficit and reserve shortfall')];
        }
        $basis = $context->facts->one('funding.basis');
        if (! is_string($basis?->value) || trim($basis->value) === '' || ! $basis->confirmed) {
            return [Subcheck::missing('basis', 'the confirmed basis every scenario deficit and reserve shortfall is calculated on')];
        }
        $checks = [];
        /** @var array<string,Fact> $deficits */
        $deficits = [];
        /** @var array<string,Fact> $reserves */
        $reserves = [];
        foreach ($scenarios as $i => $scenario) {
            $name = $scenario->scenario;
            $deficit = $context->facts->one('scenario.cash_deficit', scenario: $name);
            $reserve = $context->facts->one('scenario.reserve_gap', scenario: $name);
            // A deficit calculated on a different basis is a different number,
            // so an unmatched basis is missing evidence rather than a mismatch.
            if ($deficit?->number() === null || $reserve?->number() === null
                || $deficit->basis !== $basis->value || $reserve->basis !== $basis->value) {
                $checks[] = Subcheck::missing('metrics:'.$i, 'the cash deficit and reserve shortfall for '.$name.' on the '.$basis->value.' basis');

                continue;
            }
            $deficits[$name] = $deficit;
            $reserves[$name] = $reserve;
        }
        $checks[] = $this->worst($context, 'deficit', 'funding.worst_deficit_scenario', $deficits);
        $checks[] = $this->worst($context, 'reserve', 'funding.worst_reserve_scenario', $reserves);

        return [...$checks, ...$this->request($context, $basis, $deficits, $reserves)];
    }

    /** @param array<string,Fact> $values */
    private function worst(DocumentContext $context, string $metric, string $key, array $values): Subcheck
    {
        $named = $context->facts->one($key);
        if ($values === [] || ! is_string($named?->value) || ! isset($values[$named->value])) {
            return Subcheck::missing('worst:'.$metric, 'the separately identified worst '.$metric.' scenario across the required scenarios');
        }
        $highest = BigDecimal::of(0);
        foreach ($values as $value) {
            $number = $value->number();
            if ($number !== null && $number->isGreaterThan($highest)) {
                $highest = $number;
            }
        }
        $claimed = $values[$named->value]->number();

        // Each metric peaks in its own scenario; the worst cash deficit and the
        // worst reserve shortfall are not required to be the same one.
        return Checks::result('worst:'.$metric, $claimed !== null && $claimed->isGreaterThanOrEqualTo($highest),
            'The worst '.$metric.' is reported in '.$named->value.' at '.$values[$named->value]->value.'.',
            [$named, ...array_values($values)]);
    }

    /**
     * @param  array<string,Fact>  $deficits
     * @param  array<string,Fact>  $reserves
     * @return list<Subcheck>
     */
    private function request(DocumentContext $context, Fact $basis, array $deficits, array $reserves): array
    {
        $request = $context->facts->one('model.external_request');
        $amount = $request?->number();
        if ($request === null || $amount === null || ! $amount->isPositive()) {
            return $this->noRequest($context, $deficits, $reserves);
        }
        $metric = $context->facts->one('funding.request_metric');
        $scenario = $context->facts->one('funding.request_basis_scenario');
        $nonExternal = $context->facts->one('funding.non_external_total');
        if (! is_string($metric?->value) || ! in_array($metric->value, ['cash_deficit', 'reserve_gap'], true)
            || ! is_string($scenario?->value) || ! isset($deficits[$scenario->value])
            || $nonExternal?->number() === null || ! FactValidation::date($request->asOf)) {
            return [Subcheck::missing('request', 'the dated scenario and metric the external request addresses, and the named non-external sources applied first')];
        }
        $requirement = ($metric->value === 'cash_deficit' ? $deficits : $reserves)[$scenario->value];
        $number = $requirement->number();
        if ($number === null) {
            return [Subcheck::missing('request', 'the '.$metric->value.' for '.$scenario->value.' the request is sized against')];
        }
        // Non-external sources are subtracted once, here. A request already net
        // of them would be reduced twice and understate what is needed.
        $expected = $number->minus($nonExternal->number());

        return [Checks::result('request', Checks::close($amount, $expected->isNegative() ? BigDecimal::of(0) : $expected),
            'The '.$request->value.' request meets the '.$metric->value.' of '.$requirement->value.' in '.$scenario->value
                .' on the '.$basis->value.' basis, after '.$nonExternal->value.' from named non-external sources.',
            [$requirement, $nonExternal, $request])];
    }

    /**
     * @param  array<string,Fact>  $deficits
     * @param  array<string,Fact>  $reserves
     * @return list<Subcheck>
     */
    private function noRequest(DocumentContext $context, array $deficits, array $reserves): array
    {
        if ($deficits === []) {
            return [];
        }
        $uncovered = array_any($deficits, fn (Fact $f): bool => $f->number()?->isPositive() === true);
        $shortfall = array_any($reserves, fn (Fact $f): bool => $f->number()?->isPositive() === true);
        if ($uncovered || ! $shortfall) {
            return [];
        }
        // A reserve-target shortfall with no cash deficit is a decision to run
        // thinner than policy, not an automatic need to borrow. It is allowed
        // to stand, but not to go unsaid.
        $disclosure = $context->facts->one('funding.reserve_only_disclosure');

        return [Checks::result('disclosure', is_string($disclosure?->value) && trim($disclosure->value) !== '' && $disclosure->confirmed,
            'The reserve-target shortfall is disclosed with no external request, rather than converted into borrowing.',
            [...array_values($reserves), ...($disclosure === null ? [] : [$disclosure])])];
    }
}
