<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\Entrepreneurs\Forecast\ActualBalances;
use App\Services\Entrepreneurs\Forecast\ActualMonth;
use App\Services\Entrepreneurs\Forecast\ActualProfit;
use App\Services\Entrepreneurs\Forecast\ActualSource;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\ReservePolicy;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelStore;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Records the version-2 reconstruction an advisor builds beside a plan.
 *
 * Saving is not selecting. An advisor can build and revise a reconstruction
 * with no effect on any release verdict; selecting it is the separate act that
 * makes the plan a version-2 plan, and this slice still evaluates every plan
 * against the twenty enabled rules either way.
 *
 * The sections are posted in groups rather than as one form. Each group carries
 * the sections it does not edit through unchanged, so recording founder hours
 * cannot quietly discard a reserve policy somebody else recorded first.
 *
 * @phpstan-type StreamMonthRow array{period:string,booked_units:string|null,delivery_units:string|null,invoiced_units:string|null,founder_hours:string|null,additional_capacity_units:string|null,provider:string|null,provider_unit_cost:string|null,no_cost_basis:string|null}
 * @phpstan-type StreamRow array{id:string,name:string,unit:string|null,price:string|null,preparation_hours_per_unit:string|null,delivery_hours_per_unit:string|null,in_base_case:string|null,evidence_status:string|null,schedule:list<StreamMonthRow>}
 * @phpstan-type FounderRow array{period:string,available_hours:string|null,administration_hours:string|null,business_development_hours:string|null}
 */
final class EntrepreneurForecastModelController extends Controller
{
    public function show(Request $request, EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan,
        ForecastModelStore $store, AdvisorForecastModelPayload $payload): Response
    {
        $actor = $this->authorise($request, $entrepreneurProfile, $businessPlan);
        $url = route('advisor.entrepreneurs.plans.forecast-model.show', [$entrepreneurProfile, $businessPlan], absolute: false);

        return Inertia::render('advisor/entrepreneurs/ForecastModel', [
            'model' => $payload->build($store->forPlan($businessPlan)),
            'engine' => $payload->engine(),
            'can_edit' => Gate::forUser($actor)->allows('assess', $entrepreneurProfile),
            'urls' => ['review' => $url, 'save' => $url, 'drivers' => $url.'/drivers', 'terms' => $url.'/terms', 'selection' => $url.'/selection',
                'gate' => route('advisor.entrepreneurs.plans.release-gate.show', [$entrepreneurProfile, $businessPlan], absolute: false),
                'workspace' => route('advisor.entrepreneurs.show', $entrepreneurProfile, absolute: false)],
        ]);
    }

    public function save(Request $request, EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan, ForecastModelStore $store): RedirectResponse
    {
        $actor = $this->editor($request, $entrepreneurProfile, $businessPlan);
        $data = $request->validate([
            'expected_revision' => ['present', 'nullable', 'integer', 'min:0'],
            'start_month' => ['required', 'string', 'regex:/^[1-9]\d{3}-(0[1-9]|1[0-2])$/'],
            'months' => ['required', 'integer', 'min:1', 'max:60'],
            'opening_amount' => ['present', 'nullable', 'string', 'max:30'],
            'opening_source' => ['present', 'nullable', 'string', 'max:255'],
            'opening_verified' => ['present', 'nullable', 'boolean'],
            'reserve_monthly_fixed_costs' => ['present', 'nullable', 'string', 'max:30'],
            'reserve_months_covered' => ['required', 'integer', 'min:0', 'max:60'],
            'reserve_includes_owner_pay' => ['present', 'nullable', 'boolean'],
            'reserve_one_off_cash_costs' => ['present', 'nullable', 'string', 'max:30'],
            'reserve_contingency_rate' => ['required', 'string', 'max:30'],
            'reserve_basis' => ['present', 'nullable', 'string', 'max:255'],
            'actuals' => ['present', 'array', 'max:60'],
            'actuals.*.period' => ['required', 'string', 'regex:/^[1-9]\d{3}-(0[1-9]|1[0-2])$/'],
            'actuals.*.state' => ['required', 'in:trading,pre_trading'],
            'actuals.*.reconciled' => ['present', 'nullable', 'boolean'],
            'actuals.*.closing_bank_cash' => ['present', 'nullable', 'string', 'max:30'],
            'actuals.*.reviewed_by' => ['present', 'nullable', 'string', 'max:120'],
            'actuals.*.reviewed_on' => ['present', 'nullable', 'date_format:Y-m-d'],
            'actuals.*.pre_trading_evidence' => ['present', 'nullable', 'string', 'max:500'],
        ]);
        $periods = array_column($data['actuals'], 'period');
        if (count($periods) !== count(array_unique($periods))) {
            throw ValidationException::withMessages(['actuals' => 'Each reporting month can be reviewed once. Remove the duplicate month.']);
        }

        try {
            $inputs = $this->inputs($store, $businessPlan, $data);
            // A shortened calendar can strand months that streams already
            // occupy, which is the same unprojectable state the driver form
            // refuses from its own side.
            $this->rejectMonthsOutsideCalendar($inputs, 'model');
            $store->save($businessPlan, $inputs, $actor, $data['expected_revision']);
        } catch (\InvalidArgumentException $e) {
            // The engine's own constructors are the validation of record. A
            // value it refuses is reported against the form rather than
            // stored as a reconstruction the engine could not read back.
            throw ValidationException::withMessages(['model' => $e->getMessage()]);
        }

        return back()->with('status', 'forecast-model-saved');
    }

