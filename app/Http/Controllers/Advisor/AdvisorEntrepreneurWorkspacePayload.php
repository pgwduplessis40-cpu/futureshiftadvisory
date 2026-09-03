<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\EntrepreneurStage;
use App\Enums\SurveyAssignmentStatus;
use App\Enums\SurveyType;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\InviteToken;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\SurveyAssignment;
use App\Models\User;
use App\Services\Entrepreneurs\EntrepreneurGamification;
use App\Services\ScreenShare\ScreenShareAuthorizer;
use App\Services\Surveys\SurveyActivationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @phpstan-type ProfileSummary array{id:string, name:string, email:string, stage:string, stage_label:string, assigned_advisor_name:string|null}
 * @phpstan-type ServiceOption array{value:string, label:string, description:string}
 * @phpstan-type CollaborationParticipant array{id:string, name:string}
 * @phpstan-type ScreenSharePayload array{connection_url:string, connection_heartbeat_url:string, request_url:string, ice_servers_url:string, active_url:string, signal_url:string, pending_signals_url:string, heartbeat_url:string, end_url:string, heartbeat_seconds:int, reconnect_grace_seconds:int, participants:list<CollaborationParticipant>}
 * @phpstan-type CoBrowsePayload array{connection_url:string, connection_heartbeat_url:string, request_url:string, status_url:string, heartbeat_url:string, end_url:string, action_url:string, heartbeat_seconds:int, participants:list<CollaborationParticipant>}
 * @phpstan-type PlanProgressSummary array{id:string, title:string, status:string, assessment_count:int, latest_round:int|null, latest_grade:string|null, can_assess:bool, assessment_action_label:string, assessment_run:array{status:string|null, requested_at:string|null, started_at:string|null, total_criteria:int|null, completed_criteria:int|null, current_criterion:string|null, completed_at:string|null, failed_at:string|null, failure:string|null}, latest_assessment:mixed, executive_summary:mixed, budget:mixed, preview_pdf_url:string, budget_pdf_url:string|null, funder_ready:mixed, assess_url:string, assessment_history:list<mixed>, latest_revision:mixed}
 * @phpstan-type ReadinessSummary array{completed:bool, score:float|int|null, outcome:string|null, assessed_at:string|null}
 * @phpstan-type IdeaValidationSummary array{id:string, revision_number:int, summary:string, problem:string|null, target_customer:string|null, solution:string|null, value_proposition:string|null, demand_signal:string|null, revenue_model:string|null, viability_alerts:list<mixed>, viability_gate:mixed, proposed_change_request:string, uncertainty:mixed, past_plan_pattern:list<mixed>, evaluated_at:string|null, ai_deferred:bool, advisor_gate_status:string, change_request_note:mixed, changes_requested_at:mixed, recalled_at:string|null, restored_from_revision_number:mixed, refresh_status:mixed, refresh_stale:bool, refresh_requested_at:mixed, refresh_started_at:mixed, refresh_completed_at:mixed, refresh_failed_at:mixed, refresh_failure:mixed, advisor_gate_passed_at:string|null, advisor_gate_note:string|null, gate_url:string, request_changes_url:string, refresh_url:string}
 * @phpstan-type AdvisoryReadinessSummary array{id:string, score:float|int|null, surfaced_at:string|null}
 * @phpstan-type ReportSummary array{id:string, title:string, generated_at:string|null, view_url:string, download_url:string}
 * @phpstan-type ConversionSummary array{available:bool, converted:bool, client_id:string|null, convert_url:string}
 * @phpstan-type DocumentSummary array{id:string, original_filename:string, category:string, scanner_result:string|null, scanner_message:mixed, uploaded_at:string|null, uploaded_by_name:string|null, url:string|null}
 * @phpstan-type MessageSummary array{threads_count:int, unread_count:int, latest_activity_at:string|null, url:string}
 */
final class AdvisorEntrepreneurWorkspacePayload
{
    public function __construct(
        private readonly EntrepreneurGamification $gamification,
        private readonly AdvisorEntrepreneurPlanPayload $planPayload,
        private readonly AdvisorEntrepreneurIdeaPayload $ideaPayload,
        private readonly AdvisorEntrepreneurSupportPayload $supportPayload,
        private readonly ScreenShareAuthorizer $screenShareAuthorizer,
        private readonly SurveyActivationService $surveyActivations,
    ) {}

