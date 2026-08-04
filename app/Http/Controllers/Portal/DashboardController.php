<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\ReportType;
use App\Enums\SurveyAssignmentStatus;
use App\Http\Controllers\Concerns\BuildsMessagePayloads;
use App\Http\Controllers\Controller;
use App\Models\BoardPost;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\ClientFunderRecord;
use App\Models\DdEngagement;
use App\Models\DdIntegrationPlanItem;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\MessageThread;
use App\Models\NpoEngagement;
use App\Models\NpoValueCalculation;
use App\Models\OutcomeFollowUp;
use App\Models\PostAcquisitionMigration;
use App\Models\Proposal;
use App\Models\QuestionnaireResponse;
use App\Models\Report;
use App\Models\Scenario;
use App\Models\ServiceActivation;
use App\Models\SurveyAssignment;
use App\Models\User;
use App\Models\WellbeingCheckin;
use App\Services\Board\InspirationBoard;
use App\Services\Budgets\StrategicBudgetService;
use App\Services\Dashboards\BusinessHealthRadarBuilder;
use App\Services\DataQuality\DataQualityScorer;
use App\Services\Dd\DataRoom;
use App\Services\Fees\ProposalPricingTerms;
use App\Services\Goals\GoalTracker;
use App\Services\Notifications\NotificationCenter;
use App\Services\Npo\NpoFunderMonitor;
use App\Services\Npo\NpoHealthScorer;
use App\Services\Npo\NpoImpactMetricRecorder;
use App\Services\Portal\ClientPortalResolver;
use App\Services\Portal\OnboardingWizard;
use App\Services\Portal\Welcome\WelcomeMessageRenderer;
use App\Services\Proposals\ProposalBrief;
use App\Services\ScreenShare\ClientPortalContextTokens;
use App\Services\ServiceActivations\ServiceActivationNavigation;
use App\Services\StandardAdvisory\StandardAdvisoryWorkflow;
use App\Services\StrategicPlans\StrategicPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    use BuildsMessagePayloads;

    public function __construct(
        private readonly ClientPortalResolver $clients,
        private readonly OnboardingWizard $wizard,
        private readonly DataQualityScorer $dataQuality,
        private readonly NotificationCenter $notifications,
        private readonly GoalTracker $goals,
        private readonly BusinessHealthRadarBuilder $businessHealth,
        private readonly NpoHealthScorer $npoHealth,
        private readonly NpoFunderMonitor $npoFunding,
        private readonly NpoImpactMetricRecorder $npoImpactMetrics,
        private readonly StandardAdvisoryWorkflow $standardAdvisory,
        private readonly WelcomeMessageRenderer $welcomeMessage,
        private readonly InspirationBoard $inspirationBoard,
        private readonly ServiceActivationNavigation $serviceActivationNavigation,
        private readonly StrategicBudgetService $strategicBudgets,
        private readonly StrategicPlanService $strategicPlans,
        private readonly ProposalBrief $proposalBriefs,
        private readonly ProposalPricingTerms $pricing,
        private readonly ClientPortalContextTokens $screenShareContexts,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 403);

        if ($viewer->user_type === User::TYPE_ENTREPRENEUR) {
            return to_route('portal.entrepreneur.dashboard');
        }

        if ($viewer->user_type === User::TYPE_NPO_BOARD_MEMBER) {
            return to_route('portal.npo-board.dashboard');
        }

        if (in_array($viewer->user_type, [
            User::TYPE_SUPER_ADMIN,
            User::TYPE_ADVISOR,
            User::TYPE_JUNIOR_ADVISOR,
            User::TYPE_ENTREPRENEUR_MENTOR,
            User::TYPE_BROKER,
            User::TYPE_COACH,
        ], true)) {
            return to_route('dashboard');
        }

        $client = $this->clients->resolveFor($request);
        $ddEngagement = $this->currentDdEngagement($client);
        $npoEngagement = $this->currentNpoEngagement($client);
        $postAcquisition = $this->currentPostAcquisitionMigration($client);
        $ddPlan = $ddEngagement instanceof DdEngagement ? $this->latestDdPlan($ddEngagement) : null;
        $strategicBudget = $this->strategicBudgets->ensureForClient($client, $ddPlan);
        $goals = $npoEngagement instanceof NpoEngagement
            ? $this->goals->dashboardForEngagement($client, $npoEngagement)
            : $this->goals->dashboard($client);
        $progress = $this->wizard->progress($client);
        $currentStep = $this->wizard->currentStepSlug($client);
        $onboardingUrl = route('portal.onboarding.step', ['step' => $currentStep]);
        $npoPortal = $npoEngagement instanceof NpoEngagement ? $this->npoPortalPayload($client, $npoEngagement, $goals) : null;
        $ddPlanPayload = $ddEngagement instanceof DdEngagement ? $this->ddPlanPayload($ddEngagement) : null;
        $postAcquisitionPayload = $postAcquisition instanceof PostAcquisitionMigration ? $this->postAcquisitionPayload($postAcquisition) : null;
        $serviceActivations = $this->serviceActivationNavigation->payload($client);
        $standardAdvisory = $this->standardAdvisory->portalSummary($client);
        $documents = $this->documentPayload($client, $npoEngagement);
        $reports = $this->reportPayload($client, $npoEngagement);
        $surveys = $this->surveyPayload($client);
        $outcomeFollowUps = $this->outcomeFollowUpPayload($client);

        return Inertia::render('portal/Dashboard', [
            'client' => $this->clientPayload($client),
            'screenShare' => $request->query('client') === (string) $client->getKey()
                ? [
                    'portal_context_token' => $this->screenShareContexts->issue($viewer, $client, 'portal.dashboard'),
                    'connection_url' => route('portal.screen-share.connections.store', absolute: false),
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
                    'warning_at_minutes' => max(0, (int) config('screen-share.warning_at_minutes', 25)),
                ]
                : null,
            'coBrowse' => (bool) config('co-browse.enabled') && $request->query('client') === (string) $client->getKey()
                ? [
                    'portal_context_token' => $this->screenShareContexts->issue($viewer, $client, 'portal.dashboard'),
                    'connection_url' => route('portal.co-browse.connections.store', absolute: false),
                    'prompt_url' => route('co-browse.connections.pending-prompt', ['connection' => '__connection__'], absolute: false),
                    'connection_heartbeat_url' => route('co-browse.connections.heartbeat', ['connection' => '__connection__'], absolute: false),
                    'response_url' => route('portal.co-browse.sessions.response', ['session' => '__session__'], absolute: false),
                    'pending_actions_url' => route('co-browse.sessions.pending-actions', ['session' => '__session__'], absolute: false),
                    'status_url' => route('co-browse.sessions.status', ['session' => '__session__'], absolute: false),
                    'heartbeat_url' => route('co-browse.sessions.heartbeat', ['session' => '__session__'], absolute: false),
                    'end_url' => route('co-browse.sessions.end', ['session' => '__session__'], absolute: false),
                    'heartbeat_seconds' => max(5, (int) config('co-browse.heartbeat_interval_seconds', 10)),
                ]
                : null,
            'progress' => $progress,
            'currentStep' => $currentStep,
            'welcomeMessage' => $this->welcomeMessage->renderForClient(
                $client,
                $viewer,
            ),
            'inspirationBoard' => $this->inspirationBoardPayload(),
            'onboardingUrl' => $onboardingUrl,
            // notificationSummary is shared globally (full shape) by HandleInertiaRequests;
            // do not override it here with counts-only or the bell popover (which reads
            // summary.latest) crashes the page.
            'wellbeing' => $this->wellbeingPayload($client, $request->user()),
            'businessHealth' => $this->businessHealth->portalPayload($client),
            'healthFindings' => $this->businessHealth->healthFindingsPayload($client),
            'npoHealth' => $npoEngagement instanceof NpoEngagement ? $this->npoHealth->summary($npoEngagement) : null,
            'npoPortal' => $npoPortal,
            'ddPlan' => $ddPlanPayload,
            'postAcquisition' => $postAcquisitionPayload,
            'serviceActivations' => $serviceActivations,
            'serviceJourney' => $this->serviceJourneyPayload(
                client: $client,
                progress: $progress,
                serviceActivations: $serviceActivations,
                standardAdvisory: $standardAdvisory,
                ddPlan: $ddPlanPayload,
                postAcquisition: $postAcquisitionPayload,
                npoPortal: $npoPortal,
                documents: $documents,
                reports: $reports,
                surveys: $surveys,
                outcomeFollowUps: $outcomeFollowUps,
                onboardingUrl: $onboardingUrl,
            ),
            'strategicBudget' => $this->strategicBudgets->portalPayload($strategicBudget),
            'strategicPlan' => $this->strategicPlans->portalPayload($client),
            'standardAdvisory' => $standardAdvisory,
            'goals' => $goals,
            'documents' => $documents,
            'documentUploadUrl' => route('portal.documents.store', absolute: false),
            'npoImpactMetricStoreUrl' => $npoEngagement instanceof NpoEngagement ? route('portal.npo-impact-metrics.store', absolute: false) : null,
            'scenarios' => $this->scenarioPayload($client),
            'proposals' => $this->proposalPayload($client),
            'reports' => $reports,
            'messageSummary' => $this->messageSummary($client, $viewer),
            'messagesUrl' => route('portal.messages.index', absolute: false),
            'surveys' => $surveys,
            'outcomeFollowUps' => $outcomeFollowUps,
        ]);
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
     * @return array<string, mixed>
     */
    private function clientPayload(Client $client): array
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::from((string) $client->engagement_type);
        $dataQuality = $this->dataQuality->score($client);

        return [
            'id' => $client->id,
            'legal_name' => $client->legal_name,
            'trading_name' => $client->trading_name,
            'engagement_type' => $engagementType->value,
            'engagement_type_label' => $engagementType->label(),
            'data_quality' => $dataQuality->level,
            'data_quality_summary' => $dataQuality->toPayload(),
            'nzbn' => $client->nzbn,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documentPayload(Client $client, ?NpoEngagement $engagement = null): array
    {
        return Document::query()
            ->visibleToClients()
            ->where('client_id', $client->getKey())
            ->when($engagement instanceof NpoEngagement, fn ($query) => $query->where(function ($scope) use ($engagement): void {
                $scope->whereNull('npo_engagement_id')
                    ->orWhere('npo_engagement_id', $engagement->getKey());
            }))
            ->with('verifications')
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'original_filename' => $document->original_filename,
                'category' => $document->category,
                'uploaded_at' => $document->created_at?->toIso8601String(),
                'url' => route('portal.documents.show', $document, absolute: false),
                'verification_state' => $this->documentVerificationState($document),
                'client_explanation' => $this->documentClientExplanation($document),
                'verifications' => $document->verifications
                    ->map(fn (DocumentVerification $verification): array => [
                        'id' => $verification->id,
                        'outcome' => $verification->outcome,
                        'claim_text' => $verification->claim_text,
                        'client_explanation' => $verification->clientFacingExplanation(),
                        'resolved_at' => $verification->resolved_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $progress
     * @param  array<string, mixed>  $serviceActivations
     * @param  array<string, mixed>|null  $standardAdvisory
     * @param  array<string, mixed>|null  $ddPlan
     * @param  array<string, mixed>|null  $postAcquisition
     * @param  array<string, mixed>|null  $npoPortal
     * @param  array<int, array<string, mixed>>  $documents
     * @param  array<int, array<string, mixed>>  $reports
     * @param  array<string, mixed>  $surveys
     * @param  array<string, mixed>  $outcomeFollowUps
     * @return array<string, mixed>
     */
    private function serviceJourneyPayload(
        Client $client,
        array $progress,
        array $serviceActivations,
        ?array $standardAdvisory,
        ?array $ddPlan,
        ?array $postAcquisition,
        ?array $npoPortal,
        array $documents,
        array $reports,
        array $surveys,
        array $outcomeFollowUps,
        string $onboardingUrl,
    ): array {
        $openActivation = collect((array) ($serviceActivations['items'] ?? []))
            ->first(fn (array $activation): bool => ! in_array((string) ($activation['status'] ?? ''), [
                ServiceActivation::STATUS_CANCELLED,
                ServiceActivation::STATUS_CLOSED,
                ServiceActivation::STATUS_REJECTED,
            ], true));
        $primary = is_array($openActivation)
            ? $this->activationJourneyPrimary($openActivation)
            : $this->engagementJourneyPrimary($client, $standardAdvisory, $ddPlan, $postAcquisition, $npoPortal, $onboardingUrl);

        $documentCount = count($documents);
        $reportCount = count($reports);
        $documentReviewCount = collect($documents)
            ->filter(fn (array $document): bool => in_array((string) ($document['verification_state'] ?? ''), [
                DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY,
                DocumentVerification::OUTCOME_ADVISORY_FLAG,
                DocumentVerification::OUTCOME_VERIFICATION_ERROR,
                DocumentVerification::OUTCOME_PENDING,
            ], true))
            ->count();
        $followUpCount = (int) ($surveys['total_open'] ?? 0) + (int) ($outcomeFollowUps['total_open'] ?? 0);
        $scopeComplete = (int) ($progress['percentage'] ?? 0) >= 100
            || (is_array($openActivation) && in_array((string) ($openActivation['status'] ?? ''), [
                ServiceActivation::STATUS_PACKAGE_SELECTED,
                ServiceActivation::STATUS_ACTIVE,
                ServiceActivation::STATUS_CLOSED,
            ], true))
            || (bool) data_get($standardAdvisory, 'questionnaire_submitted', false)
            || (bool) data_get($postAcquisition, 'gap_questionnaire.submitted', false)
            || (bool) data_get($npoPortal, 'questionnaire_completion.completed', false);
        $evidenceComplete = $documentCount > 0
            || (int) data_get($standardAdvisory, 'document_count', 0) > 0
            || (int) data_get($ddPlan, 'data_room_item_count', 0) > 0
            || (int) data_get($postAcquisition, 'migrated_document_count', 0) > 0;
        $analysisComplete = data_get($standardAdvisory, 'latest_report_generated_at') !== null
            || (bool) data_get($ddPlan, 'generated', false)
            || (bool) data_get($postAcquisition, 'gap_questionnaire.submitted', false)
            || (int) data_get($npoPortal, 'milestone_progress.total', 0) > 0;
        $outputsComplete = $reportCount > 0
            || data_get($standardAdvisory, 'client_report') !== null
            || (bool) data_get($ddPlan, 'plan_completed', false);

        return [
            'primary' => $primary,
            'message_url' => route('portal.messages.index', absolute: false),
            'stages' => [
                $this->journeyStage(
                    key: 'scope',
                    label: 'Scope agreed',
                    description: 'The service path, package, fee, and starting brief are clear enough for the next step.',
                    complete: $scopeComplete,
                    active: ! $scopeComplete,
                    owner: 'client',
                ),
                $this->journeyStage(
                    key: 'evidence',
                    label: 'Evidence shared',
                    description: 'FSA has the documents, questionnaire answers, or workspace inputs needed to assess the work.',
                    complete: $evidenceComplete,
                    active: $scopeComplete && ! $evidenceComplete,
                    owner: 'client',
                ),
                $this->journeyStage(
                    key: 'advisor_review',
                    label: 'FSA review',
                    description: 'Advisor analysis, verification, prioritisation, and reasoning are being completed.',
                    complete: $analysisComplete,
                    active: $evidenceComplete && ! $analysisComplete,
                    owner: 'fsa',
                ),
                $this->journeyStage(
                    key: 'outputs',
                    label: 'Outputs released',
                    description: 'Reports, plans, milestones, or workspace deliverables are ready for client review.',
                    complete: $outputsComplete,
                    active: $analysisComplete && ! $outputsComplete,
                    owner: 'fsa',
                ),
                $this->journeyStage(
                    key: 'outcomes',
                    label: 'Feedback and outcomes',
                    description: 'Client feedback and follow-up outcomes confirm whether the work met expectations.',
                    complete: $outputsComplete && $followUpCount === 0,
                    active: $outputsComplete && $followUpCount > 0,
                    owner: 'client',
                ),
            ],
            'metrics' => [
                [
                    'label' => 'Evidence',
                    'value' => $this->countLabel($documentCount, 'document', 'documents'),
                    'detail' => $documentReviewCount > 0
                        ? $this->countLabel($documentReviewCount, 'item needs verification review', 'items need verification review')
                        : 'No open verification flags in the client-visible document set.',
                ],
                [
                    'label' => 'Outputs',
                    'value' => $this->countLabel($reportCount, 'released report', 'released reports'),
                    'detail' => $outputsComplete
                        ? 'Client-visible deliverables are available in the portal.'
                        : 'FSA will release outputs once review is complete.',
                ],
                [
                    'label' => 'Feedback',
                    'value' => $followUpCount > 0
                        ? $this->countLabel($followUpCount, 'open follow-up', 'open follow-ups')
                        : 'No open follow-ups',
                    'detail' => $followUpCount > 0
                        ? 'Complete open surveys or outcome checks so FSA can measure service quality.'
                        : 'New service feedback appears here after delivery milestones.',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $activation
     * @return array<string, mixed>
     */
    private function activationJourneyPrimary(array $activation): array
    {
        $status = (string) ($activation['status'] ?? ServiceActivation::STATUS_REQUESTED);
        $serviceType = (string) ($activation['service_type'] ?? 'service');
        $statusLabel = (string) ($activation['status_label'] ?? str($status)->replace('_', ' ')->title()->toString());
        $actionUrl = (string) (($activation['workspace_url'] ?? null) ?: ($activation['url'] ?? route('portal.messages.index', absolute: false)));
        $packageLabel = $activation['package_label'] ?? null;

        if ($status === ServiceActivation::STATUS_REQUESTED) {
            return $this->journeyPrimary(
                serviceType: $serviceType,
                serviceLabel: (string) ($activation['client_label'] ?? 'Service workspace'),
                statusLabel: $statusLabel,
                owner: 'fsa',
                nextAction: 'FSA is reviewing the request and will confirm the scope, package, and next advisory step.',
                actionUrl: $actionUrl,
                actionLabel: 'Review request',
                clientNext: 'Watch for the scope and pricing update, and reply to any advisor questions.',
                fsaNext: 'Confirm the right package, fee, and workspace path from the Admin Service Rates table.',
                timeframe: 'Next advisor review cycle',
            );
        }

        if ($status === ServiceActivation::STATUS_PACKAGE_SELECTED) {
            return $this->journeyPrimary(
                serviceType: $serviceType,
                serviceLabel: (string) ($activation['client_label'] ?? 'Service workspace'),
                statusLabel: $statusLabel,
                owner: 'client',
                nextAction: $packageLabel
                    ? 'Review the selected scope and fee, then complete the required payment or acknowledgement step.'
                    : 'Review the advisor-selected scope and fee before workspace access opens.',
                actionUrl: $actionUrl,
                actionLabel: 'Review scope',
                clientNext: 'Complete the payment and fee/scope acknowledgement so the workspace can open.',
                fsaNext: 'Hold workspace access until the selected package, payment, and acknowledgement are complete.',
                timeframe: 'Actionable now',
            );
        }

        if ($status === ServiceActivation::STATUS_ACTIVE) {
            return $this->journeyPrimary(
                serviceType: $serviceType,
                serviceLabel: (string) ($activation['client_label'] ?? 'Service workspace'),
                statusLabel: $statusLabel,
                owner: 'shared',
                nextAction: 'The workspace is active. Use the workspace and messages to complete current evidence, review, and delivery steps.',
                actionUrl: $actionUrl,
                actionLabel: ($activation['workspace_url'] ?? null) !== null ? 'Open workspace' : 'Open service',
                clientNext: 'Keep the workspace inputs, evidence, and advisor messages current.',
                fsaNext: 'Review inputs, produce the agreed outputs, and keep the next step visible.',
                timeframe: 'Current workspace',
            );
        }

        return $this->journeyPrimary(
            serviceType: $serviceType,
            serviceLabel: (string) ($activation['client_label'] ?? 'Service workspace'),
            statusLabel: $statusLabel,
            owner: 'fsa',
            nextAction: 'FSA is managing the current service state and will confirm any client action through the portal.',
            actionUrl: $actionUrl,
            actionLabel: 'Open service',
            clientNext: 'Review any visible messages or requests from FSA.',
            fsaNext: 'Confirm the next service step and keep the client-facing status current.',
            timeframe: 'Next advisor update',
        );
    }

    /**
     * @param  array<string, mixed>|null  $standardAdvisory
     * @param  array<string, mixed>|null  $ddPlan
     * @param  array<string, mixed>|null  $postAcquisition
     * @param  array<string, mixed>|null  $npoPortal
     * @return array<string, mixed>
     */
    private function engagementJourneyPrimary(
        Client $client,
        ?array $standardAdvisory,
        ?array $ddPlan,
        ?array $postAcquisition,
        ?array $npoPortal,
        string $onboardingUrl,
    ): array {
        if (is_array($standardAdvisory)) {
            $nextMomentum = collect((array) data_get($standardAdvisory, 'momentum.items', []))
                ->first(fn (array $item): bool => ! in_array((string) ($item['status'] ?? ''), ['complete', 'not_required'], true));
            $owner = data_get($nextMomentum, 'owner') === 'advisor' ? 'fsa' : 'client';
            $reportUrl = data_get($standardAdvisory, 'client_report.view_url');

            return $this->journeyPrimary(
                serviceType: EngagementType::STANDARD_ADVISORY->value,
                serviceLabel: 'Standard Advisory',
                statusLabel: (string) ($standardAdvisory['status_label'] ?? 'In progress'),
                owner: $owner,
                nextAction: (string) ($standardAdvisory['next_action'] ?? data_get($nextMomentum, 'description', 'Continue the advisory journey.')),
                actionUrl: is_string($reportUrl) && $reportUrl !== '' ? $reportUrl : ($owner === 'client' ? $onboardingUrl : '#section-reports'),
                actionLabel: is_string($reportUrl) && $reportUrl !== '' ? 'View report' : ($owner === 'client' ? 'Continue' : 'View outputs'),
                clientNext: $owner === 'client'
                    ? 'Complete the next requested input so FSA can continue the advisory review.'
                    : 'Monitor outputs and messages while FSA completes the current review step.',
                fsaNext: $owner === 'fsa'
                    ? 'Complete the advisor review, reasoning, and released outputs.'
                    : 'Use the client inputs to confirm analysis readiness and next priorities.',
                timeframe: $owner === 'client' ? 'Actionable now' : 'With FSA',
            );
        }

        if (is_array($postAcquisition)) {
            $gapSubmitted = (bool) data_get($postAcquisition, 'gap_questionnaire.submitted', false);
            $proposalUrl = data_get($postAcquisition, 'proposal.signoff_url');

            return $this->journeyPrimary(
                serviceType: EngagementType::POST_ACQUISITION_ADVISORY->value,
                serviceLabel: 'Post-acquisition Advisory',
                statusLabel: $gapSubmitted ? 'Gap questionnaire submitted' : 'Gap questionnaire needed',
                owner: $gapSubmitted ? 'fsa' : 'client',
                nextAction: $gapSubmitted
                    ? 'FSA is using the DD evidence and gap questionnaire to prepare the post-close advisory plan.'
                    : 'Complete the post-acquisition gap questionnaire so FSA can shape the first-100-days plan.',
                actionUrl: is_string($proposalUrl) && $proposalUrl !== '' ? $proposalUrl : (string) ($postAcquisition['gap_questionnaire_url'] ?? $onboardingUrl),
                actionLabel: is_string($proposalUrl) && $proposalUrl !== '' ? 'Review proposal' : ($gapSubmitted ? 'View handoff' : 'Complete gap review'),
                clientNext: $gapSubmitted ? 'Review any proposal or follow-up questions FSA releases.' : 'Complete the gap questionnaire and upload any post-close evidence.',
                fsaNext: 'Turn the DD baseline and post-close gap evidence into prioritised advisory actions.',
                timeframe: $gapSubmitted ? 'With FSA' : 'Actionable now',
            );
        }

        if (is_array($ddPlan)) {
            $generated = (bool) ($ddPlan['generated'] ?? false);

            return $this->journeyPrimary(
                serviceType: EngagementType::DUE_DILIGENCE->value,
                serviceLabel: 'Due Diligence',
                statusLabel: $generated ? 'DD plan prepared' : 'DD plan not generated',
                owner: $generated ? 'shared' : 'fsa',
                nextAction: $generated
                    ? 'Use the DD workspace to review the plan, evidence, and acquisition next steps.'
                    : 'FSA is preparing the due-diligence plan from questionnaire answers and data-room evidence.',
                actionUrl: (string) ($ddPlan['url'] ?? $onboardingUrl),
                actionLabel: $generated ? 'Open plan' : 'Open DD',
                clientNext: 'Keep data-room evidence and advisor questions current.',
                fsaNext: 'Review the acquisition evidence, red flags, valuation context, and next decision point.',
                timeframe: $generated ? 'Current workspace' : 'With FSA',
            );
        }

        if (is_array($npoPortal)) {
            $questionnaireComplete = (bool) data_get($npoPortal, 'questionnaire_completion.completed', false);

            return $this->journeyPrimary(
                serviceType: EngagementType::NPO->value,
                serviceLabel: 'NPO Advisory',
                statusLabel: $questionnaireComplete ? 'NPO inputs submitted' : 'NPO inputs needed',
                owner: $questionnaireComplete ? 'fsa' : 'client',
                nextAction: $questionnaireComplete
                    ? 'FSA is reviewing NPO health, impact, funder, and governance evidence.'
                    : 'Complete the NPO questionnaire and share board, funding, or impact evidence.',
                actionUrl: $onboardingUrl,
                actionLabel: $questionnaireComplete ? 'View portal' : 'Continue inputs',
                clientNext: $questionnaireComplete ? 'Watch for advisor findings, report access, or impact metric requests.' : 'Complete NPO inputs and upload supporting evidence.',
                fsaNext: 'Connect NPO health, funder accountability, governance, and impact signals into useful advice.',
                timeframe: $questionnaireComplete ? 'With FSA' : 'Actionable now',
            );
        }

        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        return $this->journeyPrimary(
            serviceType: $engagementType?->value ?? 'service',
            serviceLabel: $engagementType?->label() ?? 'FSA service',
            statusLabel: 'Getting started',
            owner: 'client',
            nextAction: 'Complete onboarding so FSA has enough context to confirm scope, evidence needs, and the next service step.',
            actionUrl: $onboardingUrl,
            actionLabel: 'Continue onboarding',
            clientNext: 'Complete the open onboarding step and upload useful evidence.',
            fsaNext: 'Review the submitted context and confirm the service pathway.',
            timeframe: 'Actionable now',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function journeyPrimary(
        string $serviceType,
        string $serviceLabel,
        string $statusLabel,
        string $owner,
        string $nextAction,
        string $actionUrl,
        string $actionLabel,
        string $clientNext,
        string $fsaNext,
        string $timeframe,
    ): array {
        return [
            'service_type' => $serviceType,
            'service_label' => $serviceLabel,
            'status_label' => $statusLabel,
            'owner' => $owner,
            'owner_label' => $this->journeyOwnerLabel($owner),
            'next_action' => $nextAction,
            'action_url' => $actionUrl,
            'action_label' => $actionLabel,
            'client_next' => $clientNext,
            'fsa_next' => $fsaNext,
            'timeframe' => $timeframe,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function journeyStage(
        string $key,
        string $label,
        string $description,
        bool $complete,
        bool $active,
        string $owner,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'status' => $complete ? 'complete' : ($active ? 'active' : 'pending'),
            'owner' => $owner,
            'owner_label' => $this->journeyOwnerLabel($owner),
        ];
    }

    private function journeyOwnerLabel(string $owner): string
    {
        return match ($owner) {
            'client' => 'Awaiting you',
            'fsa' => 'With FSA',
            default => 'Shared',
        };
    }

    private function countLabel(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? "1 {$singular}" : "{$count} {$plural}";
    }

    /**
     * @return array<string, mixed>
     */
    private function wellbeingPayload(Client $client, mixed $user): array
    {
        $current = $user instanceof User
            ? WellbeingCheckin::query()
                ->where('client_id', $client->getKey())
                ->where('user_id', $user->getKey())
                ->whereDate('period_start', now()->startOfMonth()->toDateString())
                ->latest('submitted_at')
                ->first()
            : null;

        return [
            'prompt_due' => ! $current instanceof WellbeingCheckin,
            'period_start' => now()->startOfMonth()->toDateString(),
            'submitted_at' => $current?->submitted_at?->toIso8601String(),
            'url' => route('portal.wellbeing.show'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scenarioPayload(Client $client): array
    {
        return Scenario::query()
            ->where('client_id', $client->getKey())
            ->where('is_client_visible', true)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->sortBy('position')
            ->map(fn (Scenario $scenario): array => [
                'id' => $scenario->id,
                'name' => $scenario->name,
                'kind' => $scenario->kind,
                'pv_impact' => $scenario->pv_impact,
                'position' => $scenario->position,
                'economic_overlay' => [
                    'applied_growth_rate' => $scenario->economic_overlay['applied_growth_rate'] ?? null,
                    'discount_method' => $scenario->economic_overlay['discount_method'] ?? null,
                    'indicators' => $scenario->economic_overlay['indicators'] ?? [],
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function proposalPayload(Client $client): array
    {
        return Proposal::query()
            ->with('feeCalculation')
            ->where('client_id', $client->getKey())
            ->whereIn('status', ['released', 'awaiting_signature', 'signed'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Proposal $proposal): array => [
                'id' => $proposal->id,
                'version' => $proposal->version,
                'status' => $proposal->status->value,
                'status_label' => str($proposal->status->value)->replace('_', ' ')->title()->toString(),
                'suggested_mid' => $this->pricing->payableMid($proposal),
                'brief' => $this->proposalBriefs->for($proposal),
                'signed_at' => $proposal->signed_at?->toIso8601String(),
                'signoff_url' => route('portal.proposals.signoff.show', $proposal, absolute: false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reportPayload(Client $client, ?NpoEngagement $engagement = null): array
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        return Report::query()
            ->where('client_id', $client->getKey())
            ->when(
                $engagement instanceof NpoEngagement,
                fn ($query) => $query->where(function ($scope) use ($engagement): void {
                    $scope->where('type', ReportType::Client->value)
                        ->orWhere(function ($npoReports) use ($engagement): void {
                            $npoReports
                                ->where('npo_engagement_id', $engagement->getKey())
                                ->where(function ($reviewedScope): void {
                                    $reviewedScope
                                        ->where(function ($clientReady): void {
                                            $clientReady
                                                ->whereIn('type', [
                                                    ReportType::GovernanceReview->value,
                                                    ReportType::NpoHealth->value,
                                                    ReportType::SocialEnterpriseDual->value,
                                                ])
                                                ->whereIn('review_status', ['not_required', 'reviewed']);
                                        })
                                        ->orWhere(function ($reviewedReports): void {
                                            $reviewedReports
                                                ->whereIn('type', [
                                                    ReportType::FunderAccountability->value,
                                                    ReportType::ImpactSummary->value,
                                                ])
                                                ->where('review_status', 'reviewed');
                                        });
                                });
                        });
                }),
                fn ($query) => $query->where(function ($scope) use ($engagementType): void {
                    $scope
                        ->where('type', ReportType::Client->value)
                        ->whereIn('review_status', ['not_required', 'reviewed']);

                    if ($engagementType === EngagementType::STANDARD_ADVISORY) {
                        $scope->orWhere(function ($standardAdvisory): void {
                            $standardAdvisory
                                ->whereIn('type', [
                                    ReportType::Valuation->value,
                                    ReportType::SuccessionValueGap->value,
                                ])
                                ->where('review_status', 'reviewed');
                        });
                    }

                    if ($engagementType === EngagementType::POST_ACQUISITION_ADVISORY) {
                        $scope->orWhere(function ($postAcquisition): void {
                            $postAcquisition
                                ->where('type', ReportType::PostAcquisitionGap->value)
                                ->whereIn('review_status', ['not_required', 'reviewed']);
                        });
                    }

                    if ($engagementType === EngagementType::DUE_DILIGENCE) {
                        $scope->orWhere(function ($dueDiligence): void {
                            $dueDiligence
                                ->whereIn('type', [
                                    ReportType::DueDiligence->value,
                                    ReportType::AcquisitionGoNoGo->value,
                                ])
                                ->whereIn('review_status', ['not_required', 'reviewed']);
                        });
                    }
                }),
            )
            ->latest('generated_at')
            ->limit(5)
            ->get()
            ->map(function (Report $report): array {
                $url = route('portal.reports.show', $report, absolute: false);

                return [
                    'id' => $report->id,
                    'title' => $report->title,
                    'type' => $report->type->value,
                    'generated_at' => $report->generated_at?->toIso8601String(),
                    'view_url' => $url,
                    'download_url' => $url,
                ];
            })
            ->values()
            ->all();
    }

    private function currentNpoEngagement(Client $client): ?NpoEngagement
    {
        return NpoEngagement::query()
            ->where('client_id', $client->getKey())
            ->whereIn('sub_type', [
                NpoEngagementSubType::StandardNpo->value,
                NpoEngagementSubType::SocialEnterprise->value,
            ])
            ->latest()
            ->first();
    }

    private function currentDdEngagement(Client $client): ?DdEngagement
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        if ($engagementType !== EngagementType::DUE_DILIGENCE) {
            $activation = ServiceActivation::query()
                ->where('client_id', $client->getKey())
                ->where('service_type', ServiceActivation::SERVICE_DUE_DILIGENCE)
                ->where('status', ServiceActivation::STATUS_ACTIVE)
                ->whereNotNull('related_dd_engagement_id')
                ->latest()
                ->first();

            if (! $activation instanceof ServiceActivation) {
                return null;
            }

            return DdEngagement::query()
                ->whereKey($activation->related_dd_engagement_id)
                ->first();
        }

        return DdEngagement::query()
            ->where('client_id', $client->getKey())
            ->latest()
            ->first();
    }

    private function currentPostAcquisitionMigration(Client $client): ?PostAcquisitionMigration
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        if ($engagementType !== EngagementType::POST_ACQUISITION_ADVISORY) {
            return null;
        }

        return PostAcquisitionMigration::query()
            ->where('advisory_client_id', $client->getKey())
            ->with([
                'ddReport',
                'engagement',
                'gapQuestionnaireResponse.answers',
                'gapQuestionnaireResponse.questionnaire.sections.questions',
                'proposal.feeCalculation',
            ])
            ->latest('migrated_at')
            ->latest()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function postAcquisitionPayload(PostAcquisitionMigration $migration): array
    {
        $response = $migration->gapQuestionnaireResponse;
        $questions = collect($response?->questionnaire?->sections ?? [])
            ->flatMap(fn ($section) => $section->questions)
            ->values();
        $totalQuestions = $questions?->count() ?? 0;
        $answeredQuestions = $response?->answers?->count() ?? 0;
        $remainingQuestionIds = data_get($migration->metadata, 'gap_questions_remaining');
        $submitted = $response instanceof QuestionnaireResponse && $response->submitted_at !== null;
        $remainingQuestions = $submitted
            ? 0
            : (is_array($remainingQuestionIds) ? count($remainingQuestionIds) : max(0, $totalQuestions - $answeredQuestions));
        $proposal = $migration->proposal;
        $proposalStatus = $proposal instanceof Proposal
            ? (is_string($proposal->status) ? $proposal->status : $proposal->status->value)
            : null;
        $proposalClientVisible = $proposal instanceof Proposal && in_array($proposalStatus, [
            'released',
            'awaiting_signature',
            'signed',
        ], true);

        return [
            'source_client_id' => $migration->buyer_client_id,
            'advisory_client_id' => $migration->advisory_client_id,
            'source_target_name' => $migration->engagement?->target_name,
            'dd_pv_baseline' => $migration->dd_pv_baseline,
            'migrated_at' => $migration->migrated_at?->toIso8601String(),
            'migrated_document_count' => count(is_array($migration->migrated_document_ids) ? $migration->migrated_document_ids : []),
            'gap_questionnaire_url' => route('portal.onboarding.step', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE], absolute: false),
            'gap_questionnaire' => [
                'submitted' => $submitted,
                'submitted_at' => $response?->submitted_at?->toIso8601String(),
                'answered_questions' => $answeredQuestions,
                'total_questions' => $totalQuestions,
                'remaining_questions' => $remainingQuestions,
            ],
            'proposal' => $proposal instanceof Proposal ? [
                'id' => $proposal->id,
                'status' => $proposalStatus,
                'status_label' => str((string) $proposalStatus)->replace('_', ' ')->title()->toString(),
                'suggested_mid' => $this->pricing->payableMid($proposal),
                'client_visible' => $proposalClientVisible,
                'signoff_url' => $proposalClientVisible ? route('portal.proposals.signoff.show', $proposal, absolute: false) : null,
            ] : null,
            'dd_report' => $migration->ddReport instanceof Report ? [
                'id' => $migration->ddReport->id,
                'title' => $migration->ddReport->title,
                'generated_at' => $migration->ddReport->generated_at?->toIso8601String(),
            ] : null,
            'integration_actions' => $this->postAcquisitionIntegrationActions($migration),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function postAcquisitionIntegrationActions(PostAcquisitionMigration $migration): array
    {
        return DdIntegrationPlanItem::query()
            ->where('dd_engagement_id', $migration->dd_engagement_id)
            ->orderBy('day')
            ->limit(8)
            ->get()
            ->map(fn (DdIntegrationPlanItem $item): array => [
                'id' => $item->id,
                'day' => $item->day,
                'phase' => $item->phase,
                'action' => $item->action,
                'owner' => $item->owner,
                'priority' => $item->priority,
                'status' => $item->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function ddPlanPayload(DdEngagement $engagement): array
    {
        $plan = $this->latestDdPlan($engagement);

        return [
            'url' => route('portal.dd-plan.show', absolute: false),
            'generated' => $plan instanceof BusinessPlan,
            'status' => $plan?->status,
            'plan_completed' => $plan instanceof BusinessPlan && $plan->status === BusinessPlan::STATUS_FOUNDING,
            'business_advice_requested' => PostAcquisitionMigration::query()
                ->where('dd_engagement_id', $engagement->getKey())
                ->exists(),
            'updated_at' => $plan?->updated_at?->toIso8601String(),
            'target_name' => $engagement->target_name,
            'data_room_item_count' => $engagement->dataRoomItems()->count(),
            'workstream_options' => collect(DataRoom::WORKSTREAMS)
                ->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values()
                ->all(),
        ];
    }

    private function latestDdPlan(DdEngagement $engagement): ?BusinessPlan
    {
        return BusinessPlan::query()
            ->where('dd_engagement_id', $engagement->getKey())
            ->where('source_type', BusinessPlan::SOURCE_DUE_DILIGENCE)
            ->latest()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $goals
     * @return array<string, mixed>
     */
    private function npoPortalPayload(Client $client, NpoEngagement $engagement, array $goals): array
    {
        $funding = $this->npoFunding->clientSummary($client, $engagement) ?? $this->emptyFundingPayload();
        $milestones = collect($goals['goals'] ?? [])
            ->flatMap(fn (array $goal): array => (array) ($goal['milestones'] ?? []))
            ->values();
        $completed = $milestones->where('status', 'completed')->count();
        $total = $milestones->count();
        $questionnaire = QuestionnaireResponse::query()
            ->where('client_id', $client->getKey())
            ->where('npo_engagement_id', $engagement->getKey())
            ->latest('submitted_at')
            ->latest()
            ->first();

        return [
            'engagement_id' => $engagement->id,
            'sub_type' => $engagement->sub_type?->value,
            'legal_structure' => $engagement->legal_structure?->value,
            'funding' => $funding,
            'milestone_progress' => [
                'completed' => $completed,
                'total' => $total,
                'percentage' => $total > 0 ? (int) round($completed / $total * 100) : 0,
                'cost_per_beneficiary' => $this->costPerBeneficiaryPayload($engagement),
            ],
            'accountability_reports_due' => $this->accountabilityReportsDue($engagement),
            'impact_metrics' => $this->npoImpactMetrics->payloads($this->npoImpactMetrics->latest($engagement)),
            'questionnaire_completion' => [
                'completed' => $questionnaire instanceof QuestionnaireResponse && $questionnaire->submitted_at !== null,
                'submitted_at' => $questionnaire?->submitted_at?->toIso8601String(),
                'answered_questions' => $questionnaire instanceof QuestionnaireResponse ? $questionnaire->answers()->count() : 0,
            ],
        ];
    }

    /**
     * @return array{threads_count:int, unread_count:int, latest_url:string}
     */
    private function messageSummary(Client $client, User $viewer): array
    {
        $threads = $this->clientMessageThreads($client);
        $latestThread = $threads->first();

        return [
            'threads_count' => $threads->count(),
            'unread_count' => (int) $threads->sum(fn (MessageThread $thread): int => $this->unreadCount($thread, $viewer)),
            'latest_url' => $latestThread instanceof MessageThread
                ? route('portal.messages.show', $latestThread, absolute: false)
                : route('portal.messages.index', absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function surveyPayload(Client $client): array
    {
        $assignments = SurveyAssignment::query()
            ->with('survey')
            ->where('client_id', $client->getKey())
            ->whereIn('status', SurveyAssignmentStatus::activeValues())
            ->latest('activated_at')
            ->get();

        return [
            'total_open' => $assignments->count(),
            'index_url' => route('portal.surveys.index', absolute: false),
            'items' => $assignments
                ->take(3)
                ->map(fn (SurveyAssignment $assignment): array => [
                    'id' => $assignment->id,
                    'survey_title' => $assignment->survey?->title ?? 'Client experience survey',
                    'status' => $assignment->status?->value,
                    'due_at' => $assignment->due_at?->toIso8601String(),
                    'url' => route('portal.surveys.show', $assignment, absolute: false),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outcomeFollowUpPayload(Client $client): array
    {
        $followUps = OutcomeFollowUp::query()
            ->with(['entrepreneurProfile', 'ddEngagement'])
            ->where('client_id', $client->getKey())
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
                    'subject_label' => $followUp->subject_type === OutcomeFollowUp::SUBJECT_DUE_DILIGENCE
                        ? 'Buying outcome'
                        : 'Idea outcome',
                    'subject_name' => $followUp->subject_type === OutcomeFollowUp::SUBJECT_DUE_DILIGENCE
                        ? ($followUp->ddEngagement?->target_name ?? 'Acquisition follow-up')
                        : ($followUp->entrepreneurProfile?->name ?? 'Idea follow-up'),
                    'cadence_month' => $followUp->cadence_month,
                    'due_at' => $followUp->due_at?->toIso8601String(),
                    'url' => route('portal.outcome-follow-ups.show', $followUp, absolute: false),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function costPerBeneficiaryPayload(NpoEngagement $engagement): ?array
    {
        $calculation = NpoValueCalculation::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->where('type', NpoValueCalculation::TYPE_COST_PER_BENEFICIARY)
            ->orderByDesc('calculated_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $calculation instanceof NpoValueCalculation) {
            return null;
        }

        return [
            'id' => $calculation->id,
            'cost_per_beneficiary' => $calculation->result['cost_per_beneficiary'] ?? null,
            'benchmark_cost_per_beneficiary' => $calculation->result['benchmark_cost_per_beneficiary'] ?? null,
            'additional_beneficiaries_mid' => $calculation->result['improvement']['additional_beneficiaries_mid'] ?? null,
            'benchmark_note' => $calculation->result['benchmark_note'] ?? null,
            'rating' => $calculation->rating,
            'calculated_at' => $calculation->calculated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accountabilityReportsDue(NpoEngagement $engagement): array
    {
        return ClientFunderRecord::query()
            ->with('funder')
            ->where('npo_engagement_id', $engagement->getKey())
            ->whereNotNull('reporting_deadline')
            ->where('reporting_deadline', '<=', now()->addDays(60)->toDateString())
            ->orderBy('reporting_deadline')
            ->limit(6)
            ->get()
            ->map(fn (ClientFunderRecord $record): array => [
                'id' => $record->id,
                'funder_name' => $record->funder?->name,
                'grant_name' => $record->grant_name,
                'reporting_deadline' => $record->reporting_deadline?->toDateString(),
                'grant_amount' => (float) $record->grant_amount,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyFundingPayload(): array
    {
        return [
            'summary' => [
                'active_records' => 0,
                'active_amount' => 0.0,
                'due_60_count' => 0,
                'expiry_alerts_count' => 0,
            ],
            'records' => [],
            'alerts' => [],
            'concentration' => [
                'total_active_amount' => 0.0,
                'largest_funder_amount' => 0.0,
                'largest_funder_ratio' => 0.0,
                'largest_funder_name' => null,
                'risk_level' => 'low',
                'source' => 'client_funder_records',
            ],
            'deadlines_60' => [],
        ];
    }

    private function documentVerificationState(Document $document): string
    {
        $outcomes = $document->verifications->pluck('outcome')->all();

        foreach ([
            DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY,
            DocumentVerification::OUTCOME_ADVISORY_FLAG,
            DocumentVerification::OUTCOME_VERIFICATION_ERROR,
            DocumentVerification::OUTCOME_PENDING,
            DocumentVerification::OUTCOME_VERIFIED,
        ] as $outcome) {
            if (in_array($outcome, $outcomes, true)) {
                return $outcome;
            }
        }

        return DocumentVerification::OUTCOME_PENDING;
    }

    private function documentClientExplanation(Document $document): string
    {
        $verification = $document->verifications
            ->sortBy(fn (DocumentVerification $verification): int => match ($verification->outcome) {
                DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY => 0,
                DocumentVerification::OUTCOME_ADVISORY_FLAG => 1,
                DocumentVerification::OUTCOME_VERIFICATION_ERROR => 2,
                DocumentVerification::OUTCOME_PENDING => 3,
                default => 4,
            })
            ->first();

        return $verification instanceof DocumentVerification
            ? $verification->clientFacingExplanation()
            : 'Verification is in progress.';
    }
}
