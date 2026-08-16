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
        $planSnapshot = $assessment->plan_snapshot;
        $snapshotAvailable = is_array($planSnapshot) && is_array($planSnapshot['phases'] ?? null);
        $reusedScores = collect($assessment->ai_scores ?? [])
            ->filter(fn (mixed $score): bool => is_array($score)
                && (string) ($score['score_source'] ?? data_get($score, 'metadata.score_source')) === 'reused_identical_context');
        $hasFallbackScores = AssessmentScoring::hasFallbackScores($assessment);
        $requiresFullReassessment = $reusedScores->isNotEmpty() || $hasFallbackScores;
        $automatedScoreAvailable = ! $hasFallbackScores;
        $automatedScoreDescription = match (true) {
            $hasFallbackScores => 'No valid AI score was returned for this historical round. Its calculated fallback values are retained only for audit and must not be used for advice or progression.',
            $reusedScores->isNotEmpty() => 'This historical round carried forward automatic scores from an earlier assessment. The submitted-plan snapshot is correct for this round, but no new AI score was generated. Run a fresh assessment before relying on the automatic score.',
            default => 'Advisor-reviewed scores override the automated score only where an advisor has added a review score.',
        };
        $explanation = match (true) {
            $hasFallbackScores => sprintf(
                'Assessment round %d has no valid AI score. It is retained as an audit record only and is excluded from progression. Run a fresh assessment before relying on it.',
                max(1, (int) $assessment->round),
            ),
            $requiresFullReassessment => sprintf(
                'This score is the weighted total from assessment round %d. The plan snapshot belongs to this round, but the automatic scores were carried forward from an earlier round instead of being generated again. Run a fresh assessment to score all submitted plan evidence. A score of %.0f or above marks the plan as advisory ready.',
                max(1, (int) $assessment->round),
                AdvisoryReadiness::THRESHOLD,
            ),
            default => sprintf(
                'This score is the weighted total from assessment round %d. Advisor-reviewed scores are used where present; otherwise the automated score generated for this round is used. A score of %.0f or above marks the plan as advisory ready.',
                max(1, (int) $assessment->round),
                AdvisoryReadiness::THRESHOLD,
            ),
        };

        return [
            'id' => $assessment->id,
            'round' => $assessment->round,
            'status' => $assessment->finalised_at === null ? 'in_review' : 'completed',
            'overall_grade' => $framework?->gradeFor($weightedScore) ?? $assessment->overall_grade,
            'weighted_score' => $weightedScore,
            'threshold' => AdvisoryReadiness::THRESHOLD,
            'requires_full_reassessment' => $requiresFullReassessment,
            'automated_score_available' => $automatedScoreAvailable,
            'finalised_at' => $assessment->finalised_at?->toIso8601String(),
            'created_at' => $assessment->created_at?->toIso8601String(),
            'basis' => [
                'label' => $assessment->round > 1 ? 'Resubmitted business plan' : 'Submitted business plan',
                'business_plan_id' => $assessment->business_plan_id,
                'business_plan_title' => $assessment->businessPlan?->title,
                'business_plan_status' => $assessment->businessPlan?->status,
                'business_plan_submitted_at' => $assessment->businessPlan?->submitted_at?->toIso8601String(),
                'business_plan_updated_at' => $assessment->businessPlan?->updated_at?->toIso8601String(),
                'plan_snapshot_available' => $snapshotAvailable,
                'plan_snapshot_url' => null,
                'plan_snapshot_captured_at' => is_array($planSnapshot) ? data_get($planSnapshot, 'captured_at') : null,
                'summary' => $snapshotAvailable
                    ? sprintf(
                        'Round %d was scored from the submitted plan snapshot captured for this assessment round. %s',
                        max(1, (int) $assessment->round),
                        $automatedScoreDescription,
                    )
                    : sprintf(
                        'Round %d was scored from the business plan evidence available when this assessment was created. A submitted-plan snapshot is not available for this historical round.',
                        max(1, (int) $assessment->round),
                    ),
            ],
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
            'explanation' => $explanation,
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

        unset(
            $notes['private_advisory'],
            $notes['advisor_feedback'],
            $notes['proposed_reply'],
            $notes['feedback_sent_at'],
            $notes['feedback_sent_by_user_id'],
            $notes['feedback_snapshot'],
            $notes['updated_by_user_id'],
            $notes['updated_at'],
        );

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
