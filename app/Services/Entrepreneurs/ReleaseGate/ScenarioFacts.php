<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\Forecast\ActualsRebase;
use App\Services\Entrepreneurs\Forecast\ActualsWindow;
use App\Services\Entrepreneurs\Forecast\CashMovement;
use App\Services\Entrepreneurs\Forecast\ForecastRun;
use App\Services\Entrepreneurs\Forecast\ScenarioApplication;
use App\Services\Entrepreneurs\Forecast\ScenarioCatalogue;
use App\Services\Entrepreneurs\Forecast\ScenarioDefinition;
use App\Services\Entrepreneurs\Forecast\ScenarioRunner;
use App\Services\Entrepreneurs\Forecast\ScenarioShock;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * The stress dimensions SS-02 reads, measured from the reconstruction.
 *
 * SS-02 asks a narrow question: for each input dimension the plan claims to
 * have stressed, what size of shock was requested, what size did the model
 * actually apply, and if the second is smaller, what capped it. Nothing on the
 * platform stated any of that before this, so the rule reported every
 * dimension as not established.
 *
 * Every effective figure here is measured rather than assumed. A requested
 * factor is what the catalogue asked for; an effective one is read back off
 * the shocked model, because the two genuinely differ - founder capacity binds
 * before a volume shock does, a cost shock reaches only the categories it
 * names, and a collections delay reaches nothing at all when no eligible
 * receipt falls inside the window. Reporting a requested figure as though it
 * were the applied one would overstate the test, which is the single thing
 * this rule exists to catch.
 */
final class ScenarioFacts implements ProvidesMethodology
{
    public const VERSION = 'phase-2e.1';

    /**
     * The cash categories a collections-delay shock may reach.
     *
     * Customer receipts only. Financing draws arrive when a lender releases
     * them and tax refunds when the department pays them; neither is a trade
     * collection, and delaying them would model a different event under the
     * name of this one.
     */
    public const COLLECTION_TARGETS = ['operating_receipt'];

    /** The unit each stress dimension's size is necessarily measured in. */
    private const UNITS = ['revenue_volume' => 'percent', 'slow_start_volume' => 'percent',
        'cost_rate' => 'percent', 'collection_delay' => 'days'];

    /**
     * What each line of the published decomposition contains.
     *
     * Stated on the fact because three of the five group parts the model keeps
     * separately, and a reader comparing them against the model's own figures
     * needs to know which went where.
     *
     * @var array<string,string>
     */
    private const PROFIT_BASIS = [
        'revenue' => 'recurring_streams_plus_one_off_trading_income',
        'variable_costs' => 'direct_costs_one_off_operating_costs_asset_expense_and_deductible_owner_pay',
        'fixed_costs' => 'recognised_recurring_overheads',
        'depreciation' => 'asset_schedule_depreciation',
        'interest' => 'financing_charges_recognised_in_the_month',
    ];

    public function __construct(
        private readonly ScenarioApplication $application = new ScenarioApplication,
        private readonly ForecastRun $run = new ForecastRun,
        private readonly ScenarioRunner $runner = new ScenarioRunner,
        private readonly CashCurveFacts $curve = new CashCurveFacts,
    ) {}

    public static function methodologyIds(): array
    {
        return ['entrepreneur.scenarios_metrics_funding'];
    }

