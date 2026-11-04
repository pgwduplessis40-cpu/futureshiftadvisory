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

final class ScenarioRule implements Rule
{
    public function id(): string
    {
        return 'SS-01';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $scenarios = $context->facts->all('scenario.lowest_cash');
        if ($scenarios === []) {
            return [Subcheck::missing('scenarios', 'scenario inputs, ledgers and reported extrema')];
        }
        $checks = [];
        $base = $context->facts->one('scenario.baseline');
        foreach ($scenarios as $i => $lowest) {
            $checks = [...$checks, ...(new ScenarioInputs)->compare($context, $lowest->scenario)];
            foreach (['tax.policy', 'tax.rate', 'tax.opening_losses', 'tax.payment_policy'] as $key) {
                $baseTax = $context->facts->one($key);
                $scenarioTax = $context->facts->one($key, scenario: $lowest->scenario);
                $checks[] = $baseTax === null || $scenarioTax === null || ! $baseTax->confirmed || ! $scenarioTax->confirmed
                    ? Subcheck::missing('shared:'.$lowest->scenario.':'.$key, 'the confirmed shared '.$key.' policy and starting position')
                    : Checks::result('shared:'.$lowest->scenario.':'.$key, $baseTax->value === $scenarioTax->value,
                        'Scenarios retain the same tax and payment policy unless an explicit policy scenario is supported.', [$baseTax, $scenarioTax]);
            }
            $identity = $context->facts->one('scenario.baseline', scenario: $lowest->scenario);
            $shocks = $context->facts->one('scenario.shocks', scenario: $lowest->scenario);
            if (! is_string($base?->value) || $identity?->value === null || $shocks?->value === null || ! $identity->confirmed || ! $shocks->confirmed) {
                $checks[] = Subcheck::missing('inputs:'.$i, 'the shared baseline, dated opening balances and declared shocks for '.$lowest->scenario);
            } else {
                $checks[] = Checks::result('baseline:'.$i, $identity->value === $base->value,
                    'Scenario inputs share the same baseline apart from declared shocks and documented dependent responses.', [$base, $identity, $shocks]);
            }
            foreach ((new CashLedgerRule)->scenario($context, $lowest->scenario) as $check) {
                $checks[] = new Subcheck($lowest->scenario.':'.$check->id, $check->status, $check->severity,
                    $check->message, $check->compared, $check->missingInputs, $check->scopeReason, $check->precision);
            }
            foreach ((new TaxRule)->scenario($context, $configuration, $lowest->scenario) as $check) {
                $checks[] = new Subcheck($lowest->scenario.':tax:'.$check->id, $check->status, $check->severity,
                    $check->message, $check->compared, $check->missingInputs, $check->scopeReason, $check->precision);
            }
            $cash = $context->facts->all('cash.closing', $lowest->scenario);
            $numbers = array_map(fn (Fact $f): ?BigDecimal => $f->number(), $cash);
            if (! FactValidation::monthlyCoverage($cash, FactValidation::horizon($context->facts->one('model.horizon'))) || in_array(null, $numbers, true) || $lowest->number() === null) {
                $checks[] = Subcheck::missing('extremum:'.$i, 'all scenario cash balances and its reported minimum');
            } else {
                usort($numbers, fn (BigDecimal $a, BigDecimal $b): int => $a->compareTo($b));
                $checks[] = Checks::result('extremum:'.$i, Checks::close($numbers[0], $lowest->number()),
                    'Reported lowest cash reconciles to that scenario cash curve; no cross-scenario ordering is required.', [...$cash, $lowest]);
            }
            $profits = $context->facts->all('scenario.profit', $lowest->scenario);
            if (! FactValidation::monthlyCoverage($profits, FactValidation::horizon($context->facts->one('model.horizon')))) {
                $checks[] = Subcheck::missing('profit:'.$i, 'scenario revenue, costs, depreciation, interest and profit reconciliation');
            }
            foreach ($profits as $j => $profit) {
                $parts = [];
                foreach (['revenue', 'variable_costs', 'fixed_costs', 'depreciation', 'interest'] as $key) {
                    $parts[$key] = $context->facts->one('scenario.'.$key, period: $profit->period, scenario: $profit->scenario);
                }
                if ($profit->number() === null || array_any($parts, fn (?Fact $f): bool => $f?->number() === null)) {
                    $checks[] = Subcheck::missing('profit:'.$i.':'.$j, 'all scenario profit components for '.$profit->period);

                    continue;
                }
                $expected = $parts['revenue']->number()->minus($parts['variable_costs']->number())->minus($parts['fixed_costs']->number())
                    ->minus($parts['depreciation']->number())->minus($parts['interest']->number());
                $checks[] = Checks::result('profit:'.$i.':'.$j, Checks::close($expected, $profit->number()),
                    'Scenario NPBT reconciles to revenue less costs, depreciation and interest.', [...array_values($parts), $profit]);
            }
        }

        return $checks;
    }
}
