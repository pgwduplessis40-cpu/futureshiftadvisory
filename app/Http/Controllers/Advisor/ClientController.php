<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Actions\Clients\PopulateFromNzbn;
use App\Enums\EngagementType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\ConflictDeclaration;
use App\Models\User;
use App\Services\Audit\AuditWriter;
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
    public function __construct(private readonly AuditWriter $auditWriter) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Client::class);

        return Inertia::render('advisor/clients/Index', [
            'clients' => Client::query()
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (Client $client): array => $this->clientSummary($client))
                ->values(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Client::class);

        return Inertia::render('advisor/clients/Create', $this->createPayload());
    }

    public function lookupNzbn(Request $request, PopulateFromNzbn $populate): Response
    {
        Gate::authorize('create', Client::class);

        $validated = $request->validate([
            'nzbn' => ['required', 'string', 'regex:/^\d{13}$/'],
        ]);

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
            'conflict.declared' => ['accepted'],
            'conflict.referral_type' => ['required', Rule::in(['client_creation', 'due_diligence', 'broker_referral', 'coach_referral'])],
            'conflict.existing_relationship' => ['required', 'boolean'],
            'conflict.details' => ['nullable', 'string', 'max:2000'],
        ]);

        $lookup = $populate->handle($validated['nzbn']);

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

            ClientTeamMember::query()->create([
                'client_id' => $client->id,
                'user_id' => $user->getKey(),
                'role' => 'lead_advisor',
                'granted_modules' => [$validated['engagement_type']],
            ]);

            $declaration = ConflictDeclaration::query()->create([
                'client_id' => $client->id,
                'advisor_id' => $user->getKey(),
                'declaration' => [
                    'declared' => true,
                    'referral_type' => Arr::get($validated, 'conflict.referral_type'),
                    'existing_relationship' => (bool) Arr::get($validated, 'conflict.existing_relationship'),
                    'details' => Arr::get($validated, 'conflict.details'),
                ],
                'declared_at' => now(),
            ]);

            $this->auditWriter->record('conflict.declared', subject: $declaration, actor: $user, after: [
                'client_id' => $client->id,
                'referral_type' => Arr::get($validated, 'conflict.referral_type'),
                'existing_relationship' => (bool) Arr::get($validated, 'conflict.existing_relationship'),
            ]);

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

    public function show(Client $client): Response
    {
        Gate::authorize('view', $client);

        return Inertia::render('advisor/clients/Show', [
            'client' => [
                ...$this->clientSummary($client),
                'address' => $client->address,
                'directors' => $client->directors ?? [],
                'registry_sources' => $client->registry_sources ?? [],
                'engagement_type_locked' => $client->engagementTypeIsLocked(),
                'created_at' => $client->created_at?->toIso8601String(),
            ],
            'conflictDeclaration' => $client->conflictDeclarations()
                ->latest('declared_at')
                ->first()
                ?->only(['id', 'declaration', 'declared_at']),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $lookup
     * @return array<string, mixed>
     */
    private function createPayload(?array $lookup = null, array $input = []): array
    {
        return [
            'engagementTypes' => EngagementType::options(),
            'lookup' => $lookup,
            'defaults' => [
                'engagement_type' => $input['engagement_type'] ?? EngagementType::STANDARD_ADVISORY->value,
                'nzbn' => $input['nzbn'] ?? '',
                'legal_name' => Arr::get($lookup, 'summary.legal_name', $input['legal_name'] ?? ''),
                'trading_name' => $input['trading_name'] ?? '',
                'entity_type' => Arr::get($lookup, 'summary.entity_type', $input['entity_type'] ?? ''),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clientSummary(Client $client): array
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::from((string) $client->engagement_type);

        return [
            'id' => $client->id,
            'engagement_type' => $engagementType->value,
            'engagement_type_label' => $engagementType->label(),
            'nzbn' => $client->nzbn,
            'legal_name' => $client->legal_name,
            'trading_name' => $client->trading_name,
            'entity_type' => $client->entity_type,
            'gst_registered' => $client->gst_registered,
            'filing_status' => $client->filing_status,
            'data_quality' => $client->data_quality,
        ];
    }
}
