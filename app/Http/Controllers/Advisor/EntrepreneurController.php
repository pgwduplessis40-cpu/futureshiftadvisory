<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\EntrepreneurStage;
use App\Enums\ReportType;
use App\Enums\SurveyAssignmentStatus;
use App\Enums\SurveyType;
use App\Http\Controllers\Controller;
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
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\AdvisorEntrepreneurCapacity;
use App\Services\Entrepreneurs\BudgetPackBuilder;
use App\Services\Entrepreneurs\BusinessPlanPreviewRenderer;
use App\Services\Entrepreneurs\CanonicalEntrepreneurWorkspace;
use App\Services\Entrepreneurs\EntrepreneurGamification;
use App\Services\Entrepreneurs\FounderChangeRequestMessage;
use App\Services\Entrepreneurs\IdeaViabilityGate;
use App\Services\Pdf\PdfRenderer;
use App\Services\ScreenShare\ScreenShareAuthorizer;
use App\Services\Security\InviteIssuer;
use App\Services\Surveys\SurveyActivationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

final class EntrepreneurController extends Controller
{
    use BuildsEntrepreneurAssessmentPayload;

    public function __construct(
        private readonly AdvisorEntrepreneurCapacity $capacity,
        private readonly AuditWriter $auditWriter,
        private readonly InviteIssuer $inviteIssuer,
        private readonly EntrepreneurGamification $gamification,
        private readonly FounderChangeRequestMessage $changeRequestMessages,
        private readonly IdeaViabilityGate $ideaViabilityGate,
        private readonly BusinessPlanPreviewRenderer $planPreview,
        private readonly BudgetPackBuilder $budgetPack,
        private readonly PdfRenderer $pdf,
        private readonly CanonicalEntrepreneurWorkspace $entrepreneurWorkspaces,
        private readonly ScreenShareAuthorizer $screenShareAuthorizer,
        private readonly SurveyActivationService $surveyActivations,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', EntrepreneurProfile::class);

        $user = $this->actor($request);

        return Inertia::render('advisor/entrepreneurs/Index', [
            'entrepreneurs' => $this->visibleProfiles($user)
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (EntrepreneurProfile $profile): array => $this->profileSummary($profile))
                ->values(),
            'capacity' => $this->capacity->summary($user),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', EntrepreneurProfile::class);

        return Inertia::render('advisor/entrepreneurs/Create', [
            'capacity' => $this->capacity->summary($this->actor($request)),
            'mode' => 'invite',
            'serviceOptions' => ServiceRatePackage::entrepreneurPackageScopeOptions(),
        ]);
    }

    public function createManual(Request $request): Response
    {
        Gate::authorize('create', EntrepreneurProfile::class);

        return Inertia::render('advisor/entrepreneurs/Create', [
            'capacity' => $this->capacity->summary($this->actor($request)),
            'mode' => 'manual',
            'serviceOptions' => ServiceRatePackage::entrepreneurPackageScopeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', EntrepreneurProfile::class);

        $advisor = $this->actor($request);
        $this->capacity->ensureCanAdd($advisor);

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('entrepreneur_profiles', 'email'),
            ],
            'concept_summary' => ['nullable', 'string', 'max:2000'],
            'intended_package_scope' => [
                'required',
                'string',
                Rule::in(ServiceRatePackage::entrepreneurPackageScopes()),
            ],
        ]);
        $email = (string) $validated['email'];
        $packageScope = ServiceRatePackage::normaliseEntrepreneurScope(
            (string) $validated['intended_package_scope'],
        );

        $profile = DB::transaction(function () use ($advisor, $email, $packageScope, $validated): EntrepreneurProfile {
            $issued = $this->inviteIssuer->issue(
                email: $email,
                targetUserType: User::TYPE_ENTREPRENEUR,
                targetRole: User::TYPE_ENTREPRENEUR,
                intendedServiceType: ServiceActivation::SERVICE_ENTREPRENEUR,
                intendedPackageScope: $packageScope,
                issuedBy: $advisor,
                deliver: true,
            );

            $profile = EntrepreneurProfile::query()->create([
                'assigned_advisor_id' => $advisor->getKey(),
                'invite_token_id' => $issued->invite->getKey(),
                'intended_service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
                'intended_package_scope' => $packageScope,
                'name' => $validated['name'],
                'email' => $email,
                'stage' => EntrepreneurStage::INVITED,
                'concept_summary' => $validated['concept_summary'] ?? null,
            ]);

            $this->auditWriter->record('entrepreneur.created', subject: $profile, actor: $advisor, after: [
                'entrepreneur_profile_id' => $profile->getKey(),
                'stage' => EntrepreneurStage::INVITED->value,
                'assigned_advisor_id' => $advisor->getKey(),
                'invite_token_id' => $issued->invite->getKey(),
                'intended_service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
                'intended_package_scope' => $packageScope,
            ]);

            return $profile;
        });

        return to_route('advisor.entrepreneurs.show', $profile)->with('status', 'entrepreneur-invited');
    }

