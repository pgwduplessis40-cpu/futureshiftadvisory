<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\PlanSection;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Plans\PlanBuilder as SharedPlanBuilder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PlanBuilder
{
    public function __construct(
        private readonly SharedPlanBuilder $plans,
        private readonly ApprovedIdeaPlanStarter $approvedIdeaPlans,
        private readonly IdeaValidationService $ideaValidations,
        private readonly AuditWriter $audit,
        private readonly EntrepreneurMilestones $milestones,
        private readonly EntrepreneurStreak $streak,
    ) {}

    public function start(EntrepreneurProfile $profile, User $actor): BusinessPlan
    {
        if (! $this->ideaValidations->planBuilderUnlocked($profile)) {
            throw new InvalidArgumentException('The entrepreneur plan builder is locked until an advisor passes the idea-validation gate.');
        }

        return DB::transaction(function () use ($profile, $actor): BusinessPlan {
            $latestValidation = IdeaValidation::query()
                ->where('entrepreneur_profile_id', $profile->getKey())
                ->whereNotNull('advisor_gate_passed_at')
                ->latest('advisor_gate_passed_at')
                ->first();
            if (! $latestValidation instanceof IdeaValidation) {
                throw new InvalidArgumentException('The entrepreneur plan builder is locked until an advisor passes the idea-validation gate.');
            }

            $plan = $this->approvedIdeaPlans->start($profile, $latestValidation, $actor);

            $this->audit->record('entrepreneur.plan_started', subject: $plan, actor: $actor, after: [
                'entrepreneur_profile_id' => $profile->getKey(),
                'phase_count' => $plan->phases()->count(),
            ]);

            return $plan->refresh()->load('phases.sections');
        });
    }

    public function upsertSection(
        BusinessPlan $plan,
        string $phaseKey,
        string $key,
        string $title,
        string $body,
        ?User $actor,
        array $metadata = [],
        array $attachedDocumentIds = [],
        ?string $completenessStatus = null,
    ): PlanSection {
        return DB::transaction(function () use ($plan, $phaseKey, $key, $title, $body, $actor, $metadata, $attachedDocumentIds, $completenessStatus): PlanSection {
            $warning = $this->plans->dependencyWarning($plan, $phaseKey);
            $section = $this->plans->upsertSection(
                plan: $plan,
                phaseKey: $phaseKey,
                key: $key,
                title: $title,
                body: $body,
                sourceType: BusinessPlan::SOURCE_ENTREPRENEUR,
                metadata: [
                    'dependency_warning' => $warning,
                    ...$metadata,
                    'updated_by_user_id' => $actor?->getKey(),
                    'updated_by' => $actor instanceof User ? 'user' : 'system',
                ],
                attachedDocumentIds: $attachedDocumentIds,
                completenessStatus: $completenessStatus,
            );

            $phasePosition = (int) $section->phase()->value('position');
            if ($phasePosition > (int) $plan->current_phase) {
                $plan->forceFill(['current_phase' => $phasePosition])->save();
            }

            $this->audit->record('entrepreneur.plan_section_saved', subject: $section, actor: $actor, after: [
                'business_plan_id' => $plan->getKey(),
                'phase' => $phaseKey,
                'dependency_warning' => $warning,
            ]);
            $section = $section->refresh()->load('businessPlan.entrepreneurProfile');
            $this->streak->recordSectionSaved($section);
            $this->milestones->awardCompletedPhases($plan->refresh()->load('entrepreneurProfile', 'phases', 'sections'));

            return $section->refresh();
        });
    }

    /**
     * @return array{blocked:bool, missing_dependencies:array<int, string>}
     */
    public function dependencyWarning(BusinessPlan $plan, string $phaseKey): array
    {
        return $this->plans->dependencyWarning($plan, $phaseKey);
    }
}