    /** @return list<ProfileSummary> */
    public function indexProfiles(User $viewer): array
    {
        return $this->visibleProfiles($viewer)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (EntrepreneurProfile $profile): array => $this->profileSummary($profile))
            ->values()
            ->all();
    }

    /** @return array{entrepreneur:array{id:string, name:string, email:string, stage:string, stage_label:string, assigned_advisor_name:string|null, concept_summary:string|null, user_id:int|null, invite_accepted_at:string|null, invite_expires_at:string|null, invite_delivery_label:string, invite_update_url:string|null, invite_resend_url:string|null, invite_cancel_url:string|null, intended_package_scope:string, intended_package_scope_label:string, created_at:string|null, latest_plan:PlanProgressSummary|null, readiness:ReadinessSummary, feedback_survey:array{action_url:string}, service_feedback_survey:mixed, idea_validation:IdeaValidationSummary|null, advisory_readiness:AdvisoryReadinessSummary|null, reports:list<ReportSummary>, conversion:ConversionSummary, documents:list<DocumentSummary>, messages:MessageSummary, client_actions:mixed, gamification:mixed}, serviceOptions:array<int, ServiceOption>, screenShare:ScreenSharePayload|null, coBrowse:CoBrowsePayload|null} */
    public function show(User $viewer, EntrepreneurProfile $profile): array
    {
        $profile->loadMissing([
            'assignedAdvisor', 'inviteToken', 'user', 'businessPlans.assessments.ratingFramework.criteria',
            'businessPlans.budgetRunway', 'businessPlans.revisions',
        ]);
        $latestPlan = $profile->businessPlans
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->sortByDesc('updated_at')
            ->first();
        $serviceOptions = ServiceRatePackage::entrepreneurInviteServiceOptions();
        $intendedPackageScope = $this->intendedEntrepreneurScope($profile);
        $intendedPackageOption = collect($serviceOptions)->firstWhere('value', $intendedPackageScope);
        $intendedPackageLabel = is_array($intendedPackageOption)
            ? (string) $intendedPackageOption['label']
            : ServiceRatePackage::packageScopeLabel($intendedPackageScope);
        $activeInvite = $profile->inviteToken instanceof InviteToken && $profile->inviteToken->isUsable();
        return [
            'entrepreneur' => [
                ...$this->profileSummary($profile),
                'concept_summary' => $profile->concept_summary,
                'user_id' => $profile->user_id,
                'invite_accepted_at' => $profile->inviteToken?->accepted_at?->toIso8601String(),
                'invite_expires_at' => $profile->inviteToken?->expires_at?->toIso8601String(),
                'invite_delivery_label' => $profile->user_id ? 'Account onboarded' : ($activeInvite ? 'Email sent' : 'No active invite'),
                'invite_update_url' => $this->canUpdateInviteDetails($profile) ? route('advisor.entrepreneurs.invite.update', $profile, absolute: false) : null,
                'invite_resend_url' => $this->canResendInvite($profile) ? route('advisor.entrepreneurs.invite.resend', $profile, absolute: false) : null,
                'invite_cancel_url' => $this->canCancelInvite($profile) ? route('advisor.entrepreneurs.invite.cancel', $profile, absolute: false) : null,
                'intended_package_scope' => $intendedPackageScope,
                'intended_package_scope_label' => $intendedPackageLabel,
                'created_at' => $profile->created_at?->toIso8601String(),
                'latest_plan' => $latestPlan instanceof BusinessPlan ? $this->planPayload->summary($latestPlan, $profile) : null,
                'readiness' => $this->supportPayload->readiness($profile),
                'feedback_survey' => ['action_url' => route('advisor.entrepreneurs.survey-assignments.store', $profile, absolute: false)],
                'service_feedback_survey' => $this->serviceFeedbackSurvey($viewer, $profile),
                'idea_validation' => $this->ideaPayload->summary($profile),
                'advisory_readiness' => $this->supportPayload->advisoryReadiness($profile),
                'reports' => $this->supportPayload->reports($profile),
                'conversion' => $this->supportPayload->conversion($profile, $latestPlan instanceof BusinessPlan ? $latestPlan : null),
                'documents' => $this->supportPayload->documents($profile),
                'messages' => $this->supportPayload->messages($profile, $viewer),
                'client_actions' => $profile->client_id !== null ? [
                    'email_url' => route('advisor.clients.compose', $profile->client_id, absolute: false),
                    'offboard_url' => route('advisor.clients.offboarding.create', $profile->client_id, absolute: false),
                ] : null,
                'gamification' => [
                    ...$this->gamification->payload($profile, $latestPlan instanceof BusinessPlan ? $latestPlan : null),
                    'enabled' => (bool) $profile->gamification_on,
                    'toggle_url' => route('advisor.entrepreneurs.gamification.update', $profile, absolute: false),
                ],
            ],
            'serviceOptions' => $serviceOptions,
            'screenShare' => $this->screenSharePayload($viewer, $profile),
            'coBrowse' => $this->coBrowsePayload($viewer, $profile),
        ];
    }

    /** @return ScreenSharePayload|null */
    private function screenSharePayload(User $viewer, EntrepreneurProfile $profile): ?array
    {
        if (! ($profile->user instanceof User) || ! $this->screenShareAuthorizer->canRequestForEntrepreneur($viewer, $profile, $profile->user)) {
            return null;
        }

        return [
            'connection_url' => route('advisor.entrepreneurs.screen-share.connections.store', $profile, absolute: false),
            'connection_heartbeat_url' => route('screen-share.connections.heartbeat', ['connection' => '__connection__'], absolute: false),
            'request_url' => route('advisor.entrepreneurs.screen-share.sessions.store', $profile, absolute: false),
            'ice_servers_url' => route('screen-share.sessions.ice-servers', ['session' => '__session__'], absolute: false),
            'active_url' => route('screen-share.sessions.active', ['session' => '__session__'], absolute: false),
            'signal_url' => route('screen-share.sessions.signal', ['session' => '__session__'], absolute: false),
            'pending_signals_url' => route('screen-share.sessions.pending-signals', ['session' => '__session__'], absolute: false),
            'heartbeat_url' => route('screen-share.sessions.heartbeat', ['session' => '__session__'], absolute: false),
            'end_url' => route('screen-share.sessions.end', ['session' => '__session__'], absolute: false),
            'heartbeat_seconds' => max(5, (int) config('screen-share.heartbeat_interval_seconds', 10)),
            'reconnect_grace_seconds' => max(15, (int) config('screen-share.reconnect_grace_seconds', 60)),
            'participants' => [['id' => (string) $profile->user->getKey(), 'name' => $profile->user->name]],
        ];
    }

    /** @return CoBrowsePayload|null */
    private function coBrowsePayload(User $viewer, EntrepreneurProfile $profile): ?array
    {
        if (! (bool) config('co-browse.enabled') || ! ($profile->user instanceof User) || ! $this->screenShareAuthorizer->canRequestForEntrepreneur($viewer, $profile, $profile->user)) {
            return null;
        }

        return [
            'connection_url' => route('advisor.entrepreneurs.co-browse.connections.store', $profile, absolute: false),
            'connection_heartbeat_url' => route('co-browse.connections.heartbeat', ['connection' => '__connection__'], absolute: false),
            'request_url' => route('advisor.entrepreneurs.co-browse.sessions.store', $profile, absolute: false),
            'status_url' => route('co-browse.sessions.status', ['session' => '__session__'], absolute: false),
            'heartbeat_url' => route('co-browse.sessions.heartbeat', ['session' => '__session__'], absolute: false),
            'end_url' => route('co-browse.sessions.end', ['session' => '__session__'], absolute: false),
            'action_url' => route('co-browse.sessions.actions.store', ['session' => '__session__'], absolute: false),
            'heartbeat_seconds' => max(5, (int) config('co-browse.heartbeat_interval_seconds', 10)),
            'participants' => [['id' => (string) $profile->user->getKey(), 'name' => $profile->user->name]],
        ];
    }

    /** @return array{action_url:string|null,service_label:string|null,unavailable_reason:string|null,has_open_survey:bool}|null */
    private function serviceFeedbackSurvey(User $viewer, EntrepreneurProfile $profile): ?array
    {
        if (! $viewer->hasRole(User::TYPE_SUPER_ADMIN)) {
            return null;
        }

        $serviceSnapshot = $this->surveyActivations->completedEntrepreneurServiceSnapshot($profile);
        if ($serviceSnapshot === null) {
            return [
                'action_url' => null,
                'service_label' => null,
                'unavailable_reason' => 'Service feedback is available after an Idea Validation gate is approved or once the entrepreneur is advisory ready or launched.',
                'has_open_survey' => false,
            ];
        }

        $serviceLabel = is_string($serviceSnapshot['service_label'] ?? null) ? $serviceSnapshot['service_label'] : 'Service';
        $hasOpenServiceSurvey = SurveyAssignment::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->whereNull('service_activation_id')
            ->whereNotNull('service_snapshot')
            ->whereIn('status', SurveyAssignmentStatus::activeValues())
            ->whereHas('survey', fn (Builder $query) => $query->where('type', SurveyType::ServiceImprovement->value))
            ->exists();

        return [
            'action_url' => route('admin.service-surveys.entrepreneurs.store', $profile, absolute: false),
            'service_label' => $serviceLabel,
            'unavailable_reason' => $hasOpenServiceSurvey ? 'A service feedback survey is already awaiting a response. Sending again will cancel the old survey and issue the latest version.' : null,
            'has_open_survey' => $hasOpenServiceSurvey,
        ];
    }

    private function canResendInvite(EntrepreneurProfile $profile): bool
    {
        return $profile->user_id === null && $profile->user === null && $profile->inviteToken?->accepted_at === null && filter_var($profile->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function canCancelInvite(EntrepreneurProfile $profile): bool
    {
        return $profile->user_id === null
            && $profile->user === null
            && $profile->currentStage() === EntrepreneurStage::INVITED
            && $profile->inviteToken instanceof InviteToken
            && $profile->inviteToken->isUsable()
            && filter_var($profile->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function canUpdateInviteDetails(EntrepreneurProfile $profile): bool
    {
        return $profile->user_id === null && $profile->user === null && $profile->inviteToken?->accepted_at === null;
    }

    private function intendedEntrepreneurScope(EntrepreneurProfile $profile): string
    {
        if ($profile->intended_service_type === ServiceActivation::SERVICE_ENTREPRENEUR && is_string($profile->intended_package_scope) && $profile->intended_package_scope !== '') {
            return ServiceRatePackage::normaliseEntrepreneurScope($profile->intended_package_scope);
        }

        $invite = $profile->inviteToken;
        if ($invite instanceof InviteToken && $invite->intended_service_type === ServiceActivation::SERVICE_ENTREPRENEUR && is_string($invite->intended_package_scope)) {
            return ServiceRatePackage::normaliseEntrepreneurScope($invite->intended_package_scope);
        }

        return ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO;
    }

    /** @return Builder<EntrepreneurProfile> */
    private function visibleProfiles(User $user): Builder
    {
        $query = EntrepreneurProfile::query()
            ->withoutOperationalHealthFixtures()
            ->with([
                'assignedAdvisor', 'inviteToken', 'user',
                'businessPlans' => fn (Relation $plans): mixed => $plans
                    ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
                    ->latest('updated_at')
                    ->limit(1),
            ]);

        if ($user->fsaRole() === User::TYPE_SUPER_ADMIN) {
            return $query;
        }

        return $user->user_type === User::TYPE_ENTREPRENEUR
            ? $query->where('user_id', $user->getKey())
            : $query->where('assigned_advisor_id', $user->getKey());
    }

    /** @return ProfileSummary */
    private function profileSummary(EntrepreneurProfile $profile): array
    {
        $stage = $profile->currentStage();

        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'email' => $profile->email,
            'stage' => $stage->value,
            'stage_label' => $this->profileStageLabel($profile, $stage),
            'assigned_advisor_name' => $profile->assignedAdvisor?->name,
        ];
    }

    private function profileStageLabel(EntrepreneurProfile $profile, EntrepreneurStage $stage): string
    {
        $latestPlan = $profile->relationLoaded('businessPlans')
            ? $profile->businessPlans->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)->sortByDesc('updated_at')->first()
            : null;
        if ($latestPlan instanceof BusinessPlan && $latestPlan->status === BusinessPlan::STATUS_REVISING) {
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
