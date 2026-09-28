<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\User;

/**
 * @phpstan-type ProfileSummary array{id:string, name:string, email:string, stage:string, stage_label:string, invite_status:string|null, invite_status_label:string|null, assigned_advisor_name:string|null}
 */
final class AdvisorEntrepreneurProfileSummary
{
    /** @return ProfileSummary */
    public function for(EntrepreneurProfile $profile): array
    {
        $stage = $profile->currentStage();
        $inviteStatus = $profile->invitationStatus();

        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'email' => $profile->email,
            'stage' => $stage->value,
            'stage_label' => $this->stageLabel($profile, $stage),
            'invite_status' => $inviteStatus?->value,
            'invite_status_label' => $inviteStatus?->label(),
            'assigned_advisor_name' => $profile->assignedAdvisor?->name,
        ];
    }

    private function stageLabel(EntrepreneurProfile $profile, EntrepreneurStage $stage): string
    {
        $latestPlan = $profile->relationLoaded('businessPlans')
            ? $profile->businessPlans->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)->sortByDesc('updated_at')->first()
            : null;
        if (! in_array($stage, [EntrepreneurStage::CANCELLED, EntrepreneurStage::SUSPENDED], true) && $latestPlan instanceof BusinessPlan && $latestPlan->status === BusinessPlan::STATUS_REVISING) {
            return 'Revision requested - awaiting resubmission';
        }
        if ($stage === EntrepreneurStage::INVITED && $profile->inviteToken?->isAccepted()) {
            return 'Invite accepted';
        }
        if (in_array($stage, [EntrepreneurStage::INVITED, EntrepreneurStage::ONBOARDING], true) && ($profile->user_id !== null || $profile->user instanceof User || $profile->inviteToken?->isAccepted())) {
            return 'Active';
        }

        return $stage->label();
    }
}
