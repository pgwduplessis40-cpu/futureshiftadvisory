<?php

declare(strict_types=1);

namespace App\Http\Resources\Advisor;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Enums\ReportType;
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
use App\Models\Report;
use App\Models\User;
use App\Models\WellbeingCheckin;
use App\Services\Advisor\AdvisorClientServiceWorkspaces;
use App\Services\Budgets\StrategicBudgetService;
use App\Services\Clients\AdvisorClientCollaborationPayloadBuilder;
use App\Services\Clients\AdvisorClientPayloadBuilder;
use App\Services\Dashboards\PaymentStatusReport;
use App\Services\DataQuality\DataQualityScorer;
use App\Services\Dd\BuyerDecisionReadiness;
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
use Illuminate\Database\Eloquent\Collection;
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
        private readonly AdvisorClientServiceWorkspaces $serviceWorkspaces,
        private readonly CanonicalEntrepreneurWorkspace $entrepreneurWorkspaces,
        private readonly DataQualityScorer $dataQuality,
        private readonly GoalTracker $goals,
        private readonly DdOnboarding $ddOnboarding,
        private readonly BuyerDecisionReadiness $buyerDecisionReadiness,
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

    /** @return array{client:array<array-key,mixed>,serviceWorkspaces:array{active_key:string,items:list<array{key:string,label:string,href:string,active:bool}>},screenShare:array<array-key,mixed>,coBrowse:?array<array-key,mixed>,conflictDeclaration:?array<array-key,mixed>} */
    public function build(Client $client, ?User $user, ?string $highlight): array
    {
        $dataQuality = $this->dataQuality->score($client);
        $strategicBudget = $this->strategicBudgets->ensureForClient($client);
        $invite = $this->clientPayloads->inviteFor($client);
        $isDueDiligenceClient = $this->isDueDiligenceClient($client);

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
                'proposals' => $this->proposalSummaries($client, strategicPlanGenerationAllowed: ! $isDueDiligenceClient),
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
                'strategic_plan' => $isDueDiligenceClient ? null : $this->strategicPlans->advisorPayload($client),
                'strategic_plan_deployment_guard' => $this->strategicPlanDeploymentGuard($client),
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
            'serviceWorkspaces' => $this->serviceWorkspaces->payload(
                $client,
                $this->entrepreneurWorkspaces->forClient($client) instanceof EntrepreneurProfile
                    ? AdvisorClientServiceWorkspaces::KEY_ENTREPRENEUR
                    : AdvisorClientServiceWorkspaces::KEY_DUE_DILIGENCE,
            ),
            'screenShare' => $this->collaborationPayloads->screenShare($client),
            'coBrowse' => $this->collaborationPayloads->coBrowse($client),
            'conflictDeclaration' => $client->conflictDeclarations()
                ->latest('declared_at')
                ->first()
                ?->only(['id', 'declaration', 'declared_at']),
        ];
    }

    private function isDueDiligenceClient(Client $client): bool
    {
        return $client->engagement_type === EngagementType::DUE_DILIGENCE;
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

        $reports = $this->dueDiligenceReports($client);
        $latestReport = $reports->last();
        $latestReportReviewed = $latestReport instanceof Report
            && in_array((string) $latestReport->review_status, ['not_required', 'reviewed'], true);
        $decisionReadiness = $this->buyerDecisionReadiness->forEngagement($engagement, $latestReport);
        $decisionReady = (bool) ($decisionReadiness['ready'] ?? false);

        return array_merge($this->ddOnboarding->targetPanel($engagement), [
            'data_room' => $this->dataRoom->summary($engagement),
            'decision_readiness' => $decisionReadiness,
            'assessment_ready' => $latestReportReviewed && $decisionReady,
            'assessment_status_label' => $latestReport instanceof Report
                ? str((string) $latestReport->review_status)->replace('_', ' ')->title()->toString()
                : 'Report not generated',
            'assessment_summary' => match (true) {
                $latestReportReviewed && $decisionReady => 'DD report has been reviewed and is buyer decision-ready before Business Plan & Budget approval.',
                $latestReportReviewed => 'DD report has been reviewed, but buyer decision-readiness gaps remain before it should be relied on for the buy / renegotiate / walk-away decision.',
                $latestReport instanceof Report => 'Review the DD report and resolve buyer decision-readiness gaps before approving the Business Plan & Budget.',
                default => 'Generate and review the buyer decision-ready DD report before approving the Business Plan & Budget.',
            },
            'report_title' => $latestReport?->title,
            'report_generated_at' => $latestReport?->generated_at?->toIso8601String(),
            'report_review_status' => $latestReport instanceof Report ? $latestReport->review_status : null,
            'report_url' => $latestReport instanceof Report
                ? route('advisor.reports.download', $latestReport, absolute: false)
                : null,
            'report_review_url' => $latestReport instanceof Report
                ? route('advisor.reports.review', $latestReport, absolute: false)
                : null,
            'suggested_reply' => $this->dueDiligenceSuggestedReplyPayload(
                client: $client,
                engagement: $engagement,
                latestReport: $latestReport,
                decisionReadiness: $decisionReadiness,
            ),
            'report_versions' => $this->dueDiligenceReportVersionsPayload($engagement, $reports),
        ]);
    }

    /**
     * @return array{allowed: bool, missing: array<int, string>, message: ?string}
     */
    private function strategicPlanDeploymentGuard(Client $client): array
    {
        if (! $this->isDueDiligenceClient($client)) {
            return [
                'allowed' => true,
                'missing' => [],
                'message' => null,
            ];
        }

        return [
            'allowed' => false,
            'missing' => ['advisory service access'],
            'message' => 'Strategic planning is not part of the DD workspace. After the DD report and Business Plan & Budget are approved, open advisory service access through the advisory/proposal workflow.',
        ];
    }

    /**
     * @return Collection<int, Report>
     */
    private function dueDiligenceReports(Client $client): Collection
    {
        return Report::query()
            ->where('client_id', $client->getKey())
            ->whereIn('type', [
                ReportType::DueDiligence->value,
                ReportType::AcquisitionGoNoGo->value,
            ])
            ->withCount('sections')
            ->orderBy('generated_at')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  array<array-key, mixed>  $decisionReadiness
     * @return array<array-key, mixed>
     */
    private function dueDiligenceSuggestedReplyPayload(
        Client $client,
        DdEngagement $engagement,
        ?Report $latestReport,
        array $decisionReadiness,
    ): array {
        $feedback = (array) data_get($latestReport?->metadata, 'advisor_client_reply', []);
        $priorities = $this->dueDiligenceFeedbackPriorities($decisionReadiness);
        $suggestedFeedback = $this->suggestedDueDiligenceAdvisorFeedback($engagement, $latestReport, $decisionReadiness, $priorities);
        $suggestedReply = $this->suggestedDueDiligenceClientReply($client, $engagement, $decisionReadiness, $priorities);
        $status = (string) ($feedback['status'] ?? ($latestReport instanceof Report ? $latestReport->review_status : 'not_generated'));
        $messageThreadId = data_get($feedback, 'client_message_thread_id');

        return [
            'id' => $latestReport?->getKey(),
            'status' => $status,
            'status_label' => $this->ddFeedbackStatusLabel($status),
            'advisor_feedback' => (string) ($feedback['advisor_feedback'] ?? $suggestedFeedback),
            'proposed_reply' => (string) ($feedback['proposed_reply'] ?? $suggestedReply),
            'suggested_feedback' => $suggestedFeedback,
            'suggested_reply' => $suggestedReply,
            'priorities' => $priorities,
            'saved_at' => $feedback['saved_at'] ?? null,
            'sent_at' => $feedback['sent_at'] ?? null,
            'can_save' => $latestReport instanceof Report,
            'can_send' => $latestReport instanceof Report,
            'action_url' => $latestReport instanceof Report
                ? route('advisor.reports.dd-feedback', $latestReport, absolute: false)
                : '',
            'message_url' => is_string($messageThreadId) && $messageThreadId !== ''
                ? route('advisor.clients.messages.show', [$client, $messageThreadId], absolute: false)
                : null,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $decisionReadiness
     * @param  array<int, array<array-key, mixed>>  $priorities
     */
    private function suggestedDueDiligenceAdvisorFeedback(
        DdEngagement $engagement,
        ?Report $latestReport,
        array $decisionReadiness,
        array $priorities,
    ): string {
        if (! $latestReport instanceof Report) {
            return 'Generate the DD assessment or DD decision report first. The advisor summary will then capture the decision-readiness gaps, evidence position, valuation/risk position, and proposed client reply.';
        }

        $nextStep = $this->dueDiligenceNextStep($decisionReadiness, $priorities);
        $priceAdjustment = $this->formatDdCurrency((float) ($decisionReadiness['price_adjustment_nzd'] ?? 0));
        $valuation = is_numeric($decisionReadiness['valuation_midpoint_nzd'] ?? null)
            ? $this->formatDdCurrency((float) $decisionReadiness['valuation_midpoint_nzd'])
            : 'not available';

        return implode("\n\n", [
            'DD version: '.$latestReport->title,
            'Buyer decision: '.(string) ($decisionReadiness['decision_label'] ?? 'Decision not ready').' ('.Str::of((string) ($decisionReadiness['confidence'] ?? 'low'))->replace('_', ' ')->lower()->toString().' confidence).',
            'Recommendation: '.$this->ddRecommendationLabel((string) ($decisionReadiness['recommendation'] ?? 'pending')).'. '.(string) ($decisionReadiness['recommendation_rationale'] ?? ''),
            'Evidence and risk position: '.(int) ($decisionReadiness['completed_workstreams'] ?? 0).'/'.(int) ($decisionReadiness['required_workstreams'] ?? 0).' workstreams complete; '.(int) ($decisionReadiness['evidence_item_count'] ?? 0).' evidence item(s); '.(int) ($decisionReadiness['verified_finding_count'] ?? 0).' verified finding(s); '.(int) ($decisionReadiness['flagged_finding_count'] ?? 0).' unresolved evidence flag(s); '.(int) ($decisionReadiness['material_risk_count'] ?? 0).' material risk(s); price adjustment '.$priceAdjustment.'; valuation midpoint '.$valuation.'.',
            'Suggested next step: '.$nextStep,
            'Use the suggested client reply as a starting point. Edit it before sending if commercial wording, purchase sensitivity, or legal/accounting caveats need nuance.',
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $decisionReadiness
     * @param  array<int, array<array-key, mixed>>  $priorities
     */
    private function suggestedDueDiligenceClientReply(
        Client $client,
        DdEngagement $engagement,
        array $decisionReadiness,
        array $priorities,
    ): string {
        $clientName = $client->trading_name ?: $client->legal_name ?: 'there';
        $targetName = $engagement->target_name !== '' ? $engagement->target_name : 'the acquisition target';
        $decisionLabel = (string) ($decisionReadiness['decision_label'] ?? 'Decision not ready');
        $confidence = Str::of((string) ($decisionReadiness['confidence'] ?? 'low'))->replace('_', ' ')->lower()->toString();
        $nextStep = $this->dueDiligenceNextStep($decisionReadiness, $priorities);
        $priorityLines = collect($priorities)
            ->take(3)
            ->map(fn (array $priority): string => '- '.$priority['title'].': '.$priority['suggested_next_step'])
            ->implode("\n");

        return trim(implode("\n\n", array_filter([
            "Hi {$clientName},",
            "I have reviewed the Due Diligence assessment for {$targetName}. Based on the financials and information available at the time of assessment, the current buyer decision position is: {$decisionLabel} ({$confidence} confidence).",
            'Recommendation: '.$this->ddRecommendationLabel((string) ($decisionReadiness['recommendation'] ?? 'pending')).'. '.(string) ($decisionReadiness['recommendation_rationale'] ?? ''),
            $priorityLines !== '' ? "Key DD points:\n{$priorityLines}" : null,
            "Suggested next step: {$nextStep}",
            'Please remember that this DD view is advisory support based on the information provided. The final decision to buy, renegotiate, pause, or walk away remains yours as the buyer.',
        ])));
    }

    /**
     * @param  Collection<int, Report>  $reports
     * @return array<int, array<array-key, mixed>>
     */
    private function dueDiligenceReportVersionsPayload(DdEngagement $engagement, Collection $reports): array
    {
        return $reports
            ->values()
            ->map(function (Report $report, int $index) use ($engagement): array {
                $readiness = $this->buyerDecisionReadiness->forEngagement($engagement, $report);
                $feedback = (array) data_get($report->metadata, 'advisor_client_reply', []);
                $messageThreadId = data_get($feedback, 'client_message_thread_id');
                $gateCount = count((array) ($readiness['gates'] ?? []));
                $passedGateCount = collect((array) ($readiness['gates'] ?? []))
                    ->filter(fn (mixed $gate): bool => is_array($gate) && (bool) ($gate['passed'] ?? false))
                    ->count();

                return [
                    'id' => $report->getKey(),
                    'version' => $index + 1,
                    'type' => $report->type->value,
                    'type_label' => $report->type->label(),
                    'title' => $report->title,
                    'generated_at' => $report->generated_at?->toIso8601String(),
                    'review_status' => $report->review_status,
                    'review_status_label' => $this->reportReviewStatusLabel((string) $report->review_status),
                    'render_status' => $report->render_status,
                    'render_status_label' => $this->reportRenderStatusLabel((string) $report->render_status),
                    'decision_label' => (string) ($readiness['decision_label'] ?? 'Decision not ready'),
                    'confidence' => (string) ($readiness['confidence'] ?? 'low'),
                    'recommendation' => (string) ($readiness['recommendation'] ?? 'pending'),
                    'recommendation_label' => $this->ddRecommendationLabel((string) ($readiness['recommendation'] ?? 'pending')),
                    'gates_passed' => $passedGateCount,
                    'gates_total' => $gateCount,
                    'sections_count' => (int) ($report->sections_count ?? 0),
                    'report_url' => route('advisor.reports.download', $report, absolute: false),
                    'feedback_status' => (string) ($feedback['status'] ?? 'not_started'),
                    'feedback_status_label' => $this->ddFeedbackStatusLabel((string) ($feedback['status'] ?? 'not_started')),
                    'feedback_sent_at' => $feedback['sent_at'] ?? null,
                    'suggested_reply_excerpt' => Str::limit((string) ($feedback['proposed_reply'] ?? ''), 180),
                    'message_url' => is_string($messageThreadId) && $messageThreadId !== ''
                        ? route('advisor.clients.messages.show', [$report->client_id, $messageThreadId], absolute: false)
                        : null,
                ];
            })
            ->sortByDesc('version')
            ->values()
            ->all();
    }

    /**
     * @param  array<array-key, mixed>  $decisionReadiness
     * @return array<int, array<array-key, mixed>>
     */
    private function dueDiligenceFeedbackPriorities(array $decisionReadiness): array
    {
        $required = max(1, (int) ($decisionReadiness['required_workstreams'] ?? 0));
        $completed = (int) ($decisionReadiness['completed_workstreams'] ?? 0);
        $gates = collect((array) ($decisionReadiness['gates'] ?? []));
        $passedGates = $gates->filter(fn (mixed $gate): bool => is_array($gate) && (bool) ($gate['passed'] ?? false))->count();
        $gateTotal = max(1, $gates->count());
        $flaggedFindings = (int) ($decisionReadiness['flagged_finding_count'] ?? 0);
        $evidenceItems = (int) ($decisionReadiness['evidence_item_count'] ?? 0);
        $verifiedFindings = (int) ($decisionReadiness['verified_finding_count'] ?? 0);

        return collect([
            [
                'key' => 'workstream_coverage',
                'title' => 'Workstream coverage',
                'score' => min(100, (int) round(($completed / $required) * 100)),
                'summary' => "{$completed}/{$required} DD workstreams are complete.",
                'suggested_next_step' => $completed >= $required
                    ? 'Keep the DD workstream record current before relying on the decision report.'
                    : 'Complete the remaining DD workstreams before asking the client to rely on the decision report.',
            ],
            [
                'key' => 'evidence_quality',
                'title' => 'Evidence quality',
                'score' => min(100, ($evidenceItems > 0 ? 35 : 0) + ($verifiedFindings > 0 ? 35 : 0) + ($flaggedFindings === 0 ? 30 : 0)),
                'summary' => "{$evidenceItems} evidence item(s), {$verifiedFindings} verified finding(s), {$flaggedFindings} unresolved evidence flag(s).",
                'suggested_next_step' => $flaggedFindings === 0
                    ? 'Keep evidence references visible in the DD report and decision notes.'
                    : 'Resolve or explain unresolved evidence flags before sending decision guidance.',
            ],
            [
                'key' => 'buyer_decision_readiness',
                'title' => 'Buyer decision readiness',
                'score' => min(100, (int) round(($passedGates / $gateTotal) * 100)),
                'summary' => (string) ($decisionReadiness['decision_headline'] ?? $decisionReadiness['label'] ?? 'Decision readiness has not been assessed.'),
                'suggested_next_step' => $this->firstDueDiligenceBlocker($decisionReadiness)
                    ?? 'Review the final buy / renegotiate / walk-away position with the client before release.',
            ],
        ])
            ->sortBy('score')
            ->values()
            ->map(function (array $priority, int $index): array {
                $score = (int) $priority['score'];
                $status = match (true) {
                    $score >= 90 => 'met',
                    $score >= 50 => 'review',
                    default => 'missing',
                };

                return [
                    ...$priority,
                    'rank' => $index + 1,
                    'status' => $status,
                    'status_label' => match ($status) {
                        'met' => 'Met',
                        'review' => 'Needs review',
                        default => 'Missing',
                    },
                ];
            })
            ->all();
    }

    /**
     * @param  array<array-key, mixed>  $decisionReadiness
     * @param  array<int, array<array-key, mixed>>  $priorities
     */
    private function dueDiligenceNextStep(array $decisionReadiness, array $priorities): string
    {
        $blocker = $this->firstDueDiligenceBlocker($decisionReadiness);
        if ($blocker !== null) {
            return $blocker;
        }

        $priority = collect($priorities)->firstWhere('status', 'review')
            ?? collect($priorities)->firstWhere('status', 'missing');

        if (is_array($priority)) {
            return (string) $priority['suggested_next_step'];
        }

        return 'Review the DD decision report with the buyer and confirm the buy / renegotiate / walk-away decision.';
    }

    /**
     * @param  array<array-key, mixed>  $decisionReadiness
     */
    private function firstDueDiligenceBlocker(array $decisionReadiness): ?string
    {
        $blocker = collect((array) ($decisionReadiness['blockers'] ?? []))
            ->map(fn (mixed $item): string => trim((string) $item))
            ->first(fn (string $item): bool => $item !== '');

        return is_string($blocker) && $blocker !== ''
            ? 'Resolve this DD decision-readiness gap: '.$blocker.'.'
            : null;
    }

    private function ddRecommendationLabel(string $recommendation): string
    {
        return match ($recommendation) {
            DdEngagement::RECOMMENDATION_PROCEED => 'Proceed',
            DdEngagement::RECOMMENDATION_RENEGOTIATE => 'Renegotiate',
            DdEngagement::RECOMMENDATION_ABANDON => 'Walk away',
            default => 'Pending',
        };
    }

    private function ddFeedbackStatusLabel(string $status): string
    {
        return match ($status) {
            'feedback_sent' => 'Feedback sent',
            'feedback_saved' => 'Feedback saved',
            'reviewed' => 'Reviewed',
            'pending_review' => 'Pending review',
            'not_required' => 'Review not required',
            'not_generated' => 'Generate report first',
            'not_started' => 'No feedback yet',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function reportReviewStatusLabel(string $status): string
    {
        return match ($status) {
            'reviewed' => 'Reviewed',
            'pending_review' => 'Pending review',
            'not_required' => 'Review not required',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function reportRenderStatusLabel(string $status): string
    {
        return match ($status) {
            Report::RENDER_STATUS_COMPOSING => 'Rendering',
            Report::RENDER_STATUS_RENDERED => 'Rendered',
            Report::RENDER_STATUS_FAILED => 'Render failed',
            '' => 'Not rendered',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function formatDdCurrency(float $amount): string
    {
        return 'NZ$'.number_format($amount, 0);
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
    private function proposalSummaries(Client $client, bool $strategicPlanGenerationAllowed = true): array
    {
        return Proposal::query()
            ->with('feeCalculation.integrationScope')
            ->where('client_id', $client->getKey())
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (Proposal $proposal) use ($strategicPlanGenerationAllowed): array {
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
                    'strategic_plan_generate_url' => $strategicPlanGenerationAllowed && $status === ProposalStatus::Signed
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
