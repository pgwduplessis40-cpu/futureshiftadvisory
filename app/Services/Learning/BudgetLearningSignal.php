<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\LearningUpdate;

final class BudgetLearningSignal
{
    public const SOURCE_TYPE = 'entrepreneur_budget_model';

    /**
     * @param  array<int, mixed>  $activeFlagKeys
     * @param  array<int, mixed>  $missingAssumptions
     */
    public function key(
        mixed $budgetId,
        array $activeFlagKeys,
        array $missingAssumptions,
        mixed $breakEvenYear,
        mixed $cashFlowPositiveYear,
    ): string {
        return hash('sha256', implode('|', [
            (string) $budgetId,
            implode(',', $this->normaliseList($activeFlagKeys)),
            implode(',', $this->normaliseList($missingAssumptions)),
            $this->normaliseValue($breakEvenYear),
            $this->normaliseValue($cashFlowPositiveYear),
        ]));
    }

    public function keyForUpdate(LearningUpdate $update): ?string
    {
        return $this->keyForPayload((array) $update->source, (array) $update->evidence);
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $evidence
     */
    public function keyForPayload(array $source, array $evidence): ?string
    {
        if (data_get($source, 'type') !== self::SOURCE_TYPE || blank($budgetId = data_get($source, 'budget_id'))) {
            return null;
        }

        return $this->key(
            budgetId: $budgetId,
            activeFlagKeys: (array) data_get($evidence, 'active_flag_keys', []),
            missingAssumptions: (array) data_get($evidence, 'missing_assumptions', []),
            breakEvenYear: data_get($evidence, 'break_even_year'),
            cashFlowPositiveYear: data_get($evidence, 'cash_flow_positive_year'),
        );
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    private function normaliseList(array $values): array
    {
        return collect($values)
            ->filter(fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '')
            ->map(fn (mixed $value): string => trim((string) $value))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function normaliseValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'none';
        }

        return is_scalar($value) ? trim((string) $value) : 'none';
    }
}
