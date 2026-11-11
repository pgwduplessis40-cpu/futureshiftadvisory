<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Support\Methodology\ProvidesMethodology;

/**
 * Runs the required scenarios and records what each one needs.
 *
 * Results are reported as computed. A lower-volume scenario may legitimately
 * improve an early cash minimum through lower supplier payments or tax, and
 * nothing here adjusts a figure to make a downside look worse than the base
 * case. Where that happens the bridge is visible in each scenario's own
 * measures rather than smoothed away.
 *
 * @phpstan-type Maximum array{value:?string,scenario_id:?string,date:?string}
 * @phpstan-type FundingResult array{status:string,lowest_cash:?string,lowest_cash_date:?string,cash_deficit:?string,reserve_target:?string,reserve_shortfall:?string,reserve_shortfall_date:?string,missing:list<string>}
 * @phpstan-type RunResult array{engine_version:string,scope:string,status:string,outcomes:list<ScenarioOutcome>,worst:array{scenario_id:?string,ranking_metric:?string,value:?string},maxima:array<string,Maximum>,unranked:list<string>,refusal:?string}
 */
final class ScenarioRunner implements ProvidesMethodology
{
    public const VERSION = 'phase-2e.1';

    public function __construct(
        private readonly ScenarioApplication $application = new ScenarioApplication,
        private readonly FundingMeasures $funding = new FundingMeasures,
        // One run instance across every scenario on purpose. The base scenario
        // shocks nothing, so it is handed the reconstruction itself and reads
        // the projection this instance already computed for the main model
        // rather than repeating it.
        private readonly ForecastRun $forecast = new ForecastRun,
    ) {}

    public static function methodologyIds(): array
    {
        return ['entrepreneur.scenarios_metrics_funding'];
    }

    /**
     * Runs every supplied scenario against the reconstruction.
     *
     * The reconstruction is taken whole rather than as the handful of parts a
     * ledger needs, because a scenario is not a re-dating of one movement
     * list. A cost-rate shock changes the overhead invoices and the capital
     * payments the ledger carries, and a volume shock changes revenue-linked
     * owner pay. Those only reach cash by being applied to the inputs and run
     * back through the engines, which needs every section the movements are
     * built from, not the movements alone.
     *
     * The scope stays before the proposed facility. Funding already recorded
     * against the business is in the ledger because it is money the business
     * has arranged; the facility this sizing exists to recommend is not an
     * input and so cannot fund its own case.
     *
     * @param  list<ScenarioDefinition>  $definitions
     * @return RunResult
     */
    public function run(ForecastModelInputs $inputs, ActualsWindow $actuals, array $definitions): array
    {
        $outcomes = [];
        try {
            foreach ($definitions as $definition) {
                $outcomes[] = $this->outcome($inputs, $actuals, $definition);
            }
        } catch (\InvalidArgumentException $e) {
            // The schedules refuse inputs they cannot read, the ledger refuses
            // movements it cannot position, and a shock refuses a scope it
            // cannot express. None of those leaves a worst case to report, so
            // the run states the reason instead of ranking whatever survived.
            return $this->refused($e->getMessage());
        }

        [$worst, $unranked] = $this->worst($outcomes);

        return [
            'engine_version' => self::VERSION,
            'scope' => 'before_proposed_facility',
            'status' => $this->status($outcomes),
            'outcomes' => $outcomes,
            'worst' => $worst,
            'maxima' => $this->maxima($outcomes),
            'unranked' => $unranked,
            'refusal' => null,
        ];
    }

    private function outcome(ForecastModelInputs $inputs, ActualsWindow $actuals, ScenarioDefinition $definition): ScenarioOutcome
    {
        // Reported from the unshocked streams on purpose: the report's whole
        // job is to compare what the shock asked for against what the model
        // did with it, which needs the base case beside the shocked one.
        $report = $this->application->revenueShockReport(
            $inputs->calendar, $actuals, $inputs->streams, $inputs->founderMonths, $definition);

        $opening = $inputs->cashOpening;
        $policy = $inputs->reservePolicy;
        if ($opening === null || $policy === null) {
            return new ScenarioOutcome($definition, $this->unverifiable([
                ...($opening === null ? ['cash_opening'] : []),
                ...($policy === null ? ['reserve_policy'] : []),
            ]), $report);
        }

        $shocked = $this->application->shockInputs($actuals, $inputs, $definition);
        [$movements, , $dated, $flat] = $this->forecast->movements($shocked);
        $ledger = (new CashLedger)->calculate($inputs->calendar, $opening,
            $this->application->shockCollections($actuals, $movements, $definition));

        $gaps = $this->gaps($dated, [...$flat, ...$ledger['missing']]);
        if ($gaps !== []) {
            // A ledger standing on an incomplete movement set does not get to
            // publish a cash deficit. The measures stay absent so the scenario
            // is listed as unranked rather than compared on a figure that is
            // missing part of what it was built from.
            return new ScenarioOutcome($definition, $this->unverifiable($gaps), $report);
        }

        return new ScenarioOutcome($definition, $this->funding->calculate($ledger, $policy), $report);
    }

