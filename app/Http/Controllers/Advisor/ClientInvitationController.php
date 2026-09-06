<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\InviteToken;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Clients\AdvisorClientCapacity;
use App\Services\Clients\AdvisorClientPayloadBuilder;
use App\Services\Npo\NpoEngagementSetup;
use App\Services\Security\InviteIssuer;
use App\Services\ServiceActivations\ServiceActivationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ClientInvitationController extends Controller
{
    public function __construct(
        private readonly AuditWriter $auditWriter,
        private readonly AdvisorClientCapacity $clientCapacity,
        private readonly AdvisorClientPayloadBuilder $clientPayloads,
        private readonly NpoEngagementSetup $npoEngagements,
    ) {}

    public function invite(Request $request): Response
    {
        Gate::authorize('create', Client::class);

        [$engagement, $wasFiltered] = $this->clientInviteEngagementFrom($request->query('engagement_type'));

        return Inertia::render('advisor/clients/Invite', [
            'engagementTypes' => $this->clientInviteEngagementOptions(),
            'serviceOfferPackages' => [
                'due_diligence' => $this->clientInvitePackageOptions(ServiceActivation::SERVICE_DUE_DILIGENCE),
                'dd_plan_budget' => $this->clientInvitePackageOptions(ServiceActivation::SERVICE_DD_PLAN_BUDGET),
            ],
            'defaults' => [
                'email' => '',
                'engagement_type' => $engagement->value,
                'return_to' => $wasFiltered
                    ? route('advisor.clients.index', ['engagement_type' => $engagement->value], absolute: false)
                    : route('advisor.clients.index', absolute: false),
            ],
        ]);
    }

    public function storeInvite(
        Request $request,
        InviteIssuer $issuer,
        ServiceActivationManager $serviceActivations,
    ): RedirectResponse {
        Gate::authorize('create', Client::class);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $allowedEngagements = array_map(
            static fn (EngagementType $type): string => $type->value,
            $this->clientInviteEngagementTypes(),
        );
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'engagement_type' => ['required', 'string', Rule::in($allowedEngagements)],
            'due_diligence_package_id' => [
                Rule::requiredIf(fn (): bool => $request->input('engagement_type') === EngagementType::DUE_DILIGENCE->value),
                'nullable',
                'uuid',
            ],
            'dd_plan_budget_package_id' => ['nullable', 'uuid'],
            'return_to' => ['nullable', 'string', 'max:255'],
        ]);
        $engagement = EngagementType::from((string) $validated['engagement_type']);
        $dueDiligencePackage = $engagement === EngagementType::DUE_DILIGENCE
            ? $this->activeInvitePackage((string) ($validated['due_diligence_package_id'] ?? ''), ServiceActivation::SERVICE_DUE_DILIGENCE)
            : null;
        $planBudgetPackage = $this->optionalActiveInvitePackage(
            $validated['dd_plan_budget_package_id'] ?? null,
            ServiceActivation::SERVICE_DD_PLAN_BUDGET,
        );
        if (
            is_string($validated['dd_plan_budget_package_id'] ?? null)
            && trim((string) $validated['dd_plan_budget_package_id']) !== ''
            && ! $planBudgetPackage instanceof ServiceRatePackage
        ) {
            return back()->withErrors([
                'dd_plan_budget_package_id' => 'Choose a currently active Business Plan & Budget package.',
            ])->withInput();
        }
        if ($planBudgetPackage instanceof ServiceRatePackage && $engagement !== EngagementType::DUE_DILIGENCE) {
            return back()->withErrors([
                'dd_plan_budget_package_id' => 'Business Plan & Budget can only be included with a Due Diligence invitation.',
            ])->withInput();
        }
        $this->clientCapacity->ensureCanAdd($user);

        DB::transaction(function () use ($dueDiligencePackage, $engagement, $issuer, $planBudgetPackage, $serviceActivations, $user, $validated): void {
            $issued = $issuer->issue(
                email: (string) $validated['email'],
                targetUserType: User::TYPE_CLIENT_PRIMARY,
                targetRole: User::TYPE_CLIENT_PRIMARY,
                intendedServiceType: $engagement === EngagementType::DUE_DILIGENCE
                    ? ServiceActivation::SERVICE_DUE_DILIGENCE
                    : null,
                intendedPackageScope: $dueDiligencePackage?->packageScope(),
                issuedBy: $user,
                deliver: true,
            );
            $client = $this->createInvitedClientWorkspace(
                email: (string) $validated['email'],
                engagement: $engagement,
                inviteId: (string) $issued->invite->getKey(),
                advisor: $user,
            );
            if ($dueDiligencePackage instanceof ServiceRatePackage) {
                $serviceActivations->offerFromClientInvite(
                    client: $client,
                    advisor: $user,
                    package: $dueDiligencePackage,
                    inviteTokenId: (string) $issued->invite->getKey(),
                );
            }
            if ($planBudgetPackage instanceof ServiceRatePackage) {
                $serviceActivations->offerFromClientInvite(
                    client: $client,
                    advisor: $user,
                    package: $planBudgetPackage,
                    inviteTokenId: (string) $issued->invite->getKey(),
                );
            }
            $this->auditWriter->record('client.invite_issued', subject: $issued->invite, actor: $user, after: [
                'client_id' => $client->getKey(),
                'email' => $validated['email'],
                'engagement_type' => $engagement->value,
                'invite_token_id' => $issued->invite->getKey(),
                'service_rate_package_ids' => array_values(array_filter([
                    $dueDiligencePackage?->getKey(),
                    $planBudgetPackage?->getKey(),
                ])),
            ]);
        });

        return redirect($this->safeClientInviteReturnUrl($validated['return_to'] ?? null, $engagement))
            ->with('status', 'client-invited');
    }

    public function resendInvite(
        Request $request,
        Client $client,
        InviteIssuer $issuer,
        ServiceActivationManager $serviceActivations,
    ): RedirectResponse {
        Gate::authorize('update', $client);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $invite = $this->clientPayloads->inviteFor($client);
        if (! $this->clientPayloads->canResendInvite($client, $invite)) {
            return back()->withErrors(['invite' => 'Only pending or cancelled client invitations can be resent.']);
        }

        $email = $this->clientPayloads->inviteEmail($client);
        $engagement = $client->engagement_type;

        $dueDiligenceOffer = $serviceActivations->invitationOffers($client)
            ->first(fn (ServiceActivation $offer): bool => $offer->service_type === ServiceActivation::SERVICE_DUE_DILIGENCE);
        $dueDiligenceScope = is_string(data_get($dueDiligenceOffer?->selected_package_snapshot, 'package_scope'))
            ? data_get($dueDiligenceOffer?->selected_package_snapshot, 'package_scope')
            : null;

        DB::transaction(function () use ($actor, $client, $dueDiligenceScope, $email, $engagement, $invite, $issuer, $serviceActivations): void {
            if ($invite instanceof InviteToken && ! $invite->isAccepted()) {
                $invite->forceFill(['expires_at' => now()->subMinute()])->save();
            }

            $issued = $issuer->issue(
                email: $email,
                targetUserType: User::TYPE_CLIENT_PRIMARY,
                targetRole: User::TYPE_CLIENT_PRIMARY,
                intendedServiceType: $engagement === EngagementType::DUE_DILIGENCE
                    ? ServiceActivation::SERVICE_DUE_DILIGENCE
                    : null,
                intendedPackageScope: $dueDiligenceScope,
                issuedBy: $actor,
                deliver: true,
            );
            if ($invite instanceof InviteToken) {
                $serviceActivations->rebindInvitationOffers(
                    client: $client,
                    oldInviteTokenId: (string) $invite->getKey(),
                    newInviteTokenId: (string) $issued->invite->getKey(),
                );
            }
            $registrySources = is_array($client->registry_sources) ? $client->registry_sources : [];
            unset($registrySources['invite_cancelled_at'], $registrySources['invite_cancelled_by_user_id']);
            $client->forceFill([
                'registry_sources' => [
                    ...$registrySources,
                    'invite_token_id' => $issued->invite->getKey(),
                    'invite_email' => $email,
                    'invite_resent_at' => now()->toIso8601String(),
                ],
            ])->save();
            $this->auditWriter->record('client.invite_resent', subject: $client, actor: $actor, after: [
                'client_id' => $client->getKey(),
                'email' => $email,
                'previous_invite_token_id' => $invite?->getKey(),
                'invite_token_id' => $issued->invite->getKey(),
            ]);
        });

        return to_route('advisor.clients.show', $client)->with('status', 'client-invite-resent');
    }

    public function cancelInvite(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $invite = $this->clientPayloads->inviteFor($client);
        if (! $this->clientPayloads->canCancelInvite($client, $invite)) {
            return back()->withErrors(['invite' => 'Only pending client invitations can be cancelled.']);
        }

        DB::transaction(function () use ($actor, $client, $invite): void {
            $invite?->forceFill(['expires_at' => now()->subMinute()])->save();
            $registrySources = is_array($client->registry_sources) ? $client->registry_sources : [];
            $client->forceFill([
                'registry_sources' => [
                    ...$registrySources,
                    'invite_cancelled_at' => now()->toIso8601String(),
                    'invite_cancelled_by_user_id' => $actor->getKey(),
                ],
            ])->save();
            $this->auditWriter->record('client.invite_cancelled', subject: $client, actor: $actor, after: [
                'client_id' => $client->getKey(),
                'email' => $this->clientPayloads->inviteEmail($client),
                'invite_token_id' => $invite?->getKey(),
            ]);
        });

        return to_route('advisor.clients.show', $client)->with('status', 'client-invite-cancelled');
    }

    /** @return array{0:EngagementType,1:bool} */
    private function clientInviteEngagementFrom(mixed $value): array
    {
        $engagement = is_string($value) ? EngagementType::tryFrom(trim($value)) : null;

        if ($engagement instanceof EngagementType && in_array($engagement, $this->clientInviteEngagementTypes(), true)) {
            return [$engagement, true];
        }

        return [EngagementType::STANDARD_ADVISORY, false];
    }

    /** @return list<EngagementType> */
    private function clientInviteEngagementTypes(): array
    {
        return [
            EngagementType::STANDARD_ADVISORY,
            EngagementType::DUE_DILIGENCE,
            EngagementType::POST_ACQUISITION_ADVISORY,
            EngagementType::NPO,
        ];
    }

    /** @return list<array{value:string,label:string,description:string}> */
    private function clientInviteEngagementOptions(): array
    {
        return array_map(
            static fn (EngagementType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ],
            $this->clientInviteEngagementTypes(),
        );
    }

    private function safeClientInviteReturnUrl(mixed $value, EngagementType $fallback): string
    {
        $url = is_string($value) ? trim($value) : '';
        $allowedUrls = [route('advisor.clients.index', absolute: false)];
        foreach ($this->clientInviteEngagementTypes() as $type) {
            $allowedUrls[] = route('advisor.clients.index', ['engagement_type' => $type->value], absolute: false);
        }

        return in_array($url, $allowedUrls, true)
            ? $url
            : route('advisor.clients.index', ['engagement_type' => $fallback->value], absolute: false);
    }

    /**
     * @return list<array{id:string,label:string,description:string,fee:float|null,currency:string,scope_label:string}>
     */
    private function clientInvitePackageOptions(string $serviceType): array
    {
        $now = now();

        return ServiceRatePackage::query()
            ->where('service_type', $serviceType)
            ->where('is_active', true)
            ->where('effective_from', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $now);
            })
            ->orderBy('fixed_fee')
            ->orderBy('client_label')
            ->get()
            ->map(fn (ServiceRatePackage $package): array => [
                'id' => (string) $package->getKey(),
                'label' => (string) $package->client_label,
                'description' => (string) $package->scope_description,
                'fee' => $package->fixed_fee,
                'currency' => (string) ($package->currency ?: 'NZD'),
                'scope_label' => ServiceRatePackage::packageScopeLabel($package->packageScope()),
            ])
            ->values()
            ->all();
    }

    private function activeInvitePackage(string $id, string $serviceType): ServiceRatePackage
    {
        $package = $this->optionalActiveInvitePackage($id, $serviceType);
        if (! $package instanceof ServiceRatePackage) {
            throw ValidationException::withMessages([
                'due_diligence_package_id' => 'Choose an active Due Diligence package and its displayed fee before sending this invite.',
            ]);
        }

        return $package;
    }

    private function optionalActiveInvitePackage(mixed $id, string $serviceType): ?ServiceRatePackage
    {
        if (! is_string($id) || trim($id) === '') {
            return null;
        }

        $now = now();

        return ServiceRatePackage::query()
            ->whereKey($id)
            ->where('service_type', $serviceType)
            ->where('is_active', true)
            ->where('effective_from', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $now);
            })
            ->first();
    }

    private function createInvitedClientWorkspace(
        string $email,
        EngagementType $engagement,
        string $inviteId,
        User $advisor,
    ): Client {
        $client = Client::query()->create([
            'engagement_type' => $engagement->value,
            'legal_name' => Str::limit('Invited client - '.$email, 255, ''),
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'registry_sources' => [
                'source' => 'advisor_client_invite',
                'source_label' => 'Created from an advisor invitation; client details are completed during onboarding.',
                'invite_token_id' => $inviteId,
                'invite_email' => $email,
                'invite_engagement_type' => $engagement->value,
            ],
            'created_by_user_id' => $advisor->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [$engagement->value],
        ]);

        if ($engagement === EngagementType::NPO) {
            $this->npoEngagements->create($client, $advisor, [
                'sub_type' => NpoEngagementSubType::GovernanceReview->value,
                'legal_structure' => NpoLegalStructure::UnincorporatedCommunityOrganisation->value,
                'isa_2022_reregistered' => null,
            ]);
        }

        return $client;
    }
}
