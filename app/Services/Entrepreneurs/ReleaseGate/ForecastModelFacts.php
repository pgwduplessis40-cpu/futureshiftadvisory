<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Services\Entrepreneurs\Forecast\ForecastRun;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelCodec;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Produces release-gate facts from a selected version-2 reconstruction.
 *
 * Everything here is evidenced against the captured snapshot rather than
 * against live state, so a gate run and a later re-reading of it see the same
 * model. The reconstruction is decoded back through the engine's own codec,
 * which means a payload the engine would refuse produces no facts at all
 * rather than a thinner set that reads as a complete model with gaps.
 *
 * Periods follow the `month:N` convention the rest of the gate already uses,
 * counted from the model's own start month, so a fact produced here can be
 * compared with one produced from the stored budget.
 */
final class ForecastModelFacts implements ProvidesMethodology
{
    /**
     * Mostly this class adapts engine output into sourced facts, as the
     * budget and plan producers beside it do. It is marked rather than
     * excluded because it does own one figure: the month-on-month ramp it
     * derives per stream. A formula that decides a release finding has to
     * name the methodology it belongs to, and that one is a revenue driver.
     *
     * @return list<string>
     */
    public static function methodologyIds(): array
    {
        return ['entrepreneur.driver_revenue'];
    }

    /**
     * Keys this producer deliberately does not emit yet.
     *
     * Each is read by a rule that is already enabled, so emitting one now
     * would move a verdict before the nine phase-2 rules are registered:
     * `stream.revenue` is read by PM-04, which currently sees none;
     * `scope.required_horizon` feeds MI-06 and the snapshot's own horizon;
     * `scenario.lowest_cash` feeds SS-01; and a second
     * `model.external_request` would make PM-12's lookup ambiguous, because
     * competing records resolve to nothing rather than to the first one.
     *
     * They belong to the slice that registers the nine rules, where the
     * interaction can be designed and tested as a single change.
     *
     * @var list<string>
     */
    public const WITHHELD = ['stream.revenue', 'scope.required_horizon', 'scenario.lowest_cash', 'model.external_request'];

    public function __construct(private readonly ForecastRun $run = new ForecastRun) {}

    /** @return list<Fact> */
    public function capture(SourceRecords $source): array
    {
        if (! is_array($source->value('forecast_model'))) {
            return [];
        }
        try {
            $inputs = ForecastModelCodec::decode(
                (string) $source->value('forecast_model.engine_version'),
                $source->value('forecast_model.drivers'),
                $source->value('forecast_model.actuals'),
                $source->value('forecast_model.reserve_policy'),
            );
        } catch (\InvalidArgumentException) {
            // A reconstruction the engine cannot read is evidence of nothing.
            // The advisor screen reports the load failure; inventing partial
            // facts from it here would put unreadable figures in front of a
            // rule as though they had been verified.
            return [];
        }

        return [...$this->model($source, $inputs), ...$this->streams($source, $inputs),
            ...$this->actuals($source, $inputs), ...$this->cash($source, $inputs)];
    }

    /** @return list<Fact> */
    private function model(SourceRecords $source, ForecastModelInputs $inputs): array
    {
        $at = [$source->reference('forecast_model.drivers.calendar')];

        return [
            new Fact('model.start_date', $inputs->calendar->startMonth.'-01', confirmed: true, sources: $at),
            new Fact('model.start_period', $inputs->calendar->startMonth, confirmed: true, sources: $at),
            // The date this reading of the plan was taken. The elapsed-actuals
            // rule counts closed reporting months from it, so it is the
            // snapshot's own date rather than anything inside the model.
            new Fact('actuals.as_of', $source->version->asOf, confirmed: true, sources: [$source->reference('forecast_model')]),
        ];
    }

    /**
     * Per-stream and per-month driver facts.
     *
     * A stream nobody has classified emits no membership fact at all. The
     * rules that read it require a boolean, and an absent flag reported as
     * missing is the finding; a flag invented here would be the defect.
     *
     * @return list<Fact>
     */
    private function streams(SourceRecords $source, ForecastModelInputs $inputs): array
    {
        $facts = [];
        $run = $this->run->revenue($inputs);
        $months = array_column($run['months'], null, 'period');
        foreach ($inputs->calendar->periods as $offset => $period) {
            $label = 'month:'.($offset + 1);
            $month = $months[$period] ?? null;
            $facts[] = new Fact('model.base_revenue', $month['base_revenue'] ?? null, period: $label, unit: 'NZD',
                confirmed: true, sources: [$source->reference('forecast_model.drivers.streams')]);
            $facts[] = new Fact('capacity.available_hours', $month['founder_hours_available'] ?? null, period: $label,
                unit: 'hours', confirmed: true, sources: [$source->reference('forecast_model.drivers.founder_months')]);
        }
        foreach ($inputs->streams as $index => $stream) {
            $at = [$source->reference('forecast_model.drivers.streams.'.$index)];
            if ($stream->inBaseCase !== null) {
                $facts[] = new Fact('stream.membership', $stream->inBaseCase, $stream->id, confirmed: true, sources: $at);
            }
            $facts[] = new Fact('stream.preparation_hours_per_unit', $stream->preparationHoursPerUnit, $stream->id,
                unit: 'hours', confirmed: true, sources: $at);
            $facts[] = new Fact('stream.delivery_hours_per_unit', $stream->deliveryHoursPerUnit, $stream->id,
                unit: 'hours', confirmed: true, sources: $at);
            $provider = $this->provider($stream);
            if ($provider !== null) {
                $facts[] = new Fact('capacity.provider', $provider, $stream->id, confirmed: true, sources: $at);
            }
            $facts = [...$facts, ...$this->streamMonths($source, $inputs, $stream, $index)];
        }

        return $facts;
    }

