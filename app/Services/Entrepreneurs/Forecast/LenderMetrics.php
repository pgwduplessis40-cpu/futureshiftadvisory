<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * The lender metrics produced as standard for a scenario.
 *
 * Each metric returns its own status rather than a bare number, because the
 * honest answer is often "not applicable" or "no finite result under this
 * case", and those are different from zero. A zero break-even would say the
 * business needs no sales; a zero DSCR would say it cannot service debt it
 * does not have.
 *
 * @phpstan-type Checkpoint array{date:string,movement_id:?string,balance:?string}
 * @phpstan-type MetricDetail array{missing?:list<string>,contribution_per_unit?:string,unit?:string,capacity_units?:string,utilisation?:string,mix_total?:string,monthly_expense_basis?:string,essential_basis_excluding_owner_pay?:string,essential_months_cover?:?string,balance?:string,horizon_end?:string,covers_horizon?:bool,period?:string,numerator?:string,denominator?:string,terms?:DebtService}
 * @phpstan-type MetricResult array{status:string,value:?string,basis:?string,detail:MetricDetail}
 */
final class LenderMetrics implements ProvidesMethodology
{
    public const VERSION = 'phase-2d.1';

    public const STATUS_OK = 'ok';

    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    public const STATUS_NO_FINITE_RESULT = 'no_finite_result';

    public const STATUS_CANNOT_VERIFY = 'cannot_verify';

    public static function methodologyIds(): array
    {
        return ['entrepreneur.scenarios_metrics_funding'];
    }

    /**
     * Monthly operating break-even in units, and utilisation against capacity.
     *
     * A non-positive contribution per unit means no volume recovers fixed
     * costs: that is no finite break-even under this case, not zero units.
     *
     * @return MetricResult
     */
    public function breakEven(?string $monthlyFixedCosts, ?string $pricePerUnit, ?string $grossMargin, ?string $capacityUnits = null, ?string $unit = null): array
    {
        $fixed = DriverNumbers::read($monthlyFixedCosts);
        $price = DriverNumbers::read($pricePerUnit);
        $margin = DriverNumbers::read($grossMargin);

        if ($fixed === null || $price === null || $margin === null || $unit === null || trim($unit) === '') {
            return $this->metric(self::STATUS_CANNOT_VERIFY, null, null, ['missing' => $this->missingBreakEvenInputs($monthlyFixedCosts, $pricePerUnit, $grossMargin, $unit)]);
        }

        $contribution = $price->multipliedBy($margin);
        if ($contribution->isNegativeOrZero()) {
            return $this->metric(self::STATUS_NO_FINITE_RESULT, null, 'contribution per unit is not positive', [
                'contribution_per_unit' => DriverNumbers::money($contribution),
                'unit' => $unit,
            ]);
        }

        $units = $fixed->dividedBy($contribution, 4, RoundingMode::HalfUp);
        $detail = ['contribution_per_unit' => DriverNumbers::money($contribution), 'unit' => $unit];

        $capacity = DriverNumbers::read($capacityUnits);
        if ($capacity !== null && $capacity->isPositive()) {
            $detail['capacity_units'] = (string) $capacity;
            $detail['utilisation'] = (string) $units->dividedBy($capacity, 4, RoundingMode::HalfUp);
        }

        return $this->metric(self::STATUS_OK, (string) $units, 'monthly fixed costs over price times gross margin', $detail);
    }

    /**
     * Break-even across several streams needs an explicit sales mix: without
     * one there is no single unit to solve for.
     *
     * @param  array<string,string>  $mix  Share of unit volume per stream, summing to 1.
     * @param  array<string,array{price:?string,gross_margin:?string}>  $streams
     * @return MetricResult
     */
    public function blendedBreakEven(?string $monthlyFixedCosts, array $mix, array $streams): array
    {
        if ($mix === []) {
            return $this->metric(self::STATUS_CANNOT_VERIFY, null, null, ['missing' => ['break_even.sales_mix']]);
        }

        $share = BigDecimal::zero();
        $contribution = BigDecimal::zero();
        foreach ($mix as $streamId => $weight) {
            $weightValue = DriverNumbers::read($weight);
            $stream = $streams[$streamId] ?? null;
            $price = DriverNumbers::read($stream['price'] ?? null);
            $margin = DriverNumbers::read($stream['gross_margin'] ?? null);
            if ($weightValue === null || $price === null || $margin === null) {
                return $this->metric(self::STATUS_CANNOT_VERIFY, null, null, ['missing' => ['break_even.stream.'.$streamId]]);
            }
            $share = $share->plus($weightValue);
            $contribution = $contribution->plus($price->multipliedBy($margin)->multipliedBy($weightValue));
        }

        if (! $share->toScale(6, RoundingMode::HalfUp)->isEqualTo(BigDecimal::one()->toScale(6))) {
            return $this->metric(self::STATUS_CANNOT_VERIFY, null, null, ['missing' => ['break_even.sales_mix_sums_to_one'], 'mix_total' => (string) $share]);
        }

        return $this->breakEven($monthlyFixedCosts, DriverNumbers::money($contribution), '1', null, 'blended unit');
    }

