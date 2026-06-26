<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

final class BudgetCalculator
{
    private const MONTHS = 12;

    /**
     * @param  array<int, array<string, mixed>>  $launchCosts
     * @param  array<int, array<string, mixed>>  $monthlyFixedCosts
     * @param  array<int, array<string, mixed>>  $revenueForecast
     * @param  array<int, array<string, mixed>>  $fundingSources
     * @return array<string, mixed>
     */
    public function compute(
        array $launchCosts,
        array $monthlyFixedCosts,
        array $revenueForecast,
        array $fundingSources,
        ?int $expectedRunwayMonths,
    ): array {
        $launchRows = $this->normaliseRows($launchCosts);
        $fixedRows = $this->normaliseRows($monthlyFixedCosts);
        $revenueRows = $this->normaliseRows($revenueForecast);
        $fundingRows = $this->normaliseRows($fundingSources);

        $totalLaunchCosts = $this->sumRows($launchRows);
        $monthlyFixedTotal = $this->sumRows($fixedRows);
        $totalFunding = $this->sumRows($fundingRows);
        $availableAfterLaunch = $totalFunding - $totalLaunchCosts;
        $cumulativeCash = $availableAfterLaunch;
        $breakEvenMonth = null;
        $runwayMonths = null;
        $runwayOpenEnded = false;
        $series = [];

        for ($month = 1; $month <= self::MONTHS; $month++) {
            $monthlyRevenue = $this->monthlyRevenue($revenueRows, $month);
            $variableCosts = $this->monthlyVariableCosts($revenueRows, $month);
            $netCashFlow = $monthlyRevenue - $variableCosts - $monthlyFixedTotal;
            $cumulativeCash += $netCashFlow;

            if ($breakEvenMonth === null && $netCashFlow >= 0) {
                $breakEvenMonth = $month;
            }

            if ($runwayMonths === null && $cumulativeCash < 0) {
                $runwayMonths = max(0, $month - 1);
            }

            $series[] = [
                'month' => $month,
                'revenue' => round($monthlyRevenue, 2),
                'variable_costs' => round($variableCosts, 2),
                'fixed_costs' => round($monthlyFixedTotal, 2),
                'net_cash_flow' => round($netCashFlow, 2),
                'cumulative_cash' => round($cumulativeCash, 2),
            ];
        }

        if ($runwayMonths === null && $this->hasAnyInput($launchRows, $fixedRows, $revenueRows, $fundingRows)) {
            $runwayMonths = self::MONTHS;
            $lastMonth = end($series);
            $runwayOpenEnded = is_array($lastMonth) && ((float) $lastMonth['cumulative_cash']) >= 0;
        }

        $populatedInputs = [
            'launch_costs' => count($launchRows),
            'monthly_fixed_costs' => count($fixedRows),
            'revenue_forecast' => count($revenueRows),
            'funding_sources' => count($fundingRows),
            'expected_runway_months' => $expectedRunwayMonths === null ? 0 : 1,
        ];

        return [
            'total_launch_costs' => round($totalLaunchCosts, 2),
            'monthly_fixed_costs' => round($monthlyFixedTotal, 2),
            'total_funding' => round($totalFunding, 2),
            'available_after_launch' => round($availableAfterLaunch, 2),
            'runway_months' => $runwayMonths,
            'runway_open_ended' => $runwayOpenEnded,
            'break_even_month' => $breakEvenMonth,
            'break_even_reached' => $breakEvenMonth !== null,
            'monthly_series' => $series,
            'populated_inputs' => $populatedInputs,
            'input_count' => array_sum($populatedInputs),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function normaliseRows(array $rows): array
    {
        return collect($rows)
            ->map(function (array $row): array {
                $label = trim((string) ($row['label'] ?? $row['name'] ?? ''));
                $amount = $this->number($row['amount'] ?? $row['value'] ?? 0);
                $quantity = max(1.0, $this->number($row['quantity'] ?? 1));
                $month = $this->month($row['month'] ?? 1);
                $monthlyGrowthPercent = $this->number($row['monthly_growth_percent'] ?? $row['growth'] ?? 0);
                $variableCostPercent = $this->number($row['variable_cost_percent'] ?? 0);
                $confidence = $this->confidence($row['confidence'] ?? null);

                return [
                    'label' => $label,
                    'amount' => round($amount, 2),
                    'quantity' => round($quantity, 2),
                    'month' => $month,
                    'monthly_growth_percent' => round($monthlyGrowthPercent, 2),
                    'variable_cost_percent' => round($variableCostPercent, 2),
                    'confidence' => $confidence,
                ];
            })
            ->filter(fn (array $row): bool => $row['label'] !== '' || $row['amount'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function sumRows(array $rows): float
    {
        return array_reduce(
            $rows,
            fn (float $total, array $row): float => $total + (((float) $row['amount']) * ((float) $row['quantity'])),
            0.0,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function monthlyRevenue(array $rows, int $month): float
    {
        return array_reduce($rows, function (float $total, array $row) use ($month): float {
            if ((int) $row['month'] > $month) {
                return $total;
            }

            $elapsed = max(0, $month - (int) $row['month']);
            $base = ((float) $row['amount']) * ((float) $row['quantity']);
            $growth = 1 + (((float) $row['monthly_growth_percent']) / 100);

            return $total + ($base * ($growth > 0 ? $growth ** $elapsed : 1));
        }, 0.0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function monthlyVariableCosts(array $rows, int $month): float
    {
        return array_reduce($rows, function (float $total, array $row) use ($month): float {
            if ((int) $row['month'] > $month) {
                return $total;
            }

            $elapsed = max(0, $month - (int) $row['month']);
            $base = ((float) $row['amount']) * ((float) $row['quantity']);
            $growth = 1 + (((float) $row['monthly_growth_percent']) / 100);
            $revenue = $base * ($growth > 0 ? $growth ** $elapsed : 1);

            return $total + ($revenue * (((float) $row['variable_cost_percent']) / 100));
        }, 0.0);
    }

    /**
     * @param  array<int, array<string, mixed>>  ...$groups
     */
    private function hasAnyInput(array ...$groups): bool
    {
        foreach ($groups as $group) {
            if ($group !== []) {
                return true;
            }
        }

        return false;
    }

    private function number(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return max(0.0, (float) $value);
        }

        if (is_string($value)) {
            $cleaned = preg_replace('/[^0-9.\-]/', '', $value);

            return max(0.0, is_numeric($cleaned) ? (float) $cleaned : 0.0);
        }

        return 0.0;
    }

    private function month(mixed $value): int
    {
        $month = is_numeric($value) ? (int) $value : 1;

        return min(self::MONTHS, max(1, $month));
    }

    private function confidence(mixed $value): string
    {
        return in_array($value, ['known', 'estimate', 'guess'], true)
            ? (string) $value
            : 'estimate';
    }
}
