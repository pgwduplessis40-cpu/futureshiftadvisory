<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Portal\Concerns\BuildsEntrepreneurAssessmentPayload;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use App\Models\PlanRevision;
use App\Services\Entrepreneurs\AssessmentScoring;
use App\Services\Entrepreneurs\BusinessPlanExecutiveSummary;
use App\Services\Entrepreneurs\BusinessPlanPreviewRenderer;
use App\Services\Entrepreneurs\FunderReadyBusinessPlanBuilder;

/**
 * @phpstan-type AssessmentHistoryEntry array{id:string, round:int, status:string, overall_grade:string|null, weighted_score:float|null, automated_score_available:bool, score_delta:float|null, score_source_summary:string, created_at:string|null, submitted_at:string|null, snapshot_available:bool, snapshot_captured_at:mixed, snapshot_note:string, assessment_url:string, plan_snapshot_url:string|null}
 * @phpstan-type BudgetSummary array{status:string, expected_runway_months:float|int|null, calculated_runway_months:mixed, runway_open_ended:bool, break_even_month:mixed, available_after_launch:mixed, active_flags:list<mixed>}
 * @phpstan-type PlanProgressSummary array{id:string, title:string, status:string, assessment_count:int, latest_round:int|null, latest_grade:string|null, can_assess:bool, assessment_action_label:string, assessment_run:array{status:string|null, requested_at:string|null, started_at:string|null, total_criteria:int|null, completed_criteria:int|null, current_criterion:string|null, completed_at:string|null, failed_at:string|null, failure:string|null}, latest_assessment:mixed, executive_summary:mixed, budget:BudgetSummary, preview_pdf_url:string, budget_pdf_url:string|null, funder_ready:mixed, assess_url:string, assessment_history:list<AssessmentHistoryEntry>, latest_revision:mixed}
 */
final class AdvisorEntrepreneurPlanPayload
{
    use BuildsEntrepreneurAssessmentPayload;

    public function __construct(
        private readonly BusinessPlanPreviewRenderer $planPreview,
        private readonly FunderReadyBusinessPlanBuilder $funderReadyPlans,
        private readonly BusinessPlanExecutiveSummary $executiveSummaries,
    ) {}