    /**
     * No-sales expense cover: unrestricted cash over the stated monthly basis.
     * Essential cover excluding owner pay is reported alongside it.
     *
     * @return MetricResult
     */
    public function expenseCover(?string $unrestrictedCash, ?string $monthlyExpenseBasis, ?string $ownerPayPerMonth = null): array
    {
        $cash = DriverNumbers::read($unrestrictedCash);
        $basis = DriverNumbers::read($monthlyExpenseBasis);
        if ($cash === null || $basis === null) {
            return $this->metric(self::STATUS_CANNOT_VERIFY, null, null, ['missing' => ['expense_cover.inputs']]);
        }
        if (! $basis->isPositive()) {
            return $this->metric(self::STATUS_NO_FINITE_RESULT, null, 'the monthly expense basis is not positive', []);
        }

        $detail = ['monthly_expense_basis' => DriverNumbers::money($basis)];
        $ownerPay = DriverNumbers::read($ownerPayPerMonth);
        if ($ownerPay !== null) {
            $essential = $basis->minus($ownerPay);
            $detail['essential_basis_excluding_owner_pay'] = DriverNumbers::money($essential);
            $detail['essential_months_cover'] = $essential->isPositive()
                ? (string) $cash->dividedBy($essential, 2, RoundingMode::HalfUp)
                : null;
        }

        return $this->metric(self::STATUS_OK, (string) $cash->dividedBy($basis, 2, RoundingMode::HalfUp), 'months of the stated monthly expense basis', $detail);
    }

    /**
     * Forecast cash runway: the first date unrestricted cash goes negative.
     *
     * Cash that never goes negative within the horizon reports horizon
     * coverage, never an indefinite runway: the model simply stops looking.
     *
     * @param  list<Checkpoint>  $checkpoints
     * @return MetricResult
     */
    public function runway(array $checkpoints, string $horizonEnd): array
    {
        foreach ($checkpoints as $checkpoint) {
            if ($checkpoint['balance'] === null) {
                return $this->metric(self::STATUS_CANNOT_VERIFY, null, null, ['missing' => ['runway.checkpoint_balances']]);
            }
        }
        if ($checkpoints === []) {
            return $this->metric(self::STATUS_CANNOT_VERIFY, null, null, ['missing' => ['runway.checkpoints']]);
        }

        foreach ($checkpoints as $checkpoint) {
            if (DriverNumbers::readSigned($checkpoint['balance'])->isNegative()) {
                return $this->metric(self::STATUS_OK, $checkpoint['date'], 'first date unrestricted forecast cash is negative', [
                    'balance' => $checkpoint['balance'],
                ]);
            }
        }

        return $this->metric(self::STATUS_OK, null, 'cash stays positive for the modelled horizon', [
            'horizon_end' => $horizonEnd,
            'covers_horizon' => true,
        ]);
    }

    /** Owner pay as a share of revenue for the stated period.
     * @return MetricResult
     */
    public function ownerPayRatio(?string $ownerPay, ?string $revenue): array
    {
        $pay = DriverNumbers::read($ownerPay);
        $turnover = DriverNumbers::read($revenue);
        if ($pay === null || $turnover === null) {
            return $this->metric(self::STATUS_CANNOT_VERIFY, null, null, ['missing' => ['owner_pay_ratio.inputs']]);
        }
        if (! $turnover->isPositive()) {
            return $this->metric(self::STATUS_NO_FINITE_RESULT, null, 'no revenue to express owner pay against', []);
        }

        return $this->metric(self::STATUS_OK, (string) $pay->dividedBy($turnover, 4, RoundingMode::HalfUp), 'owner pay over revenue for the stated period', []);
    }

    /**
     * DSCR for one stated period, numerator and denominator on the same basis.
     *
     * With no debt service the answer is Not applicable. Reporting zero would
     * claim the business cannot service debt; reporting infinity would claim
     * unlimited capacity. Neither is true of a business with no facility.
     *
     * @return MetricResult
     */
    public function debtServiceCover(?string $profitBeforeTax, ?string $depreciation, DebtService $debt): array
    {
        if ($debt->isAbsent()) {
            return $this->metric(self::STATUS_NOT_APPLICABLE, null, 'no debt service in the stated period', ['period' => $debt->period]);
        }

        $missing = $debt->missingTerms();
        $pbt = DriverNumbers::readSigned($profitBeforeTax);
        $depn = DriverNumbers::read($depreciation);
        $interest = DriverNumbers::read($debt->interest) ?? BigDecimal::zero();
        $total = $debt->total();

        if ($pbt === null) {
            $missing[] = 'dscr.profit_before_tax';
        }
        if ($depn === null) {
            $missing[] = 'dscr.depreciation';
        }
        if ($missing !== []) {
            sort($missing);

            return $this->metric(self::STATUS_CANNOT_VERIFY, null, null, ['missing' => $missing, 'period' => $debt->period]);
        }

        $numerator = $pbt->plus($interest)->plus($depn);

        return $this->metric(self::STATUS_OK, (string) $numerator->dividedBy($total, 4, RoundingMode::HalfUp),
            'labelled EBITDA-based planning ratio for the stated period, not a lender covenant definition', [
                'period' => $debt->period,
                'numerator' => DriverNumbers::money($numerator),
                'denominator' => DriverNumbers::money($total),
                'terms' => $debt,
            ]);
    }

    /** @return list<string> */
    private function missingBreakEvenInputs(?string $fixed, ?string $price, ?string $margin, ?string $unit): array
    {
        $missing = [];
        foreach (['break_even.monthly_fixed_costs' => $fixed, 'break_even.price_per_unit' => $price,
            'break_even.gross_margin' => $margin, 'break_even.unit' => $unit] as $key => $value) {
            if ($value === null || trim($value) === '') {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /** @param MetricDetail $detail
     * @return MetricResult
     */
    private function metric(string $status, ?string $value, ?string $basis, array $detail): array
    {
        return ['status' => $status, 'value' => $value, 'basis' => $basis, 'detail' => $detail];
    }
}