    /**
     * Records the revenue streams and the founder capacity behind them.
     *
     * A stream carries an explicit base-case flag with three states, because a
     * stream nobody has classified is not the same as a stream excluded from
     * the base case. Recording the difference here is what lets a later rule
     * report an unflagged stream as unverifiable rather than silently leaving
     * it out of a base-revenue total.
     */
    public function drivers(Request $request, EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan, ForecastModelStore $store): RedirectResponse
    {
        $actor = $this->editor($request, $entrepreneurProfile, $businessPlan);
        $month = ['required', 'string', 'regex:/^[1-9]\d{3}-(0[1-9]|1[0-2])$/'];
        $number = ['present', 'nullable', 'string', 'max:30'];
        $data = $request->validate([
            'expected_revision' => ['present', 'nullable', 'integer', 'min:0'],
            'streams' => ['present', 'array', 'max:20'],
            'streams.*.id' => ['required', 'string', 'max:60'],
            'streams.*.name' => ['required', 'string', 'max:160'],
            'streams.*.unit' => ['present', 'nullable', 'string', 'max:60'],
            'streams.*.price' => $number,
            'streams.*.preparation_hours_per_unit' => $number,
            'streams.*.delivery_hours_per_unit' => $number,
            'streams.*.in_base_case' => ['present', 'nullable', 'in:base,upside'],
            'streams.*.evidence_status' => ['present', 'nullable', 'in:verified,own_record,founder_stated,founder_assumption'],
            'streams.*.schedule' => ['present', 'array', 'max:60'],
            'streams.*.schedule.*.period' => $month,
            'streams.*.schedule.*.booked_units' => $number,
            'streams.*.schedule.*.delivery_units' => $number,
            'streams.*.schedule.*.invoiced_units' => $number,
            'streams.*.schedule.*.founder_hours' => $number,
            'streams.*.schedule.*.additional_capacity_units' => $number,
            'streams.*.schedule.*.provider' => ['present', 'nullable', 'string', 'max:160'],
            'streams.*.schedule.*.provider_unit_cost' => $number,
            'streams.*.schedule.*.no_cost_basis' => ['present', 'nullable', 'string', 'max:255'],
            'founder_months' => ['present', 'array', 'max:60'],
            'founder_months.*.period' => $month,
            'founder_months.*.available_hours' => $number,
            'founder_months.*.administration_hours' => $number,
            'founder_months.*.business_development_hours' => $number,
        ]);
        $this->rejectDuplicates($data['streams'], $data['founder_months']);

        try {
            $inputs = $this->driverInputs($store, $businessPlan, $data);
            $this->rejectMonthsOutsideCalendar($inputs);
            $store->save($businessPlan, $inputs, $actor, $data['expected_revision']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['drivers' => $e->getMessage()]);
        }

        return back()->with('status', 'forecast-model-drivers-saved');
    }

