<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Enums\ReportType;
use App\Enums\SurveyAssignmentStatus;
use App\Enums\SurveyType;
use App\Http\Controllers\Portal\Concerns\BuildsEntrepreneurAssessmentPayload;
use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\Document;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\InviteToken;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\PlanAssessment;
use App\Models\PlanRevision;
use App\Models\ReadinessAssessment;
use App\Models\Report;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\SurveyAssignment;
use App\Models\User;
use App\Services\ScreenShare\ScreenShareAuthorizer;
use App\Services\Surveys\SurveyActivationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @phpstan-type ProfileSummary array{id:string, name:string, email:string, stage:string, stage_label:string, assigned_advisor_name:string|null}
 * @phpstan-type ServiceOption array{value:string, label:string, description:string}
 * @phpstan-type CollaborationParticipant array{id:string, name:string}
 * @phpstan-type ScreenSharePayload array{connection_url:string, connection_heartbeat_url:string, request_url:string, ice_servers_url:string, active_url:string, signal_url:string, pending_signals_url:string, heartbeat_url:string, end_url:string, heartbeat_seconds:int, participants:list<CollaborationParticipant>}
 * @phpstan-type CoBrowsePayload array{connection_url:string, connection_heartbeat_url:string, request_url:string, status_url:string, heartbeat_url:string, end_url:string, action_url:string, heartbeat_seconds:int, participants:list<CollaborationParticipant>}
 * @phpstan-type PlanProgressSummary array{id:string, title:string, status:string, assessment_count:int, latest_round:int|null, latest_grade:string|null, can_assess:bool, assessment_action_label:string, assessment_run:array{status:string|null, requested_at:string|null, started_at:string|null, total_criteria:int|null, completed_criteria:int|null, current_criterion:string|null, completed_at:string|null, failed_at:string|null, failure:string|null}, latest_assessment:mixed, executive_summary:mixed, budget:BudgetSummary, preview_pdf_url:string, budget_pdf_url:string|null, funder_ready:mixed, assess_url:string, assessment_history:list<AssessmentHistoryEntry>, latest_revision:mixed}
 * @phpstan-type AssessmentHistoryEntry array{id:string, round:int, status:string, overall_grade:string|null, weighted_score:float|null, automated_score_available:bool, score_delta:float|null, score_source_summary:string, created_at:string|null, submitted_at:string|null, snapshot_available:bool, snapshot_captured_at:mixed, snapshot_note:string, assessment_url:string, plan_snapshot_url:string|null}
 * @phpstan-type BudgetSummary array{status:string, expected_runway_months:float|int|null, calculated_runway_months:mixed, runway_open_ended:bool, break_even_month:mixed, available_after_launch:mixed, active_flags:list<mixed>}
 * @phpstan-type ReadinessSummary array{completed:bool, score:float|int|null, outcome:string|null, assessed_at:string|null}
 * @phpstan-type IdeaValidationSummary array{id:string, revision_number:int, summary:string, problem:string|null, target_customer:string|null, solution:string|null, value_proposition:string|null, demand_signal:string|null, revenue_model:string|null, viability_alerts:list<mixed>, viability_gate:mixed, proposed_change_request:string, uncertainty:mixed, past_plan_pattern:list<mixed>, evaluated_at:string|null, ai_deferred:bool, advisor_gate_status:string, change_request_note:mixed, changes_requested_at:mixed, recalled_at:string|null, restored_from_revision_number:mixed, refresh_status:mixed, refresh_stale:bool, refresh_requested_at:mixed, refresh_started_at:mixed, refresh_completed_at:mixed, refresh_failed_at:mixed, refresh_failure:mixed, advisor_gate_passed_at:string|null, advisor_gate_note:string|null, gate_url:string, request_changes_url:string, refresh_url:string}
 * @phpstan-type Finding array{recommended_action?:mixed, title?:mixed, body?:mixed}
 * @phpstan-type FounderAction array{horizon:string, action:string}
 * @phpstan-type AdvisoryReadinessSummary array{id:string, score:float|int|null, surfaced_at:string|null}
 * @phpstan-type ReportSummary array{id:string, title:string, generated_at:string|null, view_url:string, download_url:string}
 * @phpstan-type ConversionSummary array{available:bool, converted:bool, client_id:string|null, convert_url:string}
 * @phpstan-type DocumentSummary array{id:string, original_filename:string, category:string, scanner_result:string|null, scanner_message:mixed, uploaded_at:string|null, uploaded_by_name:string|null, url:string|null}
 * @phpstan-type MessageSummary array{threads_count:int, unread_count:int, latest_activity_at:string|null, url:string}
 */
