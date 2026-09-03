<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\EntrepreneurStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Advisor\Entrepreneurs\UpdateInviteRequest;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\InviteToken;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\Advisor\AdvisorClientServiceWorkspaces;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\AdvisorEntrepreneurCapacity;
use App\Services\Entrepreneurs\CanonicalEntrepreneurWorkspace;
use App\Services\Security\InviteIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurController extends Controller
{
    public function __construct(
        private readonly AdvisorEntrepreneurCapacity $capacity,
        private readonly AuditWriter $auditWriter,
        private readonly InviteIssuer $inviteIssuer,
        private readonly AdvisorClientServiceWorkspaces $serviceWorkspaces,
        private readonly CanonicalEntrepreneurWorkspace $entrepreneurWorkspaces,
        private readonly AdvisorEntrepreneurWorkspacePayload $workspacePayloads,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', EntrepreneurProfile::class);

        $user = $this->actor($request);

        return Inertia::render('advisor/entrepreneurs/Index', [
            'entrepreneurs' => $this->workspacePayloads->indexProfiles($user),
            'capacity' => $this->capacity->summary($user),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', EntrepreneurProfile::class);

        return Inertia::render('advisor/entrepreneurs/Create', [
            'capacity' => $this->capacity->summary($this->actor($request)),
            'mode' => 'invite',
            'serviceOptions' => ServiceRatePackage::entrepreneurInviteServiceOptions(),
        ]);
    }

    public function createManual(Request $request): Response
    {
        Gate::authorize('create', EntrepreneurProfile::class);

        return Inertia::render('advisor/entrepreneurs/Create', [
            'capacity' => $this->capacity->summary($this->actor($request)),
            'mode' => 'manual',
            'serviceOptions' => ServiceRatePackage::entrepreneurInviteServiceOptions(),
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
        Gate::authorize('manageInvite', $entrepreneurProfile);

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

    public function updateInvite(UpdateInviteRequest $request, EntrepreneurProfile $entrepreneurProfile): RedirectResponse
    {
        $advisor = $this->actor($request);

        $entrepreneurProfile->loadMissing(['inviteToken', 'user']);

        if (! $this->canUpdateInviteDetails($entrepreneurProfile)) {
            return back()->withErrors([
                'invite' => 'Only pending entrepreneur invitations can be edited.',
            ]);
        }

        $validated = $request->validated();

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
        Gate::authorize('manageInvite', $entrepreneurProfile);

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

        $client = $this->serviceWorkspaces->clientForProfile($canonicalProfile);
        if ($client instanceof Client && $this->serviceWorkspaces->hasActiveSecondaryWorkspace($client)) {
            Gate::authorize('view', $client);

            return to_route('advisor.clients.show', $client);
        }

        $viewer = $this->actor($request);

        return Inertia::render('advisor/entrepreneurs/Show', $this->workspacePayloads->show($viewer, $entrepreneurProfile));
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
}
