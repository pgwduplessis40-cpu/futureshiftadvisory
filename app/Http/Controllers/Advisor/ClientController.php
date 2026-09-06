<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Actions\Clients\PopulateFromNzbn;
use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Http\Controllers\Controller;
use App\Http\Resources\Advisor\AdvisorClientIndexPayloadBuilder;
use App\Http\Resources\Advisor\AdvisorClientShowPayloadBuilder;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Clients\AdvisorClientCapacity;
use App\Services\Conflicts\ConflictDeclarer;
use App\Services\Dashboards\EconomicExposureMapper;
use App\Services\Npo\NpoEngagementSetup;
use App\Services\Security\InviteIssuer;
use App\Services\ServiceActivations\ServiceActivationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ClientController extends Controller
{
    public function __construct(
        private readonly AuditWriter $auditWriter,
        private readonly AdvisorClientCapacity $clientCapacity,
        private readonly AdvisorClientIndexPayloadBuilder $indexPayloads,
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
        return app(ClientInvitationController::class)->invite($request);
    }

    public function storeInvite(Request $request, InviteIssuer $issuer, ServiceActivationManager $serviceActivations): RedirectResponse
    {
        return app(ClientInvitationController::class)->storeInvite($request, $issuer, $serviceActivations);
    }

    public function resendInvite(Request $request, Client $client, InviteIssuer $issuer, ServiceActivationManager $serviceActivations): RedirectResponse
    {
        return app(ClientInvitationController::class)->resendInvite($request, $client, $issuer, $serviceActivations);
    }

    public function cancelInvite(Request $request, Client $client): RedirectResponse
    {
        return app(ClientInvitationController::class)->cancelInvite($request, $client);
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
        if ($entrepreneurProfile instanceof EntrepreneurProfile && $this->showPayloads->shouldRedirectToEntrepreneurWorkspace($client)) {
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
}
