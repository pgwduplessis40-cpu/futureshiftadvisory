<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\User;
use App\Services\Plans\PlanBuilder as SharedPlanBuilder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Starts an entrepreneur plan from one advisor-approved Idea Validation.
 *
 * The validated concept is a dated source snapshot, not a live mirror: a
 * later idea revision must never overwrite a founder's plan edits.
 */
final class ApprovedIdeaPlanStarter
{
    public function __construct(private readonly SharedPlanBuilder $plans) {}

    public function start(EntrepreneurProfile $profile, IdeaValidation $validation, User $actor): BusinessPlan
    {
        if ((string) $validation->entrepreneur_profile_id !== (string) $profile->getKey()
            || $validation->advisor_gate_passed_at === null) {
            throw new InvalidArgumentException('An advisor-approved Idea Validation is required before starting the business plan.');
        }

        return DB::transaction(function () use ($profile, $validation, $actor): BusinessPlan {
            $plan = $this->plans->createOrUpdateForEntrepreneur($profile, [
                'title' => 'Business plan: '.$profile->name,
                'status' => BusinessPlan::STATUS_BUILDING,
                'current_phase' => 1,
            ], $actor);
            $plan->loadMissing('phases.sections');

            $alreadySeeded = $plan->phases
                ->flatMap(fn ($phase) => $phase->sections)
                ->contains(fn ($section): bool => $section->key === 'idea-validation-summary');

            if (! $alreadySeeded) {
                $this->plans->upsertSection(
                    plan: $plan,
                    phaseKey: 'foundation',
                    key: 'idea-validation-summary',
                    title: 'Validated concept foundation',
                    body: sprintf(
                        "Problem: %s\nTarget customer: %s\nSolution: %s\nValue proposition: %s\nDemand evidence: %s\nRevenue model: %s",
                        $validation->problem,
                        $validation->target_customer,
                        $validation->solution,
                        $validation->value_proposition,
                        $validation->demand_signal,
                        $validation->revenue_model,
                    ),
                    sourceType: BusinessPlan::SOURCE_ENTREPRENEUR,
                    metadata: [
                        'idea_validation_id' => $validation->getKey(),
                        'idea_validation_revision_number' => $validation->revision_number,
                        'advisor_gate_passed_at' => $validation->advisor_gate_passed_at->toIso8601String(),
                        'advisor_gate_note' => $validation->advisor_gate_note,
                        'viability_alerts' => $validation->viability_alerts ?? [],
                        'seeded_as_immutable_source' => true,
                    ],
                );
            }

            return $plan->refresh()->load('phases.sections');
        });
    }
}