    /**
     * Records the trade terms, owner pay steps, assets and tax planning that
     * sit behind the cash ledger.
     *
     * The mapping itself lives in AdvisorForecastTerms, because these four
     * sections carry the identity and ordering invariants the ledger depends
     * on and those are worth reading in one place rather than threaded through
     * a controller action.
     */
    public function terms(Request $request, EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan,
        ForecastModelStore $store, AdvisorForecastTerms $terms): RedirectResponse
    {
        $actor = $this->editor($request, $entrepreneurProfile, $businessPlan);
        $data = $request->validate($terms->rules());
        $model = $store->forPlan($businessPlan);
        $existing = $model === null ? null : $store->inputs($model);
        if ($existing === null) {
            // Depreciation and tax are both projected across a horizon. There
            // is no horizon to project them across yet, so the screen says
            // which step has to come first rather than inventing one.
            throw ValidationException::withMessages([
                'terms' => 'Record the forecast calendar and opening position before the trade, pay, asset and tax terms that sit inside it.',
            ]);
        }

        try {
            $store->save($businessPlan, $terms->apply($existing, $data), $actor, $data['expected_revision']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['terms' => $e->getMessage()]);
        }

        return back()->with('status', 'forecast-model-terms-saved');
    }

    public function selection(Request $request, EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan, ForecastModelStore $store): RedirectResponse
    {
        $actor = $this->editor($request, $entrepreneurProfile, $businessPlan);
        $data = $request->validate(['selected' => ['required', 'boolean']]);
        $model = $store->forPlan($businessPlan);
        if ($model === null) {
            throw ValidationException::withMessages(['selected' => 'Save a reconstruction before selecting it as the plan financial model.']);
        }
        if ($data['selected']) {
            // Selection is a decision about a model somebody has read. A model
            // the engine cannot load is not one, however complete it looks.
            try {
                $store->inputs($model);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['selected' => 'This reconstruction cannot be read back by the engine and cannot be selected: '.$e->getMessage()]);
            }
            $store->select($model, $actor);

            return back()->with('status', 'forecast-model-selected');
        }
        $store->deselect($model, $actor);