    public function storeManual(Request $request): RedirectResponse
    {
        Gate::authorize('create', EntrepreneurProfile::class);

        $advisor = $this->actor($request);
        $this->capacity->ensureCanAdd($advisor);

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('entrepreneur_profiles', 'email'),
            ],
            'concept_summary' => ['nullable', 'string', 'max:2000'],
        ]);

        $profile = EntrepreneurProfile::query()->create([
            'assigned_advisor_id' => $advisor->getKey(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'stage' => EntrepreneurStage::ONBOARDING,
            'concept_summary' => $validated['concept_summary'] ?? null,
        ]);

        $this->auditWriter->record('entrepreneur.created_manual', subject: $profile, actor: $advisor, after: [
            'entrepreneur_profile_id' => $profile->getKey(),
            'stage' => EntrepreneurStage::ONBOARDING->value,
            'assigned_advisor_id' => $advisor->getKey(),
        ]);

        return to_route('advisor.entrepreneurs.show', $profile)->with('status', 'entrepreneur-created');
    }

    public function resendInvite(Request $request, EntrepreneurProfile $entrepreneurProfile): RedirectResponse
    {
        Gate::authorize('view', $entrepreneurProfile);

        $advisor = $this->actor($request);

        $entrepreneurProfile->loadMissing(['inviteToken', 'user']);

        if (! $this->canResendInvite($entrepreneurProfile)) {
            return back()->withErrors([
                'invite' => 'This entrepreneur has already accepted their invitation.',
            ]);
        }

        DB::transaction(function () use ($advisor, $entrepreneurProfile): void {
            $previousInvite = $entrepreneurProfile->inviteToken;
            if ($previousInvite instanceof InviteToken && ! $previousInvite->isAccepted()) {
                $previousInvite->forceFill(['expires_at' => now()->subMinute()])->save();
            }

            $issued = $this->inviteIssuer->issue(
                email: $entrepreneurProfile->email,
                targetUserType: User::TYPE_ENTREPRENEUR,
                targetRole: User::TYPE_ENTREPRENEUR,
                intendedServiceType: ServiceActivation::SERVICE_ENTREPRENEUR,
                intendedPackageScope: $this->intendedEntrepreneurScope($entrepreneurProfile),
                issuedBy: $advisor,
                deliver: true,
            );

            $entrepreneurProfile->forceFill([
                'invite_token_id' => $issued->invite->getKey(),
                'intended_service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
                'intended_package_scope' => $issued->invite->intended_package_scope,
                'stage' => EntrepreneurStage::INVITED,
            ])->save();

            $this->auditWriter->record('entrepreneur.invite_resent', subject: $entrepreneurProfile, actor: $advisor, after: [
                'entrepreneur_profile_id' => $entrepreneurProfile->getKey(),
                'invite_token_id' => $issued->invite->getKey(),
                'previous_invite_token_id' => $previousInvite?->getKey(),
                'email' => $entrepreneurProfile->email,
            ]);
        });

        return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)
            ->with('status', 'entrepreneur-invite-resent');
    }

    public function updateInvite(Request $request, EntrepreneurProfile $entrepreneurProfile): RedirectResponse
    {
        Gate::authorize('view', $entrepreneurProfile);

        $advisor = $this->actor($request);

        $entrepreneurProfile->loadMissing(['inviteToken', 'user']);

        if (! $this->canUpdateInviteDetails($entrepreneurProfile)) {
            return back()->withErrors([
                'invite' => 'Only pending entrepreneur invitations can be edited.',
            ]);
        }

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('entrepreneur_profiles', 'email')->ignore($entrepreneurProfile->getKey()),
                Rule::unique('users', 'email'),
            ],
            'concept_summary' => ['nullable', 'string', 'max:2000'],
            'intended_package_scope' => [
                'required',
                'string',
                Rule::in(ServiceRatePackage::entrepreneurPackageScopes()),
            ],
        ]);

        $previousEmail = Str::lower(trim((string) $entrepreneurProfile->email));
        $previousScope = $this->intendedEntrepreneurScope($entrepreneurProfile);
        $nextEmail = (string) $validated['email'];
        $nextScope = ServiceRatePackage::normaliseEntrepreneurScope(
            (string) $validated['intended_package_scope'],
        );
        $inviteTargetChanged = $previousEmail !== $nextEmail || $previousScope !== $nextScope;

        DB::transaction(function () use (
            $advisor,
            $entrepreneurProfile,
            $inviteTargetChanged,
            $nextEmail,
            $nextScope,
            $previousEmail,
            $previousScope,
            $validated,
        ): void {
            $previousInvite = $entrepreneurProfile->inviteToken;

            if ($inviteTargetChanged && $previousInvite instanceof InviteToken && ! $previousInvite->isAccepted()) {
                $previousInvite->forceFill(['expires_at' => now()->subMinute()])->save();
            }

            $entrepreneurProfile->forceFill([
                'name' => $validated['name'],
                'email' => $nextEmail,
                'concept_summary' => $validated['concept_summary'] ?? null,
                'intended_service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
                'intended_package_scope' => $nextScope,
            ])->save();

            $this->auditWriter->record('entrepreneur.invite_details_updated', subject: $entrepreneurProfile, actor: $advisor, after: [
                'entrepreneur_profile_id' => $entrepreneurProfile->getKey(),
                'invite_token_id' => $previousInvite?->getKey(),
                'old_email' => $previousEmail,
                'new_email' => $nextEmail,
                'old_intended_package_scope' => $previousScope,
                'new_intended_package_scope' => $nextScope,
                'previous_invite_expired' => $inviteTargetChanged,
            ]);
        });

        return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)
            ->with('status', 'entrepreneur-invite-details-updated');
    }

    public function cancelInvite(Request $request, EntrepreneurProfile $entrepreneurProfile): RedirectResponse
    {
        Gate::authorize('view', $entrepreneurProfile);

        $advisor = $this->actor($request);

        $entrepreneurProfile->loadMissing(['inviteToken', 'user']);

        if (! $this->canCancelInvite($entrepreneurProfile)) {
            return back()->withErrors([
                'invite' => 'Only pending entrepreneur invitations can be cancelled.',
            ]);
        }

        DB::transaction(function () use ($advisor, $entrepreneurProfile): void {
            $invite = $entrepreneurProfile->inviteToken;
            if ($invite instanceof InviteToken && ! $invite->isAccepted()) {
                $invite->forceFill(['expires_at' => now()->subMinute()])->save();
            }

            $entrepreneurProfile->forceFill([
                'stage' => EntrepreneurStage::CANCELLED,
            ])->save();

            $this->auditWriter->record('entrepreneur.invite_cancelled', subject: $entrepreneurProfile, actor: $advisor, after: [
                'entrepreneur_profile_id' => $entrepreneurProfile->getKey(),
                'invite_token_id' => $invite?->getKey(),
                'email' => $entrepreneurProfile->email,
            ]);
        });

        return to_route('advisor.entrepreneurs.show', $entrepreneurProfile)
            ->with('status', 'entrepreneur-invite-cancelled');
    }

    public function show(Request $request, EntrepreneurProfile $entrepreneurProfile): Response|RedirectResponse
    {
        Gate::authorize('view', $entrepreneurProfile);
        $canonicalProfile = $this->entrepreneurWorkspaces->forProfile($entrepreneurProfile);

        if (! $canonicalProfile->is($entrepreneurProfile)) {
            Gate::authorize('view', $canonicalProfile);

            return to_route('advisor.entrepreneurs.show', $canonicalProfile);
        }

        $viewer = $this->actor($request);

        $entrepreneurProfile->loadMissing([
            'assignedAdvisor',
            'inviteToken',
            'user',
            'businessPlans.assessments.ratingFramework.criteria',
            'businessPlans.budgetRunway',
            'businessPlans.revisions',
        ]);
        $latestPlan = $entrepreneurProfile->businessPlans
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->sortByDesc('updated_at')
            ->first();
        $activeInvite = $entrepreneurProfile->inviteToken instanceof InviteToken
            && $entrepreneurProfile->inviteToken->isUsable();

        return Inertia::render('advisor/entrepreneurs/Show', [
            'entrepreneur' => [
                ...$this->profileSummary($entrepreneurProfile),
                'concept_summary' => $entrepreneurProfile->concept_summary,
                'user_id' => $entrepreneurProfile->user_id,
                'invite_accepted_at' => $entrepreneurProfile->inviteToken?->accepted_at?->toIso8601String(),
                'invite_expires_at' => $entrepreneurProfile->inviteToken?->expires_at?->toIso8601String(),
                'invite_delivery_label' => $entrepreneurProfile->user_id
                    ? 'Account onboarded'
                    : ($activeInvite ? 'Email sent' : 'No active invite'),
                'invite_update_url' => $this->canUpdateInviteDetails($entrepreneurProfile)
                    ? route('advisor.entrepreneurs.invite.update', $entrepreneurProfile, absolute: false)
                    : null,
                'invite_resend_url' => $this->canResendInvite($entrepreneurProfile)
                    ? route('advisor.entrepreneurs.invite.resend', $entrepreneurProfile, absolute: false)
                    : null,
                'invite_cancel_url' => $this->canCancelInvite($entrepreneurProfile)
                    ? route('advisor.entrepreneurs.invite.cancel', $entrepreneurProfile, absolute: false)
                    : null,
                'intended_package_scope' => $this->intendedEntrepreneurScope($entrepreneurProfile),
                'intended_package_scope_label' => ServiceRatePackage::packageScopeLabel($this->intendedEntrepreneurScope($entrepreneurProfile)),
                'created_at' => $entrepreneurProfile->created_at?->toIso8601String(),
                'latest_plan' => $latestPlan instanceof BusinessPlan
                    ? $this->planProgressSummary($latestPlan, $entrepreneurProfile)
                    : null,
                'readiness' => $this->readinessSummary($entrepreneurProfile),
                'feedback_survey' => [
                    'action_url' => route('advisor.entrepreneurs.survey-assignments.store', $entrepreneurProfile, absolute: false),
                ],
                'service_feedback_survey' => $this->serviceFeedbackSurvey($viewer, $entrepreneurProfile),
                'idea_validation' => $this->ideaValidationSummary($entrepreneurProfile),
                'advisory_readiness' => $this->advisoryReadinessSummary($entrepreneurProfile),
                'reports' => $this->reportSummary($entrepreneurProfile),
                'conversion' => $this->conversionSummary($entrepreneurProfile, $latestPlan),
                'documents' => $this->latestDocuments($entrepreneurProfile),
                'messages' => $this->messageSummary($entrepreneurProfile, $viewer),
                'client_actions' => $entrepreneurProfile->client_id !== null
                    ? [
                        'email_url' => route('advisor.clients.compose', $entrepreneurProfile->client_id, absolute: false),
                        'offboard_url' => route('advisor.clients.offboarding.create', $entrepreneurProfile->client_id, absolute: false),
                    ]
                    : null,
                'gamification' => [
                    ...$this->gamification->payload($entrepreneurProfile, $latestPlan instanceof BusinessPlan ? $latestPlan : null),
                    'enabled' => (bool) $entrepreneurProfile->gamification_on,
                    'toggle_url' => route('advisor.entrepreneurs.gamification.update', $entrepreneurProfile, absolute: false),
                ],
            ],
            'serviceOptions' => ServiceRatePackage::entrepreneurPackageScopeOptions(),
            'screenShare' => $this->screenSharePayload($viewer, $entrepreneurProfile),
            'coBrowse' => $this->coBrowsePayload($viewer, $entrepreneurProfile),
        ]);
    }

    public function latestPlanPreview(EntrepreneurProfile $entrepreneurProfile): SymfonyResponse
    {
        Gate::authorize('view', $entrepreneurProfile);

        $businessPlan = $this->latestEntrepreneurPlan($entrepreneurProfile);
        abort_unless($businessPlan instanceof BusinessPlan, 404);

        return $this->planPreviewResponse($entrepreneurProfile, $businessPlan);
    }

    public function planPreview(EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan): SymfonyResponse
    {
        Gate::authorize('view', $entrepreneurProfile);
        $this->assertPlanBelongsToProfile($businessPlan, $entrepreneurProfile);

        return $this->planPreviewResponse($entrepreneurProfile, $businessPlan);
    }

    public function latestBudgetPackPdf(EntrepreneurProfile $entrepreneurProfile): SymfonyResponse
    {
        Gate::authorize('view', $entrepreneurProfile);

        $businessPlan = $this->latestEntrepreneurPlan($entrepreneurProfile);
        abort_unless($businessPlan instanceof BusinessPlan, 404);
        abort_unless($this->planPreview->budgetUnlocked($businessPlan), 404);

        return $this->budgetPackPdfResponse($entrepreneurProfile, $businessPlan);
    }

    public function budgetPackPdf(EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan): SymfonyResponse
    {
        Gate::authorize('view', $entrepreneurProfile);
        $this->assertPlanBelongsToProfile($businessPlan, $entrepreneurProfile);
        abort_unless($this->planPreview->budgetUnlocked($businessPlan), 404);

        return $this->budgetPackPdfResponse($entrepreneurProfile, $businessPlan);
    }

    private function planPreviewResponse(EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan): SymfonyResponse
    {
        $pdf = $this->planPreview->pdf($entrepreneurProfile, $businessPlan);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->planPreview->filename($entrepreneurProfile).'"',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function budgetPackPdfResponse(EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan): SymfonyResponse
    {
        try {
            $pdf = $this->pdf->render($this->budgetPack->html($entrepreneurProfile, $businessPlan));
        } catch (Throwable $exception) {
            report($exception);
            $pdf = $this->budgetPack->fallbackPdf($entrepreneurProfile, $businessPlan);
        }
        $filename = Str::slug($entrepreneurProfile->name ?: 'entrepreneur').'-budget-pack.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
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

    /**
     * @return array<string, mixed>|null
     */
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

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
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
                'businessPlans' => fn (HasMany $plans) => $plans
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
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
                'automated_score_available' => $latestAssessmentPayload['automated_score_available'],
                'finalised_at' => $latestAssessmentPayload['finalised_at'],
                'rating_framework' => $latestAssessmentPayload['rating_framework'],
                'url' => route('advisor.entrepreneurs.assessments.show', [$profile, $latestAssessment], absolute: false),
                'finalise_url' => route('advisor.entrepreneurs.assessments.finalise', [$profile, $latestAssessment], absolute: false),
            ] : null,
            'budget' => $this->budgetSummary($plan->budgetRunway),
            'preview_pdf_url' => route('advisor.entrepreneurs.plans.latest.preview', $profile, absolute: false),
            'budget_pdf_url' => $this->planPreview->budgetUnlocked($plan)
                ? route('advisor.entrepreneurs.plans.latest.budget-pack.pdf', $profile, absolute: false)
                : null,
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

    /**
     * @return array<int, array<string, mixed>>
     */
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

    private function assertPlanBelongsToProfile(BusinessPlan $plan, EntrepreneurProfile $profile): void
    {
        abort_unless(
            $plan->source_type === BusinessPlan::SOURCE_ENTREPRENEUR
            && (string) $plan->entrepreneur_profile_id === (string) $profile->getKey(),
            404,
        );
    }

    private function latestEntrepreneurPlan(EntrepreneurProfile $profile): ?BusinessPlan
    {
        return $profile->businessPlans()
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->latest('updated_at')
            ->latest('created_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function budgetSummary(?EntrepreneurBudget $budget): array
    {
        $computed = (array) ($budget?->computed ?? []);
        $activeFlags = collect((array) ($budget?->flags ?? []))
            ->filter(fn (array $flag): bool => empty($flag['acknowledged_at']))
            ->values()
            ->all();

        return [
            'status' => $budget?->status ?? EntrepreneurBudget::STATUS_NOT_STARTED,
            'expected_runway_months' => $budget?->expected_runway_months,
            'calculated_runway_months' => data_get($computed, 'runway_months'),
            'runway_open_ended' => (bool) data_get($computed, 'runway_open_ended', false),
            'break_even_month' => data_get($computed, 'break_even_month'),
            'available_after_launch' => data_get($computed, 'available_after_launch'),
            'active_flags' => $activeFlags,
        ];
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>|null
     */
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
            ->filter(fn (array $action): bool => trim((string) ($action['action'] ?? '')) !== '')
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
            ->filter(fn (array $action): bool => ($action['horizon'] ?? 'now') === 'now')
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
            ->filter(fn (array $action): bool => ($action['horizon'] ?? 'now') === 'long_term')
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
     * @param  array<string, mixed>  $finding
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

    private function numberedFeedbackActions(mixed $actions): string
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

    /**
     * @return array<string, mixed>|null
     */
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

    /**
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @return array<string, mixed>
     */
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
