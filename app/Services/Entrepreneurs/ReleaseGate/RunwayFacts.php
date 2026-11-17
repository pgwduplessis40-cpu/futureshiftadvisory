<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\LenderMetrics;
use App\Services\Entrepreneurs\Forecast\ReservePolicy;
use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;

/**
 * How long the money lasts, on each basis the model can state one.
 *
 * PM-10 holds a runway claim against the model's own figure and refuses to
 * compare unlike measures, so a figure is worth nothing here without the two
 * statements that say what it counts. Every figure this produces therefore
 * carries whether it includes future receipts and whether it includes owner
 * pay, under the basis it was measured on.
 *
 * The two bases answer different questions and are never reconciled against
 * each other. Forecast cash asks how long the modelled business stays solvent
 * while trading as planned. Essential cost on no sales asks how long the cash
 * in hand covers the monthly cost basis if the revenue stops. A business can
 * have nine months of the first and three of the second with nothing wrong.
 *
 * The inclusions are read off the ledger rather than asserted. Whether the
 * forecast cash curve counts customer receipts is a property of the movements
 * in it, not an intention: a reconstruction that states no collection terms
 * turns no revenue into cash, and a curve with no receipts in it must not
 * claim to include them, because PM-10 would then compare a founder's
 * receipts-inclusive claim against a cost-only model and report agreement or
 * a contradiction where it should report unlike measures.
 */
final class RunwayFacts implements ProvidesMethodology
{
    public const FORECAST_CASH = 'forecast_cash';

    public const NO_SALES = 'essential_cost_no_sales';

    public function __construct(private readonly LenderMetrics $metrics = new LenderMetrics) {}

    public static function methodologyIds(): array
    {
        return ['entrepreneur.dated_cash', 'entrepreneur.scenarios_metrics_funding'];
    }

    /**
     * @param  list<array{period:string,opening_cash:?string,receipts:?string,payments:?string,closing_cash:?string}>  $months
     * @param  list<CashMovement>  $movements
     * @param  list<SourceReference>  $at
     * @return list<Fact>
     */
    public function capture(ForecastCalendar $calendar, array $months, array $movements,
        ?ReservePolicy $reserve, ?string $openingCash, array $at): array
    {
        return [...$this->forecastCash($calendar, $months, $movements, $at),
            ...$this->noSales($reserve, $openingCash, $at)];
    }

    /**
     * Months of modelled solvency, and what the curve counts.
     *
     * The count is of whole months that close without the cash going negative,
     * so a curve negative at the end of its first month has a runway of none
     * rather than of one.
     *
     * A curve that never goes negative states no runway. The model did not
     * find the business solvent indefinitely; it stopped looking at the end of
     * the horizon, and publishing the horizon's own length as the runway would
     * read as a figure measured rather than a question unasked - a claim of
     * eighteen months against a twelve-month model would then be reported as a
     * demonstrated contradiction when the model is simply silent past month
     * twelve.
     *
     * An unreadable month is the same refusal unless the cash has already run
     * out before it. Where it has, the runway is known: the business failed
     * inside the part of the curve that could be read, and a gap after that
     * does not unmake it.
     *
     * @param  list<array{period:string,opening_cash:?string,receipts:?string,payments:?string,closing_cash:?string}>  $months
     * @param  list<CashMovement>  $movements
     * @param  list<SourceReference>  $at
     * @return list<Fact>
     */
    private function forecastCash(ForecastCalendar $calendar, array $months, array $movements, array $at): array
    {
        $closing = array_column($months, 'closing_cash', 'period');
        $survived = 0;
        $runway = null;
        foreach ($calendar->periods as $period) {
            $balance = $closing[$period] ?? null;
            if ($balance === null) {
                break;
            }
            if (BigDecimal::of($balance)->isNegative()) {
                $runway = (string) $survived;

                break;
            }
            $survived++;
        }
        if ($runway === null) {
            return [];
        }

        $inside = array_flip($calendar->periods);
        $holds = function (string $category) use ($movements, $inside): bool {
            foreach ($movements as $movement) {
                if ($movement->category === $category && $movement->date !== null
                    && isset($inside[substr($movement->date, 0, 7)])) {
                    return true;
                }
            }

            return false;
        };

        return [
            new Fact('model.runway_months', $runway, self::FORECAST_CASH, unit: 'months',
                basis: 'months_closing_without_negative_forecast_cash', confirmed: true, sources: $at),
            new Fact('runway.includes_receipts', $holds('operating_receipt'), self::FORECAST_CASH,
                basis: 'operating_receipts_dated_in_the_horizon', confirmed: true, sources: $at),
            new Fact('runway.includes_owner_pay', $holds('owner_pay'), self::FORECAST_CASH,
                basis: 'owner_pay_dated_in_the_horizon', confirmed: true, sources: $at),
        ];
    }

    /**
     * Months the cash in hand covers the policy's monthly cost basis with no
     * sales at all.
     *
     * The basis and whether it includes owner pay are the reserve policy's own
     * statements: the same cash covers a different number of months depending
     * on that choice, and the policy is where the model records it. An
     * unanswered owner-pay question withholds the whole measure rather than
     * publishing a figure nobody can interpret - PM-10 needs the figure and
     * the statement together or it has nothing to compare.
     *
     * Receipts are excluded by construction, not by observation. This is what
     * the measure is: if the sales stop, there are none.
     *
     * @param  list<SourceReference>  $at
     * @return list<Fact>
     */
    private function noSales(?ReservePolicy $reserve, ?string $openingCash, array $at): array
    {
        if ($reserve === null || $reserve->includesOwnerPay === null) {
            return [];
        }
        $cover = $this->metrics->expenseCover($openingCash, $reserve->monthlyFixedCosts);
        if ($cover['status'] !== LenderMetrics::STATUS_OK || $cover['value'] === null) {
            return [];
        }

        return [
            new Fact('model.runway_months', $cover['value'], self::NO_SALES, unit: 'months',
                basis: 'opening_cash_over_the_policy_monthly_cost_basis', confirmed: true, sources: $at),
            new Fact('runway.includes_receipts', false, self::NO_SALES,
                basis: 'a_no_sales_measure_counts_no_receipts', confirmed: true, sources: $at),
            new Fact('runway.includes_owner_pay', $reserve->includesOwnerPay, self::NO_SALES,
                basis: 'the_reserve_policy_monthly_cost_basis', confirmed: true, sources: $at),
        ];
    }
}
