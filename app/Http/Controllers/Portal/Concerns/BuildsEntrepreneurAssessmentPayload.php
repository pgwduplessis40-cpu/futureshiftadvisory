<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Concerns;

use App\Models\PlanAssessment;
use App\Models\RatingFramework;
use App\Services\Entrepreneurs\AdvisoryReadiness;
use App\Services\Entrepreneurs\AssessmentScoring;

trait BuildsEntrepreneurAssessmentPayload
{
    /**
     * @return array<string, mixed>
     */
    protected function assessmentPayload(PlanAssessment $assessment): array
    {
        $assessment->loadMissing('businessPlan', 'ratingFramework.criteria');

        $criteria = AssessmentScoring::criteriaPayload($assessment);
        $weightedScore = round(collect($criteria)->sum('contribution'), 2);
        $framework = $assessment->ratingFramework;
        $currentFramework = $this->currentPublishedFrameworkFor($framework);
        $isCurrentFramework = ! $framework instanceof RatingFramework
            || ! $currentFramework instanceof RatingFramework
            || (string) $framework->getKey() === (string) $currentFramework->getKey();

        return [
            'id' => $assessment->id,
            'round' => $assessment->round,
            'status' => $assessment->finalised_at === null ? 'in_review' : 'completed',
            'overall_grade' => $framework?->gradeFor($weightedScore) ?? $assessment->overall_grade,
            'weighted_score' => $weightedScore,
            'threshold' => AdvisoryReadiness::THRESHOLD,
            'finalised_at' => $assessment->finalised_at?->toIso8601String(),
            'created_at' => $assessment->created_at?->toIso8601String(),
            'rating_framework' => [
                'id' => $framework?->id,
                'version' => $framework?->version,
                'criteria_count' => $framework?->criteria->count() ?? count($criteria),
                'published_at' => $framework?->published_at?->toIso8601String(),
                'is_current' => $isCurrentFramework,
                'current_version' => $currentFramework?->version,
                'current_criteria_count' => $currentFramework?->criteria->count(),
                'current_published_at' => $currentFramework?->published_at?->toIso8601String(),
                'current_has_budget' => $currentFramework?->criteria
                    ->contains(fn ($criterion): bool => (int) $criterion->number === 12
                        && strcasecmp((string) $criterion->name, 'Budget') === 0) ?? false,
            ],
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

    private function currentPublishedFrameworkFor(?RatingFramework $framework): ?RatingFramework
    {
        $query = RatingFramework::query()
            ->with('criteria')
            ->where('status', RatingFramework::STATUS_PUBLISHED)
            ->latest('version');

        if ($framework instanceof RatingFramework) {
            $query->where('industry_variant', $framework->industry_variant);
        } else {
            $query->whereNull('industry_variant');
        }

        return $query->first();
    }
}
