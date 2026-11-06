<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast\Storage;

use App\Services\Entrepreneurs\Forecast\ActualMonth;
use App\Services\Entrepreneurs\Forecast\AssetInput;
use App\Services\Entrepreneurs\Forecast\BankOpening;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\OwnerPayStep;
use App\Services\Entrepreneurs\Forecast\ReservePolicy;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\TaxPeriod;
use App\Services\Entrepreneurs\Forecast\TaxPolicy;
use App\Services\Entrepreneurs\Forecast\TradeItem;

/**
 * One complete version-2 reconstruction, as the engine's own value objects.
 *
 * This is the only shape the store accepts and the only shape it returns. The
 * three stored columns are split along the lines an advisor actually reviews:
 * forecast drivers, reviewed actuals and the reserve policy behind a funding
 * target. Nothing derived lives here; outputs are recomputed from these.
 */
final readonly class ForecastModelInputs
{
    /**
     * @param  list<RevenueStream>  $streams
     * @param  array<string,FounderMonth>  $founderMonths
     * @param  list<TradeItem>  $tradeItems
     * @param  list<OwnerPayStep>  $ownerPaySteps
     * @param  list<AssetInput>  $assets
     * @param  list<TaxPeriod>  $taxPeriods
     * @param  list<ActualMonth>  $actualMonths
     */
    public function __construct(
        public ForecastCalendar $calendar,
        public array $streams = [],
        public array $founderMonths = [],
        public ?CashOpening $cashOpening = null,
        public ?BankOpening $bankOpening = null,
        public array $tradeItems = [],
        public array $ownerPaySteps = [],
        public array $assets = [],
        public ?TaxPolicy $taxPolicy = null,
        public array $taxPeriods = [],
        public array $actualMonths = [],
        public ?ReservePolicy $reservePolicy = null,
    ) {}

    /**
     * @return array{calendar:ForecastCalendar,streams:list<RevenueStream>,founder_months:array<string,FounderMonth>,cash_opening:?CashOpening,bank_opening:?BankOpening,trade_items:list<TradeItem>,owner_pay_steps:list<OwnerPayStep>,assets:list<AssetInput>,tax_policy:?TaxPolicy,tax_periods:list<TaxPeriod>}
     */
    public function driversPayload(): array
    {
        return [
            'calendar' => $this->calendar,
            'streams' => $this->streams,
            'founder_months' => $this->founderMonths,
            'cash_opening' => $this->cashOpening,
            'bank_opening' => $this->bankOpening,
            'trade_items' => $this->tradeItems,
            'owner_pay_steps' => $this->ownerPaySteps,
            'assets' => $this->assets,
            'tax_policy' => $this->taxPolicy,
            'tax_periods' => $this->taxPeriods,
        ];
    }

    /** @return array{months:list<ActualMonth>} */
    public function actualsPayload(): array
    {
        return ['months' => $this->actualMonths];
    }

    /** The reserve policy serializes itself, including its derived target. */
    public function reservePolicyPayload(): ?ReservePolicy
    {
        return $this->reservePolicy;
    }
}
