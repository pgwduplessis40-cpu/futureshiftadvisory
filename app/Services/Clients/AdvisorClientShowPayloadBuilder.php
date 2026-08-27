<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\ClientStatus;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Http\Resources\Advisor\AdvisorClientWorkspacePayloadBuilder;
use App\Models\AccountingConnection;
use App\Models\AnalysisFeedback;
use App\Models\AnalysisFinding;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\DdEngagement;
use App\Models\EntrepreneurProfile;
use App\Models\FeeCalculation;
use App\Models\FinancialSnapshot;
use App\Models\Proposal;
use App\Models\User;
use App\Models\WellbeingCheckin;
use App\Services\Budgets\StrategicBudgetService;
use App\Services\Dashboards\PaymentStatusReport;
use App\Services\DataQuality\DataQualityScorer;
use App\Services\Dd\DataRoom;
use App\Services\Dd\DdOnboarding;
use App\Services\Entrepreneurs\CanonicalEntrepreneurWorkspace;
use App\Services\Entrepreneurs\FoundingAdvisoryService;
use App\Services\Fees\ProposalPricingTerms;
use App\Services\Goals\GoalTracker;
use App\Services\Integration\IntegrationActivationResolver;
use App\Services\Npo\GovernanceReviewConversion;
use App\Services\Npo\NpoEngagementConfiguration;
use App\Services\Npo\NpoFunderMonitor;
use App\Services\Npo\NpoHealthScorer;
use App\Services\Npo\NpoValueCalculator;
use App\Services\Npo\SocialEnterpriseAssessment;
use App\Services\Proposals\ProposalBrief;
use App\Services\StandardAdvisory\StandardAdvisoryWorkflow;
use App\Services\StrategicPlans\StrategicPlanDurationPolicy;
use App\Services\StrategicPlans\StrategicPlanService;
use Illuminate\Support\Str;

/**
 * Typed Inertia contract for the advisor client workspace.
 *
 * The builder owns all presentation-only aggregation for the workspace. The
 * controller only authorizes, handles the entrepreneur redirect, and renders
 * this contract.
 */
final class AdvisorClientShowPayloadBuilder
{
    public function __construct(
        private readonly AdvisorClientCollaborationPayloadBuilder $collaborationPayloads,
        private readonly AdvisorClientPayloadBuilder $clientPayloads,
        private readonly AdvisorClientWorkspacePayloadBuilder $workspacePayloads,
        private readonly CanonicalEntrepreneurWorkspace $entrepreneurWorkspaces,
        private readonly DataQualityScorer $dataQuality,
        private readonly GoalTracker $goals,
        private readonly DdOnboarding $ddOnboarding,
        private readonly DataRoom $dataRoom,
        private readonly IntegrationActivationResolver $integrations,
        private readonly ProposalBrief $proposalBriefs,
        private readonly ProposalPricingTerms $pricing,
        private readonly StrategicPlanDurationPolicy $durations,
        private readonly PaymentStatusReport $payments,
        private readonly GovernanceReviewConversion $npoConversion,
        private readonly NpoEngagementConfiguration $npoConfiguration,
        private readonly NpoHealthScorer $npoHealth,
        private readonly NpoFunderMonitor $npoFunders,
        private readonly NpoValueCalculator $npoValues,
        private readonly SocialEnterpriseAssessment $socialEnterprise,
        private readonly StandardAdvisoryWorkflow $standardAdvisory,
        private readonly StrategicBudgetService $strategicBudgets,
        private readonly StrategicPlanService $strategicPlans,
        private readonly FoundingAdvisoryService $foundingAdvisory,
    ) {}

    public function entrepreneurWorkspace(Client $client): ?EntrepreneurProfile
    {
        return $this->entrepreneurWorkspaces->forClient($client);
    }

