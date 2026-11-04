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

final class FundingSourcesRule implements Rule
{
    public function id(): string
    {
        return 'MI-02';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        $opening = $context->facts->one('model.opening_cash');
        $available = $context->facts->one('model.available_funds');
        $declared = $context->facts->one('model.funding_total');
        $sources = $context->facts->all('funding.amount', 'base');
        $checks = [];
        $total = $sources !== [] ? Checks::sum($sources) : $declared?->number();
        if ($sources !== [] && $total !== null) {
            $checks[] = $declared?->number() === null ? Subcheck::missing('declared-total', 'the declared total of named funding')
                : Checks::result('declared-total', Checks::close($total, $declared->number()), 'The funding total equals the named sources.', [$declared, ...$sources]);
        }
        if ($opening?->number() === null || $available?->number() === null || $total === null) {
            $checks[] = Subcheck::missing('available-funds', 'opening cash, named funding and available funds');
        } else {
            $checks[] = Checks::result('available-funds', Checks::close($opening->number()->plus($total), $available->number()),
                'Opening cash '.$opening->value.' plus funding '.$total.' must equal available funds '.$available->value.'.',
                [$opening, $available, ...$sources, ...($declared === null ? [] : [$declared])]);
        }
        if ($opening === null || ! FactValidation::date($opening->asOf) || $opening->basis === null || $opening->unit === null || ! $opening->confirmed || $opening->sources === []) {
            $checks[] = Subcheck::missing('opening-provenance', 'dated opening cash by account, currency and restriction with source status');
        }
        $inventory = $context->facts->one('funding.inventory_complete');
        if ($inventory?->value !== true || ! $inventory->confirmed || ($sources === [] && ($declared?->number() === null || ! $declared->number()->isZero()))) {
            $checks[] = Subcheck::missing('source-inventory', 'all named funding source identities, amounts, commitments, restrictions and draw schedules');
        }
        $identities = [];
        foreach ($sources as $i => $amount) {
            $metadata = [];
            foreach (['identity', 'type', 'commitment', 'availability', 'restriction', 'draw_date'] as $key) {
                $metadata[$key] = $context->facts->one('funding.'.$key, $amount->entity, $amount->period, $amount->scenario);
            }
            if ($amount->number() === null || $amount->sources === [] || $amount->unit !== $opening?->unit
                || array_any($metadata, fn (?Fact $f): bool => $f === null || ! is_string($f->value) || trim($f->value) === '')) {
                $checks[] = Subcheck::missing('source:'.$i, 'complete provenance, currency and schedule for '.$amount->entity);

                continue;
            }
            $identity = (string) $metadata['identity']->value;
            $checks[] = Checks::result('identity:'.$i, ! in_array($identity, $identities, true) && $identity !== $opening?->basis,
                'Funding '.$identity.' must not duplicate opening cash or another source.', [$amount, ...array_values($metadata)]);
            $identities[] = $identity;
            $availability = (string) $metadata['availability']->value;
            $draw = (string) $metadata['draw_date']->value;
            if (! FactValidation::date($availability) || ! FactValidation::date($draw)) {
                $checks[] = Subcheck::missing('date:'.$i, 'valid availability and draw dates for '.$identity);
            } else {
                $checks[] = Checks::result('date:'.$i, $draw >= $availability, 'Funding cannot be drawn before its availability date.', [$metadata['availability'], $metadata['draw_date']]);
            }
            if (! $amount->confirmed || $metadata['commitment']->value !== 'committed') {
                $checks[] = Subcheck::missing('commitment:'.$i, 'a confirmed commitment for funding '.$identity);
            }
            $checks[] = Checks::result('restriction:'.$i, $metadata['restriction']->value === 'unrestricted' && ! $amount->number()->isNegative(),
                'Available funding must be non-negative and unrestricted for the forecast use.', [$amount, $metadata['restriction']]);
            $draws = array_values(array_filter($context->facts->all('ledger.funding_identity', 'base'), fn (Fact $f): bool => $f->value === $identity));
            $drawAmounts = [];
            if ($draws === []) {
                $checks[] = Subcheck::missing('draws:'.$i, 'dated financing ledger entries for '.$identity);
            }
            foreach ($draws as $j => $line) {
                $cash = $context->facts->one('ledger.amount', $line->entity, $line->period);
                $date = $context->facts->one('ledger.date', $line->entity, $line->period);
                $category = $context->facts->one('ledger.category', $line->entity, $line->period);
                if ($cash?->number() === null || ! FactValidation::date($date?->value)) {
                    $checks[] = Subcheck::missing('draw:'.$i.':'.$j, 'the financing amount and valid cash date');

                    continue;
                }
                $drawAmounts[] = $cash;
                $checks[] = Checks::result('draw:'.$i.':'.$j, $date->value >= $availability && $date->value >= $draw && $category?->value === 'financing_inflows',
                    'Funding enters cash only on or after its availability and draw dates.', [$cash, $date, $metadata['draw_date'], $metadata['availability']]);
            }
            if ($draws !== [] && count($draws) === count($drawAmounts)) {
                $checks[] = Checks::result('draw-total:'.$i, Checks::close(Checks::sum($drawAmounts), $amount->number()),
                    'Each committed source is counted once in the financing ledger.', [$amount, ...$drawAmounts]);
            }
        }
        foreach ($context->facts->all('ledger.category', 'base') as $i => $category) {
            if ($category->value !== 'financing_inflows') {
                continue;
            }
            $identity = $context->facts->one('ledger.funding_identity', $category->entity, $category->period);
            $checks[] = $identity === null || count($identities) !== count($sources) ? Subcheck::missing('ledger-source:'.$i, 'the named funding source for financing entry '.$category->entity)
                : Checks::result('ledger-source:'.$i, in_array($identity->value, $identities, true), 'Every financing inflow maps to one declared funding source.', [$category, $identity]);
        }

        return $checks;
    }
}
