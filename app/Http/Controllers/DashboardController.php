<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Enums\Permission;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\EconomicIndicator;
use App\Models\ExchangeRate;
use App\Models\IntegrationHealthSample;
use App\Models\LearningUpdate;
use App\Models\MessageThread;
use App\Models\Proposal;
use App\Models\ProspectLead;
use App\Models\RedFlag;
use App\Models\Scenario;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Analytics\FunnelTracker;
use App\Services\Dashboards\ClientEngagementScorer;
use App\Services\Dashboards\EconomicExposureMapper;
use App\Services\Dashboards\PaymentStatusReport;
use App\Services\EconomicData\EconomicIndicatorRefresher;
use App\Services\Panels\Coach\SignalDetector;
use App\Services\Pv\PvWaterfallBuilder;
use App\Services\Questionnaires\QuestionnaireOptimisationLayer;
use App\Services\Reports\PracticeHealthReport;
use App\Services\Terms\TermsAcceptanceGate;
use App\Services\Wellbeing\WellbeingTrendAnalytics;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        TermsAcceptanceGate $termsGate,
        ClientEngagementScorer $engagementScorer,
        EconomicExposureMapper $economicExposure,
        PaymentStatusReport $paymentStatus,
        PvWaterfallBuilder $pvWaterfalls,
        FunnelTracker $funnels,
        PracticeHealthReport $practiceHealth,
        QuestionnaireOptimisationLayer $questionnaireOptimisation,
        WellbeingTrendAnalytics $wellbeing,
        SignalDetector $coachSignals,
    ): Response|RedirectResponse {
        $user = $request->user();

        if ($user instanceof User && $user->user_type === User::TYPE_ENTREPRENEUR) {
            return to_route('portal.entrepreneur.dashboard');
        }

        if (
            $user instanceof User
            && in_array($user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true)
            && $user->accessibleClientIds() !== []
        ) {
            return to_route('portal.dashboard');
        }

        if ($user instanceof User && $this->usesAdvisorDashboard($user)) {
            return Inertia::render('advisor/Dashboard', $this->advisorDashboardPayload($user, $termsGate, $engagementScorer, $economicExposure, $paymentStatus, $pvWaterfalls, $funnels, $practiceHealth, $questionnaireOptimisation, $wellbeing, $coachSignals));
        }

        return Inertia::render('dashboard');
    }

    /**
     * @return array<string, mixed>
     */
    private function advisorDashboardPayload(
        User $user,
        TermsAcceptanceGate $termsGate,
        ClientEngagementScorer $engagementScorer,
        EconomicExposureMapper $economicExposure,
        PaymentStatusReport $paymentStatus,
        PvWaterfallBuilder $pvWaterfalls,
        FunnelTracker $funnels,
        PracticeHealthReport $practiceHealth,
        QuestionnaireOptimisationLayer $questionnaireOptimisation,
        WellbeingTrendAnalytics $wellbeing,
        SignalDetector $coachSignals,
    ): array {
        $clientIds = $this->visibleClientIds($user);
        $pvWaterfall = $pvWaterfalls->forClients($clientIds);
        $wellbeingAnalytics = $wellbeing->forClientIds($clientIds);
        $coachSignalsPayload = $coachSignals->advisorPanel($user);
        $funnelAnalytics = $funnels->summary($clientIds);

        return [
            'clientsHealth' => $this->clientsHealth($clientIds, $engagementScorer),
            'redFlags' => $this->redFlags($clientIds),
            'documentVerificationFlags' => $this->documentVerificationFlags($clientIds),
            'pendingTermsReacceptance' => $this->pendingTermsReacceptance($clientIds, $termsGate),
            'prospectInbox' => $this->prospectInbox(),
            'integrationHealth' => $this->integrationHealth($user),
            'economicIndicators' => $this->economicIndicators($clientIds, $economicExposure),
            'paymentStatus' => $paymentStatus->forClientIds($clientIds),
            'pvWaterfall' => [
                ...$pvWaterfall,
                'methodology_id' => 'pv.waterfall',
            ],
            'practiceHealth' => $practiceHealth->forClientIds($clientIds),
            'proposalStatus' => $this->proposalStatus($clientIds),
            'questionnaireOptimisation' => $questionnaireOptimisation->summary(),
            'wellbeingAnalytics' => [
                ...$wellbeingAnalytics,
                'methodology_id' => 'wellbeing.trend',
            ],
            'coachSignals' => [
                ...$coachSignalsPayload,
                'methodology_id' => 'coach.signal_mapping',
            ],
            'scenarioPlanning' => $this->scenarioPlanning($clientIds),
            'funnelAnalytics' => [
                ...$funnelAnalytics,
                'methodology_id' => 'funnel.drop_off',
            ],
        ];
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    private function proposalStatus(?array $clientIds): array
    {
        if ($clientIds === [] || ! Schema::hasTable('proposals')) {
            return $this->emptyProposalStatus();
        }

        $base = Proposal::query();

        if (is_array($clientIds)) {
            $base->whereIn('client_id', $clientIds);
        }

        $statusCounts = (clone $base)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
        $statuses = [];

        foreach (ProposalStatus::lifecycleStatuses() as $status) {
            $statuses[$status->value] = (int) ($statusCounts[$status->value] ?? 0);
        }

        $expiryBase = (clone $base)
            ->where('status', ProposalStatus::Released->value)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(14)]);
        $expiringSoon = (clone $expiryBase)->count();
        $expiryAlerts = $expiryBase
            ->with('client')
            ->orderBy('expires_at')
            ->limit(8)
            ->get()
            ->map(fn (Proposal $proposal): array => [
                'id' => $proposal->id,
                'client_id' => $proposal->client_id,
                'client_name' => $proposal->client?->legal_name,
                'version' => $proposal->version,
                'status' => $proposal->status->value,
                'expires_at' => $proposal->expires_at?->toIso8601String(),
                'client_url' => route('advisor.clients.show', $proposal->client_id, absolute: false),
            ])
            ->values()
            ->all();

        return [
            'summary' => [
                'total' => array_sum($statuses),
                'released' => $statuses[ProposalStatus::Released->value],
                'expiring_soon' => $expiringSoon,
                'expired' => $statuses[ProposalStatus::Expired->value],
            ],
            'statuses' => $statuses,
            'expiry_alerts' => $expiryAlerts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyProposalStatus(): array
    {
        $statuses = [];

        foreach (ProposalStatus::lifecycleStatuses() as $status) {
            $statuses[$status->value] = 0;
        }

        return [
            'summary' => [
                'total' => 0,
                'released' => 0,
                'expiring_soon' => 0,
                'expired' => 0,
            ],
            'statuses' => $statuses,
            'expiry_alerts' => [],
        ];
    }

    private function usesAdvisorDashboard(User $user): bool
    {
        return in_array($user->user_type, [
            User::TYPE_SUPER_ADMIN,
            User::TYPE_ADVISOR,
            User::TYPE_JUNIOR_ADVISOR,
            User::TYPE_ENTREPRENEUR_MENTOR,
        ], true);
    }

    /**
     * A null client id list means "all clients" for super-admins.
     *
     * @return array<int, string>|null
     */
    private function visibleClientIds(User $user): ?array
    {
        if ($user->user_type === User::TYPE_SUPER_ADMIN) {
            return null;
        }

        return $user->accessibleClientIds();
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    private function redFlags(?array $clientIds): array
    {
        if ($clientIds === []) {
            return [
                'summary' => [
                    'open' => 0,
                    'unacknowledged' => 0,
                ],
                'items' => [],
            ];
        }

        $query = RedFlag::query()
            ->whereNull('resolved_at');

        if (is_array($clientIds)) {
            $query->whereIn('client_id', $clientIds);
        }

        $open = (clone $query)->count();
        $unacknowledged = (clone $query)->whereNull('acknowledged_at')->count();
        $flags = $query
            ->with(['client', 'finding.run'])
            ->latest('surfaced_at')
            ->limit(12)
            ->get();

        return [
            'summary' => [
                'open' => $open,
                'unacknowledged' => $unacknowledged,
            ],
            'items' => $flags
                ->map(fn (RedFlag $flag): array => [
                    'id' => $flag->id,
                    'client_id' => $flag->client_id,
                    'client_name' => $flag->client?->legal_name,
                    'analysis_finding_id' => $flag->analysis_finding_id,
                    'module' => $flag->finding?->run?->module?->value,
                    'category' => $flag->category,
                    'severity' => $flag->severity,
                    'headline' => $flag->headline,
                    'detail' => $flag->detail,
                    'trigger' => $this->redFlagTrigger($flag),
                    'surfaced_at' => $flag->surfaced_at?->toIso8601String(),
                    'acknowledged_at' => $flag->acknowledged_at?->toIso8601String(),
                    'acknowledge_url' => route('advisor.red-flags.acknowledge', $flag, absolute: false),
                    'resolve_url' => route('advisor.red-flags.resolve', $flag, absolute: false),
                    'finding_url' => $this->redFlagFindingUrl($flag),
                    'client_url' => route('advisor.clients.show', $flag->client_id, absolute: false),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{summary:string, source_reference:string}|null
     */
    private function redFlagTrigger(RedFlag $flag): ?array
    {
        $attributions = $flag->finding?->attributions ?? [];

        foreach ($attributions as $attribution) {
            if (! is_array($attribution)) {
                continue;
            }

            $claim = trim((string) ($attribution['claim'] ?? ''));
            $sourceReference = trim((string) ($attribution['source_reference'] ?? ''));

            if ($claim !== '' && $sourceReference !== '') {
                return [
                    'summary' => $claim,
                    'source_reference' => $sourceReference,
                ];
            }
        }

        return null;
    }

    private function redFlagFindingUrl(RedFlag $flag): ?string
    {
        $findingId = trim((string) ($flag->analysis_finding_id ?? ''));

        if ($findingId === '') {
            return null;
        }

        return route('advisor.clients.show', [
            'client' => $flag->client_id,
            'focus' => 'analysis',
            'highlight' => $findingId,
        ], absolute: false);
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    private function clientsHealth(?array $clientIds, ClientEngagementScorer $engagementScorer): array
    {
        $clients = $this->scopedClientQuery($clientIds)
            ->orderBy('legal_name')
            ->limit(20)
            ->get();

        $idsForActivity = $clients
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();

        $flagCounts = $this->openDocumentFlagCounts($idsForActivity);
        $latestDocumentActivity = $this->latestDocumentActivity($idsForActivity);
        $latestMessageActivity = $this->latestMessageActivity($idsForActivity);
        $engagementScores = $engagementScorer->scoreMany($clients);
        $engagementCounts = collect($engagementScores)->countBy('level');

        $green = (int) ($engagementCounts['green'] ?? 0);
        $amber = (int) ($engagementCounts['amber'] ?? 0);
        $red = (int) ($engagementCounts['red'] ?? 0);

        return [
            'methodology_id' => 'engagement.score',
            'summary' => [
                'total' => $clients->count(),
                'high' => $green,
                'medium' => $amber,
                'low' => $red,
                'insufficient' => 0,
                'needs_attention' => $red,
            ],
            'clients' => $clients
                ->map(function (Client $client) use ($engagementScores, $flagCounts, $latestDocumentActivity, $latestMessageActivity): array {
                    $clientId = (string) $client->getKey();

                    return $this->clientSummary(
                        $client,
                        $engagementScores[$clientId],
                        (int) ($flagCounts[$clientId] ?? 0),
                        $this->latestActivityFor(
                            $client,
                            $latestDocumentActivity[$clientId] ?? null,
                            $latestMessageActivity[$clientId] ?? null,
                        ),
                    );
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return Builder<Client>
     */
    private function scopedClientQuery(?array $clientIds): Builder
    {
        $query = Client::query();

        if (is_array($clientIds)) {
            if ($clientIds === []) {
                return $query->whereRaw('1 = 0');
            }

            $query->whereIn('id', $clientIds);
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $clientIds
     * @return array<string, int>
     */
    private function openDocumentFlagCounts(array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        /** @var array<string, int> $counts */
        $counts = DocumentVerification::query()
            ->outstandingFlags()
            ->whereIn('client_id', $clientIds)
            ->select('client_id', DB::raw('count(*) as aggregate'))
            ->groupBy('client_id')
            ->pluck('aggregate', 'client_id')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return $counts;
    }

    /**
     * @param  array<int, string>  $clientIds
     * @return array<string, Carbon>
     */
    private function latestDocumentActivity(array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        return Document::query()
            ->whereIn('client_id', $clientIds)
            ->select('client_id', DB::raw('max(created_at) as last_activity_at'))
            ->groupBy('client_id')
            ->pluck('last_activity_at', 'client_id')
            ->map(fn ($value): ?Carbon => $this->carbon($value))
            ->filter()
            ->all();
    }

    /**
     * @param  array<int, string>  $clientIds
     * @return array<string, Carbon>
     */
    private function latestMessageActivity(array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        return MessageThread::query()
            ->whereIn('client_id', $clientIds)
            ->select('client_id', DB::raw('max(last_activity_at) as last_activity_at'))
            ->groupBy('client_id')
            ->pluck('last_activity_at', 'client_id')
            ->map(fn ($value): ?Carbon => $this->carbon($value))
            ->filter()
            ->all();
    }

    private function carbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    private function latestActivityFor(Client $client, ?Carbon ...$activities): ?Carbon
    {
        $candidates = collect([$this->carbon($client->updated_at), ...$activities])
            ->filter();

        if ($candidates->isEmpty()) {
            return null;
        }

        /** @var Carbon $latest */
        $latest = $candidates->sortDesc()->first();

        return $latest;
    }

    /**
     * @return array<string, mixed>
     */
    private function clientSummary(Client $client, array $engagement, int $openDocumentFlags, ?Carbon $latestActivity): array
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::from((string) $client->engagement_type);
        $status = $client->status instanceof ClientStatus
            ? $client->status
            : ClientStatus::from((string) ($client->status ?? ClientStatus::ACTIVE->value));

        return [
            'id' => $client->id,
            'legal_name' => $client->legal_name,
            'trading_name' => $client->trading_name,
            'engagement_type' => $engagementType->value,
            'engagement_type_label' => $engagementType->label(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'data_quality' => $client->data_quality,
            'engagement' => [
                ...$engagement,
                'methodology_id' => 'engagement.score',
                'drill_url' => route('advisor.clients.show', [
                    'client' => $client,
                    'focus' => $engagement['focus_section'],
                ], absolute: false),
            ],
            'open_document_flags_count' => $openDocumentFlags,
            'last_activity_at' => $latestActivity?->toIso8601String(),
            'show_url' => route('advisor.clients.show', $client, absolute: false),
        ];
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return array<int, array<string, mixed>>
     */
    private function documentVerificationFlags(?array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        $query = DocumentVerification::query()
            ->outstandingFlags()
            ->with(['client', 'document.client'])
            ->latest()
            ->limit(25);

        if (is_array($clientIds)) {
            $query->whereIn('client_id', $clientIds);
        }

        return $query
            ->get()
            ->map(fn (DocumentVerification $verification): array => [
                'id' => $verification->id,
                'outcome' => $verification->outcome,
                'claim_text' => $verification->claim_text,
                'explanation' => $verification->explanation,
                'client_explanation' => $verification->clientFacingExplanation(),
                'client_name' => $verification->client?->legal_name ?? $verification->document?->client?->legal_name,
                'document_name' => $verification->document?->original_filename,
                'created_at' => $verification->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    private function pendingTermsReacceptance(?array $clientIds, TermsAcceptanceGate $termsGate): array
    {
        $latestVersion = $termsGate->latestPublishedVersion();

        if (! $latestVersion instanceof TermsVersion || $clientIds === []) {
            return [
                'latest_version' => $this->termsVersionSummary($latestVersion),
                'total' => 0,
                'items' => [],
            ];
        }

        $query = ClientTeamMember::query()
            ->with(['client', 'user'])
            ->whereHas('user', function (Builder $query): void {
                $query->whereIn('user_type', [
                    User::TYPE_CLIENT_PRIMARY,
                    User::TYPE_CLIENT_TEAM,
                ]);
            })
            ->orderBy('created_at');

        if (is_array($clientIds)) {
            $query->whereIn('client_id', $clientIds);
        }

        $pending = $query
            ->get()
            ->filter(fn (ClientTeamMember $member): bool => $member->user instanceof User
                && $termsGate->requiresAcceptance($member->user));

        return [
            'latest_version' => $this->termsVersionSummary($latestVersion),
            'total' => $pending->count(),
            'items' => $pending
                ->take(8)
                ->map(fn (ClientTeamMember $member): array => [
                    'id' => $member->id,
                    'client_id' => $member->client_id,
                    'client_name' => $member->client?->legal_name,
                    'user_id' => $member->user_id,
                    'user_name' => $member->user?->name,
                    'user_email' => $member->user?->email,
                    'role' => $member->role,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function termsVersionSummary(?TermsVersion $termsVersion): ?array
    {
        if (! $termsVersion instanceof TermsVersion) {
            return null;
        }

        return [
            'id' => $termsVersion->id,
            'version' => $termsVersion->version,
            'published_at' => $termsVersion->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prospectInbox(): array
    {
        if (! Schema::hasTable('prospect_leads')) {
            return [
                'total' => 0,
                'triage_enabled' => false,
                'index_url' => route('advisor.prospects.index', absolute: false),
                'items' => [],
            ];
        }

        $hasStatus = Schema::hasColumn('prospect_leads', 'status');
        $leads = ProspectLead::query()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ProspectLead $lead): array => [
                'id' => $lead->id,
                'name' => $lead->name,
                'email' => $lead->email,
                'company' => $lead->company,
                'source' => $lead->source,
                'status' => $lead->status ?? ProspectLead::STATUS_NEW,
                'created_at' => $lead->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'total' => ProspectLead::query()->count(),
            'triage_enabled' => $hasStatus,
            'index_url' => route('advisor.prospects.index', absolute: false),
            'items' => $leads,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function integrationHealth(User $user): array
    {
        if (! $user->can(Permission::INTEGRATION_HEALTH_VIEW->value)) {
            return $this->emptyIntegrationHealth();
        }

        if (! Schema::hasTable('integration_health_samples')) {
            return $this->emptyIntegrationHealth();
        }

        $samples = IntegrationHealthSample::query()
            ->latest('window_end')
            ->limit(100)
            ->get()
            ->unique('service')
            ->values();

        return [
            'methodology_id' => 'integration.health.banding',
            'summary' => [
                'total' => $samples->count(),
                'green' => $samples->where('health', IntegrationHealthSample::HEALTH_GREEN)->count(),
                'amber' => $samples->where('health', IntegrationHealthSample::HEALTH_AMBER)->count(),
                'red' => $samples->where('health', IntegrationHealthSample::HEALTH_RED)->count(),
            ],
            'index_url' => route('admin.integration-health.index', absolute: false),
            'services' => $samples
                ->map(fn (IntegrationHealthSample $sample): array => [
                    'id' => $sample->id,
                    'service' => $sample->service,
                    'health' => $sample->health,
                    'success_rate' => $sample->success_rate,
                    'p95_latency_ms' => $sample->p95_latency_ms,
                    'window_end' => $sample->window_end?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyIntegrationHealth(): array
    {
        return [
            'methodology_id' => 'integration.health.banding',
            'summary' => [
                'total' => 0,
                'green' => 0,
                'amber' => 0,
                'red' => 0,
            ],
            'index_url' => null,
            'services' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function economicIndicators(?array $clientIds, EconomicExposureMapper $economicExposure): array
    {
        if (! Schema::hasTable('economic_indicators') || ! Schema::hasTable('exchange_rates')) {
            return $this->emptyEconomicIndicators();
        }

        $indicatorOrder = [
            EconomicIndicator::OCR,
            EconomicIndicator::CPI_ANNUAL,
            EconomicIndicator::GDP_QUARTERLY,
            EconomicIndicator::UNEMPLOYMENT_RATE,
            EconomicIndicator::MINIMUM_WAGE,
            EconomicIndicator::LIVING_WAGE,
        ];

        $indicators = EconomicIndicator::query()
            ->whereIn('indicator', $indicatorOrder)
            ->latest('fetched_at')
            ->limit(100)
            ->get()
            ->unique('indicator')
            ->sortBy(function (EconomicIndicator $indicator) use ($indicatorOrder): int {
                $position = array_search($indicator->indicator, $indicatorOrder, true);

                return $position === false ? 999 : (int) $position;
            })
            ->values();

        $exchangeRates = ExchangeRate::query()
            ->where('base_currency', 'NZD')
            ->whereIn('quote_currency', ['USD', 'AUD'])
            ->latest('fetched_at')
            ->limit(20)
            ->get()
            ->unique(fn (ExchangeRate $rate): string => $rate->base_currency.'/'.$rate->quote_currency)
            ->values();

        $alerts = Schema::hasTable('learning_updates')
            ? LearningUpdate::query()
                ->where('layer_id', EconomicIndicatorRefresher::LAYER_ID)
                ->where('status', LearningUpdate::STATUS_DETECTED)
                ->where('source->type', 'economic_indicator_auto_update')
                ->latest()
                ->limit(3)
                ->get()
            : collect();

        $latestFetchedAt = $indicators
            ->pluck('fetched_at')
            ->merge($exchangeRates->pluck('fetched_at'))
            ->filter()
            ->sortDesc()
            ->first();

        return [
            'methodology_id' => 'economic.exposure',
            'summary' => [
                'indicators' => $indicators->count(),
                'exchange_rates' => $exchangeRates->count(),
                'change_alerts' => $alerts->count(),
                'latest_fetched_at' => $latestFetchedAt instanceof Carbon ? $latestFetchedAt->toIso8601String() : null,
            ],
            'indicators' => $indicators
                ->map(function (EconomicIndicator $indicator) use ($clientIds, $economicExposure): array {
                    $previous = $this->previousIndicator($indicator);

                    return [
                        'id' => $indicator->id,
                        'indicator' => $indicator->indicator,
                        'label' => $indicator->label,
                        'value' => $indicator->value,
                        'unit' => $indicator->unit,
                        'period_date' => $indicator->period_date?->toDateString(),
                        'source' => $indicator->source,
                        'source_badge' => $indicator->source_badge,
                        'degraded' => $indicator->degraded,
                        'fetched_at' => $indicator->fetched_at?->toIso8601String(),
                        ...$this->indicatorTrend($indicator, $previous),
                        'exposure' => $economicExposure->forIndicator($indicator->indicator, $clientIds),
                    ];
                })
                ->all(),
            'exchange_rates' => $exchangeRates
                ->map(function (ExchangeRate $rate) use ($clientIds, $economicExposure): array {
                    $previous = $this->previousExchangeRate($rate);

                    return [
                        'id' => $rate->id,
                        'base_currency' => $rate->base_currency,
                        'quote_currency' => $rate->quote_currency,
                        'rate' => $rate->rate,
                        'rate_date' => $rate->rate_date?->toDateString(),
                        'source' => $rate->source,
                        'source_badge' => $rate->source_badge,
                        'degraded' => $rate->degraded,
                        'fetched_at' => $rate->fetched_at?->toIso8601String(),
                        ...$this->exchangeRateTrend($rate, $previous),
                        'exposure' => $economicExposure->forExchangeRate($rate->base_currency, $rate->quote_currency, $clientIds),
                    ];
                })
                ->all(),
            'alerts' => $alerts
                ->map(fn (LearningUpdate $update): array => [
                    'id' => $update->id,
                    'summary' => $update->summary,
                    'created_at' => $update->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    private function previousIndicator(EconomicIndicator $indicator): ?EconomicIndicator
    {
        return EconomicIndicator::query()
            ->where('indicator', $indicator->indicator)
            ->where('source', $indicator->source)
            ->where('period_date', '<', $indicator->period_date)
            ->orderByDesc('period_date')
            ->latest('fetched_at')
            ->first();
    }

    /**
     * @return array{previous_value:float|null, change_abs:float|null, change_pct:float|null, direction:string}
     */
    private function indicatorTrend(EconomicIndicator $current, ?EconomicIndicator $previous): array
    {
        if (! $previous instanceof EconomicIndicator) {
            return [
                'previous_value' => null,
                'change_abs' => null,
                'change_pct' => null,
                'direction' => 'none',
            ];
        }

        $change = round((float) $current->value - (float) $previous->value, 4);

        return [
            'previous_value' => $previous->value,
            'change_abs' => $change,
            'change_pct' => $this->percentChange((float) $previous->value, $change),
            'direction' => $this->directionFor($change),
        ];
    }

    private function previousExchangeRate(ExchangeRate $rate): ?ExchangeRate
    {
        return ExchangeRate::query()
            ->where('base_currency', $rate->base_currency)
            ->where('quote_currency', $rate->quote_currency)
            ->where('source', $rate->source)
            ->where('rate_date', '<', $rate->rate_date)
            ->orderByDesc('rate_date')
            ->latest('fetched_at')
            ->first();
    }

    /**
     * @return array{previous_rate:float|null, change_abs:float|null, change_pct:float|null, direction:string}
     */
    private function exchangeRateTrend(ExchangeRate $current, ?ExchangeRate $previous): array
    {
        if (! $previous instanceof ExchangeRate) {
            return [
                'previous_rate' => null,
                'change_abs' => null,
                'change_pct' => null,
                'direction' => 'none',
            ];
        }

        $change = round((float) $current->rate - (float) $previous->rate, 8);

        return [
            'previous_rate' => $previous->rate,
            'change_abs' => $change,
            'change_pct' => $this->percentChange((float) $previous->rate, $change),
            'direction' => $this->directionFor($change),
        ];
    }

    private function percentChange(float $previous, float $change): ?float
    {
        if (abs($previous) < 0.00000001) {
            return null;
        }

        return round(($change / abs($previous)) * 100, 2);
    }

    private function directionFor(float $change): string
    {
        return match (true) {
            $change > 0.00000001 => 'up',
            $change < -0.00000001 => 'down',
            default => 'flat',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyEconomicIndicators(): array
    {
        return [
            'methodology_id' => 'economic.exposure',
            'summary' => [
                'indicators' => 0,
                'exchange_rates' => 0,
                'change_alerts' => 0,
                'latest_fetched_at' => null,
            ],
            'indicators' => [],
            'exchange_rates' => [],
            'alerts' => [],
        ];
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    private function scenarioPlanning(?array $clientIds): array
    {
        if ($clientIds === [] || ! Schema::hasTable('scenarios')) {
            return [
                'summary' => [
                    'scenarios' => 0,
                    'clients' => 0,
                ],
                'items' => [],
            ];
        }

        $query = Scenario::query()
            ->with('client')
            ->orderByDesc('created_at')
            ->limit(15);

        $countQuery = Scenario::query();

        if (is_array($clientIds)) {
            $query->whereIn('client_id', $clientIds);
            $countQuery->whereIn('client_id', $clientIds);
        }

        $items = $query
            ->get()
            ->sortBy('position')
            ->map(fn (Scenario $scenario): array => [
                'id' => $scenario->id,
                'client_id' => $scenario->client_id,
                'client_name' => $scenario->client?->legal_name,
                'name' => $scenario->name,
                'kind' => $scenario->kind,
                'pv_impact' => $scenario->pv_impact,
                'position' => $scenario->position,
                'is_client_visible' => $scenario->is_client_visible,
            ])
            ->values()
            ->all();

        return [
            'summary' => [
                'scenarios' => $countQuery->count(),
                'clients' => (clone $countQuery)->select('client_id')->distinct()->count('client_id'),
            ],
            'items' => $items,
        ];
    }
}
