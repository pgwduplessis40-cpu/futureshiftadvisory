<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast\Storage;

use App\Services\Entrepreneurs\Forecast\ActualMonth;
use App\Services\Entrepreneurs\Forecast\AssetInput;
use App\Services\Entrepreneurs\Forecast\BankOpening;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\FixedCost;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\FundingSource;
use App\Services\Entrepreneurs\Forecast\OwnerPayStep;
use App\Services\Entrepreneurs\Forecast\ReservePolicy;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\TaxCashPlan;
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
     * @param  list<FundingSource>  $fundingSources
     * @param  list<FixedCost>  $fixedCosts
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
        public array $fundingSources = [],
        // Whether the advisor has stated that every source is recorded. It
        // cannot be read off the rows: an empty list is equally a business
        // with no funding and a funding section nobody has filled in, and
        // only somebody who looked can tell the two apart. Absent until they
        // say, and never inferred from the count.
        public ?bool $fundingInventoryComplete = null,
        public array $fixedCosts = [],
        // The same question the funding inventory asks, for the same reason:
        // no row count can tell a business with no overheads from a section
        // nobody has filled in.
        public ?bool $fixedCostScheduleComplete = null,
        // When tax and GST cash actually leaves the bank, which is a separate
        // question from what the tax expense is. The plan carries its own
        // scope states, because a business with nothing due and a business
        // nobody has asked about both have no payments recorded.
        public ?TaxCashPlan $taxCashPlan = null,
        // Whether the owner's pay is a deductible expense of the business or
        // a drawing against it. The two give different profits off the same
        // cash, and which applies depends on the entity rather than on the
        // payment, so it is asked rather than inferred from the entity basis,
        // which is free text.
        public ?bool $ownerPayDeductible = null,
    ) {}

    /**
     * The same reconstruction with its three cost-bearing sections replaced.
     *
     * A scenario is this reconstruction with shocked inputs run through the
     * same engines, so it needs a copy that differs in exactly the sections a
     * shock can reach and in nothing else. Streams carry the delivery volumes
     * and the provider unit cost, fixed costs the overhead rates, assets the
     * capital spend; every other section is a scenario-invariant and is
     * carried across unchanged.
     *
     * Each field is passed by hand because the class is readonly and `clone
     * with` is not available on the PHP version this runs on. That makes a
     * field added later easy to forget, so `ForecastModelInputsCloneTest`
     * compares every property by reflection and fails if one is dropped.
     *
     * @param  list<RevenueStream>  $streams
     * @param  list<FixedCost>  $fixedCosts
     * @param  list<AssetInput>  $assets
     */
    public function withCostInputs(array $streams, array $fixedCosts, array $assets): self
    {
        return new self(
            calendar: $this->calendar,
            streams: $streams,
            founderMonths: $this->founderMonths,
            cashOpening: $this->cashOpening,
            bankOpening: $this->bankOpening,
            tradeItems: $this->tradeItems,
            ownerPaySteps: $this->ownerPaySteps,
            assets: $assets,
            taxPolicy: $this->taxPolicy,
            taxPeriods: $this->taxPeriods,
            actualMonths: $this->actualMonths,
            reservePolicy: $this->reservePolicy,
            ownerPaySource: $this->ownerPaySource,
            ownerPayFirstPaymentOn: $this->ownerPayFirstPaymentOn,
            openingEligibleLosses: $this->openingEligibleLosses,
            fundingSources: $this->fundingSources,
            fundingInventoryComplete: $this->fundingInventoryComplete,
            fixedCosts: $fixedCosts,
            fixedCostScheduleComplete: $this->fixedCostScheduleComplete,
            taxCashPlan: $this->taxCashPlan,
            ownerPayDeductible: $this->ownerPayDeductible,
        );
    }

    /**
     * @return array{calendar:ForecastCalendar,streams:list<RevenueStream>,founder_months:array<string,FounderMonth>,cash_opening:?CashOpening,bank_opening:?BankOpening,trade_items:list<TradeItem>,owner_pay_steps:list<OwnerPayStep>,owner_pay_source:?string,owner_pay_first_payment_on:?string,assets:list<AssetInput>,tax_policy:?TaxPolicy,tax_periods:list<TaxPeriod>,opening_eligible_losses:?string,funding_sources:list<FundingSource>,funding_inventory_complete:?bool,fixed_costs:list<FixedCost>,fixed_cost_schedule_complete:?bool,tax_cash_plan:?TaxCashPlan,owner_pay_deductible:?bool}
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
            'funding_sources' => $this->fundingSources,
            'funding_inventory_complete' => $this->fundingInventoryComplete,
            'fixed_costs' => $this->fixedCosts,
            'fixed_cost_schedule_complete' => $this->fixedCostScheduleComplete,
            'tax_cash_plan' => $this->taxCashPlan,
            'owner_pay_deductible' => $this->ownerPayDeductible,
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
