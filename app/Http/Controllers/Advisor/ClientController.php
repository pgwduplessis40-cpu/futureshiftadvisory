<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Actions\Clients\PopulateFromNzbn;
use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\InviteToken;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Clients\AdvisorClientCapacity;
use App\Services\Clients\AdvisorClientIndexPayloadBuilder;
use App\Services\Clients\AdvisorClientPayloadBuilder;
use App\Services\Clients\AdvisorClientShowPayloadBuilder;
use App\Services\Conflicts\ConflictDeclarer;
use App\Services\Dashboards\EconomicExposureMapper;
use App\Services\Npo\NpoEngagementSetup;
use App\Services\Security\InviteIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ClientController extends Controller
{
    public function __construct(
        private readonly AuditWriter $auditWriter,
        private readonly AdvisorClientCapacity $clientCapacity,
        private readonly AdvisorClientIndexPayloadBuilder $indexPayloads,
        private readonly AdvisorClientPayloadBuilder $clientPayloads,
        private readonly AdvisorClientShowPayloadBuilder $showPayloads,
        private readonly ConflictDeclarer $conflicts,
        private readonly NpoEngagementSetup $npoEngagements,
    ) {}

    public function index(Request $request, EconomicExposureMapper $economicExposure): Response
    {
        Gate::authorize('viewAny', Client::class);

        return Inertia::render('advisor/clients/Index', $this->indexPayloads->build($request, $economicExposure));
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Client::class);

        return Inertia::render('advisor/clients/Create', $this->createPayload(input: $request->query()));
    }

    public function invite(Request $request): Response
    {
        Gate::authorize('create', Client::class);

        [$engagement, $wasFiltered] = $this->clientInviteEngagementFrom($request->query('engagement_type'));

        return Inertia::render('advisor/clients/Invite', [
            'engagementTypes' => $this->clientInviteEngagementOptions(),
            'defaults' => [
                'email' => '',
                'engagement_type' => $engagement->value,
                'return_to' => $wasFiltered
                    ? route('advisor.clients.index', ['engagement_type' => $engagement->value], absolute: false)
                    : route('advisor.clients.index', absolute: false),
            ],
        ]);
    }

    public function storeInvite(Request $request, InviteIssuer $issuer): RedirectResponse
    {
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
            'return_to' => ['nullable', 'string', 'max:255'],
        ]);
        $engagement = EngagementType::from((string) $validated['engagement_type']);
        $this->clientCapacity->ensureCanAdd($user);

        DB::transaction(function () use ($engagement, $issuer, $user, $validated): void {
            $issued = $issuer->issue(
                email: (string) $validated['email'],
                targetUserType: User::TYPE_CLIENT_PRIMARY,
                targetRole: User::TYPE_CLIENT_PRIMARY,
                intendedServiceType: $engagement === EngagementType::DUE_DILIGENCE
                    ? ServiceActivation::SERVICE_DUE_DILIGENCE
                    : null,
                issuedBy: $user,
                deliver: true,
            );
            $client = $this->createInvitedClientWorkspace(
                email: (string) $validated['email'],
                engagement: $engagement,
                inviteId: (string) $issued->invite->getKey(),
                advisor: $user,
            );
            $this->auditWriter->record('client.invite_issued', subject: $issued->invite, actor: $user, after: [
                'client_id' => $client->getKey(),
                'email' => $validated['email'],
                'engagement_type' => $engagement->value,
                'invite_token_id' => $issued->invite->getKey(),
            ]);
        });

        return redirect($this->safeClientInviteReturnUrl($validated['return_to'] ?? null, $engagement))
            ->with('status', 'client-invited');
    }

    public function resendInvite(Request $request, Client $client, InviteIssuer $issuer): RedirectResponse
    {
        Gate::authorize('update', $client);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $invite = $this->clientPayloads->inviteFor($client);
        if (! $this->clientPayloads->canResendInvite($client, $invite)) {
            return back()->withErrors(['invite' => 'Only pending or cancelled client invitations can be resent.']);
        }

        $email = $this->clientPayloads->inviteEmail($client);
        $engagement = $client->engagement_type;

        DB::transaction(function () use ($actor, $client, $email, $engagement, $invite, $issuer): void {
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
                issuedBy: $actor,
                deliver: true,
            );
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

    public function lookupNzbn(Request $request, PopulateFromNzbn $populate): Response
    {
        Gate::authorize('create', Client::class);

        $validated = $request->validate(['nzbn' => ['required', 'string', 'regex:/^\d{13}$/']]);

        return Inertia::render(
            'advisor/clients/Create',
            $this->createPayload($populate->handle($validated['nzbn']), $request->all()),
        );
    }

    public function store(Request $request, PopulateFromNzbn $populate): RedirectResponse
    {
        Gate::authorize('create', Client::class);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'engagement_type' => ['required', Rule::enum(EngagementType::class)],
            'nzbn' => ['required', 'string', 'regex:/^\d{13}$/'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'trading_name' => ['nullable', 'string', 'max:255'],
            'entity_type' => ['nullable', 'string', 'max:120'],
            'npo.sub_type' => ['required_if:engagement_type,'.EngagementType::NPO->value, Rule::enum(NpoEngagementSubType::class)],
            'npo.legal_structure' => ['required_if:engagement_type,'.EngagementType::NPO->value, Rule::enum(NpoLegalStructure::class)],
            'npo.isa_2022_reregistered' => ['nullable', 'boolean'],
            'conflict.declared' => ['accepted'],
            'conflict.referral_type' => ['required', Rule::in(ConflictDeclarer::referralTypes())],
            'conflict.existing_relationship' => ['required', 'boolean'],
            'conflict.details' => ['nullable', 'string', 'max:2000'],
        ]);
        $lookup = $populate->handle($validated['nzbn']);
        $this->clientCapacity->ensureCanAdd($user);

        $client = DB::transaction(function () use ($user, $validated, $lookup): Client {
            $summary = $lookup['summary'];
            $client = Client::query()->create([
                'engagement_type' => $validated['engagement_type'],
                'nzbn' => $validated['nzbn'],
                'legal_name' => $validated['legal_name'] ?: (string) ($summary['legal_name'] ?? ''),
                'trading_name' => $validated['trading_name'] ?? null,
                'entity_type' => $validated['entity_type'] ?: ($summary['entity_type'] ?? null),
                'address' => $summary['address'] ?? null,
                'gst_registered' => (bool) ($summary['gst_registered'] ?? false),
                'directors' => $summary['directors'] ?? [],
                'filing_status' => $summary['filing_status'] ?? ($summary['status'] ?? null),
                'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
                'registry_sources' => $lookup['source_badges'],
                'created_by_user_id' => $user->getKey(),
            ]);
            if ($validated['engagement_type'] === EngagementType::NPO->value) {
                $this->npoEngagements->create($client, $user, [
                    'sub_type' => (string) Arr::get($validated, 'npo.sub_type'),
                    'legal_structure' => (string) Arr::get($validated, 'npo.legal_structure'),
                    'isa_2022_reregistered' => Arr::get($validated, 'npo.isa_2022_reregistered'),
                ]);
            }
            ClientTeamMember::query()->create([
                'client_id' => $client->id,
                'user_id' => $user->getKey(),
                'role' => 'lead_advisor',
                'granted_modules' => [$validated['engagement_type']],
            ]);
            $this->conflicts->declare(
                advisor: $user,
                client: $client,
                referralType: (string) Arr::get($validated, 'conflict.referral_type'),
                existingRelationship: (bool) Arr::get($validated, 'conflict.existing_relationship'),
                details: Arr::get($validated, 'conflict.details'),
            );
            $this->auditWriter->record('client.created', subject: $client, actor: $user, after: [
                'client_id' => $client->id,
                'engagement_type' => $validated['engagement_type'],
                'nzbn' => $validated['nzbn'],
                'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
                'registry_sources' => $lookup['source_badges'],
            ]);

            return $client;
        });

        return to_route('advisor.clients.show', $client)->with('status', 'client-created');
    }

    public function show(Request $request, Client $client): Response|RedirectResponse
    {
        Gate::authorize('view', $client);

        $entrepreneurProfile = $client->engagement_type === EngagementType::FOUNDING_ADVISORY
            ? null
            : $this->showPayloads->entrepreneurWorkspace($client);
        if ($entrepreneurProfile !== null) {
            return to_route('advisor.entrepreneurs.show', $entrepreneurProfile);
        }

        $user = $request->user();
        $highlight = $request->query('highlight');

        return Inertia::render('advisor/clients/Show', $this->showPayloads->build(
            $client,
            $user instanceof User ? $user : null,
            is_string($highlight) ? $highlight : null,
        ));
    }

    /**
     * @param  array<array-key, mixed>|null  $lookup
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    private function createPayload(?array $lookup = null, array $input = []): array
    {
        return [
            'engagementTypes' => EngagementType::options(),
            'npoOptions' => [
                'subTypes' => NpoEngagementSubType::options(),
                'legalStructures' => NpoLegalStructure::options(),
            ],
            'lookup' => $lookup,
            'defaults' => [
                'engagement_type' => $input['engagement_type'] ?? EngagementType::STANDARD_ADVISORY->value,
                'nzbn' => $input['nzbn'] ?? '',
                'legal_name' => Arr::get($lookup, 'summary.legal_name', $input['legal_name'] ?? ''),
                'trading_name' => $input['trading_name'] ?? '',
                'entity_type' => Arr::get($lookup, 'summary.entity_type', $input['entity_type'] ?? ''),
                'npo' => [
                    'sub_type' => Arr::get($input, 'npo.sub_type', NpoEngagementSubType::GovernanceReview->value),
                    'legal_structure' => Arr::get($input, 'npo.legal_structure', ''),
                    'isa_2022_reregistered' => (bool) Arr::get($input, 'npo.isa_2022_reregistered', false),
                ],
            ],
        ];
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
