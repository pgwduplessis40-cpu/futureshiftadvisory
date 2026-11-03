<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Support\Methodology\ProvidesMethodology;

/** @phpstan-type Row array{amount:float|int,quantity:float|int,month:int,growth_percent?:float|int,growth_cadence?:string,monthly_capacity_units?:float|int|null,monthly_schedule?:list<float|int|null>} */
final class BudgetMonthlySchedule implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.budget_forecast'];
    }

    /** @return list<float|null> */
    public static function normalise(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_map(fn (mixed $value): ?float => is_numeric($value) ? round(max(0, (float) $value), 2) : null, array_slice(array_values($values), 0, 12));
    }

    /** @param Row $row */
    public static function yearOneRevenue(array $row, int $month): float
    {
        if ($month < $row['month']) {
            return 0.0;
        }
        $scheduled = $row['monthly_schedule'][$month - 1] ?? null;
        $elapsed = max(0, $month - $row['month']);
        $factor = 1 + (float) ($row['growth_percent'] ?? 0) / 100;
        $growth = $elapsed === 0 ? 1 : ($factor <= 0 ? 0 : $factor ** (($row['growth_cadence'] ?? 'monthly') === 'annual' ? $elapsed / 12 : $elapsed));
        $units = $scheduled ?? (float) $row['quantity'] * $growth;
        $capacity = $row['monthly_capacity_units'] ?? null;

        return (float) $row['amount'] * (is_numeric($capacity) && $capacity > 0 ? min($units, $capacity) : $units);
    }

    /** @param Row $row */
    public static function yearOneAverage(array $row): float
    {
        return array_sum(array_map(fn (int $month): float => self::yearOneRevenue($row, $month), range(1, 12))) / 12;
    }

    /** @param list<float|int|null> $schedule */
    public static function rate(array $schedule, int $month, float $default): float
    {
        return (float) ($schedule[min(12, $month) - 1] ?? $default);
    }

    /** @param list<float|int|null> $schedule */
    public static function averageRate(array $schedule, float $default): float
    {
        return array_sum(array_map(fn (int $month): float => self::rate($schedule, $month, $default), range(1, 12))) / 12;
    }
}
