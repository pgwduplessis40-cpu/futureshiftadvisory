<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurMilestoneAward;
use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class EntrepreneurGamification
{
    /**
     * @return array<string, mixed>
     */
    public function payload(EntrepreneurProfile $profile, ?BusinessPlan $plan = null): array
    {
        if (! $profile->gamification_on) {
            return [
                'enabled' => false,
            ];
        }

        $plan ??= BusinessPlan::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->with('assessments.ratingFramework.criteria', 'sections')
            ->latest('updated_at')
            ->latest()
            ->first();

        $awards = EntrepreneurMilestoneAward::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->orderBy('earned_at')
            ->get();
        $completion = $plan instanceof BusinessPlan
            ? PlanRequirements::completion($plan)
            : ['total' => 0, 'completed' => 0, 'percent' => 0];

        return [
            'enabled' => true,
            'current_level' => $this->currentLevel($profile, $plan),
            'plan_completion' => $completion,
            'current_streak' => $profile->current_streak,
            'last_active_at' => $profile->last_active_at?->toIso8601String(),
            'badges' => $awards
                ->map(fn (EntrepreneurMilestoneAward $award): array => $this->awardPayload($award))
                ->values()
                ->all(),
            'new_badge_count' => $awards->whereNull('seen_at')->count(),
            'next_milestone' => $this->nextMilestone($awards, $completion),
            'grade_trajectory' => $plan instanceof BusinessPlan ? $this->gradeTrajectory($plan) : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function awardPayload(EntrepreneurMilestoneAward $award): array
    {
        return [
            'id' => $award->id,
            'key' => $award->milestone_key,
            'label' => EntrepreneurMilestones::labels()[$award->milestone_key] ?? $this->label($award->milestone_key),
            'earned_at' => $award->earned_at?->toIso8601String(),
            'earned_at_estimated' => (bool) data_get($award->evidence_snapshot, 'earned_at_estimated', false),
            'seen_at' => $award->seen_at?->toIso8601String(),
            'evidence_source_type' => $award->evidence_source_type,
            'evidence_source_id' => $award->evidence_source_id,
            'evidence_snapshot' => $award->evidence_snapshot ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentLevel(EntrepreneurProfile $profile, ?BusinessPlan $plan): array
    {
        $stage = $profile->stage;
        $stageValue = $stage instanceof \BackedEnum ? (string) $stage->value : (string) $stage;
        $stageLabel = is_object($stage) && method_exists($stage, 'label') ? $stage->label() : $this->label($stageValue);

        return [
            'stage' => $stageValue,
            'stage_label' => $stageLabel,
            'phase' => $plan?->current_phase,
            'label' => $plan instanceof BusinessPlan
                ? $stageLabel.' · Phase '.$plan->current_phase
                : $stageLabel,
        ];
    }

    /**
     * @param  array{total:int, completed:int, percent:int}  $completion
     * @return array<string, mixed>|null
     */
    private function nextMilestone(Collection $awards, array $completion): ?array
    {
        $earned = $awards->pluck('milestone_key')->all();

        foreach (EntrepreneurMilestones::labels() as $key => $label) {
            if (! in_array($key, $earned, true)) {
                return [
                    'key' => $key,
                    'label' => $label,
                    'progress_percent' => $completion['percent'],
                ];
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function gradeTrajectory(BusinessPlan $plan): array
    {
        $plan->loadMissing('assessments.ratingFramework.criteria');

        return $plan->assessments
            ->sortBy('round')
            ->filter(fn (PlanAssessment $assessment): bool => $assessment->finalised_at instanceof CarbonInterface)
            ->map(fn (PlanAssessment $assessment): array => [
                'round' => $assessment->round,
                'grade' => $assessment->overall_grade,
                'finalised_at' => $assessment->finalised_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function label(string $value): string
    {
        return str((string) $value)->replace('_', ' ')->title()->toString();
    }
}
