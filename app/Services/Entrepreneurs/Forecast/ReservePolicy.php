<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use Brick\Math\BigDecimal;

/**
 * The reserve policy behind a funding target.
 *
 * The monthly cost basis and whether it includes owner pay are recorded on the
 * policy rather than assumed by the caller, because the same reserve target
 * means different things depending on that choice and a lender will ask.
 */
final readonly class ReservePolicy implements \JsonSerializable
{
    public function __construct(
        public ?string $monthlyFixedCosts,
        public int $monthsCovered = 3,
        public ?bool $includesOwnerPay = null,
        public ?string $oneOffCashCosts = null,
        public string $contingencyRate = '0.10',
        public ?string $basis = null,
    ) {
        if ($monthsCovered < 0) {
            throw new \InvalidArgumentException('A reserve policy covers a non-negative number of months.');
        }
        foreach ([$monthlyFixedCosts, $oneOffCashCosts, $contingencyRate] as $value) {
            DriverNumbers::read($value);
        }
    }

    /** Inputs the policy needs before a target can be stated at all.
     * @return list<string>
     */
    public function missing(): array
    {
        $missing = [];
        if ($this->monthlyFixedCosts === null) {
            $missing[] = 'reserve.monthly_fixed_costs';
        }
        if ($this->includesOwnerPay === null) {
            $missing[] = 'reserve.includes_owner_pay';
        }
        if (trim($this->basis ?? '') === '') {
            $missing[] = 'reserve.basis';
        }

        return $missing;
    }

    /** B: the policy's months of fixed costs. */
    public function baseReserve(): ?BigDecimal
    {
        $monthly = DriverNumbers::read($this->monthlyFixedCosts);

        return $monthly?->multipliedBy($this->monthsCovered);
    }

    /** Q: contingency on the base reserve plus identified one-off cash costs.
     *
     * The one-off costs themselves are already outflows in the dated cash
     * ledger; only their contingency is added here, so the principal is never
     * counted twice.
     */
    public function contingency(): ?BigDecimal
    {
        $base = $this->baseReserve();
        if ($base === null) {
            return null;
        }
        $oneOff = DriverNumbers::read($this->oneOffCashCosts) ?? BigDecimal::zero();

        return $base->plus($oneOff)->multipliedBy(BigDecimal::of($this->contingencyRate));
    }

    /** reserve_target = B + Q */
    public function target(): ?BigDecimal
    {
        $base = $this->baseReserve();
        $contingency = $this->contingency();

        return $base === null || $contingency === null ? null : $base->plus($contingency);
    }

    /** @return array{monthly_fixed_costs:?string,months_covered:int,includes_owner_pay:?bool,one_off_cash_costs:?string,contingency_rate:string,basis:?string,base_reserve:?string,contingency:?string,target:?string} */
    public function jsonSerialize(): array
    {
        $base = $this->baseReserve();
        $contingency = $this->contingency();
        $target = $this->target();

        return ['monthly_fixed_costs' => $this->monthlyFixedCosts, 'months_covered' => $this->monthsCovered,
            'includes_owner_pay' => $this->includesOwnerPay, 'one_off_cash_costs' => $this->oneOffCashCosts,
            'contingency_rate' => $this->contingencyRate, 'basis' => $this->basis,
            'base_reserve' => $base === null ? null : DriverNumbers::money($base),
            'contingency' => $contingency === null ? null : DriverNumbers::money($contingency),
            'target' => $target === null ? null : DriverNumbers::money($target)];
    }
}
