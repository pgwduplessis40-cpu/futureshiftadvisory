<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Support\Methodology\ProvidesMethodology;

/**
 * @phpstan-type FutureCost array{
 *     classification?: string|null,
 *     year?: int|float|string|null,
 *     amount?: int|float|string|null,
 *     quantity?: int|float|string|null,
 *     recurring?: bool|int|string|null,
 *     repeat_every_years?: int|float|string|null
 * }
 */
final class FutureCostSchedule implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.budget_forecast'];
    }

    /**
     * @param  array<int, FutureCost>  $rows
     */
    public static function operatingCostsForMonth(array $rows, int $year, int $monthInYear): float
    {
        return array_reduce($rows, function (float $total, array $row) use ($year, $monthInYear): float {
            if (($row['classification'] ?? 'operating') === 'capital') {
                return $total;
            }

            $rowYear = (int) $row['year'];
            $amount = ((float) $row['amount']) * ((float) $row['quantity']);

            if ((bool) $row['recurring']) {
                return $year >= $rowYear ? $total + $amount : $total;
            }

            $repeatEveryYears = (int) ($row['repeat_every_years'] ?? 0);
            if ($repeatEveryYears > 0) {
                return $year >= $rowYear
                    && ($year - $rowYear) % $repeatEveryYears === 0
                    && $monthInYear === 1
                    ? $total + $amount
                    : $total;
            }

            return $year === $rowYear && $monthInYear === 1 ? $total + $amount : $total;
        }, 0.0);
    }

    /**
     * @param  FutureCost  $row
     */
    public static function timingLabel(array $row): string
    {
        $year = max(2, (int) ($row['year'] ?? 2));

        if ((bool) ($row['recurring'] ?? false)) {
            return 'From Year '.$year.', monthly';
        }

        $repeatEveryYears = max(0, (int) ($row['repeat_every_years'] ?? 0));

        return $repeatEveryYears > 0
            ? 'From Year '.$year.', every '.$repeatEveryYears.' years'
            : 'Year '.$year.', Month 1 once';
    }
}