    /** @return list<Fact> */
    public function capture(SourceRecords $source, ForecastModelInputs $inputs): array
    {
        $window = $this->window($source, $inputs);
        if ($window === null) {
            return [];
        }

        try {
            $definitions = ScenarioCatalogue::required($window, $this->streamIds($inputs), self::COLLECTION_TARGETS);
        } catch (\InvalidArgumentException) {
            // The catalogue refuses a set it cannot build: no eligible stream
            // to shock, no collection group to delay, or no forecast month
            // left after the actuals cutoff. SS-02 then reports the dimensions
            // as not established, which is the truthful reading. A
            // reconstruction with nothing left to stress has not been
            // stressed, and saying so beats publishing a dimension that was
            // never tested.
            return [];
        }

        $facts = [];
        foreach ($definitions as $definition) {
            // One shock per fact set, because SS-02 reads one dimension,
            // interval and shock pair per scenario and two records under one
            // key resolve to nothing. The base case declares no shock, and the
            // combined scenario restates the three dimensions its parts
            // already establish on their own, so neither carries a dimension
            // of its own. What they contribute is the ranking and the
            // reconciliation, which are SS-01's and SS-03's to read.
            if (count($definition->shocks) !== 1) {
                continue;
            }
            $facts = [...$facts, ...$this->dimension($source, $inputs, $window, $definition, $definition->shocks[0])];
        }

        return [...$facts, ...$this->curves($source, $inputs, $window, $definitions)];
    }

    /**
     * Each scenario's cash curve and the trough it reaches.
     *
     * SS-01 reconciles a scenario's reported minimum against its own months,
     * so both have to be stated and both have to mean the same thing. The
     * minimum published here is the lowest month-end closing balance, not the
     * ledger's own lowest cash: the ledger tracks the balance after every
     * individual movement and can dip below every month end, so publishing
     * that figure against a curve of month ends would report a trough the
     * curve does not contain and fail a check that is working correctly.
     *
     * The base case is skipped entirely. Its months are already stated by the
     * reconstruction's own ledger facts and its trough beside them, and a
     * second record under either resolves to nothing - which would take
     * MI-01's reading of the base curve with it.
     *
     * @param  list<ScenarioDefinition>  $definitions
     * @return list<Fact>
     */
    private function curves(SourceRecords $source, ForecastModelInputs $inputs, ActualsWindow $window, array $definitions): array
    {
        $run = $this->runner->run($inputs, $window, $definitions);
        if ($run['refusal'] !== null) {
            // A refused scenario set states no curves. The refusal itself is
            // the advisor's to see on the model screen; a gate fact asserting
            // a trough from a run that stopped would be worse than silence.
            return [];
        }

        $at = [$source->reference('forecast_model.drivers')];
        $facts = [];
        foreach ($run['outcomes'] as $outcome) {
            $scenario = $outcome->definition->id;
            $months = $outcome->ledger['months'] ?? [];
            if ($months === [] || $scenario === ScenarioCatalogue::BASE) {
                continue;
            }
            $facts = [...$facts, ...$this->curve->capture($source, $inputs->calendar, $months, $at, $scenario)];
            $trough = $this->curve->trough($months);
            if ($trough !== null) {
                $facts[] = new Fact('scenario.lowest_cash', $trough, scenario: $scenario, unit: 'NZD',
                    basis: 'lowest_month_end_closing_balance', confirmed: true, sources: $at);
            }
            $facts = [...$facts, ...$this->profit($source, $inputs, $scenario, $outcome->profit, $at)];
        }

        return $facts;
    }

