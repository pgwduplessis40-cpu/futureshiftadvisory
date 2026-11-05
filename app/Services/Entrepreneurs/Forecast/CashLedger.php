<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\ReleaseGate\CanonicalJson;
use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;

/**
 * @phpstan-type Checkpoint array{date:string,movement_id:?string,balance:?string}
 * @phpstan-type CashMonth array{period:string,opening_cash:?string,receipts:?string,payments:?string,closing_cash:?string,by_category:array<string,?string>}
 * @phpstan-type LedgerResult array{engine_version:string,scope:string,currency:string,bank_reconciled:bool,status:string,input_hash:string,opening:CashOpening,months:list<CashMonth>,checkpoints:list<Checkpoint>,lowest_cash:?string,lowest_cash_date:?string,deferred:list<CashMovement>,undated:list<CashMovement>,movements:list<CashMovement>,missing:list<string>}
 */
final class CashLedger implements ProvidesMethodology
{
    public const VERSION = 'phase-2b.1';

    public static function methodologyIds(): array
    {
        return ['entrepreneur.dated_cash'];
    }

    /** @param list<CashMovement> $movements
     * @return LedgerResult
     */
    public function calculate(ForecastCalendar $calendar, CashOpening $opening, array $movements): array
    {
        $start = $calendar->startMonth.'-01';
        $end = ForecastDates::end($calendar);
        $missing = [];
        if ($opening->amount === null) {
            $missing[] = 'opening.amount';
        }
        if (trim($opening->source ?? '') === '') {
            $missing[] = 'opening.source';
        }
        if ($opening->verified !== true) {
            $missing[] = 'opening.verification';
        }
        $ids = $positions = [];
        $undated = $deferred = [];
        foreach ($movements as $movement) {
            if (isset($ids[$movement->id])) {
                throw new \InvalidArgumentException('Cash movement identities must be unique; never count a source movement twice.');
            }
            $ids[$movement->id] = true;
            if ($movement->date !== null) {
                $position = $movement->date.':'.$movement->sequence;
                if (isset($positions[$position]) || $movement->date < $start) {
                    throw new \InvalidArgumentException('Provide unique same-day ordering and carry historical cash in the opening balance.');
                }
                $positions[$position] = true;
            }
            foreach (['date' => $movement->date, 'amount' => $movement->amount] as $field => $value) {
                if ($value === null) {
                    $missing[] = $movement->id.'.'.$field;
                }
            }
            if ($movement->category === 'funding' && $movement->verified !== true) {
                $missing[] = $movement->id.'.verification';
            }
        }
        usort($movements, fn (CashMovement $a, CashMovement $b): int => [$a->date ?? '', $a->sequence, $a->id] <=> [$b->date ?? '', $b->sequence, $b->id]);
        foreach ($movements as $movement) {
            if ($movement->date === null) {
                $undated[] = $movement;
            } elseif ($movement->date > $end) {
                $deferred[] = $movement;
            }
        }
        // An undated movement can affect any month. A dated unknown amount
        // invalidates balances from that movement onward, not earlier cash.
        $balance = $opening->amount !== null && $undated === [] ? BigDecimal::of(DriverNumbers::money(BigDecimal::of($opening->amount))) : null;
        $minimum = $balance;
        $minimumDate = $minimum === null ? null : $start;
        $checkpoints = [['date' => $start, 'movement_id' => null, 'balance' => $balance === null ? null : DriverNumbers::money($balance)]];
        $months = [];
        foreach ($calendar->periods as $period) {
            $monthOpening = $balance;
            $receipts = $payments = BigDecimal::zero();
            $complete = $undated === [];
            $categories = array_fill_keys(array_map(fn (CashMovement $m): string => $m->category, $undated), null);
            foreach ($movements as $movement) {
                if ($movement->date === null || substr($movement->date, 0, 7) !== $period) {
                    continue;
                }
                $amount = DriverNumbers::read($movement->amount);
                if ($amount === null) {
                    $categories[$movement->category] = null;
                    $balance = $minimum = null;
                    $minimumDate = null;
                    $complete = false;
                } else {
                    $amount = BigDecimal::of(DriverNumbers::money($amount));
                    $signed = $movement->direction === 'in' ? $amount : $amount->negated();
                    $balance = $balance?->plus($signed);
                    if (! array_key_exists($movement->category, $categories) || $categories[$movement->category] !== null) {
                        $categories[$movement->category] = DriverNumbers::money(BigDecimal::of($categories[$movement->category] ?? '0')->plus($signed));
                    }
                    if ($movement->direction === 'in') {
                        $receipts = $receipts->plus($amount);
                    } else {
                        $payments = $payments->plus($amount);
                    }
                    if ($minimum !== null && $balance !== null && $balance->isLessThan($minimum)) {
                        $minimum = $balance;
                        $minimumDate = $movement->date;
                    }
                }
                $checkpoints[] = ['date' => $movement->date, 'movement_id' => $movement->id, 'balance' => $balance === null ? null : DriverNumbers::money($balance)];
            }
            $months[] = ['period' => $period, 'opening_cash' => $monthOpening === null ? null : DriverNumbers::money($monthOpening),
                'receipts' => $complete ? DriverNumbers::money($receipts) : null, 'payments' => $complete ? DriverNumbers::money($payments) : null,
                'closing_cash' => $balance === null ? null : DriverNumbers::money($balance), 'by_category' => $categories];
        }

        sort($missing);

        return ['engine_version' => self::VERSION, 'scope' => 'dated_cash_only', 'currency' => 'NZD', 'bank_reconciled' => false,
            'status' => $missing === [] ? 'pass' : 'cannot_verify', 'input_hash' => CanonicalJson::hash([$calendar, $opening, $movements]),
            'opening' => $opening, 'months' => $months, 'checkpoints' => $checkpoints, 'lowest_cash' => $minimum === null ? null : DriverNumbers::money($minimum),
            'lowest_cash_date' => $minimumDate, 'deferred' => $deferred, 'undated' => $undated, 'movements' => $movements, 'missing' => $missing];
    }
}