        return back()->with('status', 'forecast-model-deselected');
    }

    /**
     * @param  array{expected_revision:int|null,start_month:string,months:int,opening_amount:string|null,opening_source:string|null,opening_verified:bool|null,reserve_monthly_fixed_costs:string|null,reserve_months_covered:int,reserve_includes_owner_pay:bool|null,reserve_one_off_cash_costs:string|null,reserve_contingency_rate:string,reserve_basis:string|null,actuals:list<array{period:string,state:string,reconciled:bool|null,closing_bank_cash:string|null,reviewed_by:string|null,reviewed_on:string|null,pre_trading_evidence:string|null}>}  $data
     */
    private function inputs(ForecastModelStore $store, BusinessPlan $plan, array $data): ForecastModelInputs
    {
        $model = $store->forPlan($plan);
        $existing = $model === null ? null : $store->inputs($model);
        $blank = fn (?string $value): ?string => $value === null || trim($value) === '' ? null : trim($value);

        // Sections this screen does not edit are carried through unchanged.
        // Rebuilding the reconstruction from the form alone would silently drop
        // every stream, asset and tax period a later screen captures.
        return new ForecastModelInputs(
            calendar: new ForecastCalendar($data['start_month'], $data['months']),
            streams: $existing->streams ?? [],
            founderMonths: $existing->founderMonths ?? [],
            cashOpening: new CashOpening($blank($data['opening_amount']), $blank($data['opening_source']), $data['opening_verified']),
            bankOpening: $existing?->bankOpening,
            tradeItems: $existing->tradeItems ?? [],
            ownerPaySteps: $existing->ownerPaySteps ?? [],
            assets: $existing->assets ?? [],
            taxPolicy: $existing?->taxPolicy,
            taxPeriods: $existing->taxPeriods ?? [],
            actualMonths: array_map(fn (array $row): ActualMonth => $this->actual($row, $blank), $data['actuals']),
            reservePolicy: new ReservePolicy(
                $blank($data['reserve_monthly_fixed_costs']), $data['reserve_months_covered'], $data['reserve_includes_owner_pay'],
                $blank($data['reserve_one_off_cash_costs']), $data['reserve_contingency_rate'], $blank($data['reserve_basis']),
            ),
            ownerPaySource: $existing?->ownerPaySource,
            ownerPayFirstPaymentOn: $existing?->ownerPayFirstPaymentOn,
            openingEligibleLosses: $existing?->openingEligibleLosses,
        );
    }

    /**
     * Refuses a reconstruction whose monthly schedules fall outside its own
     * horizon.
     *
     * The revenue projection throws on a schedule that extends beyond the
     * calendar rather than silently discarding the month, so a reconstruction
     * stored in that state cannot be projected at all. The failure would
     * surface later as an engine error with no month attached to it; naming
     * the month here is the difference between a finding and a crash.
     *
     * Both forms can reach the state from their own side: the driver form by
     * recording a month beyond the horizon, and the calendar form by
     * shortening the horizon under months already recorded.
     */
    private function rejectMonthsOutsideCalendar(ForecastModelInputs $inputs, ?string $field = null): void
    {
        $calendar = $inputs->calendar;
        $last = $calendar->periods[count($calendar->periods) - 1];
        $recorded = [];
        foreach (array_keys($inputs->founderMonths) as $period) {
            $recorded[] = [$period, 'founder_months'];
        }
        foreach ($inputs->streams as $stream) {
            foreach (array_keys($stream->schedule) as $period) {
                $recorded[] = [$period, 'streams'];
            }
        }
        foreach ($recorded as [$period, $source]) {
            if (! in_array($period, $calendar->periods, true)) {
                // Reported against whichever collection holds the month, so
                // the advisor is sent to the one they have to change. The
                // calendar form overrides that, because there the month is
                // where it was and the horizon is what moved.
                throw ValidationException::withMessages([$field ?? $source => $period.' is recorded against this reconstruction but falls outside its '
                    .'forecast calendar, which runs '.$calendar->startMonth.' to '.$last.'. Change the calendar or the month so they agree.']);
            }
        }
    }

    /**
     * @param  list<StreamRow>  $streams
     * @param  list<FounderRow>  $founderMonths
     */
    private function rejectDuplicates(array $streams, array $founderMonths): void
    {
        $ids = array_map(fn (array $stream): string => trim($stream['id']), $streams);
        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['streams' => 'Each stream needs its own reference. Two streams share one, and findings are reported against it.']);
        }
        foreach ($streams as $index => $stream) {
            $periods = array_column($stream['schedule'], 'period');
            if (count($periods) !== count(array_unique($periods))) {
                throw ValidationException::withMessages(['streams' => 'Stream '.($ids[$index] === '' ? (string) ($index + 1) : $ids[$index]).' has the same month twice. Record each month once.']);
            }
        }
        $months = array_column($founderMonths, 'period');
        if (count($months) !== count(array_unique($months))) {
            throw ValidationException::withMessages(['founder_months' => 'Each month can hold one founder capacity record. Remove the duplicate month.']);
        }
    }

    /**
     * @param  array{expected_revision:int|null,streams:list<StreamRow>,founder_months:list<FounderRow>}  $data
     */
    private function driverInputs(ForecastModelStore $store, BusinessPlan $plan, array $data): ForecastModelInputs
    {
        $model = $store->forPlan($plan);
        $existing = $model === null ? null : $store->inputs($model);
        if ($existing === null) {
            // A monthly schedule only means something inside a horizon. Rather
            // than invent a calendar to hang these months on, the screen says
            // which step has to come first.
            throw ValidationException::withMessages([
                'drivers' => 'Record the forecast calendar and opening position before the revenue streams that sit inside it.',
            ]);
        }
        $blank = fn (?string $value): ?string => $value === null || trim($value) === '' ? null : trim($value);
        $founderMonths = [];
        foreach ($data['founder_months'] as $row) {
            $founderMonths[$row['period']] = new FounderMonth(
                $blank($row['available_hours']), $blank($row['administration_hours']), $blank($row['business_development_hours']),
            );
        }

        // Everything this form does not show is read back from the stored
        // reconstruction and written out unchanged.
        return new ForecastModelInputs(
            calendar: $existing->calendar,
            streams: array_map(fn (array $row): RevenueStream => $this->stream($row, $blank), $data['streams']),
            founderMonths: $founderMonths,
            cashOpening: $existing->cashOpening,
            bankOpening: $existing->bankOpening,
            tradeItems: $existing->tradeItems,
            ownerPaySteps: $existing->ownerPaySteps,
            assets: $existing->assets,
            taxPolicy: $existing->taxPolicy,
            taxPeriods: $existing->taxPeriods,
            actualMonths: $existing->actualMonths,
            reservePolicy: $existing->reservePolicy,
            ownerPaySource: $existing->ownerPaySource,
            ownerPayFirstPaymentOn: $existing->ownerPayFirstPaymentOn,
            openingEligibleLosses: $existing->openingEligibleLosses,
        );
    }

    /**
     * @param  StreamRow  $row
     * @param  callable(?string):?string  $blank
     */
    private function stream(array $row, callable $blank): RevenueStream
    {
        $schedule = [];
        foreach ($row['schedule'] as $entry) {
            $schedule[$entry['period']] = new StreamMonth(
                $blank($entry['booked_units']), $blank($entry['delivery_units']), $blank($entry['invoiced_units']),
                $blank($entry['founder_hours']), $blank($entry['additional_capacity_units']),
                $blank($entry['provider']), $blank($entry['provider_unit_cost']), $blank($entry['no_cost_basis']),
            );
        }

        return new RevenueStream(
            id: trim($row['id']),
            name: trim($row['name']),
            unit: $blank($row['unit']),
            price: $blank($row['price']),
            preparationHoursPerUnit: $blank($row['preparation_hours_per_unit']),
            deliveryHoursPerUnit: $blank($row['delivery_hours_per_unit']),
            // Three states, not two. An unclassified stream stays null so a
            // rule can report it as unverified rather than read it as upside.
            inBaseCase: match ($row['in_base_case']) {
                'base' => true,
                'upside' => false,
                default => null,
            },
            evidenceStatus: $blank($row['evidence_status']),
            schedule: $schedule,
        );
    }

    /**
     * @param  array{period:string,state:string,reconciled:bool|null,closing_bank_cash:string|null,reviewed_by:string|null,reviewed_on:string|null,pre_trading_evidence:string|null}  $row
     * @param  callable(?string):?string  $blank
     */
    private function actual(array $row, callable $blank): ActualMonth
    {
        $balances = fn (?string $cash): ActualBalances => new ActualBalances($cash, null, null, null, null, null);

        return new ActualMonth(
            period: $row['period'],
            state: $row['state'],
            source: new ActualSource(null, 'manual', null, null, null, null, null, 'NZD', null,
                $blank($row['reviewed_by']), $blank($row['reviewed_on']), null, $row['reconciled']),
            profit: new ActualProfit(null, null, null, null, null, null, null),
            // An opening balance this screen has not captured stays absent; it
            // is not implied from the previous month's closing figure.
            opening: $balances(null),
            closing: $balances($blank($row['closing_bank_cash'])),
            movements: null,
            movementAccounts: null,
            closingObligations: null,
            preTradingEvidence: $blank($row['pre_trading_evidence']),
        );
    }

    private function editor(Request $request, EntrepreneurProfile $profile, BusinessPlan $plan): User
    {
        $actor = $this->authorise($request, $profile, $plan);
        Gate::forUser($actor)->authorize('assess', $profile);

        return $actor;
    }

    private function authorise(Request $request, EntrepreneurProfile $profile, BusinessPlan $plan): User
    {
        Gate::authorize('reviewBrief', $profile);
        abort_unless($plan->source_type === BusinessPlan::SOURCE_ENTREPRENEUR && (string) $plan->entrepreneur_profile_id === (string) $profile->getKey(), 404);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