    /**
     * One scenario's profit, decomposed the way SS-01 reconciles it.
     *
     * The rule checks profit against revenue less variable costs, fixed costs,
     * depreciation and interest. The model keeps nine parts rather than five,
     * so three of them are grouped and the grouping is stated on the fact
     * rather than left for a reader to infer: direct costs, one-off operating
     * costs, assets charged to expense and deductible owner pay are the costs
     * of trading that are neither fixed, depreciation, nor financing. Revenue
     * is likewise the recurring streams plus the one-off trading income
     * recognised in the month.
     *
     * The grouping is arithmetically closed on purpose. These five sum back to
     * the same profit the model computed from its own nine, so the rule's
     * reconciliation tests the decomposition rather than a rounding of it.
     *
     * A month the model could not complete publishes none of its parts. A
     * decomposition missing one line still adds up to something, and that
     * something would reconcile.
     *
     * @param  array{status:string,scope:string,months:list<array<string,string|null>>,missing:list<string>}|null  $profit
     * @param  list<SourceReference>  $at
     * @return list<Fact>
     */
    private function profit(SourceRecords $source, ForecastModelInputs $inputs, string $scenario, ?array $profit, array $at): array
    {
        if ($profit === null) {
            return [];
        }
        $offsets = array_flip($inputs->calendar->periods);
        $facts = [];
        foreach ($profit['months'] as $month) {
            $period = (string) $month['period'];
            if (! isset($offsets[$period]) || $month['profit'] === null) {
                continue;
            }
            $label = 'month:'.($offsets[$period] + 1);
            $parts = [
                'revenue' => $this->sum($month, ['revenue', 'one_off_income']),
                'variable_costs' => $this->sum($month, ['direct_costs', 'one_off_costs', 'asset_expense', 'owner_pay']),
                'fixed_costs' => $this->sum($month, ['fixed_costs']),
                'depreciation' => $this->sum($month, ['depreciation']),
                'interest' => $this->sum($month, ['interest']),
            ];
            if (in_array(null, $parts, true)) {
                continue;
            }
            foreach ($parts as $name => $value) {
                $facts[] = new Fact('scenario.'.$name, $value, period: $label, scenario: $scenario, unit: 'NZD',
                    basis: self::PROFIT_BASIS[$name], confirmed: true, sources: $at);
            }
            $facts[] = new Fact('scenario.profit', $month['profit'], period: $label, scenario: $scenario,
                unit: 'NZD', basis: $profit['scope'], confirmed: true, sources: $at);
        }

        return $facts;
    }

