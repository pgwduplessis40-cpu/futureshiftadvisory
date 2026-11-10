<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Models\EntrepreneurForecastModel;
use App\Services\Entrepreneurs\Forecast\ActualMonth;
use App\Services\Entrepreneurs\Forecast\AssetInput;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\DriverNumbers;
use App\Services\Entrepreneurs\Forecast\FixedCost;
use App\Services\Entrepreneurs\Forecast\ForecastRun;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\FundingSource;
use App\Services\Entrepreneurs\Forecast\OwnerPayStep;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastEngineVersion;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelStore;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use App\Services\Entrepreneurs\Forecast\TaxPeriod;
use App\Services\Entrepreneurs\Forecast\TradeItem;
use Brick\Math\BigDecimal;

/**
 * Presents a stored version-2 reconstruction for advisor review.
 *
 * The page shows what the reconstruction holds and, just as deliberately, what
 * it does not. A section this screen cannot edit is still reported, because an
 * advisor deciding whether to select a model needs to see the whole of it.
 *
 * @phpstan-type CalendarPayload array{start_month:string,months:int}
 * @phpstan-type CashOpeningPayload array{amount:string|null,source:string|null,verified:bool|null}
 * @phpstan-type ReservePayload array{monthly_fixed_costs:string|null,months_covered:int,includes_owner_pay:bool|null,one_off_cash_costs:string|null,contingency_rate:string,basis:string|null,base_reserve:string|null,contingency:string|null,target:string|null,missing:list<string>}
 * @phpstan-type ActualPayload array{period:string,state:string|null,reconciled:bool|null,closing_bank_cash:string|null,reviewed_by:string|null,reviewed_on:string|null,pre_trading_evidence:string|null,revenue:string|null,operating_expenses:string|null,depreciation:string|null,interest:string|null,other_income:string|null,profit_before_tax:string|null,income_tax_expense:string|null}
 * @phpstan-type StreamMonthPayload array{period:string,booked_units:string|null,delivery_units:string|null,invoiced_units:string|null,founder_hours:string|null,additional_capacity_units:string|null,provider:string|null,provider_unit_cost:string|null,no_cost_basis:string|null}
 * @phpstan-type StreamPayload array{id:string,name:string,unit:string|null,price:string|null,preparation_hours_per_unit:string|null,delivery_hours_per_unit:string|null,in_base_case:bool|null,evidence_status:string|null,schedule:list<StreamMonthPayload>}
 * @phpstan-type FounderMonthPayload array{period:string,available_hours:string|null,administration_hours:string|null,business_development_hours:string|null}
 * @phpstan-type FundingPayload array{id:string,source:string,identity:string|null,type:string|null,amount:string|null,commitment:string|null,available_from:string|null,drawn_on:string|null,restriction:string|null,verified:bool|null}
 * @phpstan-type FixedCostPayload array{id:string,source:string,rate:string|null,quantity:string|null,frequency:string|null,first_invoice_on:string|null,last_invoice_on:string|null,creditor_days:int|null,reviewed:bool|null}
 * @phpstan-type SettlementPayload array{id:string,source:string,date:string|null,amount:string|null,category:string,verified:bool|null}
 * @phpstan-type TradeItemPayload array{id:string,source:string,kind:string,raised_on:string|null,amount:string|null,settlements:list<SettlementPayload>|null}
 * @phpstan-type OwnerPayPayload array{effective_on:string,weekly_amount:string|null,trigger:string,threshold:string|null}
 * @phpstan-type AssetPayload array{id:string,source:string,cost:string|null,classification:string|null,policy_source:string|null,reviewed:bool|null,paid_on:string|null,recognition_start_on:string|null,life_months:int|null,residual:string|null,method:string|null,opening_depreciation:string|null}
 * @phpstan-type TaxPeriodPayload array{start_on:string,end_on:string,accounting_profit:string|null,tax_adjustments:string|null}
 * @phpstan-type TaxPayload array{entity_basis:string|null,rate_percent:string|null,losses_eligible:bool|null,policy_source:string|null,eligibility_source:string|null,version:string|null,periods:list<TaxPeriodPayload>}
 * @phpstan-type GapPayload array{reason:string,months:int,first_period:string}
 * @phpstan-type ProjectionPayload array{engine_version:string,status:string,months_projected:int,base_revenue_months:int,missing:list<GapPayload>,errors:list<GapPayload>,refusal:string|null}
 * @phpstan-type CashPayload array{status:string,movements_counted:int,settled_before_opening:int,lowest_cash:string|null,lowest_cash_date:string|null,cash_deficit:string|null,reserve_target:string|null,reserve_shortfall:string|null,missing:list<GapPayload>,refusal:string|null}
 * @phpstan-type SectionPayload array{key:string,label:string,captured:bool,editable:bool,detail:string}
 * @phpstan-type ModelPayload array{revision:int,engine_version:string,readable:bool,load_error:string|null,selected:bool,selected_at:string|null,selected_by:string|null,calendar:CalendarPayload|null,cash_opening:CashOpeningPayload,reserve:ReservePayload,actuals:list<ActualPayload>,streams:list<StreamPayload>,founder_months:list<FounderMonthPayload>,funding_sources:list<FundingPayload>,funding_inventory_complete:bool|null,fixed_costs:list<FixedCostPayload>,fixed_cost_schedule_complete:bool|null,trade_items:list<TradeItemPayload>,owner_pay_steps:list<OwnerPayPayload>,assets:list<AssetPayload>,tax:TaxPayload,projection:ProjectionPayload|null,cash:CashPayload|null,owner_pay_source:string|null,owner_pay_first_payment_on:string|null,opening_eligible_losses:string|null,sections:list<SectionPayload>}
 */
