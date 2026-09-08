<?php

declare(strict_types=1);

namespace App\Services\Budgets;

final class StrategicBudgetAssessmentScorer
{
    /**
     * @param  array<array-key, mixed>  $confidence
     * @param  array<int, array{key:string,score:int}>  $criteria
     * @return array<string, int>
     */
    public function scores(int $businessPlanReadiness, array $confidence, array $criteria): array
    {
        $confidenceScore = (int) data_get($confidence, 'score', 0);
        $reconciliationScore = (int) data_get(
            collect($criteria)->firstWhere('key', 'plan_budget_reconciliation'),
            'score',
            0,
        );

        return [
            'business_plan_readiness' => $businessPlanReadiness,
            'progress' => (int) data_get($confidence, 'progress_score', 0),
            'readiness' => min($confidenceScore, $reconciliationScore),
            'confidence' => $confidenceScore,
            'plan_budget_reconciliation' => $reconciliationScore,
        ];
    }
}
