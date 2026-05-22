<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Actions\Clients\PopulateFromNzbn;
use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Http\Controllers\Controller;
use App\Models\AccountingConnection;
use App\Models\AnalysisFeedback;
use App\Models\AnalysisFinding;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\FinancialSnapshot;
use App\Models\KnowledgeAssessment;
use App\Models\User;
use App\Models\WellbeingCheckin;
use App\Services\Audit\AuditWriter;
use App\Services\Conflicts\ConflictDeclarer;
use App\Services\DataQuality\DataQualityScorer;
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
        private readonly ConflictDeclarer $conflicts,
        private readonly DataQualityScorer $dataQuality,
    ) {}

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
            'conflict.referral_type' => ['required', Rule::in(ConflictDeclarer::referralTypes())],
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

    public function show(Request $request, Client $client): Response
    {
        Gate::authorize('view', $client);
        $dataQuality = $this->dataQuality->score($client);
        $user = $request->user();

        return Inertia::render('advisor/clients/Show', [
            'client' => [
                ...$this->clientSummary($client),
                'data_quality' => $dataQuality->level,
                'data_quality_summary' => $dataQuality->toPayload(),
                'wellbeing_trend' => $user instanceof User ? $this->wellbeingTrend($client, $user) : null,
                'status_options' => ClientStatus::options(),
                'lifecycle_update_url' => route('advisor.clients.lifecycle.update', $client, absolute: false),
                'knowledge_assessment_store_url' => route('advisor.clients.knowledge-assessments.store', $client, absolute: false),
                'latest_knowledge_assessment' => $this->latestKnowledgeAssessment($client),
                'address' => $client->address,
                'directors' => $client->directors ?? [],
                'registry_sources' => $client->registry_sources ?? [],
                'engagement_type_locked' => $client->engagementTypeIsLocked(),
                'offboarding' => $this->offboardingSummary($client),
                'accounting' => $this->accountingSummary($client),
                'analysis_findings' => $this->analysisFindingSummaries($client),
                'created_at' => $client->created_at?->toIso8601String(),
            ],
            'conflictDeclaration' => $client->conflictDeclarations()
                ->latest('declared_at')
                ->first()
                ?->only(['id', 'declaration', 'declared_at']),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestKnowledgeAssessment(Client $client): ?array
    {
        $assessment = KnowledgeAssessment::query()
            ->where('client_id', $client->getKey())
            ->latest('assessed_at')
            ->latest('created_at')
            ->first();

        if (! $assessment instanceof KnowledgeAssessment) {
            return null;
        }

        return [
            'id' => $assessment->id,
            'financial_literacy' => $assessment->financial_literacy,
            'strategic_awareness' => $assessment->strategic_awareness,
            'leadership' => $assessment->leadership,
            'calibration' => $assessment->calibration,
            'assessed_at' => $assessment->assessed_at?->toIso8601String(),
        ];
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
        $status = $client->status instanceof ClientStatus
            ? $client->status
            : ClientStatus::from((string) ($client->status ?? ClientStatus::ACTIVE->value));

        return [
            'id' => $client->id,
            'engagement_type' => $engagementType->value,
            'engagement_type_label' => $engagementType->label(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'nzbn' => $client->nzbn,
            'legal_name' => $client->legal_name,
            'trading_name' => $client->trading_name,
            'entity_type' => $client->entity_type,
            'gst_registered' => $client->gst_registered,
            'filing_status' => $client->filing_status,
            'data_quality' => $client->data_quality,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function offboardingSummary(Client $client): ?array
    {
        $record = $client->offboardingRecords()
            ->latest('triggered_at')
            ->first();

        if ($record === null) {
            return null;
        }

        return [
            'id' => $record->id,
            'triggered_at' => $record->triggered_at?->toIso8601String(),
            'reengagement_due' => $record->reengagement_due?->toIso8601String(),
            'advisor_capacity_released' => $record->advisor_capacity_released,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountingSummary(Client $client): array
    {
        $connections = AccountingConnection::query()
            ->with('latestFinancialSnapshot')
            ->where('client_id', $client->getKey())
            ->latest('connected_at')
            ->get();

        $connectedProviders = $connections
            ->filter(fn (AccountingConnection $connection): bool => $connection->connected())
            ->pluck('provider')
            ->unique()
            ->values()
            ->all();

        return [
            'providers' => collect(AccountingConnection::providerLabels())
                ->map(fn (string $label, string $provider): array => [
                    'provider' => $provider,
                    'label' => $label,
                    'connected' => in_array($provider, $connectedProviders, true),
                    'connect_url' => route('advisor.clients.accounting.connect', [$client, $provider], absolute: false),
                ])
                ->values()
                ->all(),
            'connections' => $connections
                ->map(fn (AccountingConnection $connection): array => [
                    'id' => $connection->id,
                    'provider' => $connection->provider,
                    'provider_label' => $connection->providerLabel(),
                    'external_tenant_id' => $connection->external_tenant_id,
                    'status' => $connection->status,
                    'connected' => $connection->connected(),
                    'connected_at' => $connection->connected_at?->toIso8601String(),
                    'revoked_at' => $connection->revoked_at?->toIso8601String(),
                    'last_snapshot_at' => $connection->last_snapshot_at?->toIso8601String(),
                    'pull_url' => route('advisor.clients.accounting.pull', [$client, $connection], absolute: false),
                    'revoke_url' => route('advisor.clients.accounting.revoke', [$client, $connection], absolute: false),
                    'latest_snapshot' => $this->financialSnapshotSummary($connection->latestFinancialSnapshot),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function financialSnapshotSummary(?FinancialSnapshot $snapshot): ?array
    {
        if (! $snapshot instanceof FinancialSnapshot) {
            return null;
        }

        return [
            'id' => $snapshot->id,
            'period_start' => $snapshot->period_start?->toDateString(),
            'period_end' => $snapshot->period_end?->toDateString(),
            'source' => $snapshot->source,
            'source_badge' => $snapshot->source_badge,
            'degraded' => $snapshot->degraded,
            'metrics' => $snapshot->metrics ?? [],
            'pulled_at' => $snapshot->pulled_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function analysisFindingSummaries(Client $client): array
    {
        return AnalysisFinding::query()
            ->with(['run', 'feedback.advisor'])
            ->where('client_id', $client->getKey())
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (AnalysisFinding $finding): array {
                $run = $finding->run;

                return [
                    'id' => $finding->id,
                    'analysis_run_id' => $finding->analysis_run_id,
                    'module' => $run?->module?->value,
                    'status' => $run?->status,
                    'lens' => $finding->lens->value,
                    'severity' => $finding->severity->value,
                    'title' => $finding->title,
                    'body' => $finding->body,
                    'attributions' => $finding->attributions ?? [],
                    'document_support' => $finding->document_support,
                    'uncertainty' => $finding->uncertainty?->value,
                    'data_quality_disclaimer' => $finding->data_quality_disclaimer,
                    'created_at' => $finding->created_at?->toIso8601String(),
                    'feedback_store_url' => route('advisor.analysis-findings.feedback.store', $finding, absolute: false),
                    'feedback_count' => $finding->feedback->count(),
                    'latest_feedback' => $finding->feedback
                        ->sortByDesc('created_at')
                        ->take(3)
                        ->map(fn (AnalysisFeedback $feedback): array => [
                            'id' => $feedback->id,
                            'decision' => $feedback->decision,
                            'rating' => $feedback->rating,
                            'note' => $feedback->note,
                            'has_correction' => is_string($feedback->corrected_body) && trim($feedback->corrected_body) !== '',
                            'created_at' => $feedback->created_at?->toIso8601String(),
                            'advisor_name' => $feedback->advisor?->name,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function wellbeingTrend(Client $client, User $user): ?array
    {
        if (! $this->canViewWellbeing($client, $user)) {
            return null;
        }

        return WellbeingCheckin::query()
            ->where('client_id', $client->getKey())
            ->with('user')
            ->latest('period_start')
            ->limit(12)
            ->get()
            ->sortBy('period_start')
            ->map(fn (WellbeingCheckin $checkin): array => [
                'id' => $checkin->id,
                'period_start' => $checkin->period_start?->toDateString(),
                'business_confidence' => $checkin->business_confidence,
                'personal_coping' => $checkin->personal_coping,
                'notes' => $checkin->notes,
                'submitted_at' => $checkin->submitted_at?->toIso8601String(),
                'submitted_by' => $checkin->user?->name,
            ])
            ->values()
            ->all();
    }

    private function canViewWellbeing(Client $client, User $user): bool
    {
        if ($user->user_type === User::TYPE_SUPER_ADMIN) {
            return true;
        }

        if ($user->user_type !== User::TYPE_ADVISOR) {
            return false;
        }

        return ClientTeamMember::query()
            ->where('client_id', $client->getKey())
            ->where('user_id', $user->getKey())
            ->where('role', 'lead_advisor')
            ->exists();
    }
}