    /**
     * The named parts added, or none when one of them is unstated.
     *
     * @param  array<string,string|null>  $month
     * @param  list<string>  $names
     */
    private function sum(array $month, array $names): ?string
    {
        $total = BigDecimal::zero();
        foreach ($names as $name) {
            if (($month[$name] ?? null) === null) {
                return null;
            }
            $total = $total->plus((string) $month[$name]);
        }

        return (string) $total->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * The actuals boundary the shocks are held behind.
     *
     * Built at the release snapshot's own as-of date, not at a date derived
     * from the records themselves. Deriving one would make the window agree
     * with whatever had been reviewed and silently close the gap MI-07 exists
     * to report: a plan reviewed to February and submitted in June has four
     * unreconciled months, and a window built from its own records would show
     * none.
     *
     * The entity scope comes from the actuals when there are any, so the
     * rebase can check every later month declares the same one. With no
     * actuals there is no record to check it against, and the plan under
     * evaluation is the honest name for what is being scoped. Actuals naming
     * no entity at all establish no boundary, so no scenario is stated rather
     * than one being held behind a boundary nobody set.
     *
     * An unresolved actuals review stops this too, and the status is checked
     * here rather than left to the window's own guard. That guard raises a
     * LogicException deep inside the shock application, which would travel out
     * through the whole fact capture and take every other producer's facts
     * with it. A plan whose actuals do not reconcile has not been stressed,
     * and SS-02 reporting that is worth more than a gate that cannot run.
     */
    private function window(SourceRecords $source, ForecastModelInputs $inputs): ?ActualsWindow
    {
        $entity = $inputs->actualMonths === [] ? $source->version->planId : $inputs->actualMonths[0]->source->entityId;
        if ($entity === null || trim($entity) === '') {
            return null;
        }

        try {
            $window = (new ActualsRebase)->calculate($inputs->calendar, $source->version->asOf, $entity, $inputs->actualMonths);
        } catch (\InvalidArgumentException) {
            // A rebase the engine refuses states no forecast window, so there
            // is no boundary to hold a shock behind. MI-07 reports the actuals
            // themselves; inventing a window here would let a shock reach a
            // month the refusal exists to protect.
            return null;
        }

        return in_array($window->status, ['pass', 'not_applicable'], true) ? $window : null;
    }

    /**
     * One dimension's facts: what was asked for, what landed, and why they
     * differ when they do.
     *
     * @return list<Fact>
     */
    private function dimension(SourceRecords $source, ForecastModelInputs $inputs, ActualsWindow $window,
        ScenarioDefinition $definition, ScenarioShock $shock): array
    {
        $name = $this->dimensionName($shock);
        if ($name === null) {
            return [];
        }
        $unit = self::UNITS[$name];
        try {
            [$requested, $effective, $reason] = $this->measure($inputs, $window, $definition, $shock, $unit);
        } catch (\InvalidArgumentException|\LogicException) {
            // A shock the engines refuse states nothing about this dimension,
            // and it states nothing about the others either. One refused
            // scenario leaves the rest readable rather than ending the capture
            // that every other rule's facts also come out of.
            return [];
        }

        $at = [$source->reference('forecast_model.drivers'), $source->reference('forecast_model.actuals')];
        $facts = [
            new Fact('scenario.dimension', $name, scenario: $definition->id, confirmed: true, sources: $at),
            new Fact('scenario.interval', $this->interval($window, $shock), scenario: $definition->id,
                confirmed: true, sources: [$source->reference('forecast_model.actuals')]),
            new Fact('scenario.requested_shock', $requested, scenario: $definition->id, unit: $unit,
                basis: $definition->defaultsSource, confirmed: true, sources: $at),
            new Fact('scenario.effective_shock', $effective, scenario: $definition->id, unit: $unit,
                basis: 'measured_against_the_shocked_model', confirmed: true, sources: $at),
        ];
        if ($reason !== null) {
            $facts[] = new Fact('scenario.shock_reduction_reason', $reason, scenario: $definition->id,
                confirmed: true, sources: $at);
        }

        return $facts;
    }

    /**
     * Requested size, applied size, and the cap that explains a shortfall.
     *
     * @return array{0:string,1:?string,2:?string}
     */
    private function measure(ForecastModelInputs $inputs, ActualsWindow $window, ScenarioDefinition $definition,
        ScenarioShock $shock, string $unit): array
    {
        if ($unit === 'days') {
            return $this->delay($inputs, $window, $definition, $shock);
        }

        $requested = $this->percent($shock->factorValue());
        if ($shock->type === ScenarioShock::TYPE_VOLUME_FACTOR) {
            $report = $this->application->revenueShockReport($inputs->calendar, $window, $inputs->streams,
                $inputs->founderMonths, $definition);
            $factor = $report?->effectiveFactor();

            return [$requested, $factor === null ? null : $this->percent(BigDecimal::of($factor)),
                $report !== null && $report->divergedFromRequest() && $report->reasons !== []
                    ? implode('; ', $report->reasons) : null];
        }

        return [$requested, ...$this->costRate($inputs, $window, $definition, $shock)];
    }

    /**
     * The cost shock measured over the categories it actually names.
     *
     * A shock declaring direct cost, fixed cost and capex is measured against
     * all three bases together, because that composite is what it claims to
     * have moved. A base of nothing is not a 100% increase; it is an undefined
     * proportion, and an absent effective figure says so rather than letting
     * an untested dimension read as a tested one.
     *
     * @return array{0:?string,1:?string}
     */
    private function costRate(ForecastModelInputs $inputs, ActualsWindow $window, ScenarioDefinition $definition,
        ScenarioShock $shock): array
    {
        $base = $this->costBase($inputs, $shock);
        $shocked = $this->costBase($this->application->shockInputs($window, $inputs, $definition), $shock);
        if ($base === null || $shocked === null) {
            return [null, 'a cost in a shocked category carries no rate, so no applied proportion is defined'];
        }
        if ($base->isZero()) {
            return [null, 'the shocked cost categories hold nothing to scale'];
        }

        return [$this->percent($shocked->dividedBy($base, 8, RoundingMode::HalfUp)), null];
    }

    /**
     * The total the named cost categories carry, or null when one of them
     * cannot be valued.
     */
    private function costBase(ForecastModelInputs $inputs, ScenarioShock $shock): ?BigDecimal
    {
        $total = BigDecimal::zero();
        if ($shock->appliesToTarget('fixed_cost')) {
            foreach ($this->run->fixedCosts($inputs)['totals'] as $month) {
                if ($month === null) {
                    return null;
                }
                $total = $total->plus($month);
            }
        }
        if ($shock->appliesToTarget('capex')) {
            foreach ($inputs->assets as $asset) {
                if ($asset->cost === null) {
                    return null;
                }
                $total = $total->plus($asset->cost);
            }
        }
        if ($shock->appliesToTarget('direct_cost')) {
            foreach ($this->run->revenue($inputs)['months'] as $month) {
                if ($month['base_contractor_cost'] === null) {
                    return null;
                }
                $total = $total->plus($month['base_contractor_cost']);
            }
        }

        return $total;
    }

    /**
     * A collections delay applies in full or not at all.
     *
     * `shockCollections()` shifts every eligible receipt by the whole day
     * count; nothing trims it. So the measurement that matters is whether any
     * receipt was eligible, and a scenario that moved nothing applied nothing
     * however many days it asked for. That is the case SS-02's ineffective
     * check is built to catch.
     *
     * @return array{0:string,1:?string,2:?string}
     */
    private function delay(ForecastModelInputs $inputs, ActualsWindow $window, ScenarioDefinition $definition,
        ScenarioShock $shock): array
    {
        $requested = (string) $shock->delayDays();
        try {
            [$movements] = $this->run->movements($inputs);
        } catch (\InvalidArgumentException) {
            // The movement set the delay would act on cannot be built, so
            // nothing can be said about what the delay reached.
            return [$requested, null, 'the dated cash set this delay acts on could not be built'];
        }
        $dates = array_map(static fn (CashMovement $movement): ?string => $movement->date, $movements);
        $moved = 0;
        foreach ($this->application->shockCollections($window, $movements, $definition) as $i => $movement) {
            if ($movement->date !== ($dates[$i] ?? null)) {
                $moved++;
            }
        }

        return $moved > 0
            ? [$requested, $requested, null]
            : [$requested, '0', 'no eligible customer receipt falls inside the shocked window'];
    }

    /** The dimension a shock tests, read off the shock rather than a scenario name.
     *
     * A volume shock bounded to named months is a slow start; the same shock
     * across the whole window is a volume downside. The distinction is the
     * bound, so it is taken from the bound.
     */
    private function dimensionName(ScenarioShock $shock): ?string
    {
        return match ($shock->type) {
            ScenarioShock::TYPE_VOLUME_FACTOR => $shock->periods === null ? 'revenue_volume' : 'slow_start_volume',
            ScenarioShock::TYPE_COST_RATE_FACTOR => 'cost_rate',
            ScenarioShock::TYPE_COLLECTION_DELAY_DAYS => 'collection_delay',
            default => null,
        };
    }

    /** A factor as the signed percentage change it represents: 0.8 is -20. */
    private function percent(BigDecimal $factor): string
    {
        return (string) $factor->minus(1)->multipliedBy(100)->toScale(2, RoundingMode::HalfUp);
    }

    /** The months a shock covers, stated as the range it actually reaches. */
    private function interval(ActualsWindow $window, ScenarioShock $shock): string
    {
        $periods = $shock->periods ?? $window->forecastPeriods;
        if ($periods === []) {
            return 'no forecast month';
        }
        sort($periods);
        $range = $periods[0] === $periods[count($periods) - 1]
            ? $periods[0]
            : $periods[0].' to '.$periods[count($periods) - 1];

        return $shock->periods === null
            ? $range.' (every forecast month after the actuals cutoff)'
            : $range.' ('.count($periods).' of '.count($window->forecastPeriods).' forecast months)';
    }

    /** @return list<string> */
    private function streamIds(ForecastModelInputs $inputs): array
    {
        $ids = [];
        foreach ($inputs->streams as $stream) {
            $ids[] = $stream->id;
        }

        return $ids;
    }
}
