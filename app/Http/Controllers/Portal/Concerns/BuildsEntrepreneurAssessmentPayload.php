<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Concerns;

use App\Models\PlanAssessment;
use App\Services\Entrepreneurs\AdvisoryReadiness;

trait BuildsEntrepreneurAssessmentPayload
{
    /**
     * @return array<string, mixed>
     */
    protected function assessmentPayload(PlanAssessment $assessment): array
    {
        $assessment->loadMissing('businessPlan', 'ratingFramework.criteria');

        $criteria = $this->assessmentCriteriaPayload($assessment);
        $weightedScore = round(collect($criteria)->sum('contribution'), 2);
        $framework = $assessment->ratingFramework;

        return [
            'id' => $assessment->id,
            'round' => $assessment->round,
            'status' => $assessment->finalised_at === null ? 'in_review' : 'completed',
            'overall_grade' => $framework?->gradeFor($weightedScore) ?? $assessment->overall_grade,
            'weighted_score' => $weightedScore,
            'threshold' => AdvisoryReadiness::THRESHOLD,
            'finalised_at' => $assessment->finalised_at?->toIso8601String(),
            'created_at' => $assessment->created_at?->toIso8601String(),
            'document_support' => [
                'attached_document_count' => (int) data_get($assessment->document_support, 'attached_document_count', 0),
                'summary' => (string) data_get(
                    $assessment->document_support,
                    'criterion_score_adjustment',
                    'Verified documents can support criterion scores; unresolved flags block assessment finalisation.',
                ),
            ],
            'mentor_notes' => $this->entrepreneurVisibleMentorNotes($assessment),
            'criteria' => $criteria,
            'explanation' => sprintf(
                'This score is the weighted total from the latest plan assessment. Advisor-reviewed scores are used where present; otherwise the first-pass score is used. A score of %.0f or above marks the plan as advisory ready.',
                AdvisoryReadiness::THRESHOLD,
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function assessmentCriteriaPayload(PlanAssessment $assessment): array
    {
        $aiScores = collect($assessment->ai_scores ?? [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->keyBy(fn (array $row): int => (int) ($row['criterion_number'] ?? 0));
        $advisorScores = collect($assessment->advisor_scores ?? [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->keyBy(fn (array $row): int => (int) ($row['criterion_number'] ?? 0));
        $criteria = $assessment->ratingFramework?->criteria;

        if ($criteria === null) {
            return [];
        }

        return $criteria
            ->map(function ($criterion) use ($aiScores, $advisorScores): array {
                $advisor = $advisorScores->get($criterion->number);
                $ai = $aiScores->get($criterion->number);
                $hasAdvisorScore = is_array($advisor) && is_numeric($advisor['score'] ?? null);
                $score = $hasAdvisorScore
                    ? (float) $advisor['score']
                    : (float) (is_array($ai) && is_numeric($ai['score'] ?? null) ? $ai['score'] : 0);
                $weight = (float) $criterion->weight;

                return [
                    'number' => $criterion->number,
                    'name' => $criterion->name,
                    'weight' => $weight,
                    'score' => $score,
                    'contribution' => round($score * ($weight / 100), 2),
                    'source' => $hasAdvisorScore ? 'advisor_review' : 'first_pass',
                    'source_label' => $hasAdvisorScore ? 'Advisor reviewed' : 'First pass',
                    'rationale' => $hasAdvisorScore
                        ? (string) ($advisor['note'] ?? '')
                        : (string) (is_array($ai) ? ($ai['rationale'] ?? '') : ''),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function entrepreneurVisibleMentorNotes(PlanAssessment $assessment): array
    {
        $notes = $assessment->mentor_notes ?? [];
        if (! is_array($notes)) {
            return [];
        }

        unset($notes['private_advisory']);

        return $notes;
    }
}
