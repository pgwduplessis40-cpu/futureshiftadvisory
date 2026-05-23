<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\PlanAssessment;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LivingPlan
{
    public function __construct(
        private readonly Assessment $assessments,
        private readonly Revision $revisions,
        private readonly AdvisoryReadiness $readiness,
        private readonly AuditWriter $audit,
    ) {}

    public function schedule(BusinessPlan $plan, ?CarbonInterface $from = null): BusinessPlan
    {
        $from ??= $plan->completed_at ?? now();

        $plan->forceFill([
            'status' => BusinessPlan::STATUS_LAUNCHED,
            'living_plan_next_update_at' => $from->copy()->addMonthsNoOverflow(3),
        ])->save();

        return $plan->refresh();
    }

    /**
     * @return Collection<int, BusinessPlan>
     */
    public function duePlans(?CarbonInterface $asAt = null): Collection
    {
        $asAt ??= now();

        return BusinessPlan::query()
            ->with('entrepreneurProfile')
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->where('status', BusinessPlan::STATUS_LAUNCHED)
            ->whereNotNull('living_plan_next_update_at')
            ->where('living_plan_next_update_at', '<=', $asAt)
            ->orderBy('living_plan_next_update_at')
            ->get();
    }

    public function prompt(BusinessPlan $plan, User $actor): BusinessPlan
    {
        $plan->forceFill([
            'living_plan_last_prompted_at' => now(),
        ])->save();

        $this->audit->record('entrepreneur.living_plan_prompted', subject: $plan, actor: $actor, after: [
            'business_plan_id' => $plan->getKey(),
            'next_update_at' => $plan->living_plan_next_update_at?->toIso8601String(),
        ]);

        return $plan->refresh();
    }

    public function reassess(BusinessPlan $plan, User $actor): PlanAssessment
    {
        $previous = PlanAssessment::query()
            ->with('ratingFramework.criteria')
            ->where('business_plan_id', $plan->getKey())
            ->latest('round')
            ->first();

        return DB::transaction(function () use ($plan, $actor, $previous): PlanAssessment {
            $assessment = $this->assessments->firstPass($plan->refresh()->load('sections'), $actor);
            $comparison = $previous instanceof PlanAssessment
                ? $this->revisions->compare($previous, $assessment)
                : null;
            $flags = $this->divergenceFlags($comparison);

            $plan->forceFill([
                'status' => BusinessPlan::STATUS_LAUNCHED,
                'living_plan_last_assessed_at' => now(),
                'living_plan_next_update_at' => now()->addMonthsNoOverflow(3),
                'living_plan_divergence_flags' => $flags,
            ])->save();

            $this->readiness->evaluate($plan->refresh()->load('assessments.ratingFramework.criteria'), $actor);

            $this->audit->record('entrepreneur.living_plan_reassessed', subject: $assessment, actor: $actor, after: [
                'business_plan_id' => $plan->getKey(),
                'round' => $assessment->round,
                'divergence_flags' => $flags,
            ]);

            return $assessment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>|null  $comparison
     * @return array<string, mixed>
     */
    private function divergenceFlags(?array $comparison): array
    {
        if ($comparison === null) {
            return [
                'diverged' => false,
                'reason' => 'baseline',
            ];
        }

        $overallDelta = (float) ($comparison['overall_delta'] ?? 0);
        $remainingGaps = (array) ($comparison['remaining_gaps'] ?? []);

        return [
            'diverged' => $overallDelta <= -10.0 || $remainingGaps !== [],
            'overall_delta' => $overallDelta,
            'remaining_gap_count' => count($remainingGaps),
            'advisory_readiness_attention' => $overallDelta <= -10.0 || $remainingGaps !== [],
        ];
    }
}
