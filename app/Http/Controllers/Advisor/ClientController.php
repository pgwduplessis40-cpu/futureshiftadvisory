<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Actions\Clients\PopulateFromNzbn;
use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\ProposalStatus;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Models\AccountingConnection;
use App\Models\AnalysisFeedback;
use App\Models\AnalysisFinding;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\DdEngagement;
use App\Models\FeeCalculation;
use App\Models\FinancialSnapshot;
use App\Models\GovernanceReviewFinding;
use App\Models\IndustryBriefing;
use App\Models\KnowledgeAssessment;
use App\Models\Meeting;
use App\Models\NpoEngagement;
use App\Models\PreMeetingBrief;
use App\Models\Proposal;
use App\Models\Report;
use App\Models\ReportSectionComment;
use App\Models\ReportSectionRevision;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Models\WellbeingCheckin;
use App\Services\Audit\AuditWriter;
use App\Services\Budgets\StrategicBudgetService;
use App\Services\Clients\AdvisorClientCapacity;
use App\Services\Conflicts\ConflictDeclarer;
use App\Services\Dashboards\EconomicExposureMapper;
use App\Services\Dashboards\PaymentStatusReport;
use App\Services\DataQuality\DataQualityScorer;
use App\Services\Dd\DataRoom;
use App\Services\Dd\DdOnboarding;
use App\Services\Fees\ProposalPricingTerms;
use App\Services\Goals\GoalTracker;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Npo\GovernanceReviewConversion;
use App\Services\Npo\NpoEngagementConfiguration;
use App\Services\Npo\NpoEngagementSetup;
use App\Services\Npo\NpoFunderMonitor;
use App\Services\Npo\NpoHealthScorer;
use App\Services\Npo\NpoValueCalculator;
use App\Services\Npo\SocialEnterpriseAssessment;
use App\Services\Proposals\ProposalBrief;
use App\Services\Security\InviteIssuer;
use App\Services\StandardAdvisory\StandardAdvisoryWorkflow;
use App\Services\StrategicPlans\StrategicPlanService;
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
        private readonly ConflictDeclarer $conflicts,
        private readonly DataQualityScorer $dataQuality,
        private readonly GoalTracker $goals,
        private readonly DdOnboarding $ddOnboarding,
        private readonly DataRoom $dataRoom,
        private readonly NpoEngagementSetup $npoEngagements,
        private readonly IntegrationActivationResolver $integrations,
        private readonly ProposalBrief $proposalBriefs,
        private readonly ProposalPricingTerms $pricing,
    ) {}

    public function index(Request $request, EconomicExposureMapper $economicExposure): Response
    {
        Gate::authorize('viewAny', Client::class);

        $engagementType = $request->query('engagement_type');
        $engagementType = is_string($engagementType) ? trim($engagementType) : null;
        $exposedTo = $request->query('exposed_to');
        $exposedTo = is_string($exposedTo) ? trim($exposedTo) : null;
        $engagementFilter = null;
        $filter = null;
        $user = $request->user();
        $showAdvisorAssignments = $user instanceof User && $user->user_type === User::TYPE_SUPER_ADMIN;
        $isAdvisor = $user instanceof User && in_array($user->user_type, [User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR], true);
        $canRequestTransfer = $isAdvisor;
        $clientIds = $isAdvisor
            ? $user->accessibleClientIds()
            : null;
        $query = Client::query()->latest();

        if ($showAdvisorAssignments) {
            $query->with([
                'teamMembers' => fn ($teamMembers) => $teamMembers
                    ->whereIn('role', ['lead_advisor', 'advisor'])
                    ->with(['user:id,name', 'advisorTeam:id,name']),
            ]);
        }

        if (is_array($clientIds)) {
            $clientIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('id', $clientIds);
        }

        if ($engagementType !== null && $engagementType !== '') {
            $engagement = EngagementType::tryFrom($engagementType);
            abort_unless($engagement instanceof EngagementType, 404);

            $query->where('engagement_type', $engagement->value);
            $engagementFilter = [
                'key' => $engagement->value,
                'label' => $this->engagementIndexLabel($engagement),
                'description' => $engagement->description(),
                'clear_url' => $this->clientsIndexUrl($request->query(), ['engagement_type']),
            ];
        }

        if ($exposedTo !== null && $exposedTo !== '') {
            abort_unless(in_array($exposedTo, $economicExposure->supportedFilterKeys(), true), 404);

            $exposure = $economicExposure->forKey($exposedTo, $clientIds);
            $query->whereIn('id', $exposure['client_ids']);
            $filter = [
                'key' => $exposure['key'],
                'label' => $exposure['label'],
                'exposed_count' => $exposure['exposed_count'],
                'unknown_count' => $exposure['unknown_count'],
                'clear_url' => $this->clientsIndexUrl($request->query(), ['exposed_to']),
            ];
        }

        return Inertia::render('advisor/clients/Index', [
            'clients' => $query
                ->limit(100)
                ->get()
                ->map(fn (Client $client): array => $this->clientSummary($client, $showAdvisorAssignments))
                ->values(),
            'engagementFilter' => $engagementFilter,
            'exposureFilter' => $filter,
            'showAdvisorAssignments' => $showAdvisorAssignments,
            'allocationUrl' => $showAdvisorAssignments
                ? route('admin.client-allocations.index', absolute: false)
                : null,
            'transferRequestUrl' => $canRequestTransfer
                ? route('advisor.client-transfers.index', absolute: false)
                : null,
        ]);
    }

    private function engagementIndexLabel(EngagementType $engagement): string
    {
        return match ($engagement) {
            EngagementType::STANDARD_ADVISORY => 'Advisory',
            EngagementType::NPO => 'NPOs',
            default => $engagement->label(),
        };
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<int, string>  $without
     */
    private function clientsIndexUrl(array $query = [], array $without = []): string
    {
        foreach ($without as $key) {
            unset($query[$key]);
        }

        $query = array_filter(
            $query,
            static fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '',
        );

        $url = route('advisor.clients.index', absolute: false);

        return $query === [] ? $url : $url.'?'.http_build_query($query);
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

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

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

    public function show(
        Request $request,
        Client $client,
        PaymentStatusReport $payments,
        GovernanceReviewConversion $npoConversion,
        NpoEngagementConfiguration $npoConfiguration,
        NpoHealthScorer $npoHealth,
        NpoFunderMonitor $npoFunders,
        NpoValueCalculator $npoValues,
        SocialEnterpriseAssessment $socialEnterprise,
        StandardAdvisoryWorkflow $standardAdvisory,
        StrategicBudgetService $strategicBudgets,
        StrategicPlanService $strategicPlans,
    ): Response {
        Gate::authorize('view', $client);
        $dataQuality = $this->dataQuality->score($client);
        $user = $request->user();
        $strategicBudget = $strategicBudgets->ensureForClient($client);

        return Inertia::render('advisor/clients/Show', [
            'client' => [
                ...$this->clientSummary($client),
                'data_quality' => $dataQuality->level,
                'data_quality_summary' => $dataQuality->toPayload(),
                'wellbeing_trend' => $user instanceof User ? $this->wellbeingTrend($client, $user) : null,
                'status_options' => ClientStatus::options(),
                'lifecycle_update_url' => route('advisor.clients.lifecycle.update', $client, absolute: false),
                'knowledge_assessment_store_url' => route('advisor.clients.knowledge-assessments.store', $client, absolute: false),
                'knowledge_draft_store_url' => route('advisor.clients.knowledge-drafts.store', $client, absolute: false),
                'latest_knowledge_assessment' => $this->latestKnowledgeAssessment($client),
                'goal_store_url' => route('advisor.clients.goals.store', $client, absolute: false),
                'goals' => $this->goals->dashboard($client, includeAdvisorActions: true),
                'proposal_store_url' => route('advisor.clients.proposals.store', $client, absolute: false),
                'proposal_expiry_days' => (int) config('proposals.expiry_days', 30),
                'fee_calculations' => $this->feeCalculationSummaries($client),
                'proposals' => $this->proposalSummaries($client),
                'business_health_recompute_url' => route('advisor.clients.health-radar.recompute', $client, absolute: false),
                'report_store_url' => route('advisor.clients.reports.store', $client, absolute: false),
                'reports' => $this->reportSummaries($client),
                'meeting_store_url' => route('advisor.clients.meetings.store', $client, absolute: false),
                'meetings' => $this->meetingSummaries($client),
                'industry_briefings' => $this->industryBriefingSummaries($client),
                'pre_meeting_briefs' => $this->preMeetingBriefSummaries($client),
                'address' => $client->address,
                'directors' => $client->directors ?? [],
                'registry_sources' => $client->registry_sources ?? [],
                'engagement_type_locked' => $client->engagementTypeIsLocked(),
                'offboarding' => $this->offboardingSummary($client),
                'accounting' => $this->accountingSummary($client),
                'payments' => $payments->forClient($client),
                'analysis_findings' => $this->analysisFindingSummaries($client, $request->query('highlight')),
                'standard_advisory' => $standardAdvisory->clientSummary($client),
                'strategic_budget' => $strategicBudgets->advisorPayload($strategicBudget),
                'strategic_plan' => $strategicPlans->advisorPayload($client),
                'proposal_budget_guard' => $strategicBudgets->proposalGuardPayload($strategicBudget),
                'due_diligence' => $this->dueDiligenceSummary($client),
                'npo_conversion' => $npoConversion->clientSummary($client),
                'npo_governance_review' => $this->npoGovernanceReviewSummary($client),
                'npo_configuration' => $npoConfiguration->clientSummary($client),
                'npo_health' => $npoHealth->clientSummary($client),
                'npo_funding' => $npoFunders->clientSummary($client),
                'npo_values' => $npoValues->clientSummary($client),
                'npo_social_enterprise' => $socialEnterprise->clientSummary($client),
                'created_at' => $client->created_at?->toIso8601String(),
            ],
            'screenShare' => [
                'connection_url' => route('advisor.clients.screen-share.connections.store', $client, absolute: false),
                'connection_heartbeat_url' => route('screen-share.connections.heartbeat', ['connection' => '__connection__'], absolute: false),
                'request_url' => route('advisor.clients.screen-share.sessions.store', $client, absolute: false),
                'ice_servers_url' => route('screen-share.sessions.ice-servers', ['session' => '__session__'], absolute: false),
                'active_url' => route('screen-share.sessions.active', ['session' => '__session__'], absolute: false),
                'signal_url' => route('screen-share.sessions.signal', ['session' => '__session__'], absolute: false),
                'pending_signals_url' => route('screen-share.sessions.pending-signals', ['session' => '__session__'], absolute: false),
                'heartbeat_url' => route('screen-share.sessions.heartbeat', ['session' => '__session__'], absolute: false),
                'end_url' => route('screen-share.sessions.end', ['session' => '__session__'], absolute: false),
                'heartbeat_seconds' => max(5, (int) config('screen-share.heartbeat_interval_seconds', 10)),
                'participants' => $client->teamMembers()
                    ->with('user')
                    ->get()
                    ->map(fn (ClientTeamMember $member): ?array => $member->user instanceof User
                        && in_array($member->user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true)
                        ? ['id' => (string) $member->user->getKey(), 'name' => $member->user->name]
                        : null)
                    ->filter()
                    ->values()
                    ->all(),
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
    private function npoGovernanceReviewSummary(Client $client): ?array
    {
        $engagement = NpoEngagement::query()
            ->where('client_id', $client->getKey())
            ->where('sub_type', NpoEngagementSubType::GovernanceReview->value)
            ->latest()
            ->first();

        if (! $engagement instanceof NpoEngagement) {
            return null;
        }

        $findings = GovernanceReviewFinding::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->orderByRaw("case severity when 'critical' then 0 when 'high' then 1 when 'medium' then 2 when 'low' then 3 else 4 end")
            ->latest('updated_at')
            ->orderBy('id')
            ->get();
        $pending = $findings->where('status', GovernanceReviewFinding::STATUS_PENDING_ADVISOR_REVIEW);
        $reviewed = $findings->where('status', GovernanceReviewFinding::STATUS_REVIEWED);

        return [
            'id' => $engagement->id,
            'run_url' => route('advisor.npo-engagements.governance-review.analysis', $engagement, absolute: false),
            'findings_count' => $findings->count(),
            'pending_review_count' => $pending->count(),
            'reviewed_count' => $reviewed->count(),
            'high_priority_count' => $findings
                ->filter(fn (GovernanceReviewFinding $finding): bool => in_array($finding->severity->value, ['critical', 'high'], true))
                ->count(),
            'can_generate_report' => $reviewed->isNotEmpty(),
            'findings' => $findings
                ->take(8)
                ->map(fn (GovernanceReviewFinding $finding): array => [
                    'id' => $finding->id,
                    'finding_key' => $finding->finding_key,
                    'category' => $finding->category,
                    'severity' => $finding->severity->value,
                    'title' => $finding->title,
                    'body' => $finding->body,
                    'status' => $finding->status,
                    'advisor_notes' => $finding->advisor_notes,
                    'review_url' => route('advisor.governance-review-findings.review', $finding, absolute: false),
                    'reviewed_at' => $finding->reviewed_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dueDiligenceSummary(Client $client): ?array
    {
        $engagement = DdEngagement::query()
            ->where('client_id', $client->getKey())
            ->latest()
            ->first();

        if (! $engagement instanceof DdEngagement) {
            return null;
        }

        return array_merge($this->ddOnboarding->targetPanel($engagement), [
            'data_room' => $this->dataRoom->summary($engagement),
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
     * @return array<int, array<string, mixed>>
     */
    private function feeCalculationSummaries(Client $client): array
    {
        return FeeCalculation::query()
            ->with('integrationScope')
            ->where('client_id', $client->getKey())
            ->whereDoesntHave('proposals')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (FeeCalculation $calculation): array => [
                'id' => $calculation->id,
                'method' => $calculation->method->value,
                'suggested_mid' => $calculation->suggested_mid,
                'roi_ratio' => $calculation->roi_ratio,
                'created_at' => $calculation->created_at?->toIso8601String(),
                'proposal_scope_summary' => $this->proposalScopeSummary($calculation),
            ])
            ->values()
            ->all();
    }

    private function proposalScopeSummary(FeeCalculation $calculation): ?string
    {
        if ($calculation->method !== FeeMethod::Integration || $calculation->integrationScope === null) {
            return null;
        }

        $scope = $calculation->integrationScope;
        $systems = collect($scope->systems ?? [])
            ->filter(static fn (mixed $system): bool => is_array($system));
        $systemNames = $systems
            ->mapWithKeys(fn (array $system): array => [
                (string) ($system['id'] ?? '') => (string) ($system['name'] ?? $system['vendor'] ?? 'System'),
            ]);
        $listedSystems = $systems
            ->map(fn (array $system): string => (string) ($system['name'] ?? $system['vendor'] ?? 'System'))
            ->filter()
            ->unique()
            ->take(8)
            ->implode(', ');
        $listedConnections = collect($scope->connections ?? [])
            ->filter(static fn (mixed $connection): bool => is_array($connection))
            ->map(function (array $connection) use ($systemNames): string {
                $from = $systemNames->get((string) ($connection['from_system'] ?? ''))
                    ?? str((string) ($connection['from_system'] ?? 'Source system'))->replace('_', ' ')->title()->toString();
                $to = $systemNames->get((string) ($connection['to_system'] ?? ''))
                    ?? str((string) ($connection['to_system'] ?? 'Target system'))->replace('_', ' ')->title()->toString();
                $direction = str((string) ($connection['direction'] ?? 'one_way'))->replace('_', ' ')->lower()->toString();

                return $from.' to '.$to.' ('.$direction.')';
            })
            ->take(8)
            ->implode('; ');
        $annualHours = (float) data_get($scope->computed, 'annual_hours_wasted', 0);
        $annualSavings = (float) data_get($scope->computed, 'annual_savings', 0);
        $delivery = match ($scope->delivery_mode) {
            'inhouse' => 'In-house',
            'lowcode' => 'Low-code',
            'partner' => 'Delivery partner',
            'mixed' => 'Mixed delivery',
            default => 'To be confirmed',
        };

        $parts = ['Design, build, test, and commission the agreed systems integrations.'];
        if ($listedSystems !== '') {
            $parts[] = 'Systems in scope: '.$listedSystems.'.';
        }
        if ($listedConnections !== '') {
            $parts[] = 'Connections in scope: '.$listedConnections.'.';
        }
        if ($annualHours > 0 || $annualSavings > 0) {
            $parts[] = sprintf(
                'The scoped outcome targets %s annual hours returned to the team and NZD %s in annual savings.',
                number_format($annualHours, 0),
                number_format($annualSavings, 0),
            );
        }
        $parts[] = 'Delivery model: '.$delivery.'.';

        return implode(' ', $parts);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function proposalSummaries(Client $client): array
    {
        return Proposal::query()
            ->with('feeCalculation')
            ->where('client_id', $client->getKey())
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (Proposal $proposal): array {
                $status = $proposal->status;
                $method = $proposal->feeCalculation?->method?->value ?? 'advisory';

                return [
                    'id' => $proposal->id,
                    'status' => $status->value,
                    'status_label' => str($status->value)->replace('_', ' ')->title()->toString(),
                    'version' => $proposal->version,
                    'fee_method_label' => str($method)->replace('_', ' ')->title()->toString(),
                    'brief' => $this->proposalBriefs->for($proposal),
                    'suggested_mid' => $this->pricing->payableMid($proposal),
                    'roi_ratio' => $proposal->roi_ratio,
                    'released_at' => $proposal->released_at?->toIso8601String(),
                    'expires_at' => $proposal->expires_at?->toIso8601String(),
                    'days_to_expiry' => $proposal->expires_at === null
                        ? null
                        : max(0, now()->startOfDay()->diffInDays($proposal->expires_at->copy()->startOfDay(), false)),
                    'pdf_byte_size' => $proposal->pdf_byte_size,
                    'can_release' => in_array($status, [ProposalStatus::Draft, ProposalStatus::Renewed], true),
                    'can_recall' => $status === ProposalStatus::Released,
                    'can_renew' => $status === ProposalStatus::Expired,
                    'release_url' => route('advisor.proposals.release', $proposal, absolute: false),
                    'recall_url' => route('advisor.proposals.recall', $proposal, absolute: false),
                    'renew_url' => route('advisor.proposals.renew', $proposal, absolute: false),
                    'view_url' => route('advisor.proposals.show', $proposal, absolute: false),
                    'download_url' => route('advisor.proposals.download', $proposal, absolute: false),
                    'strategic_plan_generate_url' => $status === ProposalStatus::Signed
                        ? route('advisor.proposals.strategic-plan.generate', $proposal, absolute: false)
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reportSummaries(Client $client): array
    {
        return Report::query()
            ->where('client_id', $client->getKey())
            ->latest('generated_at')
            ->limit(8)
            ->get()
            ->map(fn (Report $report): array => [
                'id' => $report->id,
                'type' => $report->type->value,
                'type_label' => $report->type->label(),
                'title' => $report->title,
                'generated_at' => $report->generated_at?->toIso8601String(),
                'pdf_byte_size' => $report->pdf_byte_size,
                'pptx_byte_size' => $report->pptx_byte_size,
                'view_url' => route('advisor.reports.download', $report, absolute: false),
                'download_url' => route('advisor.reports.download', $report, absolute: false),
                'pptx_url' => $report->pptx_path !== null
                    ? route('advisor.reports.pptx', $report, absolute: false)
                    : null,
                'review_status' => $report->review_status,
                'reviewed_at' => $report->reviewed_at?->toIso8601String(),
                'review_url' => route('advisor.reports.review', $report, absolute: false),
                'release_url' => $report->type === ReportType::Client
                    ? route('advisor.reports.release', $report, absolute: false)
                    : null,
                'can_review' => in_array($report->type, [
                    ReportType::Client,
                    ReportType::DueDiligence,
                    ReportType::Valuation,
                    ReportType::AcquisitionGoNoGo,
                    ReportType::Trajectory,
                    ReportType::SuccessionValueGap,
                    ReportType::FunderAccountability,
                    ReportType::ImpactSummary,
                ], true) && $report->review_status === 'pending_review',
                'section_count' => $report->sections()->count(),
                'revision_count' => ReportSectionRevision::query()
                    ->where('report_id', $report->getKey())
                    ->count(),
                'comment_count' => ReportSectionComment::query()
                    ->where('report_id', $report->getKey())
                    ->whereNull('resolved_at')
                    ->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function meetingSummaries(Client $client): array
    {
        return Meeting::query()
            ->with('preMeetingBrief')
            ->withCount('calendarEventMappings')
            ->where('client_id', $client->getKey())
            ->where('scheduled_at', '>=', now()->subDay())
            ->orderBy('scheduled_at')
            ->limit(8)
            ->get()
            ->map(fn (Meeting $meeting): array => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
                'location' => $meeting->location,
                'link' => $meeting->link,
                'attendees' => $meeting->attendees ?? [],
                'calendar_synced' => $meeting->calendar_event_mappings_count > 0,
                'brief_status' => $meeting->preMeetingBrief?->sent_at !== null
                    ? 'sent'
                    : ($meeting->preMeetingBrief instanceof PreMeetingBrief ? 'draft' : 'pending'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function industryBriefingSummaries(Client $client): array
    {
        return IndustryBriefing::query()
            ->where('client_id', $client->getKey())
            ->latest('period')
            ->limit(6)
            ->get()
            ->map(fn (IndustryBriefing $briefing): array => [
                'id' => $briefing->id,
                'period' => $briefing->period?->toDateString(),
                'body' => $briefing->body,
                'status' => $briefing->status,
                'reviewed_at' => $briefing->reviewed_at?->toIso8601String(),
                'sent_at' => $briefing->sent_at?->toIso8601String(),
                'review_url' => route('advisor.industry-briefings.review', $briefing, absolute: false),
                'can_review' => $briefing->status === IndustryBriefing::STATUS_DRAFT,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function preMeetingBriefSummaries(Client $client): array
    {
        return PreMeetingBrief::query()
            ->with('meeting')
            ->where('client_id', $client->getKey())
            ->latest('meeting_at')
            ->limit(6)
            ->get()
            ->map(fn (PreMeetingBrief $brief): array => [
                'id' => $brief->id,
                'meeting_title' => $brief->meeting?->title,
                'meeting_at' => $brief->meeting_at?->toIso8601String(),
                'body' => $brief->body,
                'red_flag_count' => count($brief->red_flag_ids ?? []),
                'generated_at' => $brief->generated_at?->toIso8601String(),
                'reviewed_at' => $brief->reviewed_at?->toIso8601String(),
                'sent_at' => $brief->sent_at?->toIso8601String(),
                'review_url' => route('advisor.pre-meeting-briefs.review', $brief, absolute: false),
                'can_review' => $brief->sent_at === null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $lookup
     * @return array<string, mixed>
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

    /**
     * @return array{0: EngagementType, 1: bool}
     */
    private function clientInviteEngagementFrom(mixed $value): array
    {
        $engagement = is_string($value) ? EngagementType::tryFrom(trim($value)) : null;

        if ($engagement instanceof EngagementType && in_array($engagement, $this->clientInviteEngagementTypes(), true)) {
            return [$engagement, true];
        }

        return [EngagementType::STANDARD_ADVISORY, false];
    }

    /**
     * @return array<int, EngagementType>
     */
    private function clientInviteEngagementTypes(): array
    {
        return [
            EngagementType::STANDARD_ADVISORY,
            EngagementType::DUE_DILIGENCE,
            EngagementType::NPO,
        ];
    }

    /**
     * @return array<int, array{value:string, label:string, description:string}>
     */
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
        $allowedUrls = [
            route('advisor.clients.index', absolute: false),
        ];

        foreach ($this->clientInviteEngagementTypes() as $type) {
            $allowedUrls[] = route('advisor.clients.index', ['engagement_type' => $type->value], absolute: false);
        }

        if (in_array($url, $allowedUrls, true)) {
            return $url;
        }

        return route('advisor.clients.index', ['engagement_type' => $fallback->value], absolute: false);
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

    /**
     * @return array<string, mixed>
     */
    private function clientSummary(Client $client, bool $includeAdvisorAssignments = false): array
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::from((string) $client->engagement_type);
        $status = $client->status instanceof ClientStatus
            ? $client->status
            : ClientStatus::from((string) ($client->status ?? ClientStatus::ACTIVE->value));

        $summary = [
            'id' => $client->id,
            'engagement_type' => $engagementType->value,
            'engagement_type_label' => $engagementType->label(),
            'is_npo' => $engagementType === EngagementType::NPO,
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

        if ($includeAdvisorAssignments) {
            $summary['advisor_assignments'] = $client->teamMembers
                ->map(fn (ClientTeamMember $member): array => [
                    'advisor_name' => $member->user?->name,
                    'role' => $member->role,
                    'team_name' => $member->advisorTeam?->name,
                ])
                ->values()
                ->all();
        }

        return $summary;
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
            ->where('status', AccountingConnection::STATUS_CONNECTED)
            ->whereNull('revoked_at')
            ->latest('connected_at')
            ->get();

        $connectedProviders = $connections
            ->filter(fn (AccountingConnection $connection): bool => $connection->connected())
            ->pluck('provider')
            ->unique()
            ->values()
            ->all();
        $providerLabels = AccountingConnection::applicableProviderLabels(
            $connectedProviders,
            fn (string $provider): bool => $this->integrations->isLive($provider),
        );

        return [
            'providers' => collect($providerLabels)
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
    private function analysisFindingSummaries(Client $client, mixed $highlight = null): array
    {
        $findings = AnalysisFinding::query()
            ->with(['run', 'feedback.advisor'])
            ->where('client_id', $client->getKey())
            ->latest()
            ->limit(20)
            ->get();

        $highlightId = is_string($highlight) ? trim($highlight) : '';

        if (Str::isUuid($highlightId) && ! $findings->contains(fn (AnalysisFinding $finding): bool => (string) $finding->getKey() === $highlightId)) {
            $highlighted = AnalysisFinding::query()
                ->with(['run', 'feedback.advisor'])
                ->where('client_id', $client->getKey())
                ->whereKey($highlightId)
                ->first();

            if ($highlighted instanceof AnalysisFinding) {
                $findings->prepend($highlighted);
            }
        }

        return $findings
            ->unique(fn (AnalysisFinding $finding): string => (string) $finding->getKey())
            ->map(fn (AnalysisFinding $finding): array => $this->analysisFindingPayload($finding))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function analysisFindingPayload(AnalysisFinding $finding): array
    {
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