    /**
     * One reason per gap, however many dates it reached.
     *
     * @param  list<array{0:string,1:string}>  $dated
     * @param  list<string>  $flat
     * @return list<string>
     */
    private function gaps(array $dated, array $flat): array
    {
        $reasons = $flat;
        foreach ($dated as [$reason]) {
            $reasons[] = $reason;
        }

        return array_values(array_unique($reasons));
    }

    /**
     * A scenario that cannot be measured, carrying why.
     *
     * Every measure is absent rather than zero. A zero deficit is a positive
     * statement that the business needs nothing, and that is not what an
     * unreadable input says.
     *
     * @param  list<string>  $missing
     * @return FundingResult
     */
    private function unverifiable(array $missing): array
    {
        return ['status' => 'cannot_verify', 'lowest_cash' => null, 'lowest_cash_date' => null,
            'cash_deficit' => null, 'reserve_target' => null, 'reserve_shortfall' => null,
            'reserve_shortfall_date' => null, 'missing' => $missing];
    }

    /** @return RunResult */
    private function refused(string $refusal): array
    {
        return ['engine_version' => self::VERSION, 'scope' => 'before_proposed_facility', 'status' => 'fail',
            'outcomes' => [], 'worst' => ['scenario_id' => null, 'ranking_metric' => null, 'value' => null],
            'maxima' => [], 'unranked' => [], 'refusal' => $refusal];
    }

    /** @param list<ScenarioOutcome> $outcomes */
    private function status(array $outcomes): string
    {
        foreach ($outcomes as $outcome) {
            if ($outcome->status() !== 'pass') {
                return 'cannot_verify';
            }
        }

        return $outcomes === [] ? 'cannot_verify' : 'pass';
    }

    /**
     * Selects the worst scenario by its own named ranking metric.
     *
     * Scenarios that could not be ranked are listed rather than dropped: a
     * worst case chosen from a subset is only meaningful if the reader can
     * see what was left out of the comparison.
     *
     * @param  list<ScenarioOutcome>  $outcomes
     * @return array{0:array{scenario_id:?string,ranking_metric:?string,value:?string},1:list<string>}
     */
    private function worst(array $outcomes): array
    {
        $worst = null;
        $worstValue = null;
        $unranked = [];
        foreach ($outcomes as $outcome) {
            $value = $outcome->rankingValue();
            if ($value === null) {
                $unranked[] = $outcome->definition->id;

                continue;
            }
            if ($worstValue === null || $value->isGreaterThan($worstValue)) {
                $worstValue = $value;
                $worst = $outcome;
            }
        }

        return [[
            'scenario_id' => $worst?->definition->id,
            'ranking_metric' => $worst?->definition->rankingMetric,
            'value' => $worstValue === null ? null : DriverNumbers::money($worstValue),
        ], $unranked];
    }

    /**
     * Records the maximum of each measure across the scenarios, with the
     * scenario and date that produced it.
     *
     * @param  list<ScenarioOutcome>  $outcomes
     * @return array<string,Maximum>
     */
    private function maxima(array $outcomes): array
    {
        $measures = [
            'cash_deficit' => 'lowest_cash_date',
            'reserve_shortfall' => 'reserve_shortfall_date',
        ];
        $maxima = [];
        foreach ($measures as $measure => $dateKey) {
            $best = null;
            $bestId = null;
            $bestDate = null;
            foreach ($outcomes as $outcome) {
                $raw = $outcome->funding[$measure] ?? null;
                if ($raw === null) {
                    continue;
                }
                $value = DriverNumbers::readSigned($raw);
                if ($best === null || $value->isGreaterThan($best)) {
                    $best = $value;
                    $bestId = $outcome->definition->id;
                    $bestDate = $outcome->funding[$dateKey] ?? null;
                }
            }
            $maxima[$measure] = ['value' => $best === null ? null : DriverNumbers::money($best),
                'scenario_id' => $bestId, 'date' => $bestDate];
        }

        return $maxima;
    }
}
