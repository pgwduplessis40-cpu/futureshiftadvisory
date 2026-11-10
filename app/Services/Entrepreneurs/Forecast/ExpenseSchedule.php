<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** @phpstan-type ExpenseResult array{status:string,basis:string,rate:?string,quantity:?string,frequency:string,recognition:array<string,?string>,obligations:list<TradeItem>,missing:list<string>} */
final class ExpenseSchedule implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.dated_cash'];
    }

    /**
     * Times a year each cadence raises a charge.
     *
     * @var array<string,string>
     */
    public const OCCURRENCES_PER_YEAR = ['weekly' => '52', 'fortnightly' => '26', 'monthly' => '12', 'quarterly' => '4', 'annual' => '1'];

    /**
     * The monthly expense a recurring charge recognises, unrounded.
     *
     * A fixed cost relates to the period it covers rather than to the month
     * its invoice happens to fall in, so it is recognised straight-line at its
     * annualised rate: the charge, times the number of times a year it is
     * raised, divided by twelve. An annual premium invoiced once in March is
     * an expense of one twelfth every month, not a spike in March.
     *
     * It is returned unrounded, at eight decimal places, because the cents of
     * an exact allocation have to land somewhere and a schedule that puts the
     * residual in one month is not a straight line. Rounding belongs to what
     * is displayed and to what is posted, which is a separate question from
     * what the rate recognises.
     *
     * The difference between this and the cash actually paid in a month is the
     * accrual or prepayment the cost carries, which is a real position rather
     * than a discrepancy.
     */
    public function monthlyRecognition(?string $rate, ?string $quantity, ?string $frequency): ?string
    {
        $price = DriverNumbers::read($rate);
        $units = DriverNumbers::read($quantity);
        $occurrences = $frequency === null ? null : self::OCCURRENCES_PER_YEAR[$frequency] ?? null;
        if ($price === null || $units === null || $occurrences === null) {
            return null;
        }

        return (string) $price->multipliedBy($units)->multipliedBy($occurrences)->dividedBy(12, 8, RoundingMode::HalfUp);
    }

    /**
     * Recognition weights are an explicit allocation of the total contracted
     * charges, independent of invoice/payment dates. Equal annual weights spread
     * an annual expense; the final non-zero period receives the cent residual.
     *
     * @param  array<string,?string>  $recognitionWeights
     * @return ExpenseResult
     */
    public function fixed(ForecastCalendar $calendar, string $id, string $source, ?string $rate, ?string $quantity, string $frequency,
        string $firstInvoiceOn, string $lastInvoiceOn, array $recognitionWeights, ?int $creditorDays, int $sequence): array
    {
        if (array_diff(array_keys($recognitionWeights), $calendar->periods) !== [] || $firstInvoiceOn < $calendar->startMonth.'-01' || $lastInvoiceOn > ForecastDates::end($calendar)) {
            throw new \InvalidArgumentException('Fixed-cost invoices and recognition must fit the declared calendar.');
        }
        $price = DriverNumbers::read($rate);
        $units = DriverNumbers::read($quantity);
        $charge = $price !== null && $units !== null ? DriverNumbers::money($price->multipliedBy($units)) : null;
        $items = [];
        foreach (ForecastDates::recur($firstInvoiceOn, $lastInvoiceOn, $frequency) as $index => $date) {
            $items[] = (new TradeTerms)->purchase($id.'.'.$index, $source, $charge, $date, $creditorDays, $sequence);
        }
        $missing = [];
        if ($charge === null) {
            $missing[] = 'rate_or_quantity';
        }
        if ($creditorDays === null) {
            $missing[] = 'creditor_days';
        }
        $weights = [];
        $sum = BigDecimal::zero();
        foreach ($recognitionWeights as $period => $weight) {
            $value = DriverNumbers::read($weight);
            if ($value === null) {
                $missing[] = 'recognition.'.$period;
            } else {
                $sum = $sum->plus($value);
                if (! $value->isZero()) {
                    $weights[$period] = $value;
                }
            }
        }
        if ($sum->isZero()) {
            $missing[] = 'recognition_allocation';
        }
        ksort($weights);
        $recognition = array_fill_keys($calendar->periods, '0.00');
        if ($charge === null || $sum->isZero() || in_array(null, $recognitionWeights, true)) {
            $recognition = array_fill_keys($calendar->periods, null);
        } else {
            $total = BigDecimal::of($charge)->multipliedBy(count($items));
            $remaining = $total;
            foreach ($weights as $period => $weight) {
                $amount = $period === array_key_last($weights) ? $remaining : $total->multipliedBy($weight)->dividedBy($sum, 2, RoundingMode::Down);
                $recognition[$period] = DriverNumbers::money($amount);
                $remaining = $remaining->minus($amount);
            }
        }

        return ['status' => $missing === [] ? 'pass' : 'cannot_verify', 'basis' => 'fixed', 'rate' => $rate, 'quantity' => $quantity, 'frequency' => $frequency,
            'recognition' => $recognition, 'obligations' => $items, 'missing' => $missing];
    }

    /** @param array<string,?string> $bases
     * @param  array<string,?string>  $invoiceDates
     * @return ExpenseResult
     */
    public function direct(ForecastCalendar $calendar, string $id, string $source, string $basis, ?string $rate, array $bases, array $invoiceDates, ?int $creditorDays, int $sequence): array
    {
        if (! in_array($basis, ['revenue_percent', 'delivered_units'], true) || array_diff(array_keys($bases), $calendar->periods) !== [] || array_diff(array_keys($invoiceDates), $calendar->periods) !== []) {
            throw new \InvalidArgumentException('Direct costs need an explicit basis and schedules within the calendar.');
        }
        $price = DriverNumbers::read($rate);
        $recognition = $items = $missing = [];
        foreach ($calendar->periods as $period) {
            $base = DriverNumbers::read($bases[$period] ?? null);
            $value = $price !== null && $base !== null ? $price->multipliedBy($base)->dividedByExact($basis === 'revenue_percent' ? 100 : 1) : null;
            $recognition[$period] = $value === null ? null : DriverNumbers::money($value);
            $date = $invoiceDates[$period] ?? null;
            if ($value === null || $date === null || $creditorDays === null) {
                $missing[] = $period.'.cost_or_payment_inputs';
            }
            $items[] = (new TradeTerms)->purchase($id.'.'.$period, $source, $recognition[$period], $date, $creditorDays, $sequence);
        }

        return ['status' => $missing === [] ? 'pass' : 'cannot_verify', 'basis' => $basis, 'rate' => $rate, 'quantity' => null, 'frequency' => 'monthly',
            'recognition' => $recognition, 'obligations' => $items, 'missing' => $missing];
    }
}
