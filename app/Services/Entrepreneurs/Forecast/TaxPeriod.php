<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

final readonly class TaxPeriod implements \JsonSerializable
{
    public function __construct(public string $startOn, public string $endOn, public ?string $accountingProfit, public ?string $taxAdjustments)
    {
        ForecastDates::read($startOn);
        ForecastDates::read($endOn);
        if ($startOn > $endOn) {
            throw new \InvalidArgumentException('A planning tax period must end on or after its start.');
        }
        AccountingNumbers::signed($accountingProfit);
        AccountingNumbers::signed($taxAdjustments);
    }

    /** @return array{start_on:string,end_on:string,accounting_profit:?string,tax_adjustments:?string} */
    public function jsonSerialize(): array
    {
        return ['start_on' => $this->startOn, 'end_on' => $this->endOn, 'accounting_profit' => $this->accountingProfit, 'tax_adjustments' => $this->taxAdjustments];
    }
}
