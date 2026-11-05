<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;

final class TradeTerms implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.dated_cash'];
    }

    public function sale(string $id, string $source, ?string $amount, ?string $bookedOn, ?string $invoicedOn, ?string $deliveredOn,
        ?string $depositPercent, ?string $balanceBasis, ?int $balanceDays, int $sequence): TradeItem
    {
        ForecastDates::read($bookedOn);
        ForecastDates::read($deliveredOn);
        if ($bookedOn !== null && $deliveredOn !== null && $bookedOn > $deliveredOn) {
            throw new \InvalidArgumentException('Delivery cannot precede the supplied booking date.');
        }
        if ($balanceBasis !== null && ! in_array($balanceBasis, ['before_delivery', 'invoice_days'], true)) {
            throw new \InvalidArgumentException('Choose before-delivery or invoice-plus-days balance timing.');
        }
        $this->days($balanceDays);
        $total = DriverNumbers::read($amount);
        $total = $total === null ? null : BigDecimal::of(DriverNumbers::money($total));
        $percentage = DriverNumbers::read($depositPercent);
        if ($percentage?->isGreaterThan(100)) {
            throw new \InvalidArgumentException('The deposit percentage cannot exceed 100.');
        }
        $deposit = $total !== null && $percentage !== null ? BigDecimal::of(DriverNumbers::money($total->multipliedBy($percentage)->dividedByExact(100))) : null;
        $balance = $total !== null && $deposit !== null ? $total->minus($deposit) : null;
        $due = match ($balanceBasis) {
            'before_delivery' => ForecastDates::shift($deliveredOn, $balanceDays === null ? null : -$balanceDays),
            'invoice_days' => ForecastDates::shift($invoicedOn, $balanceDays),
            default => null,
        };
        $settlements = [];
        foreach (['deposit' => [$deposit, $bookedOn], 'balance' => [$balance, $due]] as $part => [$value, $date]) {
            if ($value?->isZero()) {
                continue;
            }
            $settlements[] = new CashMovement($id.'.'.$part, $source, $date, $value === null ? null : DriverNumbers::money($value),
                'in', 'operating_receipt', $sequence + ($part === 'balance' ? 1 : 0));
        }

        return new TradeItem($id, $source, 'receivable', $invoicedOn, $total === null ? null : DriverNumbers::money($total), $settlements);
    }

    public function purchase(string $id, string $source, ?string $amount, ?string $invoicedOn, ?int $creditorDays, int $sequence): TradeItem
    {
        $this->days($creditorDays);
        $total = DriverNumbers::read($amount);
        $money = $total === null ? null : DriverNumbers::money($total);

        return new TradeItem($id, $source, 'payable', $invoicedOn, $money,
            [new CashMovement($id.'.payment', $source, ForecastDates::shift($invoicedOn, $creditorDays), $money, 'out', 'operating_payment', $sequence)]);
    }

    private function days(?int $days): void
    {
        if ($days !== null && ($days < 0 || $days > 36500)) {
            throw new \InvalidArgumentException('Payment days must be explicitly non-negative calendar days.');
        }
    }
}
