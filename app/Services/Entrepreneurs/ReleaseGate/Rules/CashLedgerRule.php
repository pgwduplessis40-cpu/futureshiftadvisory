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

final class CashLedgerRule implements Rule
{
    public function id(): string
    {
        return 'MI-01';
    }

    public function evaluate(DocumentContext $context, RuleConfiguration $configuration): array
    {
        return $this->scenario($context, 'base');
    }

    /** @return list<Subcheck> */
    public function scenario(DocumentContext $context, string $scenario): array
    {
        $horizon = FactValidation::horizon($context->facts->one('model.horizon'));
        if ($horizon === null) {
            return [Subcheck::missing('horizon', 'a non-empty forecast horizon')];
        }
        $checks = [];
        $previous = null;
        $movements = [];
        $closings = [];
        $firstStart = $context->facts->one('cash.period_start', period: 'month:1', scenario: $scenario);
        for ($month = 1; $month <= $horizon; $month++) {
            $period = 'month:'.$month;
            $start = $context->facts->one('cash.period_start', period: $period, scenario: $scenario);
            if (FactValidation::date($firstStart?->value) && FactValidation::date($start?->value)) {
                $expected = (new \DateTimeImmutable((string) $firstStart->value))->modify('+'.($month - 1).' months')->format('Y-m-d');
                $checks[] = Checks::result('calendar:'.$month, $start->value === $expected,
                    'Forecast months follow the first calendar month without gaps or duplicates.', [$firstStart, $start]);
            }
            $opening = $context->facts->one('cash.opening', period: $period, scenario: $scenario);
            $movement = $context->facts->one('cash.movement', period: $period, scenario: $scenario);
            $closing = $context->facts->one('cash.closing', period: $period, scenario: $scenario);
            if ($opening?->number() === null || $movement?->number() === null || $closing?->number() === null) {
                $checks[] = Subcheck::missing('balance:'.$month, $scenario.' '.$period.' opening, movement and closing cash');
                $previous = null;

                continue;
            }
            $checks[] = Checks::result('balance:'.$month, Checks::close($opening->number()->plus($movement->number()), $closing->number()),
                $scenario.' '.$period.': '.$opening->value.' + '.$movement->value.' must equal '.$closing->value.'.', [$opening, $movement, $closing]);
            if ($month === 1) {
                $modelOpening = $context->facts->one('model.opening_cash');
                $checks[] = $modelOpening?->number() === null ? Subcheck::missing('opening-basis', 'dated model opening cash')
                    : Checks::result('opening-basis', Checks::close($modelOpening->number(), $opening->number()), 'The cash curve starts from model opening cash.', [$modelOpening, $opening]);
            }
            if ($previous !== null) {
                $checks[] = Checks::result('continuity:'.$month, Checks::close($previous->number(), $opening->number()),
                    'Opening cash must equal the preceding closing balance.', [$previous, $opening]);
            }
            $previous = $closing;
            $year = (int) ceil($month / 12);
            $movements[$year][] = $movement;
            $closings[$year] = $closing;
            $checks = [...$checks, ...$this->ledger($context, $scenario, $period, $movement)];
        }
        foreach ($movements as $year => $rows) {
            $reported = $context->facts->one('cash.annual_movement', period: 'year:'.$year, scenario: $scenario);
            $closing = $context->facts->one('cash.annual_closing', period: 'year:'.$year, scenario: $scenario);
            $sum = Checks::sum($rows);
            if ($reported?->number() === null || $closing?->number() === null || $sum === null || count($rows) !== min(12, $horizon - (($year - 1) * 12))) {
                $checks[] = Subcheck::missing('annual:'.$year, $scenario.' year '.$year.' reported cash totals');

                continue;
            }
            $checks[] = Checks::result('annual:'.$year, Checks::close($sum, $reported->number()) && Checks::close($closings[$year]->number(), $closing->number()),
                'Annual net movement and closing cash reconcile to the monthly rows.', [...$rows, $reported, $closing]);
        }
        foreach ($context->facts->all('ledger.amount', $scenario) as $i => $entry) {
            $valid = preg_match('/^month:([1-9][0-9]*)$/D', $entry->period, $matches) === 1 && (int) $matches[1] <= $horizon;
            $checks[] = Checks::result('ledger-horizon:'.$i, $valid, 'Every supplied ledger entry belongs to the evaluated horizon.', [$entry]);
        }

        return $checks;
    }

    /** @return list<Subcheck> */
    private function ledger(DocumentContext $context, string $scenario, string $period, Fact $movement): array
    {
        $coverage = $context->facts->one('ledger.coverage', period: $period, scenario: $scenario);
        $start = $context->facts->one('cash.period_start', period: $period, scenario: $scenario);
        $entries = array_values(array_filter($context->facts->all('ledger.amount', $scenario), fn (Fact $f): bool => $f->period === $period));
        if ($coverage?->value !== 'complete' || ! $coverage->confirmed || $start === null || ! is_string($start->value)
            || ! FactValidation::date($start->value) || ! str_ends_with($start->value, '-01')) {
            return [Subcheck::missing('ledger:'.$period, 'the complete dated and classified '.$scenario.' '.$period.' cash ledger')];
        }
        $inflows = ['customer_receipts', 'other_operating_receipts', 'financing_inflows'];
        $outflows = ['operating_payments', 'cash_tax', 'gst', 'capex', 'principal', 'distributions'];
        $sum = BigDecimal::of(0);
        $ids = [];
        $checks = [];
        $complete = true;
        foreach ($entries as $i => $entry) {
            $category = $context->facts->one('ledger.category', $entry->entity, $period, $scenario);
            $date = $context->facts->one('ledger.date', $entry->entity, $period, $scenario);
            $source = $context->facts->one('ledger.source', $entry->entity, $period, $scenario);
            if ($entry->number() === null || ! in_array($category?->value, [...$inflows, ...$outflows], true)
                || ! is_string($date?->value) || ! is_string($source?->value) || trim($source->value) === '' || $entry->sources === [] || $entry->unit !== $movement->unit) {
                $checks[] = Subcheck::missing('entry:'.$period.':'.$i, 'amount, category, date and source for ledger entry '.$entry->entity);
                $complete = false;

                continue;
            }
            $dated = FactValidation::date($date->value) && substr($date->value, 0, 7) === substr($start->value, 0, 7);
            $checks[] = Checks::result('date:'.$period.':'.$i, $dated, 'Each cash movement belongs to its recorded month.', [$entry, $date, $start]);
            $sameSource = array_filter($context->facts->all('ledger.source', $scenario), fn (Fact $f): bool => $f->value === $source->value);
            $unique = ! in_array($entry->entity, $ids, true) && count($sameSource) === 1;
            $checks[] = Checks::result('identity:'.$period.':'.$i, $unique, 'Each ledger source line is counted once.', [$entry, $source]);
            $ids[] = $entry->entity;
            $checks[] = Checks::result('sign:'.$period.':'.$i, ! $entry->number()->isNegative(), 'Ledger amounts are non-negative; their classification determines direction.', [$entry]);
            $sum = in_array($category->value, $inflows, true) ? $sum->plus($entry->number()) : $sum->minus($entry->number());
        }
        if ($complete) {
            $checks[] = Checks::result('ledger:'.$period, Checks::close($sum, $movement->number()),
                'Classified receipts, payments, tax, GST, financing, capex, principal and distributions sum to '.$sum.'; reported movement is '.$movement->value.'.', [...$entries, $movement]);
        }

        return $checks;
    }
}
