<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\PlanAssessment;
use App\Models\PlanRevision;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class Revision implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.revision_progress'];
    }

    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function open(BusinessPlan $plan, User $actor): BusinessPlan
    {
        $plan->forceFill([
            'status' => BusinessPlan::STATUS_REVISING,
        ])->save();

        $this->audit->record('entrepreneur.plan_revision_opened', subject: $plan, actor: $actor, after: [
            'business_plan_id' => $plan->getKey(),
        ]);

        return $plan->refresh();
    }

    public function submit(BusinessPlan $plan, User $actor): PlanRevision
    {
        return DB::transaction(function () use ($plan, $actor): PlanRevision {
            $lockedPlan = BusinessPlan::query()
                ->whereKey($plan->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // A browser retry after the first successful hand-off must not
            // create a second revision or assessment request.
            if ($lockedPlan->status !== BusinessPlan::STATUS_REVISING) {
                return PlanRevision::query()
                    ->where('business_plan_id', $lockedPlan->getKey())
                    ->latest('round')
                    ->firstOrFail();
            }

            $latestAssessmentRound = (int) PlanAssessment::query()
                ->where('business_plan_id', $lockedPlan->getKey())
                ->orderByDesc('round')
                ->lockForUpdate()
                ->value('round');
            $latestRevisionRound = (int) PlanRevision::query()
                ->where('business_plan_id', $lockedPlan->getKey())
                ->orderByDesc('round')
                ->lockForUpdate()
                ->value('round');
            $round = max($latestAssessmentRound, $latestRevisionRound) + 1;
            $previous = PlanAssessment::query()
                ->with('ratingFramework.criteria')
                ->where('business_plan_id', $lockedPlan->getKey())
                ->latest('round')
                ->first();

            $lockedPlan->forceFill([
                'status' => BusinessPlan::STATUS_SUBMITTED,
                'assessment_run_status' => null,
                'assessment_run_requested_at' => null,
                'assessment_run_started_at' => null,
                'assessment_run_total_criteria' => null,
                'assessment_run_completed_criteria' => null,
                'assessment_run_current_criterion' => null,
                'assessment_run_completed_at' => null,
                'assessment_run_failed_at' => null,
                'assessment_run_failure' => null,
                'assessment_run_requested_by_user_id' => null,
            ])->save();

            $revision = PlanRevision::query()->create([
                'business_plan_id' => $lockedPlan->getKey(),
                'round' => $round,
                'submitted_at' => now(),
                'progress_comparison' => $this->awaitingAssessmentComparison($round, $previous),
                'submitted_by_user_id' => $actor->getKey(),
            ]);

            $this->audit->record('entrepreneur.plan_revision_submitted', subject: $revision, actor: $actor, after: [
                'business_plan_id' => $lockedPlan->getKey(),
                'round' => $round,
                'assessment_status' => 'awaiting_advisor_action',
            ]);

            return $revision->refresh();
        });
    }

    /**
     * Records the comparison only after an advisor explicitly starts the
     * assessment and the worker has produced a fresh assessment round.
     */
    public function recordAssessment(PlanAssessment $assessment, User $actor): ?PlanRevision
    {
        return DB::transaction(function () use ($assessment, $actor): ?PlanRevision {
            $revision = PlanRevision::query()
                ->where('business_plan_id', $assessment->business_plan_id)
                ->where('round', $assessment->round)
                ->lockForUpdate()
                ->first();

            if (! $revision instanceof PlanRevision) {
                return null;
            }

            if (data_get($revision->progress_comparison, 'assessment_status') === 'completed') {
                return $revision;
            }

            $previous = PlanAssessment::query()
                ->with('ratingFramework.criteria')
                ->where('business_plan_id', $assessment->business_plan_id)
                ->where('round', '<', $assessment->round)
                ->latest('round')
                ->first();
            $comparison = $previous instanceof PlanAssessment
                ? $this->compare($previous, $assessment)
                : $this->baselineComparison($assessment);

            $revision->forceFill([
                'progress_comparison' => [
                    ...$comparison,
                    'assessment_status' => 'completed',
                    'assessed_at' => now()->toIso8601String(),
                ],
            ])->save();

            $this->audit->record('entrepreneur.plan_revision_assessed', subject: $revision, actor: $actor, after: [
                'business_plan_id' => $assessment->business_plan_id,
                'round' => $assessment->round,
                'trajectory_percent' => $comparison['trajectory_percent'],
                'overall_delta' => $comparison['overall_delta'],
            ]);

            return $revision->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function compare(PlanAssessment $previous, PlanAssessment $current): array
    {
        $previous->loadMissing('ratingFramework.criteria');
        $current->loadMissing('ratingFramework.criteria');

        if ((string) $previous->business_plan_id !== (string) $current->business_plan_id) {
            throw new InvalidArgumentException('Assessment rounds must belong to the same business plan.');
        }

        $framework = $current->ratingFramework;
        $previousScores = $this->scoreRows($previous)->keyBy('criterion_number');
        $currentScores = $this->scoreRows($current)->keyBy('criterion_number');
        $criterionDeltas = $currentScores
            ->map(function (array $currentRow) use ($previousScores): array {
                $number = (int) $currentRow['criterion_number'];
                $previousRow = $previousScores->get($number, [
                    'score' => 0,
                    'criterion_name' => $currentRow['criterion_name'],
                ]);
                $delta = (int) $currentRow['score'] - (int) $previousRow['score'];

                return [
                    'criterion_number' => $number,
                    'criterion_name' => $currentRow['criterion_name'],
                    'previous_score' => (int) $previousRow['score'],
                    'current_score' => (int) $currentRow['score'],
                    'delta' => $delta,
                    'direction' => $delta > 0 ? 'improved' : ($delta < 0 ? 'regressed' : 'unchanged'),
                ];
            })
            ->values();
        $previousOverall = AssessmentScoring::weightedScoreForFramework($framework, $previous->ai_scores ?? [], $previous->advisor_scores ?? []);
        $currentOverall = AssessmentScoring::weightedScoreForFramework($framework, $current->ai_scores ?? [], $current->advisor_scores ?? []);
        $overallDelta = round($currentOverall - $previousOverall, 2);

        return [
            'previous_round' => $previous->round,
            'current_round' => $current->round,
            'previous_overall_score' => $previousOverall,
            'current_overall_score' => $currentOverall,
            'overall_delta' => $overallDelta,
            'previous_grade' => $framework->gradeFor($previousOverall),
            'current_grade' => $framework->gradeFor($currentOverall),
            'trajectory_percent' => $this->trajectoryPercent($previousOverall, $currentOverall),
            'trajectory_label' => $overallDelta > 0 ? 'improving' : ($overallDelta < 0 ? 'regressing' : 'flat'),
            'criterion_deltas' => $criterionDeltas->all(),
            'biggest_improvements' => $criterionDeltas
                ->filter(fn (array $row): bool => $row['delta'] > 0)
                ->sortByDesc('delta')
                ->take(3)
                ->values()
                ->all(),
            'remaining_gaps' => $criterionDeltas
                ->filter(fn (array $row): bool => $row['current_score'] < 60)
                ->sortBy('current_score')
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, PlanRevision>
     */
    public function progressFor(BusinessPlan $plan): Collection
    {
        return PlanRevision::query()
            ->where('business_plan_id', $plan->getKey())
            ->orderBy('round')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function baselineComparison(PlanAssessment $assessment): array
    {
        $assessment->loadMissing('ratingFramework.criteria');
        $scores = $this->scoreRows($assessment);
        $overall = AssessmentScoring::weightedScore($assessment);

        return [
            'previous_round' => null,
            'current_round' => $assessment->round,
            'previous_overall_score' => null,
            'current_overall_score' => $overall,
            'overall_delta' => 0.0,
            'previous_grade' => null,
            'current_grade' => $assessment->ratingFramework->gradeFor($overall),
            'trajectory_percent' => 0.0,
            'trajectory_label' => 'baseline',
            'criterion_deltas' => $scores
                ->map(fn (array $row): array => [
                    'criterion_number' => $row['criterion_number'],
                    'criterion_name' => $row['criterion_name'],
                    'previous_score' => null,
                    'current_score' => $row['score'],
                    'delta' => 0,
                    'direction' => 'baseline',
                ])
                ->values()
                ->all(),
            'biggest_improvements' => [],
            'remaining_gaps' => $scores
                ->filter(fn (array $row): bool => $row['score'] < 60)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function awaitingAssessmentComparison(int $round, ?PlanAssessment $previous): array
    {
        return [
            'assessment_status' => 'awaiting_advisor_action',
            'previous_round' => $previous?->round,
            'current_round' => $round,
            'previous_overall_score' => null,
            'current_overall_score' => null,
            'overall_delta' => null,
            'previous_grade' => null,
            'current_grade' => null,
            'trajectory_percent' => null,
            'trajectory_label' => 'awaiting_assessment',
            'criterion_deltas' => [],
            'biggest_improvements' => [],
            'remaining_gaps' => [],
        ];
    }

    /**
     * @return Collection<int, array{criterion_number:int,criterion_name:string,score:int,weight:float}>
     */
    private function scoreRows(PlanAssessment $assessment): Collection
    {
        return collect(AssessmentScoring::criteriaPayload($assessment))
            ->map(fn (array $row): array => [
                'criterion_number' => (int) $row['criterion_number'],
                'criterion_name' => (string) $row['criterion_name'],
                'score' => max(0, min(100, (int) round((float) $row['score']))),
                'weight' => (float) $row['weight'],
            ]);
    }

    private function trajectoryPercent(float $previousOverall, float $currentOverall): float
    {
        $remainingOpportunity = max(1.0, 100.0 - $previousOverall);

        return round((($currentOverall - $previousOverall) / $remainingOpportunity) * 100, 2);
    }
}
