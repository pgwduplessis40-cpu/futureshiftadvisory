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
use App\Services\Entrepreneurs\Forecast\ReservePolicy;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelStore;
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
            'urls' => ['review' => $url, 'save' => $url, 'selection' => $url.'/selection',
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
            $store->save($businessPlan, $this->inputs($store, $businessPlan, $data), $actor, $data['expected_revision']);
        } catch (\InvalidArgumentException $e) {
            // The engine's own constructors are the validation of record. A
            // value it refuses is reported against the form rather than
            // stored as a reconstruction the engine could not read back.
            throw ValidationException::withMessages(['model' => $e->getMessage()]);
        }

        return back()->with('status', 'forecast-model-saved');
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