    /** @return array{client:array<array-key,mixed>,screenShare:array<array-key,mixed>,coBrowse:?array<array-key,mixed>,conflictDeclaration:?array<array-key,mixed>} */
    public function build(Client $client, ?User $user, ?string $highlight): array
    {
        $dataQuality = $this->dataQuality->score($client);
        $strategicBudget = $this->strategicBudgets->ensureForClient($client);
        $invite = $this->clientPayloads->inviteFor($client);

        return [
            'client' => [
                ...$this->clientPayloads->summary(
                    $client,
                    invite: $invite,
                    hasActivatedAccount: $this->clientPayloads->hasActivatedAccount($client),
                ),
                'data_quality' => $dataQuality->level,
                'data_quality_summary' => $dataQuality->toPayload(),
                'wellbeing_trend' => $this->wellbeingTrend($client, $user),
                'status_options' => ClientStatus::options(),
                'lifecycle_update_url' => route('advisor.clients.lifecycle.update', $client, absolute: false),
                'knowledge_assessment_store_url' => route('advisor.clients.knowledge-assessments.store', $client, absolute: false),
                'knowledge_draft_store_url' => route('advisor.clients.knowledge-drafts.store', $client, absolute: false),
                'latest_knowledge_assessment' => $this->workspacePayloads->latestKnowledgeAssessment($client),
                'goal_store_url' => route('advisor.clients.goals.store', $client, absolute: false),
                'goals' => $this->goals->dashboard($client, includeAdvisorActions: true),
                'proposal_store_url' => route('advisor.clients.proposals.store', $client, absolute: false),
                'proposal_expiry_days' => (int) config('proposals.expiry_days', 30),
                'fee_calculations' => $this->feeCalculationSummaries($client),
                'proposals' => $this->proposalSummaries($client),
                'business_health_recompute_url' => route('advisor.clients.health-radar.recompute', $client, absolute: false),
                'report_store_url' => route('advisor.clients.reports.store', $client, absolute: false),
                'reports' => $this->workspacePayloads->reports($client),
                'meeting_store_url' => route('advisor.clients.meetings.store', $client, absolute: false),
                'meetings' => $this->workspacePayloads->meetings($client),
                'industry_briefings' => $this->workspacePayloads->industryBriefings($client),
                'pre_meeting_briefs' => $this->workspacePayloads->preMeetingBriefs($client),
                'address' => $client->address,
                'directors' => $client->directors ?? [],
                'registry_sources' => $client->registry_sources ?? [],
                'engagement_type_locked' => $client->engagementTypeIsLocked(),
                'offboarding' => $this->offboardingSummary($client),
                'accounting' => $this->accountingSummary($client),
                'payments' => $this->payments->forClient($client),
                'analysis_findings' => $this->analysisFindingSummaries($client, $highlight),
                'standard_advisory' => $this->standardAdvisory->clientSummary($client),
                'founding_advisory' => $this->foundingAdvisory->advisorPayload($client),
                'strategic_budget' => $this->strategicBudgets->advisorPayload($strategicBudget),
                'strategic_plan' => $this->strategicPlans->advisorPayload($client),
                'proposal_budget_guard' => $this->strategicBudgets->proposalGuardPayload($strategicBudget),
                'due_diligence' => $this->dueDiligenceSummary($client),
                'npo_conversion' => $this->npoConversion->clientSummary($client),
                'npo_governance_review' => $this->workspacePayloads->npoGovernanceReview($client),
                'npo_configuration' => $this->npoConfiguration->clientSummary($client),
                'npo_health' => $this->npoHealth->clientSummary($client),
                'npo_funding' => $this->npoFunders->clientSummary($client),
                'npo_values' => $this->npoValues->clientSummary($client),
                'npo_social_enterprise' => $this->socialEnterprise->clientSummary($client),
                'created_at' => $client->created_at?->toIso8601String(),
                'invitation' => $this->clientPayloads->invitationSummary($client, $invite),
            ],
            'screenShare' => $this->collaborationPayloads->screenShare($client),
            'coBrowse' => $this->collaborationPayloads->coBrowse($client),
            'conflictDeclaration' => $client->conflictDeclarations()
                ->latest('declared_at')
                ->first()
                ?->only(['id', 'declaration', 'declared_at']),
        ];
    }

    /** @return array<array-key, mixed>|null */
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

