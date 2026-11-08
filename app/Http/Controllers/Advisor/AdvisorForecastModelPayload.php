<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Models\EntrepreneurForecastModel;
use App\Services\Entrepreneurs\Forecast\ActualMonth;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastEngineVersion;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelStore;
use App\Services\Entrepreneurs\Forecast\StreamMonth;

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
 * @phpstan-type ActualPayload array{period:string,state:string|null,reconciled:bool|null,closing_bank_cash:string|null,reviewed_by:string|null,reviewed_on:string|null,pre_trading_evidence:string|null}
 * @phpstan-type StreamMonthPayload array{period:string,booked_units:string|null,delivery_units:string|null,invoiced_units:string|null,founder_hours:string|null,additional_capacity_units:string|null,provider:string|null,provider_unit_cost:string|null,no_cost_basis:string|null}
 * @phpstan-type StreamPayload array{id:string,name:string,unit:string|null,price:string|null,preparation_hours_per_unit:string|null,delivery_hours_per_unit:string|null,in_base_case:bool|null,evidence_status:string|null,schedule:list<StreamMonthPayload>}
 * @phpstan-type FounderMonthPayload array{period:string,available_hours:string|null,administration_hours:string|null,business_development_hours:string|null}
 * @phpstan-type SectionPayload array{key:string,label:string,captured:bool,editable:bool,detail:string}
 * @phpstan-type ModelPayload array{revision:int,engine_version:string,readable:bool,load_error:string|null,selected:bool,selected_at:string|null,selected_by:string|null,calendar:CalendarPayload|null,cash_opening:CashOpeningPayload,reserve:ReservePayload,actuals:list<ActualPayload>,streams:list<StreamPayload>,founder_months:list<FounderMonthPayload>,sections:list<SectionPayload>}
 */
final readonly class AdvisorForecastModelPayload
{
    /** Sections this screen edits, in the order it presents them. */
    public const EDITABLE = ['calendar', 'cash_opening', 'reserve_policy', 'actuals', 'streams', 'founder_months'];

    public function __construct(private ForecastModelStore $store) {}

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
            'reviewed_on' => $month->source->reviewedOn, 'pre_trading_evidence' => $month->preTradingEvidence];
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
                'detail' => $counted(count($inputs->actualMonths ?? []), 'month')],
            ['key' => 'streams', 'label' => 'Revenue streams', 'captured' => ($inputs->streams ?? []) !== [], 'editable' => true,
                'detail' => $this->streamDetail($inputs->streams ?? [], $counted)],
            ['key' => 'founder_months', 'label' => 'Founder capacity', 'captured' => ($inputs->founderMonths ?? []) !== [], 'editable' => true,
                'detail' => $counted(count($inputs->founderMonths ?? []), 'month')],
            ['key' => 'trade_items', 'label' => 'Trade terms', 'captured' => ($inputs->tradeItems ?? []) !== [], 'editable' => false,
                'detail' => $counted(count($inputs->tradeItems ?? []), 'item')],
            ['key' => 'owner_pay_steps', 'label' => 'Owner pay schedule', 'captured' => ($inputs->ownerPaySteps ?? []) !== [], 'editable' => false,
                'detail' => $counted(count($inputs->ownerPaySteps ?? []), 'step')],
            ['key' => 'assets', 'label' => 'Assets and depreciation', 'captured' => ($inputs->assets ?? []) !== [], 'editable' => false,
                'detail' => $counted(count($inputs->assets ?? []), 'asset')],
            ['key' => 'tax', 'label' => 'Tax policy and timing', 'captured' => $inputs?->taxPolicy !== null, 'editable' => false,
                'detail' => $inputs?->taxPolicy === null ? 'Not captured.' : $counted(count($inputs->taxPeriods), 'period')],
        ];
    }
}