    /** @return PlanProgressSummary */
    public function summary(BusinessPlan $plan, EntrepreneurProfile $profile): array
    {
        $latestAssessment = $plan->assessments->sortByDesc('round')->first();
        $latestRevision = $plan->revisions->sortByDesc('round')->first();
        $assessmentRunStatus = $plan->assessment_run_status;
        $assessmentRunInFlight = in_array($assessmentRunStatus, ['queued', 'running'], true);
        $latestAssessmentPayload = $latestAssessment instanceof PlanAssessment
            ? $this->assessmentPayload($latestAssessment)
            : null;

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'status' => $plan->status,
            'assessment_count' => $plan->assessments->count(),
            'latest_round' => $latestAssessment?->round,
            'latest_grade' => ($latestAssessmentPayload['automated_score_available'] ?? true)
                ? $latestAssessment?->overall_grade
                : null,
            'can_assess' => $this->canAssess($plan) && ! $assessmentRunInFlight,
            'assessment_action_label' => match ($assessmentRunStatus) {
                'queued' => 'Assessment queued',
                'running' => 'Assessment running',
                'failed' => 'Retry assessment',
                default => $latestAssessment instanceof PlanAssessment ? 'Run reassessment' : 'Run assessment',
            },
            'assessment_run' => [
                'status' => $assessmentRunStatus,
                'requested_at' => $plan->assessment_run_requested_at?->toIso8601String(),
                'started_at' => $plan->assessment_run_started_at?->toIso8601String(),
                'total_criteria' => $plan->assessment_run_total_criteria,
                'completed_criteria' => $plan->assessment_run_completed_criteria,
                'current_criterion' => $plan->assessment_run_current_criterion,
                'completed_at' => $plan->assessment_run_completed_at?->toIso8601String(),
                'failed_at' => $plan->assessment_run_failed_at?->toIso8601String(),
                'failure' => $plan->assessment_run_failure,
            ],
            'latest_assessment' => $latestAssessmentPayload ? [
                'id' => $latestAssessmentPayload['id'],
                'round' => $latestAssessmentPayload['round'],
                'status' => $latestAssessmentPayload['status'],
                'overall_grade' => $latestAssessmentPayload['overall_grade'],
                'weighted_score' => $latestAssessmentPayload['weighted_score'],
                'threshold' => $latestAssessmentPayload['threshold'],
                'meets_advisory_threshold' => (bool) $latestAssessmentPayload['automated_score_available']
                    && (float) $latestAssessmentPayload['weighted_score'] >= (float) $latestAssessmentPayload['threshold'],
                'automated_score_available' => $latestAssessmentPayload['automated_score_available'],
                'finalised_at' => $latestAssessmentPayload['finalised_at'],
                'rating_framework' => $latestAssessmentPayload['rating_framework'],
                'url' => route('advisor.entrepreneurs.assessments.show', [$profile, $latestAssessment], absolute: false),
                'finalise_url' => route('advisor.entrepreneurs.assessments.finalise', [$profile, $latestAssessment], absolute: false),
            ] : null,
            'executive_summary' => [
                ...$this->executiveSummaries->status($plan, $profile),
            ],
            'budget' => $this->budgetSummary($plan->budgetRunway),
            'preview_pdf_url' => route('advisor.entrepreneurs.plans.latest.preview', $profile, absolute: false),
            'budget_pdf_url' => $this->planPreview->budgetUnlocked($plan)
                ? route('advisor.entrepreneurs.plans.latest.budget-pack.pdf', $profile, absolute: false)
                : null,
            'funder_ready' => [
                ...$this->funderReadyPlans->status($plan, $profile),
                'document_url' => route('advisor.entrepreneurs.plans.funder-ready.pdf', [$profile, $plan], absolute: false),
            ],
            'assess_url' => route('advisor.entrepreneurs.plans.assessments.store', [$profile, $plan], absolute: false),
            'assessment_history' => $this->assessmentHistory($plan, $profile),
            'latest_revision' => $latestRevision instanceof PlanRevision ? [
                'id' => $latestRevision->id,
                'round' => $latestRevision->round,
                'submitted_at' => $latestRevision->submitted_at?->toIso8601String(),
                'trajectory_percent' => data_get($latestRevision->progress_comparison, 'trajectory_percent'),
                'overall_delta' => data_get($latestRevision->progress_comparison, 'overall_delta'),
                'biggest_improvements' => data_get($latestRevision->progress_comparison, 'biggest_improvements', []),
                'remaining_gaps' => data_get($latestRevision->progress_comparison, 'remaining_gaps', []),
            ] : null,
        ];
    }

    /** @return list<AssessmentHistoryEntry> */
    private function assessmentHistory(BusinessPlan $plan, EntrepreneurProfile $profile): array
    {
        $previousWeightedScore = null;

        return $plan->assessments
            ->sortBy('round')
            ->values()
            ->map(function (PlanAssessment $assessment) use ($plan, $profile, &$previousWeightedScore): array {
                $payload = $this->assessmentPayload($assessment);
                $snapshot = $assessment->plan_snapshot;
                $snapshotAvailable = is_array($snapshot) && is_array($snapshot['phases'] ?? null);
                $automatedScoreAvailable = (bool) ($payload['automated_score_available'] ?? true);
                $weightedScore = $automatedScoreAvailable ? (float) $payload['weighted_score'] : null;
                $scoreDelta = $weightedScore === null || $previousWeightedScore === null
                    ? null
                    : round($weightedScore - $previousWeightedScore, 1);
                if ($weightedScore !== null) {
                    $previousWeightedScore = $weightedScore;
                }

                return [
                    'id' => $assessment->id,
                    'round' => $assessment->round,
                    'status' => $payload['status'],
                    'overall_grade' => $automatedScoreAvailable ? $payload['overall_grade'] : null,
                    'weighted_score' => $weightedScore,
                    'automated_score_available' => $automatedScoreAvailable,
                    'score_delta' => $scoreDelta,
                    'score_source_summary' => $this->scoreSourceSummary($assessment),
                    'created_at' => $assessment->created_at?->toIso8601String(),
                    'submitted_at' => $this->submittedAt($plan, $assessment),
                    'snapshot_available' => $snapshotAvailable,
                    'snapshot_captured_at' => is_array($snapshot) ? data_get($snapshot, 'captured_at') : null,
                    'snapshot_note' => $snapshotAvailable
                        ? 'Submitted-plan snapshot captured for this assessment round.'
                        : 'Historical round: no submitted-plan snapshot was captured for this assessment.',
                    'assessment_url' => route('advisor.entrepreneurs.assessments.show', [$profile, $assessment], absolute: false),
                    'plan_snapshot_url' => $snapshotAvailable
                        ? route('advisor.entrepreneurs.assessments.plan-preview', [$profile, $assessment], absolute: false)
                        : null,
                ];
            })
            ->sortByDesc('round')
            ->values()
            ->all();
    }

    private function scoreSourceSummary(PlanAssessment $assessment): string
    {
        $incompleteCriterionNumbers = AssessmentScoring::incompleteCriterionNumbers($assessment);

        if ($incompleteCriterionNumbers !== []) {
            return 'Incomplete assessment: no valid score is recorded for criterion '.implode(', ', $incompleteCriterionNumbers).'. Retained for audit only and excluded from advice and progression.';
        }

        $scores = collect($assessment->ai_scores ?? [])
            ->filter(fn (mixed $score): bool => is_array($score));
        $total = $scores->count();

        if ($total === 0) {
            return 'No criterion score metadata recorded.';
        }

        $legacyReused = $scores->filter(fn (array $score): bool => (string) ($score['score_source'] ?? data_get($score, 'metadata.score_source')) === 'reused_identical_context')->count();
        $reusedUnchangedEvidence = $scores->filter(fn (array $score): bool => (string) ($score['score_source'] ?? data_get($score, 'metadata.score_source')) === 'reused_unchanged_evidence')->count();
        $ai = $scores->filter(fn (array $score): bool => (string) ($score['score_source'] ?? data_get($score, 'metadata.score_source')) === 'ai_assessment')->count();
        $fallback = $scores->filter(fn (array $score): bool => (string) ($score['score_source'] ?? data_get($score, 'metadata.score_source')) === 'deterministic_fallback')->count();

        if ($legacyReused === $total) {
            return 'Carried forward from an earlier assessment; no fresh AI score was generated.';
        }

        if ($reusedUnchangedEvidence === $total) {
            return 'Calibrated criterion evidence unchanged; all scores retained from the prior assessment.';
        }

        if ($fallback === $total) {
            return 'Invalid automated result: no AI score was returned. Retained for audit only and excluded from progression.';
        }

        if ($fallback > 0) {
            return 'Invalid automated result: '.$fallback.' criterion scores were fallback values. Retained for audit only and excluded from progression.';
        }

        if ($legacyReused > 0) {
            return $ai.' AI-scored criteria and '.$legacyReused.' carried forward from an earlier assessment.';
        }

        if ($reusedUnchangedEvidence > 0) {
            return $ai.' criteria rescored and '.$reusedUnchangedEvidence.' retained from unchanged criterion evidence.';
        }

        $calibrated = $scores->every(fn (array $score): bool => data_get($score, 'metadata.scoring_method') === 'calibrated_band_v1'
            && data_get($score, 'metadata.evidence_mode') === 'criterion_scoped_submitted_snapshot'
            && data_get($score, 'metadata.scoring_contract_version') === 'criterion_evidence_v1');
        if ($calibrated) {
            return 'Calibrated rubric-band assessment against mapped criterion evidence; full submitted plan snapshot retained for audit.';
        }

        return 'AI-scored against the captured plan context.';
    }

    private function submittedAt(BusinessPlan $plan, PlanAssessment $assessment): ?string
    {
        if ((int) $assessment->round > 1) {
            $revision = $plan->revisions->first(
                fn (PlanRevision $candidate): bool => (int) $candidate->round === (int) $assessment->round,
            );

            if ($revision instanceof PlanRevision) {
                return $revision->submitted_at?->toIso8601String();
            }
        }

        return $plan->submitted_at?->toIso8601String()
            ?? $assessment->created_at?->toIso8601String();
    }

    private function canAssess(BusinessPlan $plan): bool
    {
        return $plan->status !== BusinessPlan::STATUS_REVISING;
    }

    /** @return BudgetSummary */
    private function budgetSummary(?EntrepreneurBudget $budget): array
    {
        $computed = $budget instanceof EntrepreneurBudget ? (array) $budget->computed : [];
        $activeFlags = collect($budget instanceof EntrepreneurBudget ? (array) $budget->flags : [])
            ->filter(fn (array $flag): bool => empty($flag['acknowledged_at']))
            ->values()
            ->all();

        return [
            'status' => $budget instanceof EntrepreneurBudget ? $budget->status : EntrepreneurBudget::STATUS_NOT_STARTED,
            'expected_runway_months' => $budget?->expected_runway_months,
            'calculated_runway_months' => data_get($computed, 'runway_months'),
            'runway_open_ended' => (bool) data_get($computed, 'runway_open_ended', false),
            'break_even_month' => data_get($computed, 'break_even_month'),
            'available_after_launch' => data_get($computed, 'available_after_launch'),
            'active_flags' => $activeFlags,
        ];
    }
}
