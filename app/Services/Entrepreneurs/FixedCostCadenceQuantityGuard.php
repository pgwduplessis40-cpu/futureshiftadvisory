<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

final class FixedCostCadenceQuantityGuard
{
    /**
     * Quantity is the number of parallel units (for example, licences or people),
     * never the number of billing periods in a year. These exact values are a
     * strong signal that a rate will be converted twice.
     *
     * @param  array<int, array{label?: string, quantity?: float|int|string, cadence?: string}>  $rows
     * @return array<int, array{index:int,label:string,quantity:float,cadence:string,payments_per_year:int}>
     */
    public function conflicts(array $rows): array
    {
        $paymentsPerYear = [
            'weekly' => 52,
            'fortnightly' => 26,
            'monthly' => 12,
            'quarterly' => 4,
        ];
        $conflicts = [];

        foreach ($rows as $index => $row) {
            $cadence = (string) ($row['cadence'] ?? 'monthly');
            $expectedQuantity = $paymentsPerYear[$cadence] ?? null;
            $quantity = (float) ($row['quantity'] ?? 1);

            if ($expectedQuantity === null || abs($quantity - $expectedQuantity) >= 0.005) {
                continue;
            }

            $conflicts[] = [
                'index' => $index,
                'label' => trim((string) ($row['label'] ?? 'Unlabelled cost')) ?: 'Unlabelled cost',
                'quantity' => $quantity,
                'cadence' => $cadence,
                'payments_per_year' => $expectedQuantity,
            ];
        }

        return $conflicts;
    }

    /**
     * @param  array<int, array{label?: string, quantity?: float|int|string, cadence?: string}>  $rows
     * @return array<int, string>
     */
    public function descriptions(array $rows): array
    {
        return array_map(
            fn (array $conflict): string => sprintf(
                '%s (Qty %s with %s cadence)',
                $conflict['label'],
                number_format($conflict['quantity'], 2, '.', ''),
                $conflict['cadence'],
            ),
            $this->conflicts($rows),
        );
    }

    public function addInputQualityConflicts(array $quality, array $rows): array
    {
        $quality['fixed_cost_cadence_quantity_conflicts'] = $this->descriptions($rows);

        return $quality;
    }
}
