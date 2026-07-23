<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\SurveyAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\BuildsEntrepreneurAssessmentPayload;
use App\Models\AdvisoryReadinessSignal;
use App\Models\BoardPost;
use App\Models\BusinessPlan;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\OutcomeFollowUp;
use App\Models\ServiceActivation;
use App\Models\SurveyAssignment;
use App\Models\User;
use App\Services\Board\InspirationBoard;
use App\Services\Entrepreneurs\EntrepreneurGamification;
use App\Services\Entrepreneurs\EntrepreneurInviteReconciler;
use App\Services\Portal\Welcome\WelcomeMessageRenderer;
use App\Services\ScreenShare\ClientPortalContextTokens;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurDashboardController extends Controller
{
    use BuildsEntrepreneurAssessmentPayload;

    public function __construct(
        private readonly InspirationBoard $inspirationBoard,
        private readonly EntrepreneurGamification $gamification,
        private readonly EntrepreneurInviteReconciler $entrepreneurInvites,
        private readonly WelcomeMessageRenderer $welcomeMessage,
        private readonly ClientPortalContextTokens $screenShareContexts,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $clientActivation = $this->activeEntrepreneurActivationForUser($user);
        abort_unless($user->user_type === User::TYPE_ENTREPRENEUR || $clientActivation instanceof ServiceActivation, 403);

        $this->entrepreneurInvites->reconcile($user);

        $profile = EntrepreneurProfile::query()
            ->with([
                'assignedAdvisor',
                'businessPlans.assessments.ratingFramework.criteria',
                'advisoryReadinessSignals.planAssessment.ratingFramework.criteria',
            ])
            ->when(
                $clientActivation instanceof ServiceActivation && $clientActivation->related_entrepreneur_profile_id !== null,
                fn ($query) => $query->whereKey($clientActivation->related_entrepreneur_profile_id),
                fn ($query) => $query->where('user_id', $user->getKey()),
            )
            ->first();
        $latestPlan = $profile?->businessPlans
            ->sortByDesc('updated_at')
            ->first();
        $latestAssessment = $latestPlan?->assessments
            ->sortByDesc('round')
            ->first();
        $latestSignal = $profile?->advisoryReadinessSignals
            ->sortByDesc('surfaced_at')
            ->first();
        $latestAssessmentPayload = $latestAssessment ? $this->assessmentPayload($latestAssessment) : null;

        return Inertia::render('portal/entrepreneur/Dashboard', [
            'profile' => $profile ? [
                'id' => $profile->id,
                'name' => $profile->name,
                'email' => $profile->email,
                'stage' => $profile->currentStageValue(),
                'stage_label' => $profile->currentStageLabel(),
                'concept_summary' => $profile->concept_summary,
                'assigned_advisor' => $profile->assignedAdvisor ? [
                    'id' => $profile->assignedAdvisor->id,
                    'name' => $profile->assignedAdvisor->name,
                    'email' => $profile->assignedAdvisor->email,
                ] : null,
                'latest_plan' => $latestPlan instanceof BusinessPlan ? [
                    'id' => $latestPlan->id,
                    'status' => $latestPlan->status,
                    'assessment_count' => $latestPlan->assessments->count(),
                    'completed_assessment_count' => $latestPlan->assessments
                        ->whereNotNull('finalised_at')
                        ->count(),
                    'latest_grade' => $latestPlan->assessments->sortByDesc('round')->first()?->overall_grade,
                    'latest_assessment' => $latestAssessmentPayload ? [
                        'id' => $latestAssessmentPayload['id'],
                        'round' => $latestAssessmentPayload['round'],
                        'status' => $latestAssessmentPayload['status'],
                        'overall_grade' => $latestAssessmentPayload['overall_grade'],
                        'weighted_score' => $latestAssessmentPayload['weighted_score'],
                        'url' => route('portal.entrepreneur.assessments.show', $latestAssessment, absolute: false),
                    ] : null,
                    'living_plan_next_update_at' => $latestPlan->living_plan_next_update_at?->toIso8601String(),
                    'living_plan_divergence_flags' => $latestPlan->living_plan_divergence_flags,
                ] : null,
                'advisory_readiness_signal' => $latestSignal instanceof AdvisoryReadinessSignal
                    ? $this->advisoryReadinessSignalPayload($latestSignal)
                    : null,
                'latest_documents' => $this->latestDocuments($profile),
                'message_summary' => $this->messageSummary($profile, $user),
            ] : null,
            'inspirationBoard' => $this->inspirationBoardPayload(),
            'messagesUrl' => route('portal.messages.index', absolute: false),
            'planWorkspaceUrl' => route('portal.entrepreneur.plan.show', absolute: false),
            'buyingBusinessServiceUrl' => route('portal.service-activations.create', ['serviceType' => ServiceActivation::SERVICE_DUE_DILIGENCE], absolute: false),
            'documentUploadUrl' => route('portal.documents.store', absolute: false),
            'notificationsUrl' => route('notifications.index', absolute: false),
            'settingsUrl' => route('profile.edit', absolute: false),
            'screenShare' => $this->screenSharePayload($user, $profile),
            'coBrowse' => $this->coBrowsePayload($user, $profile),
            'surveys' => $profile ? $this->surveyPayload($profile) : [
                'total_open' => 0,
                'index_url' => route('portal.entrepreneur.surveys.index', absolute: false),
                'items' => [],
            ],
            'outcomeFollowUps' => $profile ? $this->outcomeFollowUpPayload($profile) : [
                'total_open' => 0,
                'items' => [],
            ],
            'gamification' => $profile
                ? [
                    ...$this->gamification->payload($profile, $latestPlan instanceof BusinessPlan ? $latestPlan : null),
                    'seen_url' => route('portal.entrepreneur.gamification.seen', absolute: false),
                ]
                : ['enabled' => false],
            'welcomeMessage' => $profile
                ? $this->welcomeMessage->renderForEntrepreneur($profile, $user)
                : ['has_message' => false, 'html' => '', 'version' => null],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function screenSharePayload(User $user, ?EntrepreneurProfile $profile): ?array
    {
        if (
            ! $profile instanceof EntrepreneurProfile
            || $user->user_type !== User::TYPE_ENTREPRENEUR
            || (string) $profile->user_id !== (string) $user->getKey()
        ) {
            return null;
        }

        return [
            'portal_context_token' => $this->screenShareContexts->issueForEntrepreneur(
                $user,
                $profile,
                'portal.entrepreneur.dashboard',
            ),
            'connection_url' => route('portal.entrepreneur-screen-share.connections.store', absolute: false),
            'prompt_url' => route('screen-share.connections.pending-prompt', ['connection' => '__connection__'], absolute: false),
            'connection_heartbeat_url' => route('screen-share.connections.heartbeat', ['connection' => '__connection__'], absolute: false),
            'response_url' => route('portal.screen-share.sessions.response', ['session' => '__session__'], absolute: false),
            'browser_permission_url' => route('portal.screen-share.sessions.browser-permission', ['session' => '__session__'], absolute: false),
            'ice_servers_url' => route('screen-share.sessions.ice-servers', ['session' => '__session__'], absolute: false),
            'active_url' => route('screen-share.sessions.active', ['session' => '__session__'], absolute: false),
            'signal_url' => route('screen-share.sessions.signal', ['session' => '__session__'], absolute: false),
            'pending_signals_url' => route('screen-share.sessions.pending-signals', ['session' => '__session__'], absolute: false),
            'heartbeat_url' => route('screen-share.sessions.heartbeat', ['session' => '__session__'], absolute: false),
            'end_url' => route('screen-share.sessions.end', ['session' => '__session__'], absolute: false),
            'heartbeat_seconds' => max(5, (int) config('screen-share.heartbeat_interval_seconds', 10)),
            'warning_at_minutes' => max(1, (int) config('screen-share.warning_at_minutes', 25)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function coBrowsePayload(User $user, ?EntrepreneurProfile $profile): ?array
    {
        if (
            ! (bool) config('co-browse.enabled')
            || ! $profile instanceof EntrepreneurProfile
            || $user->user_type !== User::TYPE_ENTREPRENEUR
            || (string) $profile->user_id !== (string) $user->getKey()
        ) {
            return null;
        }

        return [
            'portal_context_token' => $this->screenShareContexts->issueForEntrepreneur(
                $user,
                $profile,
                'portal.entrepreneur.dashboard',
            ),
            'connection_url' => route('portal.co-browse.connections.store', absolute: false),
            'prompt_url' => route('co-browse.connections.pending-prompt', ['connection' => '__connection__'], absolute: false),
            'connection_heartbeat_url' => route('co-browse.connections.heartbeat', ['connection' => '__connection__'], absolute: false),
            'response_url' => route('portal.co-browse.sessions.response', ['session' => '__session__'], absolute: false),
            'pending_actions_url' => route('co-browse.sessions.pending-actions', ['session' => '__session__'], absolute: false),
            'status_url' => route('co-browse.sessions.status', ['session' => '__session__'], absolute: false),
            'heartbeat_url' => route('co-browse.sessions.heartbeat', ['session' => '__session__'], absolute: false),
            'end_url' => route('co-browse.sessions.end', ['session' => '__session__'], absolute: false),
            'heartbeat_seconds' => max(5, (int) config('co-browse.heartbeat_interval_seconds', 10)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function inspirationBoardPayload(): ?array
    {
        $featured = $this->inspirationBoard->featured();

        return $featured instanceof BoardPost
            ? $this->inspirationBoard->portalPayload($featured)
            : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestDocuments(EntrepreneurProfile $profile): array
    {
        return Document::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'original_filename' => $document->original_filename,
                'category' => $document->category,
                'scanner_result' => $document->scanner_result,
                'uploaded_at' => $document->created_at?->toIso8601String(),
                'url' => route('portal.documents.show', $document, absolute: false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function messageSummary(EntrepreneurProfile $profile, User $user): array
    {
        $threadIds = MessageThread::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->pluck('id');

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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function surveyPayload(EntrepreneurProfile $profile): array
    {
        $assignments = SurveyAssignment::query()
            ->with('survey')
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->whereIn('status', SurveyAssignmentStatus::activeValues())
            ->latest('activated_at')
            ->get();

        return [
            'total_open' => $assignments->count(),
            'index_url' => route('portal.entrepreneur.surveys.index', absolute: false),
            'items' => $assignments
                ->take(3)
                ->map(fn (SurveyAssignment $assignment): array => [
                    'id' => $assignment->id,
                    'survey_title' => $assignment->survey?->title ?? 'Client experience survey',
                    'status' => $assignment->status?->value,
                    'due_at' => $assignment->due_at?->toIso8601String(),
                    'url' => route('portal.entrepreneur.surveys.show', $assignment, absolute: false),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outcomeFollowUpPayload(EntrepreneurProfile $profile): array
    {
        $followUps = OutcomeFollowUp::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('status', OutcomeFollowUp::STATUS_PENDING)
            ->oldest('due_at')
            ->get();

        return [
            'total_open' => $followUps->count(),
            'items' => $followUps
                ->take(3)
                ->map(fn (OutcomeFollowUp $followUp): array => [
                    'id' => $followUp->id,
                    'subject_type' => $followUp->subject_type,
                    'subject_label' => 'Idea outcome',
                    'subject_name' => $profile->name,
                    'cadence_month' => $followUp->cadence_month,
                    'due_at' => $followUp->due_at?->toIso8601String(),
                    'url' => route('portal.outcome-follow-ups.show', $followUp, absolute: false),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function advisoryReadinessSignalPayload(AdvisoryReadinessSignal $signal): array
    {
        $assessment = $signal->planAssessment;
        $assessmentPayload = $assessment ? $this->assessmentPayload($assessment) : null;

        return [
            'score' => $signal->score,
            'surfaced_at' => $signal->surfaced_at?->toIso8601String(),
            'threshold' => $assessmentPayload['threshold'] ?? null,
            'grade' => $assessmentPayload['overall_grade'] ?? null,
            'explanation' => $assessmentPayload['explanation'] ?? 'This score reflects the current evidence-backed advisory readiness signal.',
            'assessment_url' => $assessment
                ? route('portal.entrepreneur.assessments.show', $assessment, absolute: false)
                : null,
            'criteria' => $assessmentPayload['criteria'] ?? [],
        ];
    }

    private function activeEntrepreneurActivationForUser(User $user): ?ServiceActivation
    {
        $clientIds = $user->accessibleClientIds();

        if ($clientIds === []) {
            return null;
        }

        return ServiceActivation::query()
            ->whereIn('client_id', $clientIds)
            ->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR)
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->whereNotNull('related_entrepreneur_profile_id')
            ->latest()
            ->first();
    }
}