    /** @return list<Fact> */
    private function streamMonths(SourceRecords $source, ForecastModelInputs $inputs, RevenueStream $stream, int $index): array
    {
        $facts = [];
        $previous = null;
        foreach ($inputs->calendar->periods as $offset => $period) {
            $month = $stream->schedule[$period] ?? null;
            if ($month === null) {
                $previous = null;

                continue;
            }
            $label = 'month:'.($offset + 1);
            $at = [$source->reference('forecast_model.drivers.streams.'.$index.'.schedule.'.$period)];
            $facts[] = new Fact('stream.units', $month->deliveryUnits, $stream->id, $label, unit: 'units', confirmed: true, sources: $at);
            if ($month->provider !== null && trim($month->provider) !== '') {
                $facts[] = new Fact('provider.cost', $month->providerUnitCost, $month->provider, $label, unit: 'NZD/unit', confirmed: true, sources: $at);
                if ($month->noCostBasis !== null) {
                    $facts[] = new Fact('provider.no_cost_basis', $month->noCostBasis, $month->provider, $label, confirmed: true, sources: $at);
                }
            }
            // Month-on-month growth, and only where both months are known.
            // A ramp measured from an absent month would read as a step up
            // from zero rather than as a month nobody has filled in.
            $ramp = $this->ramp($previous, $month->deliveryUnits);
            if ($ramp !== null) {
                $facts[] = new Fact('model.ramp', $ramp, $stream->id, $label, unit: 'percent', confirmed: true, sources: $at);
            }
            $previous = $month->deliveryUnits;
        }

        return $facts;
    }

    /**
     * One provider for the stream, or none.
     *
     * A stream whose provider changes from month to month has no single
     * provider, and naming one of them would be a claim nobody made. The
     * capacity rule then reports the provider as missing, which is the
     * truthful reading of a stream delivered by several.
     */
    private function provider(RevenueStream $stream): ?string
    {
        $named = [];
        foreach ($stream->schedule as $month) {
            $named[] = $month->provider === null || trim($month->provider) === '' ? 'founder' : trim($month->provider);
        }
        $distinct = array_values(array_unique($named));

        return count($distinct) === 1 ? $distinct[0] : null;
    }

    /** @return list<Fact> */
    private function actuals(SourceRecords $source, ForecastModelInputs $inputs): array
    {
        $facts = [];
        $offsets = array_flip($inputs->calendar->periods);
        foreach ($inputs->actualMonths as $index => $month) {
            if (! isset($offsets[$month->period])) {
                continue;
            }
            $label = 'month:'.($offsets[$month->period] + 1);
            $at = [$source->reference('forecast_model.actuals.months.'.$index)];
            // Reconciled and pre-trading are the two answers the rule accepts.
            // A trading month nobody has reconciled is neither, and emitting
            // no status is what makes it report as the gap it is.
            $status = $month->state === 'pre_trading' ? 'pre_trading' : ($month->source->reconciled === true ? 'reconciled' : null);
            if ($status !== null) {
                $facts[] = new Fact('actuals.status', $status, period: $label, confirmed: true, sources: $at);
                $facts[] = new Fact('period.label', 'actual', period: $label, confirmed: true, sources: $at);
            }
            $facts[] = new Fact('actuals.closing_cash', $month->closing->bankCash, period: $label, unit: 'NZD',
                confirmed: $month->source->reconciled === true, sources: $at);
        }

        return $facts;
    }

    /** @return list<Fact> */
    private function cash(SourceRecords $source, ForecastModelInputs $inputs): array
    {
        $run = $this->run->cash($inputs);
        $facts = [];
        $reviewed = [];
        foreach ($inputs->actualMonths as $month) {
            $reviewed[$month->period] = true;
        }
        $offsets = array_flip($inputs->calendar->periods);
        foreach ($run['months'] as $month) {
            if (isset($reviewed[$month['period']]) || ! isset($offsets[$month['period']])) {
                continue;
            }
            $label = 'month:'.($offsets[$month['period']] + 1);
            $facts[] = new Fact('forecast.opening_cash', $month['opening_cash'], period: $label, unit: 'NZD',
                confirmed: true, sources: [$source->reference('forecast_model.drivers.cash_opening')]);
            $facts[] = new Fact('period.label', 'forecast', period: $label, confirmed: true,
                sources: [$source->reference('forecast_model.drivers.calendar')]);
        }
        $at = [$source->reference('forecast_model.reserve_policy')];
        $facts[] = new Fact('scenario.cash_deficit', $run['cash_deficit'], unit: 'NZD', confirmed: true, sources: $at);
        $facts[] = new Fact('scenario.reserve_gap', $run['reserve_shortfall'], unit: 'NZD', confirmed: true, sources: $at);

        return $facts;
    }

    private function ramp(?string $previous, ?string $current): ?string
    {
        if ($previous === null || $current === null) {
            return null;
        }
        $from = BigDecimal::of($previous);
        if ($from->isZero()) {
            return null;
        }

        return (string) BigDecimal::of($current)->minus($from)->multipliedBy(100)->dividedBy($from, 4, RoundingMode::HalfUp);
    }
}
