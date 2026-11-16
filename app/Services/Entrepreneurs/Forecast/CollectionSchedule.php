<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * The cash forecast revenue turns into, and when.
 *
 * Receipts are scheduled from invoiced units, not delivered ones. The model
 * records the two separately because they differ - work delivered this month
 * can be billed next, and work billed in advance is not yet delivered - and
 * cash follows the bill. Revenue recognised in the profit decomposition is the
 * delivered figure; this is the billed one, and they are not required to
 * match in any given month.
 *
 * Billing falls at month end. A stream month states a month and no invoice
 * date, so a convention is needed and this is the conservative one: cash
 * arrives later than it would under any mid-month assumption, and nothing
 * here quietly improves a cash position by billing earlier than a business
 * does.
 *
 * Nothing is scheduled without complete terms. A reconstruction that has not
 * stated how it collects reports that, and its revenue reaches no month's
 * cash - which is the position every model was in before these terms existed.
 *
 * Each receipt holds its own sequence slot. Numbering by stream alone would
 * give every month of a stream one position, which is unique only while the
 * dates are: a collection-delay shock covering one month moves that month's
 * receipt onto the next month's date, and the ledger refuses two movements
 * claiming one position. The count consumed is reported rather than left for
 * the caller to recompute, so a reservation cannot diverge from what was
 * allocated.
 *
 * @phpstan-type CollectionResult array{movements:list<CashMovement>,invoiced:array<string,string>,missing:list<string>,sequences:int}
 */
final class CollectionSchedule implements ProvidesMethodology
{
    public const VERSION = 'phase-2e.1';

    public static function methodologyIds(): array
    {
        return ['entrepreneur.dated_cash'];
    }

    /**
     * @param  list<RevenueStream>  $streams
     * @return CollectionResult
     */
    public function calculate(ForecastCalendar $calendar, array $streams, ?CollectionTerms $terms, int $sequence = 0): array
    {
        if ($terms === null) {
            return ['movements' => [], 'invoiced' => [], 'missing' => [], 'sequences' => 0];
        }
        if (! $terms->complete()) {
            // Partly stated terms schedule nothing. A day count with no
            // invoice share leaves the split unknown and a share with no day
            // count leaves the balance undated, and guessing either would put
            // cash in a month nobody said it arrives in.
            return ['movements' => [], 'invoiced' => [], 'missing' => $terms->missing(), 'sequences' => 0];
        }

        $movements = [];
        $invoiced = [];
        $missing = [];
        $slot = 0;
        $share = BigDecimal::of((string) $terms->onInvoicePercent)->dividedBy(100, 8, RoundingMode::HalfUp);

        foreach ($streams as $stream) {
            $price = DriverNumbers::read($stream->price);
            foreach ($calendar->periods as $period) {
                $month = $stream->schedule[$period] ?? null;
                $units = DriverNumbers::read($month?->invoicedUnits);
                if ($units === null) {
                    // A month nobody has billed is not a month billed nothing.
                    // Only a stream that states units elsewhere is reported,
                    // so a stream with no schedule at all stays silent rather
                    // than reporting a gap for every month of the horizon.
                    if ($month !== null) {
                        $missing[] = $stream->id.'.'.$period.'.invoiced_units';
                    }

                    continue;
                }
                if ($price === null) {
                    $missing[] = $stream->id.'.price';

                    continue;
                }
                $billed = $units->multipliedBy($price);
                $invoiced[$period] = (string) BigDecimal::of($invoiced[$period] ?? '0')->plus($billed);
                $billedOn = ForecastDates::monthEnd($period);
                $onInvoice = $billed->multipliedBy($share);
                // The balance is the remainder rather than a second
                // percentage of the bill, so the two parts add back to the
                // amount billed however the share rounds.
                $balance = $billed->minus($onInvoice);

                foreach ([['on_invoice', $onInvoice, $billedOn],
                    ['balance', $balance, ForecastDates::shift($billedOn, $terms->debtorDays)]] as [$part, $amount, $date]) {
                    if ($amount->isZero()) {
                        continue;
                    }
                    $movements[] = new CashMovement(
                        $stream->id.'.'.$period.'.'.$part,
                        $terms->source.' ('.$stream->name.')',
                        $date,
                        DriverNumbers::money($amount),
                        'in',
                        'operating_receipt',
                        $sequence + $slot,
                    );
                    $slot++;
                }
            }
        }

        return ['movements' => $movements, 'invoiced' => $invoiced,
            'missing' => array_values(array_unique($missing)), 'sequences' => $slot];
    }
}
