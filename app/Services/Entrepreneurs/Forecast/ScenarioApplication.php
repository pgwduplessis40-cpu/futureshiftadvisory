<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Applies a scenario definition's shocks to supplied forecast inputs.
 *
 * Actuals protection is the first rule, not a later guard. A shock that names
 * explicit periods is asserted against the actuals window and fails if it
 * reaches an elapsed reviewed month; a shock that names no periods covers the
 * forecast window only, so it cannot reach an actual month in the first place.
 * The two cases are deliberately different: one is an attempt to change
 * history and must be rejected, the other is an unbounded shock that is simply
 * scoped.
 *
 * Nothing here recomputes revenue, cash or tax. It returns shocked inputs for
 * the existing 2A/2B/2C engines to run, so a scenario cannot acquire its own
 * divergent arithmetic.
 */
final class ScenarioApplication implements ProvidesMethodology
{
    public const VERSION = 'phase-2e.1';

    /** Volume shocks scale delivery, booking and invoicing units together: a
     * reduced delivery volume that keeps its original invoicing would invent
     * revenue with no work behind it.
     *
     * @var list<string>
     */
    private const VOLUME_FIELDS = ['bookedUnits', 'deliveryUnits', 'invoicedUnits'];

    public static function methodologyIds(): array
    {
        return ['entrepreneur.scenarios_metrics_funding'];
    }

    /**
     * The reconstruction a scenario is actually run on.
     *
     * Volume and cost-rate shocks change inputs, so they belong here rather
     * than as a multiplier laid over a finished figure. Applying them to the
     * reconstruction and re-running the engines is what makes a shock reach
     * the cash ledger at all, and the routes are worth naming because they are
     * narrower than they look. A fixed-cost rate reaches cash directly: the
     * recurrence raises the invoices the ledger pays. An asset's cost does
     * too, through the payment its schedule dates. A volume shock reaches cash
     * only through revenue-linked owner pay, because forecast stream revenue
     * has no cash leg in this engine - operating receipts come from trade
     * items, which an advisor types as amounts rather than deriving from
     * volumes. The direct-cost rate reaches profit and not cash for the same
     * reason: contractor cost is recognised, never scheduled.
     *
     * The collection-delay shock is deliberately not applied here. It moves
     * dates on movements the engine has already built, so it stays in
     * `shockCollections()`, which the caller applies to the built set.
     *
     * A definition with no input-level shock returns the reconstruction
     * itself, not an equal copy. The base scenario is then the same object the
     * main model ran, so its ledger is that ledger rather than a second
     * computation that has to be checked for agreement with it.
     */
    public function shockInputs(ActualsWindow $actuals, ForecastModelInputs $inputs, ScenarioDefinition $definition): ForecastModelInputs
    {
        $cost = $definition->shocksOfType(ScenarioShock::TYPE_COST_RATE_FACTOR);
        if ($definition->shocksOfType(ScenarioShock::TYPE_VOLUME_FACTOR) === [] && $cost === []) {
            return $inputs;
        }

        return $inputs->withCostInputs(
            $this->shockStreams($actuals, $inputs->streams, $definition),
            $this->shockFixedCostRates($actuals, $inputs->fixedCosts, $definition),
            $this->shockCapitalCosts($actuals, $inputs->assets, $definition),
        );
    }

    /**
     * Applies every shock that reaches a stream: volume factors to the booked,
     * delivered and invoiced units, and a direct-cost rate factor to the
     * provider's unit cost.
     *
     * The two are targeted differently on purpose. A volume shock names the
     * streams it covers, because a downside rarely hits every line of a
     * business equally; a cost-rate shock names the cost category, because a
     * rate rise reaches whatever is bought at that rate.
     *
     * @param  list<RevenueStream>  $streams
     * @return list<RevenueStream>
     */
    public function shockStreams(ActualsWindow $actuals, array $streams, ScenarioDefinition $definition): array
    {
        $volume = $definition->shocksOfType(ScenarioShock::TYPE_VOLUME_FACTOR);
        $direct = $this->targeting($definition->shocksOfType(ScenarioShock::TYPE_COST_RATE_FACTOR), 'direct_cost');
        if ($volume === [] && $direct === []) {
            return $streams;
        }

        $this->assertEligiblePeriods($actuals, [...$volume, ...$direct]);

        $shocked = [];
        foreach ($streams as $stream) {
            $shocked[] = $this->shockStream($actuals, $stream, $volume, $direct);
        }

        return $shocked;
    }

