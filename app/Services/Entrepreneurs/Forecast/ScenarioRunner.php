<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

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
 * @phpstan-type RunResult array{engine_version:string,scope:string,status:string,outcomes:list<ScenarioOutcome>,worst:array{scenario_id:?string,ranking_metric:?string,value:?string},maxima:array<string,Maximum>,unranked:list<string>}
 */
final class ScenarioRunner implements ProvidesMethodology
{
    public const VERSION = 'phase-2d.1';

    public function __construct(
        private readonly ScenarioApplication $application = new ScenarioApplication,
        private readonly FundingMeasures $funding = new FundingMeasures,
    ) {}

    public static function methodologyIds(): array
    {
        return ['entrepreneur.scenarios_metrics_funding'];
    }

    /**
     * @param  list<ScenarioDefinition>  $definitions
     * @param  list<RevenueStream>  $streams
     * @param  array<string,FounderMonth>  $founderMonths
     * @param  list<CashMovement>  $movements
     * @return RunResult
     */
    public function run(
        ForecastCalendar $calendar,
        ActualsWindow $actuals,
        array $definitions,
        array $streams,
        array $founderMonths,
        CashOpening $opening,
        array $movements,
        ReservePolicy $policy,
    ): array {
        $outcomes = [];
        foreach ($definitions as $definition) {
            $outcomes[] = $this->outcome($calendar, $actuals, $definition, $streams, $founderMonths, $opening, $movements, $policy);
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
        ];
    }

    /**
     * @param  list<RevenueStream>  $streams
     * @param  array<string,FounderMonth>  $founderMonths
     * @param  list<CashMovement>  $movements
     */
    private function outcome(
        ForecastCalendar $calendar,
        ActualsWindow $actuals,
        ScenarioDefinition $definition,
        array $streams,
        array $founderMonths,
        CashOpening $opening,
        array $movements,
        ReservePolicy $policy,
    ): ScenarioOutcome {
        $shockedMovements = $this->application->shockCollections($actuals, $movements, $definition);
        $ledger = (new CashLedger)->calculate($calendar, $opening, $shockedMovements);

        return new ScenarioOutcome(
            $definition,
            $this->funding->calculate($ledger, $policy),
            $this->application->revenueShockReport($calendar, $actuals, $streams, $founderMonths, $definition),
        );
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
