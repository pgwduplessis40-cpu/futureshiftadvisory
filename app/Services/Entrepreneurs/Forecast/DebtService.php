<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use Brick\Math\BigDecimal;

/**
 * Debt service for one stated period, with the facility terms behind it.
 *
 * A DSCR without its terms is unreadable: the same ratio means different
 * things at different rates, draw dates and repayment schedules, and an
 * interest-only period flatters it. The terms are recorded on the input so the
 * ratio cannot be quoted without them.
 */
final readonly class DebtService implements \JsonSerializable
{
    /** @param list<string> $drawDates */
    public function __construct(
        public string $period,
        public ?string $principalRepayments,
        public ?string $interest,
        public ?string $facilityAmount = null,
        public ?string $rate = null,
        public array $drawDates = [],
        public ?int $termMonths = null,
        public ?string $fees = null,
        public ?string $repaymentSchedule = null,
        public ?int $interestOnlyMonths = null,
    ) {
        if (preg_match('/^[1-9]\d{3}-(0[1-9]|1[0-2])$/D', $period) !== 1) {
            throw new \InvalidArgumentException('Debt service is stated for a calendar month.');
        }
        foreach ([$principalRepayments, $interest, $facilityAmount, $rate, $fees] as $value) {
            DriverNumbers::read($value);
        }
        foreach ($drawDates as $date) {
            ForecastDates::read($date);
        }
        if (($termMonths !== null && $termMonths < 1) || ($interestOnlyMonths !== null && $interestOnlyMonths < 0)) {
            throw new \InvalidArgumentException('A facility term is at least one month and any interest-only period is non-negative.');
        }
    }

    /** True when there is no debt service at all in the stated period, so a
     * ratio would have nothing to divide by.
     */
    public function isAbsent(): bool
    {
        $principal = DriverNumbers::read($this->principalRepayments);
        $interest = DriverNumbers::read($this->interest);

        return ($principal === null || $principal->isZero()) && ($interest === null || $interest->isZero());
    }

    /** principal repayments + interest for the stated period. */
    public function total(): ?BigDecimal
    {
        $principal = DriverNumbers::read($this->principalRepayments);
        $interest = DriverNumbers::read($this->interest);
        if ($principal === null && $interest === null) {
            return null;
        }

        return ($principal ?? BigDecimal::zero())->plus($interest ?? BigDecimal::zero());
    }

    /** Terms a lender needs before the ratio is quotable. */
    public function missingTerms(): array
    {
        if ($this->isAbsent()) {
            return [];
        }
        $missing = [];
        foreach (['facilityAmount' => 'facility_amount', 'rate' => 'rate', 'repaymentSchedule' => 'repayment_schedule'] as $property => $key) {
            if ($this->{$property} === null || trim((string) $this->{$property}) === '') {
                $missing[] = 'debt.'.$key;
            }
        }
        if ($this->drawDates === []) {
            $missing[] = 'debt.draw_dates';
        }
        if ($this->termMonths === null) {
            $missing[] = 'debt.term_months';
        }

        return $missing;
    }

    /** @return array{period:string,principal_repayments:?string,interest:?string,facility_amount:?string,rate:?string,draw_dates:list<string>,term_months:?int,fees:?string,repayment_schedule:?string,interest_only_months:?int} */
    public function jsonSerialize(): array
    {
        return ['period' => $this->period, 'principal_repayments' => $this->principalRepayments, 'interest' => $this->interest,
            'facility_amount' => $this->facilityAmount, 'rate' => $this->rate, 'draw_dates' => $this->drawDates,
            'term_months' => $this->termMonths, 'fees' => $this->fees, 'repayment_schedule' => $this->repaymentSchedule,
            'interest_only_months' => $this->interestOnlyMonths];
    }
}