    /**
     * Scales the rate behind each recurring overhead.
     *
     * The rate is what the recurrence charges, so scaling it moves the monthly
     * expense and the invoices it raises together. Scaling the generated
     * invoices instead would leave the expense at its unshocked figure and put
     * the two permanently out of step.
     *
     * @param  list<FixedCost>  $costs
     * @return list<FixedCost>
     */
    public function shockFixedCostRates(ActualsWindow $actuals, array $costs, ScenarioDefinition $definition): array
    {
        $shocks = $this->targeting($definition->shocksOfType(ScenarioShock::TYPE_COST_RATE_FACTOR), 'fixed_cost');
        if ($shocks === []) {
            return $costs;
        }

        $this->assertWholeHorizon($shocks, 'fixed costs');
        $this->assertEligiblePeriods($actuals, $shocks);
        $factor = $this->factor($shocks, null);

        $shocked = [];
        foreach ($costs as $cost) {
            $shocked[] = new FixedCost($cost->id, $cost->source, $this->scaleAmount($cost->rate, $factor),
                $cost->quantity, $cost->frequency, $cost->firstInvoiceOn, $cost->lastInvoiceOn,
                $cost->creditorDays, $cost->reviewed);
        }

        return $shocked;
    }

    /**
     * Scales what each asset costs to buy.
     *
     * The depreciation charge and the payment both come off this one figure,
     * so a capex shock reaches the profit and the cash side from a single
     * change rather than two that could disagree.
     *
     * @param  list<AssetInput>  $assets
     * @return list<AssetInput>
     */
    public function shockCapitalCosts(ActualsWindow $actuals, array $assets, ScenarioDefinition $definition): array
    {
        $shocks = $this->targeting($definition->shocksOfType(ScenarioShock::TYPE_COST_RATE_FACTOR), 'capex');
        if ($shocks === []) {
            return $assets;
        }

        $this->assertWholeHorizon($shocks, 'capital costs');
        $this->assertEligiblePeriods($actuals, $shocks);
        $factor = $this->factor($shocks, null);

        $shocked = [];
        foreach ($assets as $asset) {
            $shocked[] = new AssetInput($asset->id, $asset->source, $this->scaleAmount($asset->cost, $factor),
                $asset->classification, $asset->policySource, $asset->reviewed, $asset->paidOn,
                $asset->recognitionStartOn, $asset->lifeMonths, $asset->residual, $asset->method,
                $asset->openingDepreciation);
        }

        return $shocked;
    }

    /**
     * Scales eligible cost rates. Tax, financing receipts and historical
     * actuals are never passed here: the specification requires tax and
     * dependent responses to be recomputed from the shocked inputs instead of
     * multiplied alongside them.
     *
     * @param  array<string,string>  $rates  Rate per category identifier.
     * @return array<string,string>
     */
    public function shockCostRates(ActualsWindow $actuals, array $rates, ScenarioDefinition $definition): array
    {
        $shocks = $definition->shocksOfType(ScenarioShock::TYPE_COST_RATE_FACTOR);
        if ($shocks === []) {
            return $rates;
        }

        $this->assertEligiblePeriods($actuals, $shocks);

        $shockedRates = [];
        foreach ($rates as $category => $rate) {
            $value = DriverNumbers::read($rate);
            if ($value === null) {
                $shockedRates[$category] = $rate;

                continue;
            }
            foreach ($shocks as $shock) {
                if ($shock->appliesToTarget($category)) {
                    $value = $value->multipliedBy($shock->factorValue());
                }
            }
            $shockedRates[$category] = DriverNumbers::money($value);
        }

        return $shockedRates;
    }

    /**
     * Moves eligible future collection dates later. Dates on or before the
     * actuals cutoff are already received cash and are never moved.
     *
     * @param  list<CashMovement>  $movements
     * @return list<CashMovement>
     */
    public function shockCollections(ActualsWindow $actuals, array $movements, ScenarioDefinition $definition): array
    {
        $shocks = $definition->shocksOfType(ScenarioShock::TYPE_COLLECTION_DELAY_DAYS);
        if ($shocks === []) {
            return $movements;
        }

        $this->assertEligiblePeriods($actuals, $shocks);

        $shocked = [];
        foreach ($movements as $movement) {
            $shocked[] = $this->shockMovement($actuals, $movement, $shocks);
        }

        return $shocked;
    }

