<?php

declare(strict_types=1);

namespace Tests\Unit\Budgets;

use App\Services\Budgets\StrategicBudgetAssessmentScorer;
use Tests\TestCase;

final class StrategicBudgetAssessmentScorerTest extends TestCase
{
    public function test_it_prevents_a_high_confidence_score_from_masking_unreconciled_assumptions(): void
    {
        $scores = (new StrategicBudgetAssessmentScorer)->scores(
            businessPlanReadiness: 100,
            confidence: ['score' => 76, 'progress_score' => 100],
            criteria: [[
                'key' => 'plan_budget_reconciliation',
                'score' => 18,
            ]],
        );

        $this->assertSame(18, $scores['readiness']);
        $this->assertSame(76, $scores['confidence']);
        $this->assertSame(18, $scores['plan_budget_reconciliation']);
    }
}
