<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\EntrepreneurStage;
use App\Enums\ReportType;
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
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\AdvisorEntrepreneurCapacity;
use App\Services\Entrepreneurs\EntrepreneurGamification;
use App\Services\Entrepreneurs\IdeaViabilityGate;
use App\Services\Security\InviteIssuer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurController extends Controller
{
    use BuildsEntrepreneurAssessmentPayload;

    public function __construct(
        private readonly AdvisorEntrepreneurCapacity $capacity,
        private readonly AuditWriter $auditWriter,
        private readonly InviteIssuer $inviteIssuer,
        private readonly EntrepreneurGamification $gamification,
        private readonly IdeaViabilityGate $ideaViabilityGate,
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

    public function show(Request $request, EntrepreneurProfile $entrepreneurProfile): Response
    {
        Gate::authorize('view', $entrepreneurProfile);
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
                'idea_validation' => $this->ideaValidationSummary($entrepreneurProfile),
                'advisory_readiness' => $this->advisoryReadinessSummary($entrepreneurProfile),
                'reports' => $this->reportSummary($entrepreneurProfile),
                'conversion' => $this->conversionSummary($entrepreneurProfile, $latestPlan),
                'documents' => $this->latestDocuments($entrepreneurProfile),
                'messages' => $this->messageSummary($entrepreneurProfile, $viewer),
                'gamification' => [
                    ...$this->gamification->payload($entrepreneurProfile, $latestPlan instanceof BusinessPlan ? $latestPlan : null),
                    'enabled' => (bool) $entrepreneurProfile->gamification_on,
                    'toggle_url' => route('advisor.entrepreneurs.gamification.update', $entrepreneurProfile, absolute: false),
                ],
            ],
            'serviceOptions' => ServiceRatePackage::entrepreneurPackageScopeOptions(),
        ]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
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
            ->with(['assignedAdvisor', 'inviteToken', 'user']);

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
        $latestAssessmentPayload = $latestAssessment instanceof PlanAssessment
            ? $this->assessmentPayload($latestAssessment)
            : null;

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'status' => $plan->status,
            'assessment_count' => $plan->assessments->count(),
            'latest_round' => $latestAssessment?->round,
            'latest_grade' => $latestAssessment?->overall_grade,
            'latest_assessment' => $latestAssessmentPayload ? [
                'id' => $latestAssessmentPayload['id'],
                'round' => $latestAssessmentPayload['round'],
                'status' => $latestAssessmentPayload['status'],
                'overall_grade' => $latestAssessmentPayload['overall_grade'],
                'weighted_score' => $latestAssessmentPayload['weighted_score'],
                'finalised_at' => $latestAssessmentPayload['finalised_at'],
                'rating_framework' => $latestAssessmentPayload['rating_framework'],
                'url' => route('advisor.entrepreneurs.assessments.show', [$profile, $latestAssessment], absolute: false),
                'finalise_url' => route('advisor.entrepreneurs.assessments.finalise', [$profile, $latestAssessment], absolute: false),
            ] : null,
            'budget' => $this->budgetSummary($plan->budgetRunway),
            'assess_url' => route('advisor.entrepreneurs.plans.assessments.store', [$profile, $plan], absolute: false),
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
            'action_label' => $assessment instanceof ReadinessAssessment
                ? 'Review surveys'
                : 'Send readiness questionnaire',
            'action_url' => route('advisor.entrepreneurs.surveys', $profile, absolute: false),
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
            'viability_gate' => $this->ideaViabilityGate->assess($validation),
            'proposed_change_request' => $this->proposedChangeRequest($validation),
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

    private function proposedChangeRequest(IdeaValidation $validation): string
    {
        $evaluation = $validation->ai_evaluation ?? [];
        $findings = collect((array) data_get($evaluation, 'metadata.findings', []))
            ->filter(fn (mixed $finding): bool => is_array($finding))
            ->map(fn (array $finding): string => $this->founderActionForFinding($finding))
            ->filter()
            ->take(3)
            ->values();

        if ($findings->isEmpty()) {
            $findings = collect([
                'Define the primary customer segment, the paid problem it faces, and why this offer is a better choice than the alternatives.',
                'Record at least one customer experiment with a clear hypothesis, evidence, result, and next step.',
                'Describe a repeatable offer, pricing, delivery capacity, and revenue model that is not dependent only on your personal time.',
            ]);
        }

        $alerts = collect((array) $validation->viability_alerts)
            ->filter(fn (mixed $alert): bool => is_array($alert))
            ->map(fn (array $alert): string => trim((string) ($alert['message'] ?? '')))
            ->filter()
            ->map(fn (string $alert): string => $this->completeFeedbackPoint($alert));

        $actions = $findings
            ->merge($alerts)
            ->unique(fn (string $action): string => Str::lower($action))
            ->take(4)
            ->values()
            ->map(fn (string $action, int $index): string => ($index + 1).'. '.$action)
            ->implode("\n");

        return implode("\n\n", [
            'Thank you for the work you have put into this idea validation.',
            'Your idea shows promise, but more evidence and a more repeatable commercial model are needed before it can move into business-plan development.',
            "Before resubmitting, please:\n{$actions}",
            'Please update the idea validation with this information and resubmit it for review.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $finding
     */
    private function founderActionForFinding(array $finding): string
    {
        $recommendedAction = trim((string) ($finding['recommended_action'] ?? ''));
        if ($recommendedAction !== '') {
            return $this->completeFeedbackPoint($recommendedAction);
        }

        $title = trim((string) ($finding['title'] ?? ''));
        $body = trim((string) ($finding['body'] ?? ''));
        $context = Str::lower($title.' '.$body);

        if (Str::contains($context, ['revenue', 'pricing', 'price', 'time-constrained', 'capacity'])) {
            return 'Build a sustainable revenue model: show how the offer can create income beyond your own billable days, including package pricing, delivery costs, monthly capacity, and recurring follow-on support.';
        }

        if (Str::contains($context, ['demand', 'market', 'customer evidence', 'willingness to pay'])) {
            return 'Collect and document stronger demand evidence: choose a primary customer segment, test a paid offer, and record the hypothesis, evidence, result, and next step.';
        }

        if (Str::contains($context, ['value proposition', 'differentiat', 'positioning', 'communicat'])) {
            return 'State one clear value proposition: name the customer, their pressing problem, the outcome they receive, and why this offer is more valuable than the alternatives.';
        }

        if (Str::contains($context, ['target customer', 'customer segment', 'customer'])) {
            return 'Narrow the starting customer segment and explain the specific paid problem this offer will solve for them.';
        }

        if (Str::contains($context, ['solution', 'delivery', 'offer'])) {
            return 'Describe a repeatable offer with clear outcomes, delivery steps, and what can be standardised as demand grows.';
        }

        return $this->completeFeedbackPoint(trim(implode(': ', array_filter([$title, $body]))));
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

        return $limited !== ''
            ? $limited
            : Str::limit($point, 600, '...');
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
            ->limit(6)
            ->get()
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'original_filename' => $document->original_filename,
                'category' => $document->category,
                'scanner_result' => $document->scanner_result,
                'uploaded_at' => $document->created_at?->toIso8601String(),
                'uploaded_by_name' => $document->uploadedBy?->name,
                'url' => route('advisor.entrepreneurs.documents.show', [$profile, $document], absolute: false),
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