final readonly class AdvisorForecastModelPayload
{
    /**
     * The cost at which the engine stops accepting a classification nobody has
     * reviewed. Mirrored here so the section summary can say how many assets
     * are over it and unreviewed, rather than leaving an advisor to find out
     * from a depreciation schedule that will not calculate.
     */
    private const REVIEW_AT = 1000;

    public function __construct(private ForecastModelStore $store, private ForecastRun $run = new ForecastRun) {}

    /** @return ModelPayload|null */
    public function build(?EntrepreneurForecastModel $model): ?array
    {
        if ($model === null) {
            return null;
        }
        $inputs = null;
        $error = null;
        try {
            $inputs = $this->store->inputs($model);
        } catch (\InvalidArgumentException $e) {
            // A reconstruction the engine refuses to read is shown as the load
            // failure it is. Rendering an empty form in its place would invite
            // an advisor to overwrite a model they never saw.
            $error = $e->getMessage();
        }
        $reserve = $inputs?->reservePolicy;
        $target = $reserve?->target();
        $base = $reserve?->baseReserve();
        $contingency = $reserve?->contingency();

        return [
            'revision' => $model->revision,
            'engine_version' => (string) $model->engine_version,
            'readable' => $inputs instanceof ForecastModelInputs,
            'load_error' => $error,
            'selected' => $model->isSelected(),
            'selected_at' => $model->selected_at?->toIso8601String(),
            'selected_by' => $model->selectedBy?->name,
            'calendar' => $inputs === null ? null : ['start_month' => $inputs->calendar->startMonth, 'months' => $inputs->calendar->months],
            'cash_opening' => ['amount' => $inputs?->cashOpening?->amount, 'source' => $inputs?->cashOpening?->source,
                'verified' => $inputs?->cashOpening?->verified],
            'reserve' => [
                'monthly_fixed_costs' => $reserve?->monthlyFixedCosts, 'months_covered' => $reserve->monthsCovered ?? 3,
                'includes_owner_pay' => $reserve?->includesOwnerPay, 'one_off_cash_costs' => $reserve?->oneOffCashCosts,
                'contingency_rate' => $reserve->contingencyRate ?? '0.10', 'basis' => $reserve?->basis,
                // Recomputed from the policy, never read back from storage, so
                // a displayed target cannot drift away from its own basis.
                'base_reserve' => $base === null ? null : (string) $base->toScale(2),
                'contingency' => $contingency === null ? null : (string) $contingency->toScale(2),
                'target' => $target === null ? null : (string) $target->toScale(2),
                'missing' => $reserve?->missing() ?? ['reserve.monthly_fixed_costs', 'reserve.includes_owner_pay', 'reserve.basis'],
            ],
            'actuals' => array_map($this->actual(...), $inputs->actualMonths ?? []),
            'streams' => array_map($this->stream(...), $inputs->streams ?? []),
            'founder_months' => $this->founderMonths($inputs),
            'funding_sources' => array_map($this->fundingSource(...), $inputs->fundingSources ?? []),
            'funding_inventory_complete' => $inputs?->fundingInventoryComplete,
            'fixed_costs' => array_map($this->fixedCost(...), $inputs->fixedCosts ?? []),
            'fixed_cost_schedule_complete' => $inputs?->fixedCostScheduleComplete,
            'trade_items' => array_map($this->tradeItem(...), $inputs->tradeItems ?? []),
            'owner_pay_steps' => array_map($this->ownerPayStep(...), $inputs->ownerPaySteps ?? []),
            'assets' => array_map($this->asset(...), $inputs->assets ?? []),
            'tax' => $this->tax($inputs),
            'projection' => $inputs === null ? null : $this->projection($inputs),
            'cash' => $inputs === null ? null : $this->cash($inputs),
            'owner_pay_source' => $inputs?->ownerPaySource,
            'owner_pay_first_payment_on' => $inputs?->ownerPayFirstPaymentOn,
            'opening_eligible_losses' => $inputs?->openingEligibleLosses,
            'sections' => $this->sections($inputs),
        ];
    }

    /** @return array{engine_version:string,components:array<string,string>} */
    public function engine(): array
    {
        return ['engine_version' => ForecastEngineVersion::CURRENT, 'components' => ForecastEngineVersion::components()];
    }

    /** @return ActualPayload */
    private function actual(ActualMonth $month): array
    {
        return ['period' => $month->period, 'state' => $month->state, 'reconciled' => $month->source->reconciled,
            'closing_bank_cash' => $month->closing->bankCash, 'reviewed_by' => $month->source->reviewedBy,
            'reviewed_on' => $month->source->reviewedOn, 'pre_trading_evidence' => $month->preTradingEvidence,
            'revenue' => $month->profit->revenue, 'operating_expenses' => $month->profit->operatingExpenses,
            'depreciation' => $month->profit->depreciation, 'interest' => $month->profit->interest,
            'other_income' => $month->profit->otherIncome, 'profit_before_tax' => $month->profit->profitBeforeTax,
            'income_tax_expense' => $month->profit->incomeTaxExpense];
    }

    /**
     * A stream's schedule is stored as a sparse map keyed by month, because a
     * month with no activity is genuinely absent rather than zero. It is
     * presented as a period-sorted list so the screen shows the months in the
     * order they occur, whatever order they were written in.
     *
     * @return StreamPayload
     */
    private function stream(RevenueStream $stream): array
    {
        $schedule = $stream->schedule;
        ksort($schedule);

        return [
            'id' => $stream->id, 'name' => $stream->name, 'unit' => $stream->unit, 'price' => $stream->price,
            'preparation_hours_per_unit' => $stream->preparationHoursPerUnit,
            'delivery_hours_per_unit' => $stream->deliveryHoursPerUnit,
            'in_base_case' => $stream->inBaseCase, 'evidence_status' => $stream->evidenceStatus,
            // Mapping over two arrays reindexes, so the period keys become the
            // ordered list the screen renders.
            'schedule' => array_map(
                fn (string $period, StreamMonth $month): array => [
                    'period' => $period, 'booked_units' => $month->bookedUnits, 'delivery_units' => $month->deliveryUnits,
                    'invoiced_units' => $month->invoicedUnits, 'founder_hours' => $month->founderHours,
                    'additional_capacity_units' => $month->additionalCapacityUnits, 'provider' => $month->provider,
                    'provider_unit_cost' => $month->providerUnitCost, 'no_cost_basis' => $month->noCostBasis,
                ],
                array_keys($schedule),
                $schedule,
            ),
        ];
    }

    /**
     * An unflagged stream is reported rather than counted as captured. A rule
     * that compares base-case revenue cannot read an absent flag as exclusion,
     * so the gap is named here instead of being left for the rule to discover.
     *
     * @param  list<RevenueStream>  $streams
     * @param  callable(int,string):string  $counted
     */
    private function streamDetail(array $streams, callable $counted): string
    {
        $detail = $counted(count($streams), 'stream');
        $unflagged = count(array_filter($streams, fn (RevenueStream $stream): bool => $stream->inBaseCase === null));

        return $unflagged === 0
            ? $detail
            : $detail.' '.$unflagged.' of them '.($unflagged === 1 ? 'has' : 'have').' no base-case flag.';
    }

    /** @return list<FounderMonthPayload> */
    private function founderMonths(?ForecastModelInputs $inputs): array
    {
        $months = $inputs->founderMonths ?? [];
        ksort($months);

        return array_map(
            fn (string $period, FounderMonth $month): array => [
                'period' => $period, 'available_hours' => $month->availableHours,
                'administration_hours' => $month->administrationHours,
                'business_development_hours' => $month->businessDevelopmentHours,
            ],
            array_keys($months),
            $months,
        );
    }

    /**
     * A funding source as the form shows it back.
     *
     * The draw the ledger builds from it is not presented as a field: its
     * identity, direction and category all follow from the source, and
     * showing them would invite an advisor to edit values the form does not
     * accept.
     *
     * @return FundingPayload
     */
    private function fundingSource(FundingSource $funding): array
    {
        return ['id' => $funding->id, 'source' => $funding->source, 'identity' => $funding->identity,
            'type' => $funding->type, 'amount' => $funding->amount, 'commitment' => $funding->commitment,
            'available_from' => $funding->availableFrom, 'drawn_on' => $funding->drawnOn,
            'restriction' => $funding->restriction, 'verified' => $funding->verified];
    }

    /**
     * How many reviewed months carry the profit a tax chain can read.
     *
     * A reconciled month without a profit figure and a month with one nobody
     * reconciled are both months the chain cannot use, and neither is visible
     * from a count of reviewed months. Saying how many are usable is the
     * difference between an advisor knowing the tax schedule stops early and
     * finding out from a release verdict.
     *
     * @param  list<ActualMonth>  $months
     * @param  callable(int,string):string  $counted
     */
    private function actualsDetail(array $months, callable $counted): string
    {
        $detail = $counted(count($months), 'month');
        if ($months === []) {
            return $detail;
        }
        $usable = count(array_filter($months, fn (ActualMonth $month): bool => $month->source->reconciled === true
            && $month->profit->profitBeforeTax !== null));

        return $detail.' '.($usable === count($months)
            ? 'Each one is reconciled and states its profit before tax.'
            : $usable.' of them are reconciled and state a profit before tax, which is where the monthly tax chain stops.');
    }

    /**
     * A cost's position within a day is derived from the order it is listed
     * in, so it is not presented back as a field: showing it would invite an
     * advisor to edit a value the form does not accept.
     *
     * @return FixedCostPayload
     */
    private function fixedCost(FixedCost $cost): array
    {
        return ['id' => $cost->id, 'source' => $cost->source, 'rate' => $cost->rate, 'quantity' => $cost->quantity,
            'frequency' => $cost->frequency, 'first_invoice_on' => $cost->firstInvoiceOn,
            'last_invoice_on' => $cost->lastInvoiceOn, 'creditor_days' => $cost->creditorDays, 'reviewed' => $cost->reviewed];
    }

    /**
     * What the overheads recognise a month, and what is still unanswered.
     *
     * The monthly figure is the one the whole section exists to produce, so
     * it is stated where an advisor is deciding whether to select the model
     * rather than left to be inferred from a list of rates and cadences. It
     * is withheld as soon as one cost cannot be valued: the sum of the costs
     * that happen to be priced is not the business's fixed cost.
     *
     * @param  callable(int,string):string  $counted
     */
    private function fixedCostDetail(?ForecastModelInputs $inputs, callable $counted): string
    {
        $costs = $inputs->fixedCosts ?? [];
        $detail = $counted(count($costs), 'cost').' '.match ($inputs?->fixedCostScheduleComplete) {
            true => 'The schedule is recorded as complete.',
            false => 'The schedule is recorded as incomplete, so no monthly fixed cost is stated.',
            default => 'Nobody has said whether every cost is recorded, so no monthly fixed cost is stated.',
        };
        if ($costs === [] || $inputs === null) {
            return $detail;
        }
        $monthly = array_values(array_unique(array_values($this->run->fixedCosts($inputs)['totals'])));
        $unpriced = count(array_filter($costs, fn (FixedCost $cost): bool => $cost->rate === null || $cost->quantity === null));

        return $unpriced > 0 || count($monthly) !== 1 || $monthly[0] === null
            ? $detail.' '.$unpriced.' of them carry no rate or quantity, so no monthly fixed cost is stated.'
            : $detail.' They recognise '.DriverNumbers::money(BigDecimal::of($monthly[0])).' a month.';
    }

    /**
     * A settlement's direction and its position within a day are both derived
     * from the item it settles and the order it is listed in, so neither is
     * presented back as a field: showing them would invite an advisor to edit
     * a value the form does not accept.
     *
     * @return TradeItemPayload
     */
    private function tradeItem(TradeItem $item): array
    {
        return [
            'id' => $item->id, 'source' => $item->source, 'kind' => $item->kind,
            'raised_on' => $item->raisedOn, 'amount' => $item->amount,
            // Absent settlement terms stay absent. An item nobody has given
            // terms for is not an item that settles with nothing.
            'settlements' => $item->settlements === null ? null : array_map(
                fn (CashMovement $movement): array => [
                    'id' => $movement->id, 'source' => $movement->source, 'date' => $movement->date,
                    'amount' => $movement->amount, 'category' => $movement->category, 'verified' => $movement->verified,
                ],
                $item->settlements,
            ),
        ];
    }

    /** @return OwnerPayPayload */
    private function ownerPayStep(OwnerPayStep $step): array
    {
        return ['effective_on' => $step->effectiveOn, 'weekly_amount' => $step->weeklyAmount,
            'trigger' => $step->trigger, 'threshold' => $step->threshold];
    }

    /** @return AssetPayload */
    private function asset(AssetInput $asset): array
    {
        return ['id' => $asset->id, 'source' => $asset->source, 'cost' => $asset->cost,
            'classification' => $asset->classification, 'policy_source' => $asset->policySource,
            'reviewed' => $asset->reviewed, 'paid_on' => $asset->paidOn,
            'recognition_start_on' => $asset->recognitionStartOn, 'life_months' => $asset->lifeMonths,
            'residual' => $asset->residual, 'method' => $asset->method,
            'opening_depreciation' => $asset->openingDepreciation];
    }

    /** @return TaxPayload */
    private function tax(?ForecastModelInputs $inputs): array
    {
        $policy = $inputs?->taxPolicy;

        return [
            'entity_basis' => $policy?->entityBasis, 'rate_percent' => $policy?->ratePercent,
            'losses_eligible' => $policy?->lossesEligible, 'policy_source' => $policy?->policySource,
            'eligibility_source' => $policy?->eligibilitySource, 'version' => $policy?->version,
            'periods' => array_map(
                fn (TaxPeriod $period): array => ['start_on' => $period->startOn, 'end_on' => $period->endOn,
                    'accounting_profit' => $period->accountingProfit, 'tax_adjustments' => $period->taxAdjustments],
                $inputs->taxPeriods ?? [],
            ),
        ];
    }

    /**
     * What the funding inventory holds, and whether anybody has finished it.
     *
     * A count of rows is not a complete inventory. The section counts as
     * captured only once the advisor has said every source is recorded,
     * because the funding total and available funds are both stated off that
     * declaration, and the sum of a partial list is not a total.
     *
     * @param  callable(int,string):string  $counted
     */
    private function fundingDetail(?ForecastModelInputs $inputs, callable $counted): string
    {
        $sources = $inputs->fundingSources ?? [];
        $complete = $inputs?->fundingInventoryComplete;
        $unpriced = count(array_filter($sources, fn (FundingSource $funding): bool => $funding->amount === null));
        $detail = $counted(count($sources), 'source').' '.match ($complete) {
            true => 'The inventory is recorded as complete.',
            false => 'The inventory is recorded as incomplete, so no funding total is stated.',
            default => 'Nobody has said whether every source is recorded, so no funding total is stated.',
        };

        return $unpriced === 0 ? $detail : $detail.' '.$unpriced.' of them carry no amount.';
    }

    /**
     * Two problems the engine will refuse to project around are named here
     * rather than counted as captured: an item whose settlement terms nobody
     * has recorded, and a settlement dated before the forecast opens, whose
     * cash belongs in the opening balance instead.
     *
     * @param  list<TradeItem>  $items
     * @param  callable(int,string):string  $counted
     */
    private function tradeDetail(array $items, ?ForecastModelInputs $inputs, callable $counted): string
    {
        $detail = $counted(count($items), 'item');
        $unsettled = count(array_filter($items, fn (TradeItem $item): bool => $item->settlements === null));
        if ($unsettled > 0) {
            $detail .= ' '.$unsettled.' of them '.($unsettled === 1 ? 'has' : 'have').' no settlement terms.';
        }
        $opens = ($inputs?->calendar->startMonth ?? '').'-01';
        $early = 0;
        foreach ($items as $item) {
            foreach ($item->settlements ?? [] as $settlement) {
                $early += $settlement->date !== null && $settlement->date < $opens ? 1 : 0;
            }
        }

        return $early === 0
            ? $detail
            : $detail.' '.$early.' settlement'.($early === 1 ? ' is' : 's are').' dated before the forecast opens; that cash belongs in the opening balance.';
    }

    /**
     * @param  list<AssetInput>  $assets
     * @param  callable(int,string):string  $counted
     */
    private function assetDetail(array $assets, callable $counted): string
    {
        $detail = $counted(count($assets), 'asset');
        $unreviewed = count(array_filter(
            $assets,
            fn (AssetInput $asset): bool => $asset->reviewed !== true
                && DriverNumbers::read($asset->cost)?->isGreaterThanOrEqualTo(self::REVIEW_AT) === true,
        ));

        return $unreviewed === 0
            ? $detail
            : $detail.' '.$unreviewed.' at or above '.self::REVIEW_AT.' NZD '.($unreviewed === 1 ? 'has' : 'have').' no reviewed classification.';
    }

    /**
     * Every blank policy field is a field the tax projection reports missing,
     * so the ones still blank are named rather than summarised as incomplete.
     *
     * @param  callable(int,string):string  $counted
     */
    private function taxDetail(?ForecastModelInputs $inputs, callable $counted): string
    {
        $policy = $inputs?->taxPolicy;
        if ($policy === null) {
            return 'Not captured.';
        }
        $blank = [];
        foreach ($policy->jsonSerialize() as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $blank[] = $key;
            }
        }
        // A policy that exists means a reconstruction that exists, so the
        // periods beside it are a list rather than an absence.
        $detail = $counted(count($inputs->taxPeriods), 'period');

        return $blank === [] ? $detail : $detail.' Policy still needs: '.implode(', ', $blank).'.';
    }

    /**
     * What the engine makes of the reconstruction as it stands.
     *
     * The sections above report what has been recorded; this reports whether
     * the recording adds up to something the engine can project. The two
     * answer different questions, and a complete-looking set of sections can
     * still fail to project.
     *
     * @return ProjectionPayload
     */
    private function projection(ForecastModelInputs $inputs): array
    {
        $run = $this->run->revenue($inputs);
        $priced = array_filter($run['months'], fn (array $month): bool => $month['base_revenue'] !== null);

        return ['engine_version' => $run['engine_version'], 'status' => $run['status'],
            'months_projected' => count($run['months']), 'base_revenue_months' => count($priced),
            // Capped for the screen. The count beside each reason already says
            // how far it reaches, so a longer list adds length, not meaning.
            'missing' => array_slice($run['missing'], 0, 12), 'errors' => array_slice($run['errors'], 0, 12),
            'refusal' => $run['refusal']];
    }

    /**
     * The dated cash the reconstruction implies, once the engine has run it.
     *
     * The deficit and the shortfall are passed through as the engine reports
     * them and never combined: a deficit is already inside the shortfall, so
     * a reader who adds them counts the same hole twice.
     *
     * @return CashPayload
     */
    private function cash(ForecastModelInputs $inputs): array
    {
        $run = $this->run->cash($inputs);

        return ['status' => $run['status'], 'movements_counted' => $run['movements_counted'],
            'settled_before_opening' => $run['settled_before_opening'], 'lowest_cash' => $run['lowest_cash'],
            'lowest_cash_date' => $run['lowest_cash_date'], 'cash_deficit' => $run['cash_deficit'],
            'reserve_target' => $run['reserve_target'], 'reserve_shortfall' => $run['reserve_shortfall'],
            'missing' => array_slice($run['missing'], 0, 12), 'refusal' => $run['refusal']];
    }

    /** @return list<SectionPayload> */
    private function sections(?ForecastModelInputs $inputs): array
    {
        $counted = fn (int $count, string $noun): string => $count === 0
            ? 'Not captured.'
            : $count.' '.($count === 1 ? $noun : $noun.'s').' captured.';

        return [
            ['key' => 'calendar', 'label' => 'Forecast calendar', 'captured' => $inputs !== null, 'editable' => true,
                'detail' => $inputs === null ? 'Not captured.' : $inputs->calendar->startMonth.' for '.$inputs->calendar->months.' months.'],
            ['key' => 'cash_opening', 'label' => 'Opening cash', 'captured' => $inputs?->cashOpening?->amount !== null, 'editable' => true,
                'detail' => $inputs?->cashOpening?->amount === null ? 'Not captured.' : 'Opening balance recorded with its source.'],
            ['key' => 'reserve_policy', 'label' => 'Reserve policy', 'captured' => ($inputs?->reservePolicy?->missing() ?? ['x']) === [], 'editable' => true,
                'detail' => $inputs?->reservePolicy === null ? 'Not captured.' : ($inputs->reservePolicy->missing() === []
                    ? 'Complete, with a stated cost basis.' : 'Incomplete: '.implode(', ', $inputs->reservePolicy->missing()).'.')],
            ['key' => 'actuals', 'label' => 'Reviewed actuals', 'captured' => ($inputs->actualMonths ?? []) !== [], 'editable' => true,
                'detail' => $this->actualsDetail($inputs->actualMonths ?? [], $counted)],
            ['key' => 'streams', 'label' => 'Revenue streams', 'captured' => ($inputs->streams ?? []) !== [], 'editable' => true,
                'detail' => $this->streamDetail($inputs->streams ?? [], $counted)],
            ['key' => 'founder_months', 'label' => 'Founder capacity', 'captured' => ($inputs->founderMonths ?? []) !== [], 'editable' => true,
                'detail' => $counted(count($inputs->founderMonths ?? []), 'month')],
            ['key' => 'funding_sources', 'label' => 'Funding sources', 'captured' => $inputs?->fundingInventoryComplete === true, 'editable' => true,
                'detail' => $this->fundingDetail($inputs, $counted)],
            ['key' => 'fixed_costs', 'label' => 'Fixed costs', 'captured' => $inputs?->fixedCostScheduleComplete === true, 'editable' => true,
                'detail' => $this->fixedCostDetail($inputs, $counted)],
            ['key' => 'trade_items', 'label' => 'Trade terms', 'captured' => ($inputs->tradeItems ?? []) !== [], 'editable' => true,
                'detail' => $this->tradeDetail($inputs->tradeItems ?? [], $inputs, $counted)],
            ['key' => 'owner_pay_steps', 'label' => 'Owner pay schedule', 'captured' => ($inputs->ownerPaySteps ?? []) !== [], 'editable' => true,
                'detail' => $counted(count($inputs->ownerPaySteps ?? []), 'step')],
            ['key' => 'assets', 'label' => 'Assets and depreciation', 'captured' => ($inputs->assets ?? []) !== [], 'editable' => true,
                'detail' => $this->assetDetail($inputs->assets ?? [], $counted)],
            ['key' => 'tax', 'label' => 'Tax policy and timing', 'captured' => $inputs?->taxPolicy !== null, 'editable' => true,
                'detail' => $this->taxDetail($inputs, $counted)],
            // Held by the reconstruction and captured nowhere on this screen.
            // A table that claims to say what the model holds has to say so,
            // rather than leave an advisor to assume the list is complete.
            ['key' => 'bank_opening', 'label' => 'Bank reconciliation opening', 'captured' => $inputs?->bankOpening !== null, 'editable' => false,
                'detail' => $inputs?->bankOpening === null ? 'Not captured.' : 'Opening analytical, bank and restricted balances recorded.'],
        ];
    }
}