    /**
     * Compares base and shocked revenue so the requested shock and its
     * effective result are reported separately.
     *
     * A requested 0.8 on volumes rarely produces exactly a 20% revenue
     * reduction. Capacity already bound some months, the shock may cover only
     * part of the horizon, upside streams never counted toward base revenue,
     * and streams outside the target set are untouched. Each of those is
     * reported as a reason so the difference reads as a modelled consequence.
     *
     * @param  list<RevenueStream>  $baseStreams
     * @param  array<string,FounderMonth>  $founderMonths
     */
    public function revenueShockReport(
        ForecastCalendar $calendar,
        ActualsWindow $actuals,
        array $baseStreams,
        array $founderMonths,
        ScenarioDefinition $definition,
    ): ?ShockReport {
        $shocks = $definition->shocksOfType(ScenarioShock::TYPE_VOLUME_FACTOR);
        if ($shocks === []) {
            return null;
        }

        $projection = new RevenueProjection;
        $baseline = $this->totalBaseRevenue($projection->calculate($calendar, $baseStreams, $founderMonths));
        $shockedStreams = $this->shockStreams($actuals, $baseStreams, $definition);
        $shocked = $this->totalBaseRevenue($projection->calculate($calendar, $shockedStreams, $founderMonths));

        if ($baseline === null || $shocked === null) {
            return null;
        }

        return new ShockReport(
            $definition->id,
            ScenarioShock::TYPE_VOLUME_FACTOR,
            $shocks[0]->factor,
            DriverNumbers::money($baseline),
            DriverNumbers::money($shocked),
            $this->divergenceReasons($calendar, $baseStreams, $shocks),
        );
    }

    /** Sums usable base revenue. One unusable month makes the comparison
     * meaningless, so the whole total is withheld rather than silently
     * summing the months that happened to compute.
     *
     * @param  array{months:list<array{base_revenue:?string}>}  $projection
     */
    private function totalBaseRevenue(array $projection): ?BigDecimal
    {
        $total = BigDecimal::zero();
        foreach ($projection['months'] as $month) {
            if ($month['base_revenue'] === null) {
                return null;
            }
            $total = $total->plus(BigDecimal::of($month['base_revenue']));
        }

        return $total;
    }

    /**
     * @param  list<RevenueStream>  $streams
     * @param  list<ScenarioShock>  $shocks
     * @return list<string>
     */
    private function divergenceReasons(ForecastCalendar $calendar, array $streams, array $shocks): array
    {
        $reasons = [];
        $constrained = [];
        foreach ($shocks as $shock) {
            if ($shock->periods !== null) {
                $constrained = array_merge($constrained, $shock->periods);
            }
        }
        if ($constrained !== [] && count(array_unique($constrained)) < count($calendar->periods)) {
            $reasons[] = 'the shock covers only part of the forecast horizon';
        }
        foreach ($streams as $stream) {
            if ($stream->inBaseCase === false) {
                $reasons[] = 'upside streams are excluded from base revenue';

                break;
            }
        }
        foreach ($streams as $stream) {
            $targeted = false;
            foreach ($shocks as $shock) {
                if ($shock->appliesToTarget($stream->id)) {
                    $targeted = true;
                }
            }
            if (! $targeted) {
                $reasons[] = 'some streams are outside the shock target set';

                break;
            }
        }
        $reasons[] = 'capacity limits and pricing are applied after the volume shock';

        return $reasons;
    }

    /**
     * @param  list<ScenarioShock>  $volume
     * @param  list<ScenarioShock>  $direct
     */
    private function shockStream(ActualsWindow $actuals, RevenueStream $stream, array $volume, array $direct): RevenueStream
    {
        $applicable = array_values(array_filter(
            $volume,
            static fn (ScenarioShock $shock): bool => $shock->appliesToTarget($stream->id)
        ));
        if ($applicable === [] && $direct === []) {
            return $stream;
        }

        $schedule = [];
        foreach ($stream->schedule as $period => $month) {
            $schedule[$period] = $this->shockMonth($actuals, (string) $period, $month, $applicable, $direct);
        }

        return new RevenueStream(
            $stream->id,
            $stream->name,
            $stream->unit,
            $stream->price,
            $stream->preparationHoursPerUnit,
            $stream->deliveryHoursPerUnit,
            $stream->inBaseCase,
            $stream->evidenceStatus,
            $schedule,
        );
    }

    /**
     * @param  list<ScenarioShock>  $volume
     * @param  list<ScenarioShock>  $direct
     */
    private function shockMonth(ActualsWindow $actuals, string $period, StreamMonth $month, array $volume, array $direct): StreamMonth
    {
        if (! in_array($period, $actuals->forecastPeriods, true)) {
            return $month;
        }

        $volumeFactor = $this->factor($volume, $period);
        $costFactor = $this->factor($direct, $period);
        if ($volumeFactor === null && $costFactor === null) {
            return $month;
        }

        $values = [];
        foreach (self::VOLUME_FIELDS as $field) {
            $values[$field] = $volumeFactor === null
                ? $month->{$field}
                : $this->scaleUnits($month->{$field}, $volumeFactor);
        }

        return new StreamMonth(
            $values['bookedUnits'],
            $values['deliveryUnits'],
            $values['invoicedUnits'],
            $month->founderHours,
            $month->additionalCapacityUnits,
            $month->provider,
            // A unit cost is a driver like the volumes beside it, not a cash
            // amount, so it keeps driver precision. Rounding it to the cent
            // here would compound that cent through every unit and month.
            $costFactor === null ? $month->providerUnitCost : $this->scaleUnits($month->providerUnitCost, $costFactor),
            $month->noCostBasis,
        );
    }

