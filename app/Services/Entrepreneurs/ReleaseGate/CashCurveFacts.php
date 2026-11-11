<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;

/**
 * One month-by-month cash curve, stated as the facts a rule reconciles.
 *
 * The base case and every scenario are the same shape of evidence: twelve or
 * more months of opening, movement and closing cash, the annual totals they
 * roll up to, and a coverage flag saying whether the month could be read at
 * all. MI-01 checks the base curve and SS-01 checks each scenario's against
 * the same arithmetic, so they are emitted from one place. A second
 * implementation for scenarios would be a second answer to the question of
 * what the curve says, and the two would drift.
 */
final class CashCurveFacts implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.dated_cash'];
    }

    /**
     * The lowest month-end closing balance, or none when a month could not be
     * closed.
     *
     * This is deliberately not the ledger's own lowest cash. The ledger tracks
     * the balance after every individual movement and can dip below every
     * month end, so reporting that figure against a curve of month ends would
     * state a trough the curve does not contain and fail a reconciliation that
     * is working correctly. SS-01 asks for the minimum of the closing
     * balances, so that is what this returns.
     *
     * One unreadable month withholds it rather than letting the minimum be
     * taken over the months that happened to close, which would report a floor
     * the curve has not been shown to reach.
     *
     * @param  list<array{period:string,opening_cash:?string,receipts:?string,payments:?string,closing_cash:?string}>  $months
     */
    public function trough(array $months): ?string
    {
        $lowest = null;
        foreach ($months as $month) {
            if ($month['closing_cash'] === null) {
                return null;
            }
            $closing = BigDecimal::of($month['closing_cash']);
            $lowest = $lowest === null || $closing->isLessThan($lowest) ? $closing : $lowest;
        }

        return $lowest === null ? null : (string) $lowest;
    }

    /**
     * @param  list<SourceReference>  $at
     * @param  list<array{period:string,opening_cash:?string,receipts:?string,payments:?string,closing_cash:?string}>  $months
     * @return list<Fact>
     */
    public function capture(SourceRecords $source, ForecastCalendar $calendar, array $months, array $at, string $scenario = 'base'): array
    {
        $facts = [];
        $offsets = array_flip($calendar->periods);
        $years = [];
        $calendarAt = [$source->reference('forecast_model.drivers.calendar')];

        foreach ($months as $month) {
            if (! isset($offsets[$month['period']])) {
                continue;
            }
            $number = $offsets[$month['period']] + 1;
            $label = 'month:'.$number;
            $movement = $month['receipts'] === null || $month['payments'] === null ? null
                : (string) BigDecimal::of($month['receipts'])->minus($month['payments']);
            $facts[] = new Fact('cash.period_start', $month['period'].'-01', period: $label, scenario: $scenario,
                confirmed: true, sources: $calendarAt);
            $facts[] = new Fact('cash.opening', $month['opening_cash'], period: $label, scenario: $scenario,
                unit: 'NZD', confirmed: true, sources: $at);
            $facts[] = new Fact('cash.movement', $movement, period: $label, scenario: $scenario,
                unit: 'NZD', confirmed: true, sources: $at);
            $facts[] = new Fact('cash.closing', $month['closing_cash'], period: $label, scenario: $scenario,
                unit: 'NZD', confirmed: true, sources: $at);
            // A month whose receipts and payments are both known is a month in
            // which every movement could be read and placed. Where the ledger
            // withholds them it found one it could not value or could not
            // date, and saying so is what keeps the month out of a
            // reconciliation it cannot support.
            $facts[] = new Fact('ledger.coverage', $movement === null ? 'incomplete' : 'complete',
                period: $label, scenario: $scenario, confirmed: true, sources: $at);
            // Keyed by month number rather than appended, so the month that
            // closes a year is the twelfth month of it and not whichever row
            // happened to be written last.
            $years[(int) ceil($number / 12)][$number] = ['movement' => $movement, 'closing' => $month['closing_cash']];
        }

        foreach ($years as $year => $rows) {
            // A year the reconstruction does not finish has no annual total,
            // and a sum of the months it does hold would be an annual figure
            // for part of a year. One unreadable month withholds the year.
            $last = $year * 12;
            if (count($rows) !== 12 || ! isset($rows[$last]) || $rows[$last]['closing'] === null
                || in_array(null, array_column($rows, 'movement'), true)) {
                continue;
            }
            $sum = BigDecimal::zero();
            foreach ($rows as $row) {
                $sum = $sum->plus((string) $row['movement']);
            }
            $facts[] = new Fact('cash.annual_movement', (string) $sum, period: 'year:'.$year, scenario: $scenario,
                unit: 'NZD', confirmed: true, sources: $at);
            $facts[] = new Fact('cash.annual_closing', $rows[$last]['closing'], period: 'year:'.$year,
                scenario: $scenario, unit: 'NZD', confirmed: true, sources: $at);
        }

        return $facts;
    }
}