    /** @return list<array{id:string,method:string,suggested_mid:float|int|null,roi_ratio:float|int|null,created_at:?string,proposal_scope_summary:?string,strategic_plan_duration_months:int,strategic_plan_duration_label:string,strategic_plan_complexity_band:string,strategic_plan_complexity_label:string}> */
    private function feeCalculationSummaries(Client $client): array
    {
        return FeeCalculation::query()
            ->with('integrationScope')
            ->where('client_id', $client->getKey())
            ->whereDoesntHave('proposals')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (FeeCalculation $calculation): array {
                $duration = $this->durations->forFeeCalculation($calculation);

                return [
                    'id' => $calculation->id,
                    'method' => $calculation->method->value,
                    'suggested_mid' => $calculation->suggested_mid,
                    'roi_ratio' => $calculation->roi_ratio,
                    'created_at' => $calculation->created_at?->toIso8601String(),
                    'proposal_scope_summary' => $this->proposalScopeSummary($calculation),
                    'strategic_plan_duration_months' => $duration['months'],
                    'strategic_plan_duration_label' => $duration['label'],
                    'strategic_plan_complexity_band' => $duration['complexity_band'],
                    'strategic_plan_complexity_label' => $duration['complexity_label'],
                ];
            })
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

    /** @return list<array{id:string,status:string,status_label:string,version:int,fee_method_label:string,brief:string,suggested_mid:float|int|null,roi_ratio:float|int|null,strategic_plan_duration_months:int,strategic_plan_duration_label:string,strategic_plan_complexity_band:string,strategic_plan_complexity_label:string,released_at:?string,expires_at:?string,days_to_expiry:?int,pdf_byte_size:?int,can_release:bool,can_recall:bool,can_renew:bool,release_url:string,recall_url:string,renew_url:string,view_url:string,download_url:string,strategic_plan_generate_url:?string}> */
    private function proposalSummaries(Client $client): array
    {
        return Proposal::query()
            ->with('feeCalculation.integrationScope')
            ->where('client_id', $client->getKey())
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (Proposal $proposal): array {
                $status = $proposal->status;
                $method = $proposal->feeCalculation?->method->value ?? 'advisory';
                $duration = $this->durations->forProposal($proposal);

                return [
                    'id' => $proposal->id,
                    'status' => $status->value,
                    'status_label' => str($status->value)->replace('_', ' ')->title()->toString(),
                    'version' => $proposal->version,
                    'fee_method_label' => str($method)->replace('_', ' ')->title()->toString(),
                    'brief' => $this->proposalBriefs->for($proposal),
                    'suggested_mid' => $this->pricing->payableMid($proposal),
                    'roi_ratio' => $proposal->roi_ratio,
                    'strategic_plan_duration_months' => $duration['months'],
                    'strategic_plan_duration_label' => $duration['label'],
                    'strategic_plan_complexity_band' => $duration['complexity_band'],
                    'strategic_plan_complexity_label' => $duration['complexity_label'],
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

    /** @return array<array-key, mixed>|null */
    private function offboardingSummary(Client $client): ?array
    {
        $record = $client->offboardingRecords()->latest('triggered_at')->first();

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

    /** @return array<array-key, mixed> */
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

    /** @return array<array-key, mixed>|null */
    private function financialSnapshotSummary(?FinancialSnapshot $snapshot): ?array
    {
        if (! $snapshot instanceof FinancialSnapshot) {
            return null;
        }

        return [
            'id' => $snapshot->id,
            'period_start' => $snapshot->period_start->toDateString(),
            'period_end' => $snapshot->period_end->toDateString(),
            'source' => $snapshot->source,
            'source_badge' => $snapshot->source_badge,
            'degraded' => $snapshot->degraded,
            'metrics' => $snapshot->metrics ?? [],
            'pulled_at' => $snapshot->pulled_at?->toIso8601String(),
        ];
    }

    /** @return list<array<array-key, mixed>> */
    private function analysisFindingSummaries(Client $client, ?string $highlight): array
    {
        $findings = AnalysisFinding::query()
            ->with(['run', 'feedback.advisor'])
            ->where('client_id', $client->getKey())
            ->latest()
            ->limit(20)
            ->get();
        $highlightId = trim((string) $highlight);

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

    /** @return array<array-key, mixed> */
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
            'uncertainty' => $finding->uncertainty->value,
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

    /** @return list<array<array-key, mixed>>|null */
    private function wellbeingTrend(Client $client, ?User $user): ?array
    {
        if (! $user instanceof User || ! $this->canViewWellbeing($client, $user)) {
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
                'period_start' => $checkin->period_start->toDateString(),
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
