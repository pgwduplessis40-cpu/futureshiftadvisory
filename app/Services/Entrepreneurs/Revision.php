<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\PlanAssessment;
use App\Models\PlanRevision;
use App\Models\RatingFramework;
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
        private readonly Assessment $assessments,
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
        $previous = PlanAssessment::query()
            ->with('ratingFramework.criteria')
            ->where('business_plan_id', $plan->getKey())
            ->latest('round')
            ->first();

        return DB::transaction(function () use ($plan, $actor, $previous): PlanRevision {
            $assessment = $this->assessments->firstPass($plan->refresh()->load('sections'), $actor);
            $comparison = $previous instanceof PlanAssessment
                ? $this->compare($previous, $assessment)
                : $this->baselineComparison($assessment);

            $revision = PlanRevision::query()->create([
                'business_plan_id' => $plan->getKey(),
                'round' => $assessment->round,
                'submitted_at' => now(),
                'progress_comparison' => $comparison,
                'submitted_by_user_id' => $actor->getKey(),
            ]);

            $this->audit->record('entrepreneur.plan_revision_submitted', subject: $revision, actor: $actor, after: [
                'business_plan_id' => $plan->getKey(),
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
        $previousOverall = $this->weightedScore($framework, $previousScores->values());
        $currentOverall = $this->weightedScore($framework, $currentScores->values());
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
        $overall = $this->weightedScore($assessment->ratingFramework, $scores);

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
     * @return Collection<int, array{criterion_number:int,criterion_name:string,score:int,weight:float}>
     */
    private function scoreRows(PlanAssessment $assessment): Collection
    {
        $assessment->loadMissing('ratingFramework.criteria');
        $aiScores = collect($assessment->ai_scores ?? [])->keyBy(fn (array $score): int => (int) ($score['criterion_number'] ?? 0));
        $advisorScores = collect($assessment->advisor_scores ?? [])->keyBy(fn (array $score): int => (int) ($score['criterion_number'] ?? 0));

        return $assessment->ratingFramework->criteria
            ->map(function ($criterion) use ($aiScores, $advisorScores): array {
                $advisor = $advisorScores->get($criterion->number);
                $ai = $aiScores->get($criterion->number, []);
                $score = is_array($advisor) && is_numeric($advisor['score'] ?? null)
                    ? (int) $advisor['score']
                    : (int) ($ai['score'] ?? 0);

                return [
                    'criterion_number' => (int) $criterion->number,
                    'criterion_name' => (string) $criterion->name,
                    'score' => max(0, min(100, $score)),
                    'weight' => (float) $criterion->weight,
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $scores
     */
    private function weightedScore(RatingFramework $framework, Collection $scores): float
    {
        $weights = $framework->criteria->pluck('weight', 'number');
        $totalWeight = (float) $framework->criteria->sum('weight');

        if ($totalWeight <= 0) {
            return 0.0;
        }

        return round($scores->sum(function (array $row) use ($weights, $totalWeight): float {
            $weight = (float) $weights->get((int) $row['criterion_number'], (float) ($row['weight'] ?? 0));

            return ((float) $row['score']) * ($weight / $totalWeight);
        }), 2);
    }

    private function trajectoryPercent(float $previousOverall, float $currentOverall): float
    {
        $remainingOpportunity = max(1.0, 100.0 - $previousOverall);

        return round((($currentOverall - $previousOverall) / $remainingOpportunity) * 100, 2);
    }
}
