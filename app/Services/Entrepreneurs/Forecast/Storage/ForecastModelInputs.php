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
        // The owner-pay steps say what is paid and from when it applies; the
        // schedule also needs the date the first payment actually lands and
        // the record the whole arrangement comes from, because every payment
        // it generates carries that provenance into the cash ledger.
        public ?string $ownerPaySource = null,
        public ?string $ownerPayFirstPaymentOn = null,
        // Losses brought into the first planning period. The tax projection
        // takes this beside the policy rather than inside it, because a
        // policy with losses eligible and no opening balance is a complete
        // policy with nothing carried in, not an incomplete one.
        public ?string $openingEligibleLosses = null,
    ) {}

    /**
     * @return array{calendar:ForecastCalendar,streams:list<RevenueStream>,founder_months:array<string,FounderMonth>,cash_opening:?CashOpening,bank_opening:?BankOpening,trade_items:list<TradeItem>,owner_pay_steps:list<OwnerPayStep>,owner_pay_source:?string,owner_pay_first_payment_on:?string,assets:list<AssetInput>,tax_policy:?TaxPolicy,tax_periods:list<TaxPeriod>,opening_eligible_losses:?string}
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
            'owner_pay_source' => $this->ownerPaySource,
            'owner_pay_first_payment_on' => $this->ownerPayFirstPaymentOn,
            'assets' => $this->assets,
            'tax_policy' => $this->taxPolicy,
            'tax_periods' => $this->taxPeriods,
            'opening_eligible_losses' => $this->openingEligibleLosses,
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
