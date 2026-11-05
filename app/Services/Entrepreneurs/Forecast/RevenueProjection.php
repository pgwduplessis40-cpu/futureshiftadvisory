<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\ReleaseGate\CanonicalJson;
use App\Support\Methodology\ProvidesMethodology;
use Brick\Math\BigDecimal;

/**
 * @phpstan-import-type StreamResult from StreamProjection
 *
 * @phpstan-type MonthResult array{period:string,status:string,base_revenue:?string,base_contractor_cost:?string,founder_hours_allocated:?string,founder_hours_available:?string,streams:list<StreamResult>,missing:list<string>,errors:list<string>}
 * @phpstan-type Projection array{schema_version:int,engine_version:string,scope:string,currency:string,tax_basis:string,rounding:string,input_hash:string,months:list<MonthResult>}
 */
final class RevenueProjection implements ProvidesMethodology
{
    public const VERSION = 'phase-2a.1';

    public static function methodologyIds(): array
    {
        return ['entrepreneur.driver_revenue'];
    }

    /**
     * This pure projection neither verifies evidence nor grants release. It is
     * intentionally not selected by the stored-budget workflow in slice 2A.
     *
     * @param  list<RevenueStream>  $streams
     * @param  array<string,FounderMonth>  $founderMonths
     * @return Projection
     */
    public function calculate(ForecastCalendar $calendar, array $streams, array $founderMonths): array
    {
        $ids = array_map(fn (RevenueStream $stream): string => $stream->id, $streams);
        if (count($ids) !== count(array_unique($ids))) {
            throw new \InvalidArgumentException('Revenue stream identities must be unique.');
        }
        foreach ([$founderMonths, ...array_map(fn (RevenueStream $s): array => $s->schedule, $streams)] as $schedule) {
            if (array_diff(array_keys($schedule), $calendar->periods) !== []) {
                throw new \InvalidArgumentException('A schedule extends beyond the declared calendar; do not silently discard required periods.');
            }
        }
        usort($streams, fn (RevenueStream $a, RevenueStream $b): int => strcmp($a->id, $b->id));
        $months = [];
        foreach ($calendar->periods as $period) {
            $months[] = $this->month($period, $streams, $founderMonths[$period] ?? null);
        }

        return ['schema_version' => 1, 'engine_version' => self::VERSION, 'scope' => 'revenue_drivers_only',
            'currency' => 'NZD', 'tax_basis' => 'GST exclusive',
            'rounding' => 'Capacity units rounded down to 8 decimals; stream-month money half up to cents; totals sum those line amounts.',
            'input_hash' => CanonicalJson::hash([$calendar, $streams, $founderMonths]), 'months' => $months];
    }

    /** @param list<RevenueStream> $streams
     * @return MonthResult
     */
    private function month(string $period, array $streams, ?FounderMonth $founder): array
    {
        $missing = [];
        $errors = [];
        $availability = DriverNumbers::read($founder?->availableHours);
        $knownHours = BigDecimal::zero();
        $allocationComplete = true;
        foreach (['available_hours' => $founder?->availableHours, 'administration_hours' => $founder?->administrationHours,
            'business_development_hours' => $founder?->businessDevelopmentHours] as $key => $value) {
            if ($value === null) {
                $missing[] = 'founder.'.$key;
                $allocationComplete = false;
            } elseif ($key !== 'available_hours') {
                $knownHours = $knownHours->plus($value);
            }
        }
        $revenue = BigDecimal::zero();
        $cost = BigDecimal::zero();
        $totalsComplete = true;
        $rows = [];
        foreach ($streams as $stream) {
            $input = $stream->schedule[$period] ?? null;
            $row = (new StreamProjection)->calculate($stream, $input);
            $rows[] = $row;
            if ($stream->inBaseCase === false) {
                // Upside is a separate potential line, not an allocation from
                // the base founder budget or a contribution to base totals.
                continue;
            }
            foreach ($row['missing'] as $field) {
                $missing[] = $stream->id.'.'.$field;
            }
            foreach ($row['errors'] as $error) {
                $errors[] = $stream->id.'.'.$error;
            }
            if ($stream->inBaseCase === null || $input?->founderHours === null) {
                $allocationComplete = false;
            } else {
                $knownHours = $knownHours->plus($input->founderHours);
            }
            if ($stream->inBaseCase === null || $row['revenue'] === null || $row['contractor_cost'] === null) {
                $totalsComplete = false;
            } else {
                $revenue = $revenue->plus($row['revenue']);
                $cost = $cost->plus($row['contractor_cost']);
            }
        }
        if ($availability !== null && $knownHours->isGreaterThan($availability)) {
            $errors[] = 'founder.shared_hours_exceeded';
        }
        $usable = $totalsComplete && $allocationComplete && $errors === [];

        return ['period' => $period, 'status' => $errors !== [] ? 'fail' : ($missing !== [] ? 'cannot_verify' : 'pass'),
            'base_revenue' => $usable ? DriverNumbers::money($revenue) : null,
            'base_contractor_cost' => $usable ? DriverNumbers::money($cost) : null,
            'founder_hours_allocated' => $allocationComplete ? (string) $knownHours : null,
            'founder_hours_available' => $founder?->availableHours, 'streams' => $rows, 'missing' => $missing, 'errors' => $errors];
    }
}