    /**
     * The shocks out of a set that reach one named target.
     *
     * @param  list<ScenarioShock>  $shocks
     * @return list<ScenarioShock>
     */
    private function targeting(array $shocks, string $target): array
    {
        return array_values(array_filter(
            $shocks,
            static fn (ScenarioShock $shock): bool => $shock->appliesToTarget($target)
        ));
    }

    /**
     * The compounded factor a set of shocks applies to one period, or to a
     * figure that has no period of its own when `$period` is null.
     *
     * Two shocks reaching the same figure multiply rather than the last one
     * winning, so a combined scenario is the composition of its parts.
     *
     * @param  list<ScenarioShock>  $shocks
     */
    private function factor(array $shocks, ?string $period): ?BigDecimal
    {
        $factor = null;
        foreach ($shocks as $shock) {
            if ($period !== null && ! $shock->appliesToPeriod($period)) {
                continue;
            }
            $factor = $factor === null ? $shock->factorValue() : $factor->multipliedBy($shock->factorValue());
        }

        return $factor;
    }

    /**
     * Refuses a cost-rate shock scoped to particular months on a figure that
     * has none.
     *
     * A fixed cost's rate and an asset's cost are one arrangement-level
     * figure each. A shock covering three months of a five-year horizon cannot
     * be expressed on them: applying it whole would overstate the shock across
     * every other month, and ignoring the scope would silently drop it. Both
     * are worse than saying the shock is unusable as declared.
     *
     * @param  list<ScenarioShock>  $shocks
     */
    private function assertWholeHorizon(array $shocks, string $subject): void
    {
        foreach ($shocks as $shock) {
            if ($shock->periods !== null) {
                throw new \InvalidArgumentException(sprintf(
                    'A cost-rate shock on %s cannot be scoped to named months: the rate is one arrangement-level figure with no month of its own.',
                    $subject,
                ));
            }
        }
    }

    /**
     * Scales a cash amount, which is carried in cents.
     *
     * An absent amount stays absent: scaling null would invent a cost.
     */
    private function scaleAmount(?string $amount, ?BigDecimal $factor): ?string
    {
        $value = DriverNumbers::read($amount);
        if ($value === null || $factor === null) {
            return $amount;
        }

        return DriverNumbers::money($value->multipliedBy($factor));
    }

    /** A delay moves money in later, never out later: shifting a payment would
     * improve cash under a downside and invert the shock's meaning.
     *
     * @param  list<ScenarioShock>  $shocks
     */
    private function shockMovement(ActualsWindow $actuals, CashMovement $movement, array $shocks): CashMovement
    {
        if ($movement->date === null || $movement->direction !== 'in') {
            return $movement;
        }

        $period = substr($movement->date, 0, 7);
        if (! in_array($period, $actuals->forecastPeriods, true)) {
            return $movement;
        }

        $days = 0;
        foreach ($shocks as $shock) {
            if ($shock->appliesToTarget($movement->category) && $shock->appliesToPeriod($period)) {
                $days += $shock->delayDays();
            }
        }
        if ($days === 0) {
            return $movement;
        }

        return new CashMovement(
            $movement->id,
            $movement->source,
            ForecastDates::shift($movement->date, $days),
            $movement->amount,
            $movement->direction,
            $movement->category,
            $movement->sequence,
            $movement->verified,
        );
    }

    /** An unknown driver stays unknown: scaling null would invent a volume or a
     * rate that nobody stated.
     *
     * Units are carried at the eight decimal places the driver contract allows
     * and then trimmed to their shortest exact form, so a shocked whole number
     * stays readable as "8" rather than "8.00000000" while a fractional result
     * keeps every place it needs.
     */
    private function scaleUnits(?string $units, BigDecimal $factor): ?string
    {
        $value = DriverNumbers::read($units);
        if ($value === null) {
            return null;
        }

        $scaled = (string) $value->multipliedBy($factor)->toScale(8, RoundingMode::HalfUp);
        if (! str_contains($scaled, '.')) {
            return $scaled;
        }

        return rtrim(rtrim($scaled, '0'), '.');
    }

    /** @param list<ScenarioShock> $shocks */
    private function assertEligiblePeriods(ActualsWindow $actuals, array $shocks): void
    {
        $explicit = [];
        foreach ($shocks as $shock) {
            foreach ($shock->periods ?? [] as $period) {
                $explicit[$period] = true;
            }
        }
        if ($explicit !== []) {
            $actuals->assertForecastPeriods(array_keys($explicit));

            return;
        }
        $actuals->assertForecastPeriods([]);
    }
}