final class AdvisorEntrepreneurWorkspacePayload
{
    use BuildsEntrepreneurAssessmentPayload;

    public function __construct(
        private readonly EntrepreneurGamification $gamification,
        private readonly FounderChangeRequestMessage $changeRequestMessages,
        private readonly IdeaViabilityGate $ideaViabilityGate,
        private readonly BusinessPlanPreviewRenderer $planPreview,
        private readonly FunderReadyBusinessPlanBuilder $funderReadyPlans,
        private readonly BusinessPlanExecutiveSummary $executiveSummaries,
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
            'assignedAdvisor',
            'inviteToken',
            'user',
            'businessPlans.assessments.ratingFramework.criteria',
            'businessPlans.budgetRunway',
            'businessPlans.revisions',
        ]);
        $latestPlan = $profile->businessPlans
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->sortByDesc('updated_at')
            ->first();
        $serviceOptions = ServiceRatePackage::entrepreneurInviteServiceOptions();
        $intendedPackageScope = $this->intendedEntrepreneurScope($profile);
        $intendedPackageOption = collect($serviceOptions)
            ->firstWhere('value', $intendedPackageScope);
        $intendedPackageLabel = is_array($intendedPackageOption)
            ? (string) $intendedPackageOption['label']
            : ServiceRatePackage::packageScopeLabel($intendedPackageScope);
        $activeInvite = $profile->inviteToken instanceof InviteToken
            && $profile->inviteToken->isUsable();

        return [
            'entrepreneur' => [
                ...$this->profileSummary($profile),
                'concept_summary' => $profile->concept_summary,
                'user_id' => $profile->user_id,
                'invite_accepted_at' => $profile->inviteToken?->accepted_at?->toIso8601String(),
                'invite_expires_at' => $profile->inviteToken?->expires_at?->toIso8601String(),
                'invite_delivery_label' => $profile->user_id
                    ? 'Account onboarded'
                    : ($activeInvite ? 'Email sent' : 'No active invite'),
                'invite_update_url' => $this->canUpdateInviteDetails($profile)
                    ? route('advisor.entrepreneurs.invite.update', $profile, absolute: false)
                    : null,
                'invite_resend_url' => $this->canResendInvite($profile)
                    ? route('advisor.entrepreneurs.invite.resend', $profile, absolute: false)
                    : null,
                'invite_cancel_url' => $this->canCancelInvite($profile)
                    ? route('advisor.entrepreneurs.invite.cancel', $profile, absolute: false)
                    : null,
                'intended_package_scope' => $intendedPackageScope,
                'intended_package_scope_label' => $intendedPackageLabel,
                'created_at' => $profile->created_at?->toIso8601String(),
                'latest_plan' => $latestPlan instanceof BusinessPlan
                    ? $this->planProgressSummary($latestPlan, $profile)
                    : null,
                'readiness' => $this->readinessSummary($profile),
                'feedback_survey' => [
                    'action_url' => route('advisor.entrepreneurs.survey-assignments.store', $profile, absolute: false),
                ],
                'service_feedback_survey' => $this->serviceFeedbackSurvey($viewer, $profile),
                'idea_validation' => $this->ideaValidationSummary($profile),
                'advisory_readiness' => $this->advisoryReadinessSummary($profile),
                'reports' => $this->reportSummary($profile),
                'conversion' => $this->conversionSummary($profile, $latestPlan),
                'documents' => $this->latestDocuments($profile),
                'messages' => $this->messageSummary($profile, $viewer),
                'client_actions' => $profile->client_id !== null
                    ? [
                        'email_url' => route('advisor.clients.compose', $profile->client_id, absolute: false),
                        'offboard_url' => route('advisor.clients.offboarding.create', $profile->client_id, absolute: false),
                    ]
                    : null,
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
        if (
            ! $profile->user instanceof User
            || ! $this->screenShareAuthorizer->canRequestForEntrepreneur($viewer, $profile, $profile->user)
        ) {
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
            'participants' => [[
                'id' => (string) $profile->user->getKey(),
                'name' => $profile->user->name,
            ]],
        ];
    }

    /** @return CoBrowsePayload|null */
    private function coBrowsePayload(User $viewer, EntrepreneurProfile $profile): ?array
    {
        if (
            ! (bool) config('co-browse.enabled')
            || ! $profile->user instanceof User
            || ! $this->screenShareAuthorizer->canRequestForEntrepreneur($viewer, $profile, $profile->user)
        ) {
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
            'participants' => [[
                'id' => (string) $profile->user->getKey(),
                'name' => $profile->user->name,
            ]],
        ];
    }

    /**
     * @return array{action_url:string|null,service_label:string|null,unavailable_reason:string|null,has_open_survey:bool}|null
     */
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

        $serviceLabel = is_string($serviceSnapshot['service_label'] ?? null)
            ? $serviceSnapshot['service_label']
            : 'Service';

        $hasOpenServiceSurvey = SurveyAssignment::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->whereNull('service_activation_id')
            ->whereNotNull('service_snapshot')
            ->whereIn('status', SurveyAssignmentStatus::activeValues())
            ->whereHas('survey', fn (Builder $query) => $query->where('type', SurveyType::ServiceImprovement->value))
            ->exists();

        if ($hasOpenServiceSurvey) {
            return [
                'action_url' => route('admin.service-surveys.entrepreneurs.store', $profile, absolute: false),
                'service_label' => $serviceLabel,
                'unavailable_reason' => 'A service feedback survey is already awaiting a response. Sending again will cancel the old survey and issue the latest version.',
                'has_open_survey' => true,
            ];
        }

        return [
            'action_url' => route('admin.service-surveys.entrepreneurs.store', $profile, absolute: false),
            'service_label' => $serviceLabel,
            'unavailable_reason' => null,
            'has_open_survey' => false,
        ];
    }

    private function canResendInvite(EntrepreneurProfile $profile): bool
    {
        return $profile->user_id === null
            && $profile->user === null
            && $profile->inviteToken?->accepted_at === null
            && filter_var($profile->email, FILTER_VALIDATE_EMAIL) !== false;
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
        return $profile->user_id === null
            && $profile->user === null
            && $profile->inviteToken?->accepted_at === null;
    }

    private function intendedEntrepreneurScope(EntrepreneurProfile $profile): string
    {
        if (
            $profile->intended_service_type === ServiceActivation::SERVICE_ENTREPRENEUR
            && is_string($profile->intended_package_scope)
            && $profile->intended_package_scope !== ''
        ) {
            return ServiceRatePackage::normaliseEntrepreneurScope($profile->intended_package_scope);
        }

        $invite = $profile->inviteToken;
        if (
            $invite instanceof InviteToken
            && $invite->intended_service_type === ServiceActivation::SERVICE_ENTREPRENEUR
            && is_string($invite->intended_package_scope)
        ) {
            return ServiceRatePackage::normaliseEntrepreneurScope($invite->intended_package_scope);
        }

        return ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO;
    }

    /**
     * @return Builder<EntrepreneurProfile>
     */
    private function visibleProfiles(User $user): Builder
    {
        $query = EntrepreneurProfile::query()
            ->withoutOperationalHealthFixtures()
            ->with([
                'assignedAdvisor',
                'inviteToken',
                'user',
                'businessPlans' => fn (Relation $plans): mixed => $plans
                    ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
                    ->latest('updated_at')
                    ->limit(1),
            ]);

        if ($user->fsaRole() === User::TYPE_SUPER_ADMIN) {
            return $query;
        }

        if ($user->user_type === User::TYPE_ENTREPRENEUR) {
            return $query->where('user_id', $user->getKey());
        }

        return $query->where('assigned_advisor_id', $user->getKey());
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
            ? $profile->businessPlans
                ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
                ->sortByDesc('updated_at')
                ->first()
            : null;

        if ($latestPlan instanceof BusinessPlan && $latestPlan->status === BusinessPlan::STATUS_REVISING) {
            return 'Revision requested - awaiting resubmission';
        }

        if ($stage === EntrepreneurStage::INVITED && $profile->inviteToken?->isAccepted()) {
            return 'Invite accepted';
        }

        if (
            in_array($stage, [EntrepreneurStage::INVITED, EntrepreneurStage::ONBOARDING], true)
            && ($profile->user_id !== null || $profile->user instanceof User || $profile->inviteToken?->isAccepted())
        ) {
            return 'Active';
        }

        return $stage->label();
    }

    /** @return PlanProgressSummary */
    private function planProgressSummary(BusinessPlan $plan, EntrepreneurProfile $profile): array
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
            'can_assess' => $this->canAssessPlan($plan) && ! $assessmentRunInFlight,
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
                'generate_url' => route('advisor.entrepreneurs.plans.executive-summary.store', [$profile, $plan], absolute: false),
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
                    'submitted_at' => $this->submittedAtForAssessment($plan, $assessment),
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

        $reused = $scores->filter(fn (array $score): bool => (string) ($score['score_source'] ?? data_get($score, 'metadata.score_source')) === 'reused_identical_context')->count();
        $ai = $scores->filter(fn (array $score): bool => (string) ($score['score_source'] ?? data_get($score, 'metadata.score_source')) === 'ai_assessment')->count();
        $fallback = $scores->filter(fn (array $score): bool => (string) ($score['score_source'] ?? data_get($score, 'metadata.score_source')) === 'deterministic_fallback')->count();

        if ($reused === $total) {
            return 'Carried forward from an earlier assessment; no fresh AI score was generated.';
        }

        if ($fallback === $total) {
            return 'Invalid automated result: no AI score was returned. Retained for audit only and excluded from progression.';
        }

        if ($fallback > 0) {
            return 'Invalid automated result: '.$fallback.' criterion scores were fallback values. Retained for audit only and excluded from progression.';
        }

        if ($reused > 0) {
            return $ai.' AI-scored criteria and '.$reused.' carried forward from an earlier assessment.';
        }

        $calibrated = $scores->every(fn (array $score): bool => data_get($score, 'metadata.scoring_method') === 'calibrated_band_v1'
            && data_get($score, 'metadata.evidence_mode') === 'complete_submitted_plan_snapshot');
        if ($calibrated) {
            return 'Calibrated rubric-band assessment against the complete submitted plan snapshot, including budget evidence.';
        }

        return 'AI-scored against the captured plan context.';
    }

    private function submittedAtForAssessment(BusinessPlan $plan, PlanAssessment $assessment): ?string
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

    private function canAssessPlan(BusinessPlan $plan): bool
    {
        if ($plan->status === BusinessPlan::STATUS_REVISING) {
            return false;
        }

        return true;
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

    /** @return ReadinessSummary */
    private function readinessSummary(EntrepreneurProfile $profile): array
    {
        $assessment = ReadinessAssessment::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('assessed_at')
            ->latest()
            ->first();

        return [
            'completed' => $assessment instanceof ReadinessAssessment,
            'score' => $assessment?->score,
            'outcome' => $assessment?->outcome,
            'assessed_at' => $assessment?->assessed_at?->toIso8601String(),
        ];
    }

    /** @return IdeaValidationSummary|null */
    private function ideaValidationSummary(EntrepreneurProfile $profile): ?array
    {
        $validation = IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->orderByDesc('revision_number')
            ->orderByDesc('evaluated_at')
            ->first();

        if (! $validation instanceof IdeaValidation) {
            return null;
        }

        $evaluation = $validation->ai_evaluation ?? [];
        $aiDeferred = (bool) data_get($evaluation, 'metadata.degraded', false)
            || data_get($evaluation, 'model') === 'fake-ai-client';
        $gateStatus = $this->ideaGateStatus($validation);
        $viabilityGate = $this->ideaViabilityGatePayload($validation, $gateStatus);
        $refreshStatus = data_get($evaluation, 'metadata.refresh_status');
        $refreshRequestedAt = data_get($evaluation, 'metadata.refresh_requested_at');
        $refreshStartedAt = data_get($evaluation, 'metadata.refresh_started_at');
        $refreshStale = $this->refreshStale($refreshStatus, $refreshStartedAt ?? $refreshRequestedAt);

        return [
            'id' => $validation->id,
            'revision_number' => $validation->revision_number,
            'summary' => (string) data_get($validation->ai_evaluation, 'summary', ''),
            'problem' => $validation->problem,
            'target_customer' => $validation->target_customer,
            'solution' => $validation->solution,
            'value_proposition' => $validation->value_proposition,
            'demand_signal' => $validation->demand_signal,
            'revenue_model' => $validation->revenue_model,
            'viability_alerts' => $validation->viability_alerts ?? [],
            'viability_gate' => $viabilityGate,
            'proposed_change_request' => $this->proposedChangeRequest($profile, $validation),
            'uncertainty' => data_get($evaluation, 'uncertainty'),
            'past_plan_pattern' => data_get($evaluation, 'past_plan_pattern', []),
            'evaluated_at' => $validation->evaluated_at?->toIso8601String(),
            'ai_deferred' => $aiDeferred,
            'advisor_gate_status' => $gateStatus,
            'change_request_note' => data_get($evaluation, 'metadata.change_request_note'),
            'changes_requested_at' => data_get($evaluation, 'metadata.changes_requested_at'),
            'recalled_at' => $validation->recalled_at?->toIso8601String(),
            'restored_from_revision_number' => data_get($evaluation, 'metadata.restored_from_revision_number'),
            'refresh_status' => $refreshStatus,
            'refresh_stale' => $refreshStale,
            'refresh_requested_at' => $refreshRequestedAt,
            'refresh_started_at' => $refreshStartedAt,
            'refresh_completed_at' => data_get($evaluation, 'metadata.refresh_completed_at'),
            'refresh_failed_at' => data_get($evaluation, 'metadata.refresh_failed_at'),
            'refresh_failure' => data_get($evaluation, 'metadata.refresh_failure'),
            'advisor_gate_passed_at' => $validation->advisor_gate_passed_at?->toIso8601String(),
            'advisor_gate_note' => $validation->advisor_gate_note,
            'gate_url' => route('advisor.entrepreneurs.idea-validations.gate', [$profile, $validation], absolute: false),
            'request_changes_url' => route('advisor.entrepreneurs.idea-validations.request-changes', [$profile, $validation], absolute: false),
            'refresh_url' => route('advisor.entrepreneurs.idea-validations.refresh', [$profile, $validation], absolute: false),
        ];
    }

    /**
     * @return array{status: string, label: string, summary: string, reasons: array<int, string>, approval_available: bool}
     */
    private function ideaViabilityGatePayload(IdeaValidation $validation, string $gateStatus): array
    {
        $gate = $this->ideaViabilityGate->assess($validation);

        if ($validation->advisor_gate_passed_at === null && $gateStatus === 'changes_requested') {
            return [
                ...$gate,
                'status' => IdeaViabilityGate::STATUS_AMBER,
                'label' => 'Amber - changes requested',
                'summary' => 'Advisor changes are still outstanding. The founder must update and resubmit the idea validation before it can be approved for the builder.',
                'reasons' => $gate['reasons'] !== []
                    ? $gate['reasons']
                    : ['Await founder resubmission before approving the business plan builder.'],
                'approval_available' => false,
            ];
        }

        return $gate;
    }

    private function proposedChangeRequest(EntrepreneurProfile $profile, IdeaValidation $validation): string
    {
        $evaluation = $validation->ai_evaluation ?? [];
        $findings = collect((array) data_get($evaluation, 'metadata.findings', []))
            ->filter(fn (mixed $finding): bool => is_array($finding))
            ->map(fn (array $finding): array => $this->founderActionForFinding($finding))
            ->filter(fn (array $action): bool => trim($action['action']) !== '')
            ->take(4)
            ->values();

        if ($findings->isEmpty()) {
            $findings = collect([
                ['horizon' => 'now', 'action' => 'Define the primary customer segment, the paid problem it faces, and why this offer is a better choice than the alternatives.'],
                ['horizon' => 'now', 'action' => 'Record at least one customer experiment with a clear hypothesis, evidence, result, and next step.'],
                ['horizon' => 'now', 'action' => 'Describe a repeatable offer, pricing, delivery capacity, and revenue model that is not dependent only on your personal time.'],
            ]);
        }

        $alerts = collect((array) $validation->viability_alerts)
            ->filter(fn (mixed $alert): bool => is_array($alert))
            ->map(fn (array $alert): string => trim((string) ($alert['message'] ?? '')))
            ->filter()
            ->map(fn (string $alert): array => [
                'horizon' => 'now',
                'action' => $this->completeFeedbackPoint($alert),
            ]);

        $shortTermActions = $findings
            ->merge($alerts)
            ->filter(fn (array $action): bool => $action['horizon'] === 'now')
            ->pluck('action')
            ->unique(fn (string $action): string => Str::lower($action))
            ->take(4)
            ->values();

        if ($shortTermActions->isEmpty()) {
            $shortTermActions = collect([
                'Define the immediate evidence needed to decide whether this idea should move into business-plan development.',
            ]);
        }

        $longTermActions = $findings
            ->filter(fn (array $action): bool => $action['horizon'] === 'long_term')
            ->pluck('action')
            ->unique(fn (string $action): string => Str::lower($action))
            ->take(3)
            ->values();

        if ($longTermActions->isEmpty()) {
            $longTermActions = collect([
                'Use the first validation cycle to decide which partnership, staffing, retention, and scale assumptions belong in the business-plan evidence.',
            ]);
        }

        return $this->changeRequestMessages->build($profile, [
            'Thank you for the work you have put into this idea validation.',
            'Your idea shows promise, but more evidence and a more repeatable commercial model are needed before it can move into business-plan development.',
            "Before resubmitting, please complete the short-term validation work:\n{$this->numberedFeedbackActions($shortTermActions)}",
            "Longer-term plan-builder evidence to prepare after the gate decision:\n{$this->numberedFeedbackActions($longTermActions)}",
            'Please update the idea validation with the short-term evidence and resubmit it for review. Keep the longer-term items for the plan-builder or scaling work if the gate is approved.',
        ]);
    }

    /**
     * @param  Finding  $finding
     * @return FounderAction
     */
    private function founderActionForFinding(array $finding): array
    {
        $recommendedAction = trim((string) ($finding['recommended_action'] ?? ''));
        if ($recommendedAction !== '') {
            $action = $this->completeFeedbackPoint($this->sanitiseReferenceSensitiveAction($recommendedAction));

            return [
                'horizon' => $this->feedbackHorizon($action, $this->findingContext($finding)),
                'action' => $action,
            ];
        }

        $title = trim((string) ($finding['title'] ?? ''));
        $body = trim((string) ($finding['body'] ?? ''));
        $context = Str::lower($title.' '.$body);

        if (Str::contains($context, ['revenue', 'pricing', 'price', 'time-constrained', 'capacity'])) {
            return [
                'horizon' => 'now',
                'action' => 'Build a sustainable revenue model: show how the offer can create income beyond your own billable days, including package pricing, delivery costs, monthly capacity, and recurring follow-on support.',
            ];
        }

        if (Str::contains($context, ['demand', 'market', 'customer evidence', 'willingness to pay'])) {
            return [
                'horizon' => 'now',
                'action' => 'Collect and document stronger demand evidence: choose a primary customer segment, test a paid offer, and record the hypothesis, evidence, result, and next step.',
            ];
        }

        if (Str::contains($context, ['value proposition', 'differentiat', 'positioning', 'communicat'])) {
            return [
                'horizon' => 'now',
                'action' => 'State one clear value proposition: name the customer, their pressing problem, the outcome they receive, and why this offer is more valuable than the alternatives.',
            ];
        }

        if (Str::contains($context, ['target customer', 'customer segment', 'customer'])) {
            return [
                'horizon' => 'now',
                'action' => 'Narrow the starting customer segment and explain the specific paid problem this offer will solve for them.',
            ];
        }

        if (Str::contains($context, ['solution', 'delivery', 'offer'])) {
            return [
                'horizon' => 'now',
                'action' => 'Describe a repeatable offer with clear outcomes, delivery steps, and what can be standardised as demand grows.',
            ];
        }

        $action = $this->completeFeedbackPoint(trim(implode(': ', array_filter([$title, $body]))));

        return [
            'horizon' => $this->feedbackHorizon($action, $context),
            'action' => $action,
        ];
    }

    /** @param Finding $finding */
    private function findingContext(array $finding): string
    {
        return Str::lower(implode(' ', [
            (string) ($finding['title'] ?? ''),
            (string) ($finding['body'] ?? ''),
            (string) ($finding['recommended_action'] ?? ''),
        ]));
    }

    private function feedbackHorizon(string $action, string $context): string
    {
        $haystack = Str::lower($action.' '.$context);

        if (Str::contains($haystack, [
            'before scaling',
            'full season',
            'seasonal',
            'partner agreement',
            'partnership agreement',
            'retention',
            'scaling',
            'volunteer',
            'written partnership',
        ])) {
            return 'long_term';
        }

        return 'now';
    }

    private function sanitiseReferenceSensitiveAction(string $action): string
    {
        if (! preg_match('/\bminimum wage\b|\$\d+(?:\.\d+)?\s*(?:\/\s*hr|per\s+hour|nzd_per_hour)/i', $action)) {
            return $action;
        }

        $action = preg_replace('/\s*\((?=[^)]*(?:minimum wage|\$\d+(?:\.\d+)?\s*(?:\/\s*hr|per\s+hour|nzd_per_hour)))[^)]*\)/i', '', $action) ?? $action;
        $action = preg_replace('/using\s+real\s+NZ\s+labou?r\s+rates/i', 'using current NZ wage reference data', $action) ?? $action;
        $action = preg_replace('/minimum wage\s+(?:is\s+|of\s+)?\$?\d+(?:\.\d+)?(?:\s*(?:\/\s*hr|per\s+hour|nzd_per_hour))?(?:\s+as\s+of\s+[A-Za-z]+\s+\d{4})?/i', 'current NZ minimum wage reference data', $action) ?? $action;
        $action = preg_replace('/\s+,/', ',', $action) ?? $action;

        return trim(preg_replace('/\s{2,}/', ' ', $action) ?? $action);
    }

    /** @param iterable<string> $actions */
    private function numberedFeedbackActions(iterable $actions): string
    {
        return collect($actions)
            ->values()
            ->map(fn (string $action, int $index): string => ($index + 1).'. '.$action)
            ->implode("\n");
    }

    private function completeFeedbackPoint(string $point): string
    {
        $point = trim($point);
        if (Str::length($point) <= 600) {
            return $point;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $point) ?: [];
        $limited = '';

        foreach ($sentences as $sentence) {
            $candidate = trim($limited.' '.trim($sentence));
            if (Str::length($candidate) > 600) {
                break;
            }

            $limited = $candidate;
        }

        if ($limited !== '') {
            return $limited;
        }

        $truncated = rtrim(Str::limit($point, 600, ''), " \t\n\r\0\x0B.,;:");

        return $truncated === '' ? $point : $truncated.'.';
    }

    private function ideaGateStatus(IdeaValidation $validation): string
    {
        if ($validation->recalled_at !== null) {
            return 'recalled';
        }

        if ($validation->advisor_gate_passed_at !== null) {
            return 'approved';
        }

        $status = data_get($validation->ai_evaluation, 'metadata.advisor_gate_status');

        return is_string($status) && trim($status) !== '' ? $status : 'gate_needed';
    }

    private function refreshStale(mixed $status, mixed $timestamp): bool
    {
        if (! in_array($status, ['queued', 'running'], true) || ! is_string($timestamp) || trim($timestamp) === '') {
            return false;
        }

        $staleMinutes = max(1, (int) config('services.anthropic.refresh_stale_minutes', 2));

        return Carbon::parse($timestamp)->lessThan(now()->subMinutes($staleMinutes));
    }

    /** @return AdvisoryReadinessSummary|null */
    private function advisoryReadinessSummary(EntrepreneurProfile $profile): ?array
    {
        $signal = AdvisoryReadinessSignal::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('surfaced_at')
            ->latest()
            ->first();

        if (! $signal instanceof AdvisoryReadinessSignal) {
            return null;
        }

        return [
            'id' => $signal->id,
            'score' => $signal->score,
            'surfaced_at' => $signal->surfaced_at?->toIso8601String(),
        ];
    }

    /** @return list<ReportSummary> */
    private function reportSummary(EntrepreneurProfile $profile): array
    {
        return Report::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('type', ReportType::EntrepreneurAssessment)
            ->latest('generated_at')
            ->limit(5)
            ->get()
            ->map(function (Report $report): array {
                $url = route('advisor.reports.download', $report, absolute: false);

                return [
                    'id' => $report->id,
                    'title' => $report->title,
                    'generated_at' => $report->generated_at?->toIso8601String(),
                    'view_url' => $url,
                    'download_url' => $url,
                ];
            })
            ->values()
            ->all();
    }

    /** @return ConversionSummary */
    private function conversionSummary(EntrepreneurProfile $profile, ?BusinessPlan $plan): array
    {
        $signalExists = AdvisoryReadinessSignal::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->exists();

        return [
            'available' => $signalExists && ! $plan?->client_id,
            'converted' => $plan?->client_id !== null,
            'client_id' => $plan?->client_id,
            'convert_url' => route('advisor.entrepreneurs.convert', $profile, absolute: false),
        ];
    }

    /** @return list<DocumentSummary> */
    private function latestDocuments(EntrepreneurProfile $profile): array
    {
        return Document::query()
            ->with('uploadedBy')
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest()
            ->get()
            ->groupBy(fn (Document $document): string => implode('|', [
                $document->category,
                $document->sha256 ?: $document->getKey(),
            ]))
            ->map(fn ($duplicates): Document => $duplicates->firstWhere(
                'scanner_result',
                Document::SCANNER_CLEAN,
            ) ?? $duplicates->first())
            ->sortByDesc('created_at')
            ->take(6)
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'original_filename' => $document->original_filename,
                'category' => $document->category,
                'scanner_result' => $document->scanner_result,
                'scanner_message' => data_get($document->scanner_payload, 'message'),
                'uploaded_at' => $document->created_at?->toIso8601String(),
                'uploaded_by_name' => $document->uploadedBy?->name,
                'url' => $document->isVisibleToClients()
                    ? route('advisor.entrepreneurs.documents.show', [$profile, $document], absolute: false)
                    : null,
            ])
            ->values()
            ->all();
    }

    /** @return MessageSummary */
    private function messageSummary(EntrepreneurProfile $profile, User $user): array
    {
        $threadIds = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->pluck('id');
        $latestThread = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->orderByDesc('last_activity_at')
            ->orderByDesc('created_at')
            ->first();

        $participantRows = MessageThreadParticipant::query()
            ->whereIn('thread_id', $threadIds)
            ->where('user_id', $user->getKey())
            ->get(['thread_id', 'last_read_at']);

        $unread = $participantRows->sum(function (MessageThreadParticipant $participant) use ($user): int {
            $query = Message::query()
                ->where('thread_id', $participant->thread_id)
                ->where('sender_user_id', '!=', $user->getKey());

            if ($participant->last_read_at !== null) {
                $query->where('sent_at', '>', $participant->last_read_at);
            }

            return $query->count();
        });

        return [
            'threads_count' => $threadIds->count(),
            'unread_count' => (int) $unread,
            'latest_activity_at' => $latestThread?->last_activity_at?->toIso8601String(),
            'url' => route('advisor.entrepreneurs.messages.index', $profile, absolute: false),
        ];
    }
}
