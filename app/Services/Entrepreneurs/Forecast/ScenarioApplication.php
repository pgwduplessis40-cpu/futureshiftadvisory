<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

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
    public const VERSION = 'phase-2d.1';

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
     * @param  list<RevenueStream>  $streams
     * @return list<RevenueStream>
     */
    public function shockStreams(ActualsWindow $actuals, array $streams, ScenarioDefinition $definition): array
    {
        $shocks = $definition->shocksOfType(ScenarioShock::TYPE_VOLUME_FACTOR);
        if ($shocks === []) {
            return $streams;
        }

        $this->assertEligiblePeriods($actuals, $shocks);

        $shocked = [];
        foreach ($streams as $stream) {
            $shocked[] = $this->shockStream($actuals, $stream, $shocks);
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

    /** @param list<ScenarioShock> $shocks */
    private function shockStream(ActualsWindow $actuals, RevenueStream $stream, array $shocks): RevenueStream
    {
        $applicable = array_values(array_filter(
            $shocks,
            static fn (ScenarioShock $shock): bool => $shock->appliesToTarget($stream->id)
        ));
        if ($applicable === []) {
            return $stream;
        }

        $schedule = [];
        foreach ($stream->schedule as $period => $month) {
            $schedule[$period] = $this->shockMonth($actuals, (string) $period, $month, $applicable);
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

    /** @param list<ScenarioShock> $shocks */
    private function shockMonth(ActualsWindow $actuals, string $period, StreamMonth $month, array $shocks): StreamMonth
    {
        if (! in_array($period, $actuals->forecastPeriods, true)) {
            return $month;
        }

        $factor = null;
        foreach ($shocks as $shock) {
            if ($shock->appliesToPeriod($period)) {
                $factor = $factor === null ? $shock->factorValue() : $factor->multipliedBy($shock->factorValue());
            }
        }
        if ($factor === null) {
            return $month;
        }

        $values = [];
        foreach (self::VOLUME_FIELDS as $field) {
            $values[$field] = $this->scaleUnits($month->{$field}, $factor);
        }

        return new StreamMonth(
            $values['bookedUnits'],
            $values['deliveryUnits'],
            $values['invoicedUnits'],
            $month->founderHours,
            $month->additionalCapacityUnits,
            $month->provider,
            $month->providerUnitCost,
            $month->noCostBasis,
        );
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

    /** An unknown unit count stays unknown: scaling null would invent a volume.
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
