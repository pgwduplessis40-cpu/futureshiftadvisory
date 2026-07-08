import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    Banknote,
    BarChart3,
    CheckCircle2,
    Clock,
    CreditCard,
    DatabaseZap,
    FileText,
    HeartHandshake,
    HeartPulse,
    Inbox,
    Lightbulb,
    ListChecks,
    PieChart,
    PlugZap,
    ShieldAlert,
    Sparkles,
    TrendingUp,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import type React from 'react';
import { InsightHoverCard } from '@/components/insight/InsightHoverCard';
import {
    PvSummaryBadges,
    WaterfallChart,
} from '@/components/pv/WaterfallChart';
import type { WaterfallStep } from '@/components/pv/WaterfallChart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { DocumentVerificationFlagPanel } from '@/components/verification/DocumentVerificationFlagPanel';
import type { DocumentVerificationFlag } from '@/components/verification/DocumentVerificationFlagPanel';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type HealthLevel = 'green' | 'amber' | 'red';
type DashboardTab = 'priorities' | 'signals';
type ActionPriority = 'critical' | 'warning' | 'neutral';
type EngagementScoreKey =
    | 'questionnaire_pct'
    | 'documents_pct'
    | 'milestones_on_track_pct'
    | 'comms_recency_pct';

type EngagementScore = {
    level: HealthLevel;
    score: number;
    scores: {
        questionnaire_pct: number;
        documents_pct: number;
        milestones_on_track_pct: number;
        comms_recency_pct: number;
    };
    display: {
        overdue_count: number;
        blocked_count: number;
        last_comms_days: number | null;
    };
    weakest_component: EngagementScoreKey;
    focus_section: string;
    drill_url: string;
};

type CashFlowStatus = {
    client_id: string;
    client_name: string;
    client_url: string;
    status: 'positive' | 'watch' | 'negative' | 'unknown';
    status_label: string;
    tone: 'positive' | 'warning' | 'negative' | 'muted';
    reason: string;
    source: string;
    latest_operating_cash_flow: number | null;
    latest_period_end: string | null;
    runway_months: number | null;
    runway_open_ended: boolean;
    cash_flow_positive_year: number | null;
    alert_headline: string | null;
    detail_url: string;
};

type ClientHealth = {
    id: string;
    legal_name: string;
    trading_name: string | null;
    engagement_type_label: string;
    status: string;
    status_label: string;
    engagement: EngagementScore;
    cash_flow: CashFlowStatus;
    open_document_flags_count: number;
    last_activity_at: string | null;
    show_url: string;
};

type ClientsHealthPayload = {
    summary: {
        total: number;
        advisory_clients: number;
        entrepreneurs: number;
        high: number;
        medium: number;
        low: number;
        insufficient: number;
        needs_attention: number;
    };
    clients: ClientHealth[];
};

type CashFlowStatusPayload = {
    summary: {
        total: number;
        positive: number;
        watch: number;
        negative: number;
        unknown: number;
        action_required: number;
    };
    by_client: Record<string, CashFlowStatus>;
    items: CashFlowStatus[];
};

type PendingTermsPayload = {
    latest_version: {
        id: string;
        version: string;
        published_at: string | null;
    } | null;
    total: number;
    items: Array<{
        id: string;
        client_id: string;
        client_name: string | null;
        client_url: string;
        user_id: number;
        user_name: string | null;
        user_email: string | null;
        role: string;
    }>;
};

type MessagesPendingPayload = {
    total: number;
    index_url: string;
};

type EntrepreneurReviewsPayload = {
    summary: {
        total: number;
        idea_validations: number;
        business_plans: number;
    };
    items: Array<{
        id: string;
        type: 'idea_validation' | 'business_plan';
        label: string;
        entrepreneur_id: string | null;
        entrepreneur_name: string;
        entrepreneur_email: string | null;
        status: string;
        submitted_at: string | null;
        detail_url: string | null;
        action_label: string;
    }>;
};

type StrategicPlanDeploymentsPayload = {
    summary: {
        total: number;
        ready_to_generate: number;
        ready_to_deploy: number;
    };
    items: Array<{
        id: string;
        type: 'generate' | 'deploy';
        client_id: string;
        client_name: string;
        proposal_version: number | null;
        generated_at: string | null;
        accepted_at: string | null;
        milestones_count: number;
        status_label: string;
        budget_status_label: string | null;
        client_url: string;
        detail_url: string;
        action_url: string | null;
        action_label: string;
    }>;
};

type ProspectInboxPayload = {
    total: number;
    triage_enabled: boolean;
    index_url: string;
    items: Array<{
        id: number;
        name: string;
        email: string;
        company: string | null;
        source: string;
        status: string;
        created_at: string | null;
    }>;
};

type IntegrationHealthPayload = {
    summary: {
        total: number;
        green: number;
        amber: number;
        red: number;
    };
    index_url: string | null;
    services: Array<{
        id: string;
        service: string;
        health: HealthLevel;
        success_rate: number;
        p95_latency_ms: number | null;
        window_end: string | null;
    }>;
};

type EconomicIndicatorsPayload = {
    summary: {
        indicators: number;
        exchange_rates: number;
        change_alerts: number;
        latest_fetched_at: string | null;
    };
    indicators: Array<{
        id: string;
        indicator: string;
        label: string;
        value: number;
        unit: string;
        period_date: string | null;
        source: string;
        source_badge: string;
        degraded: boolean;
        fetched_at: string | null;
        previous_value: number | null;
        change_abs: number | null;
        change_pct: number | null;
        direction: TrendDirection;
        exposure: EconomicExposure;
    }>;
    exchange_rates: Array<{
        id: string;
        base_currency: string;
        quote_currency: string;
        rate: number;
        rate_date: string | null;
        source: string;
        source_badge: string;
        degraded: boolean;
        fetched_at: string | null;
        previous_rate: number | null;
        change_abs: number | null;
        change_pct: number | null;
        direction: TrendDirection;
        exposure: EconomicExposure;
    }>;
    alerts: Array<{
        id: string;
        summary: string;
        created_at: string | null;
    }>;
};

type TrendDirection = 'up' | 'down' | 'flat' | 'none';

type EconomicExposure = {
    key: string;
    label: string;
    supported: boolean;
    status: 'supported' | 'unavailable';
    reason: string | null;
    exposed_count: number | null;
    unknown_count: number | null;
    not_exposed_count: number | null;
    drill_url: string | null;
};

type EconomicIndicatorItem = EconomicIndicatorsPayload['indicators'][number];
type ExchangeRateItem = EconomicIndicatorsPayload['exchange_rates'][number];

type RedFlagsPayload = {
    summary: {
        open: number;
        unacknowledged: number;
    };
    items: Array<{
        id: string;
        client_id: string;
        client_name: string | null;
        analysis_finding_id: string | null;
        module: string | null;
        category: string;
        severity: string;
        headline: string;
        detail: string;
        trigger: {
            summary: string;
            source_reference: string;
        } | null;
        surfaced_at: string | null;
        acknowledged_at: string | null;
        acknowledge_url: string;
        resolve_url: string;
        finding_url: string | null;
        client_url: string;
    }>;
};

type PvWaterfallPayload = {
    summary: {
        clients: number;
        current_pv: number;
        improvement_pv: number;
        risk_mitigation_pv: number;
        target_pv: number;
        target_pv_label: string;
        target_pv_range: {
            low: number;
            mid: number;
            high: number;
            range_percent: number;
        };
    };
    clients: Array<{
        client_id: string;
        client_name: string;
        client_url: string;
        business_valuation_id: string | null;
        current_pv: number;
        improvement_pv: number;
        risk_mitigation_pv: number;
        target_pv: number;
        target_pv_label: string;
        target_pv_range: {
            low: number;
            mid: number;
            high: number;
            range_percent: number;
        };
        waterfall: WaterfallStep[];
    }>;
};

type PracticeHealthPayload = {
    summary: {
        active_clients: number;
        clients_with_pv: number;
        current_pv: number;
        improvement_pv: number;
        risk_mitigation_pv: number;
        target_pv: number;
        revenue_under_management: number;
    };
    phase_two: {
        released_proposals: number;
        open_red_flags: number;
        generated_reports: number;
        funnel_events: number;
        funnel_worst_drop_off_rate: number;
        proposal_statuses: Record<string, number>;
    };
    clients: Array<{
        client_id: string;
        client_name: string;
        client_url: string;
        current_pv: number;
        improvement_pv: number;
        risk_mitigation_pv: number;
        target_pv: number;
        revenue_under_management: number;
        released_proposals: number;
        generated_reports: number;
        open_red_flags: number;
        latest_valuation_at: string | null;
        latest_revenue_period_end: string | null;
    }>;
    generated_at: string;
};

type ProposalStatusPayload = {
    summary: {
        total: number;
        released: number;
        expiring_soon: number;
        expired: number;
    };
    statuses: Record<string, number>;
    expiry_alerts: Array<{
        id: string;
        client_id: string;
        client_name: string | null;
        version: number;
        status: string;
        expires_at: string | null;
        client_url: string;
    }>;
};

type PaymentStatusPayload = {
    summary: {
        failed: number;
        retrying: number;
        retryable: number;
    };
    items: Array<{
        id: string;
        client_id: string;
        client_name: string | null;
        client_url: string;
        status: string;
        amount: number;
        currency: string;
        processed_at: string | null;
        failed_reason: string | null;
        attempt: number;
        automatic_next_retry_at: string | null;
        manual_retry_available: boolean;
        retry_url: string;
        drill_url: string;
        contact_url: string;
    }>;
};

type ScenarioPlanningPayload = {
    summary: {
        scenarios: number;
        clients: number;
    };
    items: Array<{
        id: string;
        client_id: string;
        client_name: string | null;
        client_url: string;
        name: string;
        kind: string;
        pv_impact: number;
        position: number;
        is_client_visible: boolean;
    }>;
};

type FunnelAnalyticsPayload = {
    summary: {
        events: number;
        abandoned: number;
        completed: number;
        worst_drop_off_rate: number;
    };
    steps: Array<{
        flow: string;
        step: string;
        entered: number;
        completed: number;
        abandoned: number;
        dropped_count: number;
        dropped_clients: Array<{
            id: string;
            name: string;
            last_dropped_at: string | null;
            show_url: string;
        }>;
        last_dropped_at: string | null;
        returned_count: number;
        drop_off_rate: number;
    }>;
};

type QuestionnaireOptimisationPayload = {
    summary: {
        detected_candidates: number;
        latest_run_at: string | null;
        latest_candidates_created: number;
    };
    items: Array<{
        id: string;
        summary: string;
        magnitude: string;
        confidence: number;
        questionnaire_title: string | null;
        question_prompt: string | null;
        created_at: string | null;
    }>;
};

type WellbeingAnalyticsPayload = {
    summary: {
        checkins: number;
        clients: number;
        average_business_confidence: number;
        average_personal_coping: number;
        low_personal_coping_checkins: number;
        active_low_coping_signals: number;
        current_period_completion_rate: number;
    };
    monthly: Array<{
        period_start: string;
        checkins: number;
        average_business_confidence: number;
        average_personal_coping: number;
        low_personal_coping_checkins: number;
    }>;
    signals: Array<{
        id: string;
        client_id: string;
        client_name: string | null;
        client_url: string;
        signal_type: string;
        severity: string;
        generated_at: string | null;
        auto_referral: boolean;
    }>;
};

type CoachSignalsPayload = {
    summary: {
        total: number;
        auto_referrals: number;
    };
    items: Array<{
        id: string;
        client_id: string;
        client_name: string | null;
        client_url: string;
        signal_type: string | null;
        suggested_specialisation: string;
        threshold_ref: string;
        rationale: string;
        surfaced_at: string | null;
    }>;
};

type NpoPendingConversionsPayload = {
    summary: {
        total: number;
        report_delivered: number;
        declined: number;
        nudge_due: number;
    };
    items: Array<{
        id: string;
        client_id: string;
        client_name: string | null;
        status: string | null;
        status_label: string | null;
        decline_reason: string | null;
        report_delivered_at: string | null;
        reengagement_due_at: string | null;
        next_nudge_day: number | null;
        client_url: string;
    }>;
};

type NpoFundingPayload = {
    summary: {
        active_records: number;
        active_alerts: number;
        critical_alerts: number;
    };
    alerts: Array<{
        id: string;
        client_id: string;
        record_id: string;
        funder_name: string | null;
        type: string;
        severity: string;
        message: string;
        due_on: string | null;
        triggered_at: string | null;
        client_name: string | null;
        client_url: string;
    }>;
};

type ReferenceDataTasksPayload = {
    summary: {
        total: number;
        fresh: number;
        due_soon: number;
        overdue: number;
        missing: number;
    };
    index_url: string | null;
    items: Array<{
        key: string;
        dataset: string;
        indicator: string | null;
        label: string;
        status: 'fresh' | 'due_soon' | 'overdue' | 'missing';
        cadence_days: number;
        last_as_at: string | null;
        due_at: string | null;
        source: string | null;
        entry_id: string | null;
        action_url: string;
    }>;
};

type PanelReferralQueue = {
    summary: {
        total: number;
        active: number;
        terminal: number;
    };
    stage_counts: Record<string, number>;
    items: Array<{
        id: string;
        subject_name: string;
        panel_name: string;
        stage: string;
        stage_label: string;
        reason: string | null;
        sent_at: string | null;
        detail_url: string | null;
    }>;
};

type PanelApprovalQueue = {
    summary: {
        total: number;
        broker: number;
        coach: number;
    };
    review_url: string | null;
    items: Array<{
        id: string;
        panel_type: string;
        panel_label: string;
        business_name: string;
        contact_name: string;
        email: string | null;
        status: string;
        status_label: string;
        applied_at: string | null;
        review_url: string | null;
    }>;
};

type LearningQueuePayload = {
    summary: {
        detected: number;
        staged: number;
        approved: number;
        implemented: number;
    };
    queue_url: string | null;
    items: Array<{
        id: string;
        summary: string;
        status: string;
        source_type: string | null;
        confidence: number;
        clients_affected: number;
        created_at: string | null;
        detail_url: string | null;
    }>;
};

type PanelOperationsPayload = {
    broker: PanelReferralQueue;
    coach: PanelReferralQueue;
    learning: LearningQueuePayload;
    approvals: PanelApprovalQueue;
};

type FeeStatusPayload = {
    free_access_mode: boolean;
    charging_enabled: boolean;
    current_rate_id: string | null;
    current_rate_effective_from: string | null;
    currency: string;
    can_manage: boolean;
    manage_url: string | null;
};

type ActionSummaryItem = {
    key: string;
    label: string;
    value: number;
    displayValue?: React.ReactNode;
    statusLabel?: string;
    href: string;
    targetId: string;
    tab: DashboardTab;
    priority: ActionPriority;
    explanation: string;
    nextStep: string;
    icon: React.ReactNode;
};

const signalPanelTargetIds = new Set([
    'advisor-panel-operations',
    'advisor-panel-approvals',
    'advisor-broker-referrals',
    'advisor-coach-referrals',
    'advisor-learning-queue',
    'advisor-reference-data-tasks',
    'advisor-npo-funding',
    'advisor-npo-conversions',
]);

type Props = {
    clientsHealth: ClientsHealthPayload;
    cashFlowStatus: CashFlowStatusPayload;
    redFlags: RedFlagsPayload;
    documentVerificationFlags: DocumentVerificationFlag[];
    messagesPending: MessagesPendingPayload;
    entrepreneurReviews: EntrepreneurReviewsPayload;
    strategicPlanDeployments: StrategicPlanDeploymentsPayload;
    pendingTermsReacceptance: PendingTermsPayload;
    prospectInbox: ProspectInboxPayload;
    integrationHealth: IntegrationHealthPayload;
    economicIndicators: EconomicIndicatorsPayload;
    pvWaterfall: PvWaterfallPayload;
    practiceHealth: PracticeHealthPayload;
    proposalStatus: ProposalStatusPayload;
    paymentStatus: PaymentStatusPayload;
    feeStatus: FeeStatusPayload;
    questionnaireOptimisation: QuestionnaireOptimisationPayload;
    wellbeingAnalytics: WellbeingAnalyticsPayload;
    coachSignals: CoachSignalsPayload;
    npoPendingConversions: NpoPendingConversionsPayload;
    npoFunding: NpoFundingPayload;
    referenceDataTasks: ReferenceDataTasksPayload;
    scenarioPlanning: ScenarioPlanningPayload;
    funnelAnalytics: FunnelAnalyticsPayload;
    panelOperations: PanelOperationsPayload;
};

export default function AdvisorDashboard({
    clientsHealth,
    cashFlowStatus,
    redFlags,
    documentVerificationFlags,
    messagesPending,
    entrepreneurReviews,
    strategicPlanDeployments,
    pendingTermsReacceptance,
    prospectInbox,
    integrationHealth,
    economicIndicators,
    pvWaterfall,
    practiceHealth,
    proposalStatus,
    paymentStatus,
    feeStatus,
    questionnaireOptimisation,
    wellbeingAnalytics,
    coachSignals,
    npoPendingConversions,
    npoFunding,
    referenceDataTasks,
    scenarioPlanning,
    funnelAnalytics,
    panelOperations,
}: Props) {
    const [activeTab, setActiveTab] =
        useState<DashboardTab>(initialDashboardTab);
    const actionItems = buildActionSummaryItems({
        cashFlowStatus,
        redFlags,
        documentVerificationFlags,
        entrepreneurReviews,
        strategicPlanDeployments,
        pendingTermsReacceptance,
        proposalStatus,
        paymentStatus,
        feeStatus,
        pvWaterfall,
        practiceHealth,
        npoPendingConversions,
        npoFunding,
        referenceDataTasks,
        scenarioPlanning,
        panelOperations,
    });
    const actionQueueCount = actionItems.filter(
        (item) => item.value > 0 && item.priority !== 'neutral',
    ).length;

    return (
        <>
            <Head title="Advisor dashboard" />

            <div className="space-y-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Activity className="size-4" aria-hidden="true" />
                            Live workspace
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            Advisor dashboard
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Start with action queues, then move into portfolio
                            decisions and operating signals.
                        </p>
                    </div>

                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-6">
                        <Metric
                            label="Clients"
                            value={clientsHealth.summary.advisory_clients}
                            explanation="Clients counts advisory client records visible to your current advisor role."
                            href="/advisor/clients"
                        />
                        <Metric
                            label="Entrepreneurs"
                            value={clientsHealth.summary.entrepreneurs}
                            explanation="Entrepreneurs counts founder workspaces visible to your current advisor role."
                            href="/advisor/entrepreneurs"
                        />
                        <Metric
                            label="Action queues"
                            value={actionQueueCount}
                            explanation="Action queues count dashboard areas with open priority work for the advisor team."
                            href="#advisor-command-centre"
                        />
                        <Metric
                            label="Needs attention"
                            value={clientsHealth.summary.needs_attention}
                            explanation="Needs attention counts client records with low engagement signals or document verification attention."
                            href="#advisor-clients-health"
                        />
                        <Metric
                            label="Messages pending"
                            value={messagesPending.total}
                            explanation="Messages pending counts client and entrepreneur conversations where the latest message is inbound and awaiting advisor attention."
                            href={messagesPending.index_url}
                        />
                        <Metric
                            label={pvWaterfall.summary.target_pv_label}
                            value={formatCurrency(
                                pvWaterfall.summary.target_pv,
                            )}
                            explanation={`Modelled upside assumes surfaced improvements and risk mitigations are fully captured. Planning range ${formatCurrency(pvWaterfall.summary.target_pv_range.low)} - ${formatCurrency(pvWaterfall.summary.target_pv_range.high)}.`}
                            href="#advisor-pv-waterfall"
                        />
                    </div>
                </header>

                <div
                    className="inline-flex flex-wrap gap-1 rounded-md border bg-muted/30 p-1"
                    role="tablist"
                    aria-label="Advisor dashboard sections"
                >
                    <DashboardTabButton
                        active={activeTab === 'priorities'}
                        onClick={() => setActiveTab('priorities')}
                        label="Priorities"
                        count={actionQueueCount}
                        controls="advisor-dashboard-priorities"
                    />
                    <DashboardTabButton
                        active={activeTab === 'signals'}
                        onClick={() => setActiveTab('signals')}
                        label="Signals"
                        count={
                            integrationHealth.summary.amber +
                            integrationHealth.summary.red +
                            economicIndicators.summary.change_alerts +
                            referenceDataTasks.summary.missing +
                            referenceDataTasks.summary.overdue +
                            referenceDataTasks.summary.due_soon +
                            funnelAnalytics.summary.abandoned +
                            questionnaireOptimisation.summary
                                .detected_candidates
                        }
                        controls="advisor-dashboard-signals"
                    />
                </div>

                {activeTab === 'priorities' && (
                    <div
                        id="advisor-dashboard-priorities"
                        role="tabpanel"
                        className="space-y-6"
                    >
                        <ActionCommandCentre
                            items={actionItems}
                            activeTab={activeTab}
                            onSelectTab={setActiveTab}
                        />

                        <DashboardSection
                            title="Action panel"
                            description="Work the live queues before moving into planning and portfolio decisions."
                        >
                            <div className="grid items-start gap-4 xl:grid-cols-2">
                                <EntrepreneurReviewPanel
                                    payload={entrepreneurReviews}
                                />
                                <CashFlowRiskPanel payload={cashFlowStatus} />
                                <StrategicPlanDeploymentPanel
                                    payload={strategicPlanDeployments}
                                />
                                <div id="advisor-documents">
                                    <DocumentVerificationFlagPanel
                                        flags={documentVerificationFlags}
                                    />
                                </div>
                                <PendingTermsReacceptance
                                    payload={pendingTermsReacceptance}
                                />
                            </div>

                            <div className="grid items-start gap-4 xl:grid-cols-2">
                                <ProposalStatusPanel payload={proposalStatus} />
                                <PaymentStatusPanel payload={paymentStatus} />
                            </div>
                        </DashboardSection>

                        <DashboardSection
                            title="Portfolio decisions"
                            description="Review client health, PV opportunity, practice position, and scenario options."
                        >
                            <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(340px,0.95fr)]">
                                <MyClientsHealth payload={clientsHealth} />
                                <PvWaterfallPanel payload={pvWaterfall} />
                            </div>

                            <div className="grid items-start gap-4 xl:grid-cols-2">
                                <PracticeHealth payload={practiceHealth} />
                                <ScenarioPlanning payload={scenarioPlanning} />
                            </div>
                        </DashboardSection>

                        <DashboardSection
                            title="Risk review"
                            description="AI red flags sit here for advisor review after the live action queues are clear."
                        >
                            <RedFlagPanel payload={redFlags} />
                        </DashboardSection>
                    </div>
                )}

                {activeTab === 'signals' && (
                    <div
                        id="advisor-dashboard-signals"
                        role="tabpanel"
                        className="space-y-6"
                    >
                        <DashboardSection
                            title="Panel operations"
                            description="Track partner hand-offs and governed learning work that supports the advisory team."
                        >
                            <PanelOperations payload={panelOperations} />
                        </DashboardSection>

                        <DashboardSection
                            title="Specialist workflows"
                            description="Monitor NPO conversion, funding, wellbeing, coaching, and prospect signals."
                        >
                            <div className="grid gap-4 xl:grid-cols-3">
                                <NpoPendingConversions
                                    payload={npoPendingConversions}
                                />
                                <NpoFundingPanel payload={npoFunding} />
                                <ProspectInbox payload={prospectInbox} />
                            </div>

                            <div className="grid gap-4 xl:grid-cols-3">
                                <WellbeingAnalytics
                                    payload={wellbeingAnalytics}
                                />
                                <CoachSignals payload={coachSignals} />
                            </div>
                        </DashboardSection>

                        <DashboardSection
                            title="Operating signals"
                            description="Use these lower-urgency indicators to spot systemic issues and improvement opportunities."
                        >
                            <div className="grid gap-4 xl:grid-cols-3">
                                <EconomicIndicators
                                    payload={economicIndicators}
                                />
                                <ReferenceDataTasksPanel
                                    payload={referenceDataTasks}
                                />
                                <IntegrationHealth
                                    payload={integrationHealth}
                                />
                                <FunnelAnalytics payload={funnelAnalytics} />
                                <QuestionnaireOptimisation
                                    payload={questionnaireOptimisation}
                                />
                            </div>
                        </DashboardSection>
                    </div>
                )}
            </div>
        </>
    );
}

function DashboardTabButton({
    active,
    onClick,
    label,
    count,
    controls,
}: {
    active: boolean;
    onClick: () => void;
    label: string;
    count: number;
    controls: string;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            aria-controls={controls}
            onClick={onClick}
            className={cn(
                'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                active
                    ? 'bg-sidebar-primary text-sidebar-primary-foreground shadow-sm'
                    : 'text-muted-foreground hover:bg-card hover:text-foreground',
            )}
        >
            {label}
            <Badge
                variant="outline"
                className={
                    active
                        ? 'border-sidebar-primary-foreground/20 bg-sidebar-primary-foreground/15 text-sidebar-primary-foreground'
                        : undefined
                }
            >
                {count}
            </Badge>
        </button>
    );
}

function DashboardSection({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: React.ReactNode;
}) {
    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-base font-semibold">{title}</h2>
                <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            <div className="space-y-4">{children}</div>
        </section>
    );
}

function ActionCommandCentre({
    items,
    activeTab,
    onSelectTab,
}: {
    items: ActionSummaryItem[];
    activeTab: DashboardTab;
    onSelectTab: (tab: DashboardTab) => void;
}) {
    const sortedItems = [...items].sort(
        (a, b) =>
            actionPriorityRank(a.priority) - actionPriorityRank(b.priority) ||
            b.value - a.value ||
            a.label.localeCompare(b.label),
    );
    const criticalCount = items.filter(
        (item) => item.value > 0 && item.priority === 'critical',
    ).length;
    const totalOpen = items.reduce(
        (total, item) =>
            item.priority === 'neutral' ? total : total + item.value,
        0,
    );

    return (
        <section
            id="advisor-command-centre"
            className="space-y-3 rounded-md border bg-sidebar-accent/70 p-3"
        >
            <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <Activity className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Command centre</h2>
                    </div>
                    <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                        Hover each queue to see why it matters and the next
                        action to take.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge
                        variant={criticalCount > 0 ? 'destructive' : 'outline'}
                    >
                        {criticalCount} critical
                    </Badge>
                    <Badge variant="secondary">{totalOpen} open items</Badge>
                </div>
            </div>

            <div className="grid grid-cols-[repeat(auto-fit,minmax(160px,1fr))] gap-2">
                {sortedItems.map((item) => (
                    <ActionSummaryCard
                        key={item.key}
                        item={item}
                        activeTab={activeTab}
                        onSelectTab={onSelectTab}
                    />
                ))}
            </div>
        </section>
    );
}

function ActionSummaryCard({
    item,
    activeTab,
    onSelectTab,
}: {
    item: ActionSummaryItem;
    activeTab: DashboardTab;
    onSelectTab: (tab: DashboardTab) => void;
}) {
    const handleClick = (event: React.MouseEvent<HTMLAnchorElement>) => {
        if (!item.href.startsWith('#')) {
            return;
        }

        event.preventDefault();

        if (item.tab !== activeTab) {
            onSelectTab(item.tab);
        }

        window.setTimeout(() => {
            const target = document.getElementById(item.targetId);

            if (!target) {
                return;
            }

            const highlightClasses = [
                'ring-2',
                'ring-primary',
                'ring-offset-2',
                'ring-offset-background',
                'transition-shadow',
                'scroll-mt-24',
            ];
            const previousTabIndex = target.getAttribute('tabindex');
            const hadTabIndex = target.hasAttribute('tabindex');

            target.setAttribute('tabindex', previousTabIndex ?? '-1');
            target.classList.add(...highlightClasses);
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            target.focus({ preventScroll: true });
            window.history.replaceState(null, '', item.href);

            window.setTimeout(() => {
                target.classList.remove(...highlightClasses);

                if (hadTabIndex && previousTabIndex !== null) {
                    target.setAttribute('tabindex', previousTabIndex);
                } else {
                    target.removeAttribute('tabindex');
                }
            }, 2200);
        }, 0);
    };

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <a
                    href={item.href}
                    onClick={handleClick}
                    className={cn(
                        'flex min-h-[58px] items-center gap-2 rounded-md border p-2.5 transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                        actionCardClasses(item.priority, item.value),
                    )}
                >
                    <div className="rounded-md border bg-card p-1.5 text-muted-foreground">
                        {item.icon}
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-start justify-between gap-2">
                            <div className="truncate text-base leading-none font-semibold tabular-nums">
                                {item.displayValue ?? item.value}
                            </div>
                            <Badge
                                variant={actionBadgeVariant(item)}
                                className="shrink-0 px-1.5 py-0 text-[10px]"
                            >
                                {item.statusLabel ??
                                    (item.priority === 'critical'
                                        ? 'Critical'
                                        : item.priority === 'warning'
                                          ? 'Review'
                                          : 'Clear')}
                            </Badge>
                        </div>
                        <div className="mt-1 truncate text-xs font-medium">
                            {item.label}
                        </div>
                    </div>
                </a>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-sm">
                <div className="space-y-2">
                    <p className="font-medium">{item.explanation}</p>
                    <p className="text-muted-foreground">{item.nextStep}</p>
                </div>
            </TooltipContent>
        </Tooltip>
    );
}

function buildActionSummaryItems({
    cashFlowStatus,
    redFlags,
    documentVerificationFlags,
    entrepreneurReviews,
    strategicPlanDeployments,
    pendingTermsReacceptance,
    proposalStatus,
    paymentStatus,
    feeStatus,
    pvWaterfall,
    practiceHealth,
    npoPendingConversions,
    npoFunding,
    referenceDataTasks,
    scenarioPlanning,
    panelOperations,
}: Pick<
    Props,
    | 'cashFlowStatus'
    | 'redFlags'
    | 'documentVerificationFlags'
    | 'entrepreneurReviews'
    | 'strategicPlanDeployments'
    | 'pendingTermsReacceptance'
    | 'proposalStatus'
    | 'paymentStatus'
    | 'feeStatus'
    | 'pvWaterfall'
    | 'practiceHealth'
    | 'npoPendingConversions'
    | 'npoFunding'
    | 'referenceDataTasks'
    | 'scenarioPlanning'
    | 'panelOperations'
>): ActionSummaryItem[] {
    const paymentActionCount =
        paymentStatus.summary.failed + paymentStatus.summary.retrying;
    const feesDisabled = feeStatus.free_access_mode;
    const proposalActionCount =
        proposalStatus.summary.expired + proposalStatus.summary.expiring_soon;
    const learningActionCount =
        panelOperations.learning.summary.detected +
        panelOperations.learning.summary.staged;
    const referenceDataActionCount =
        referenceDataTasks.summary.missing +
        referenceDataTasks.summary.overdue +
        referenceDataTasks.summary.due_soon;
    const brokerApprovalActionCount = panelOperations.approvals.summary.broker;
    const coachApprovalActionCount = panelOperations.approvals.summary.coach;
    const redFlagAction =
        redFlags.summary.open > 0
            ? {
                  key: 'red-flags',
                  label: 'AI red flags',
                  value: redFlags.summary.open,
                  href: '#advisor-red-flags',
                  targetId: 'advisor-red-flags',
                  tab: 'priorities' as const,
                  priority:
                      redFlags.summary.unacknowledged > 0
                          ? ('critical' as const)
                          : ('warning' as const),
                  explanation:
                      'AI red flags are advisor review prompts raised from analysed client evidence, findings, and risk triggers.',
                  nextStep:
                      'Open the risk review panel, acknowledge new items, and resolve risks once they have been reviewed with the client context.',
                  icon: <ShieldAlert className="size-4" aria-hidden="true" />,
              }
            : null;

    return [
        {
            key: 'cash-flow-risks',
            label: 'Cash flow risks',
            value: cashFlowStatus.summary.action_required,
            href: '#advisor-cash-flow-risks',
            targetId: 'advisor-cash-flow-risks',
            tab: 'priorities',
            priority:
                cashFlowStatus.summary.negative > 0
                    ? 'critical'
                    : cashFlowStatus.summary.watch > 0
                      ? 'warning'
                      : 'neutral',
            explanation:
                'Cash flow risks show clients with negative operating cash flow, short budget runway, or a forecast that has not reached cash-flow positive.',
            nextStep:
                'Open the risk panel, review the affected client, then update the budget, funding plan, debtor cadence, or strategic milestones.',
            icon: <Banknote className="size-4" aria-hidden="true" />,
        },
        ...(redFlagAction ? [redFlagAction] : []),
        {
            key: 'documents',
            label: 'Document review',
            value: documentVerificationFlags.length,
            href: '#advisor-documents',
            targetId: 'advisor-documents',
            tab: 'priorities',
            priority:
                documentVerificationFlags.length > 0 ? 'warning' : 'neutral',
            explanation:
                'Document review flags surface uploaded evidence that needs advisor verification.',
            nextStep:
                'Open each flagged document, confirm evidence quality, and clear or escalate the flag.',
            icon: <FileText className="size-4" aria-hidden="true" />,
        },
        {
            key: 'idea-validation-reviews',
            label: 'Idea reviews',
            value: entrepreneurReviews.summary.idea_validations,
            href: '#advisor-entrepreneur-reviews',
            targetId: 'advisor-entrepreneur-reviews',
            tab: 'priorities',
            priority:
                entrepreneurReviews.summary.idea_validations > 0
                    ? 'warning'
                    : 'neutral',
            explanation:
                'Idea reviews are entrepreneur concept validations waiting for advisor gate approval before the plan builder opens.',
            nextStep:
                'Open the entrepreneur record, review the submitted idea validation, then approve the builder gate with an advisor note.',
            icon: <Lightbulb className="size-4" aria-hidden="true" />,
        },
        {
            key: 'business-plan-reviews',
            label: 'Plan reviews',
            value: entrepreneurReviews.summary.business_plans,
            href: '#advisor-entrepreneur-reviews',
            targetId: 'advisor-entrepreneur-reviews',
            tab: 'priorities',
            priority:
                entrepreneurReviews.summary.business_plans > 0
                    ? 'warning'
                    : 'neutral',
            explanation:
                'Plan reviews are entrepreneur business plans submitted for assessment or waiting for final advisor review.',
            nextStep:
                'Open the entrepreneur record, run the assessment if needed, then finalise the feedback report.',
            icon: <FileText className="size-4" aria-hidden="true" />,
        },
        {
            key: 'strategic-plan-deployments',
            label: 'Strategic plans',
            value: strategicPlanDeployments.summary.total,
            href: '#advisor-strategic-plan-deployments',
            targetId: 'advisor-strategic-plan-deployments',
            tab: 'priorities',
            priority:
                strategicPlanDeployments.summary.total > 0
                    ? 'warning'
                    : 'neutral',
            explanation:
                'Strategic plan actions include signed proposals that need a plan generated and draft plans ready for deployment.',
            nextStep:
                'Generate missing strategic plans, then review draft plans with the client before deploying milestones.',
            icon: <ListChecks className="size-4" aria-hidden="true" />,
        },
        {
            key: 'terms',
            label: 'Terms re-acceptance',
            value: pendingTermsReacceptance.total,
            href: '#advisor-terms',
            targetId: 'advisor-terms',
            tab: 'priorities',
            priority:
                pendingTermsReacceptance.total > 0 ? 'warning' : 'neutral',
            explanation:
                'Client contacts in this queue must accept the latest terms before continuing portal workflows.',
            nextStep:
                'Follow up with the listed client contacts or confirm the terms gate is working as expected.',
            icon: <ShieldAlert className="size-4" aria-hidden="true" />,
        },
        {
            key: 'fee-status',
            label: 'Rates & fees',
            value: feesDisabled ? 1 : 0,
            displayValue: feesDisabled ? 'Free access' : 'Active',
            statusLabel: feesDisabled ? 'Inactive' : 'Active',
            href:
                feeStatus.can_manage && feeStatus.manage_url
                    ? feeStatus.manage_url
                    : '#advisor-dashboard-priorities',
            targetId: 'advisor-dashboard-priorities',
            tab: 'priorities',
            priority: feesDisabled ? 'warning' : 'neutral',
            explanation: feesDisabled
                ? 'Rates and fees are inactive. The app is in free-access mode, so fee calculations and package payments are set to zero.'
                : 'Rates and fees are active. Fee calculations and payment steps use the current admin service-rate settings.',
            nextStep:
                feesDisabled && feeStatus.can_manage && feeStatus.manage_url
                    ? 'Open Service rates when you are ready to reactivate fees and Stripe-backed collection.'
                    : feesDisabled
                      ? 'Ask a super admin to reactivate Service rates when charging should resume.'
                      : 'No action is required while rates and fees remain active.',
            icon: <Banknote className="size-4" aria-hidden="true" />,
        },
        {
            key: 'payments',
            label: 'Payment exceptions',
            value: paymentActionCount,
            href: '#advisor-payments',
            targetId: 'advisor-payments',
            tab: 'priorities',
            priority:
                paymentStatus.summary.failed > 0
                    ? 'critical'
                    : paymentActionCount > 0
                      ? 'warning'
                      : 'neutral',
            explanation:
                'Payment exceptions indicate failed or retrying transactions that may block delivery or renewals.',
            nextStep:
                'Open failed payments, retry where available, or contact the client for updated billing details.',
            icon: <CreditCard className="size-4" aria-hidden="true" />,
        },
        {
            key: 'proposals',
            label: 'Proposal expiry',
            value: proposalActionCount,
            href: '#advisor-proposals',
            targetId: 'advisor-proposals',
            tab: 'priorities',
            priority:
                proposalStatus.summary.expired > 0
                    ? 'critical'
                    : proposalActionCount > 0
                      ? 'warning'
                      : 'neutral',
            explanation:
                'Proposal expiry flags released proposals that need renewal, recall, or advisor follow-up.',
            nextStep:
                'Open expiring proposals and decide whether to renew, recall, or progress client sign-off.',
            icon: <FileText className="size-4" aria-hidden="true" />,
        },
        {
            key: 'pv-waterfall',
            label: 'PV waterfall',
            value: pvWaterfall.summary.clients,
            displayValue: formatCurrency(pvWaterfall.summary.target_pv),
            statusLabel: 'View',
            href: '#advisor-pv-waterfall',
            targetId: 'advisor-pv-waterfall',
            tab: 'priorities',
            priority: 'neutral',
            explanation:
                'PV waterfall bridges current portfolio value to modelled upside using improvement opportunities and risk-mitigation value.',
            nextStep:
                'Open the waterfall to see the featured client and hover each movement to understand the annual benefit, years, discount rate, and method.',
            icon: <TrendingUp className="size-4" aria-hidden="true" />,
        },
        {
            key: 'practice-health',
            label: 'Practice health',
            value: practiceHealth.summary.active_clients,
            displayValue: `${practiceHealth.summary.active_clients} active`,
            statusLabel: 'View',
            href: '#advisor-practice-health',
            targetId: 'advisor-practice-health',
            tab: 'priorities',
            priority: 'neutral',
            explanation:
                'Practice health measures the advisor portfolio position: active clients, revenue under management, target PV, released proposals, generated reports, and open red flags.',
            nextStep:
                'Open Practice health to see whether advisory work is converting into value, reports, and manageable risk across the visible portfolio.',
            icon: <PieChart className="size-4" aria-hidden="true" />,
        },
        {
            key: 'scenario-planning',
            label: 'Scenario planning',
            value: scenarioPlanning.summary.scenarios,
            displayValue: `${scenarioPlanning.summary.scenarios} scenarios`,
            statusLabel: 'View',
            href: '#advisor-scenario-planning',
            targetId: 'advisor-scenario-planning',
            tab: 'priorities',
            priority: 'neutral',
            explanation:
                'Scenario planning tracks prepared what-if cases and their PV impact so advisors can compare options before recommending action.',
            nextStep:
                'Open Scenario planning to review available scenarios or confirm no scenario work has been prepared yet.',
            icon: <BarChart3 className="size-4" aria-hidden="true" />,
        },
        {
            key: 'broker-approvals',
            label: 'Broker approvals',
            value: brokerApprovalActionCount,
            href: '#advisor-panel-approvals',
            targetId: 'advisor-panel-approvals',
            tab: 'signals',
            priority: brokerApprovalActionCount > 0 ? 'warning' : 'neutral',
            explanation:
                'Broker approvals count submitted broker applications waiting for advisor or admin review.',
            nextStep:
                'Open the partner approval queue, review the broker application, then approve, request more information, or decline it.',
            icon: <UsersRound className="size-4" aria-hidden="true" />,
        },
        {
            key: 'coach-approvals',
            label: 'Coach approvals',
            value: coachApprovalActionCount,
            href: '#advisor-panel-approvals',
            targetId: 'advisor-panel-approvals',
            tab: 'signals',
            priority: coachApprovalActionCount > 0 ? 'warning' : 'neutral',
            explanation:
                'Coach approvals count submitted coach applications waiting for advisor or admin review.',
            nextStep:
                'Open the partner approval queue, review the coach application, then approve, request more information, or decline it.',
            icon: <HeartHandshake className="size-4" aria-hidden="true" />,
        },
        {
            key: 'npo-funding',
            label: 'NPO funding',
            value: npoFunding.summary.active_alerts,
            href: '#advisor-npo-funding',
            targetId: 'advisor-npo-funding',
            tab: 'signals',
            priority:
                npoFunding.summary.critical_alerts > 0
                    ? 'critical'
                    : npoFunding.summary.active_alerts > 0
                      ? 'warning'
                      : 'neutral',
            explanation:
                'NPO funding alerts track deadlines or funder requirements that could affect client delivery.',
            nextStep:
                'Review critical alerts first and contact the client before the due date slips.',
            icon: <HeartHandshake className="size-4" aria-hidden="true" />,
        },
        {
            key: 'npo-conversions',
            label: 'NPO nudges',
            value: npoPendingConversions.summary.nudge_due,
            href: '#advisor-npo-conversions',
            targetId: 'advisor-npo-conversions',
            tab: 'signals',
            priority:
                npoPendingConversions.summary.nudge_due > 0
                    ? 'warning'
                    : 'neutral',
            explanation:
                'NPO nudges show delivered Governance Reviews where re-engagement is due.',
            nextStep:
                'Open the client record and decide whether to nudge, defer, or mark the conversion outcome.',
            icon: <Clock className="size-4" aria-hidden="true" />,
        },
        {
            key: 'broker-referrals',
            label: 'Broker referrals',
            value: panelOperations.broker.summary.active,
            href: '#advisor-broker-referrals',
            targetId: 'advisor-broker-referrals',
            tab: 'signals',
            priority:
                panelOperations.broker.summary.active > 0
                    ? 'warning'
                    : 'neutral',
            explanation:
                'Broker referrals track active hand-offs to broker partners and their current stage.',
            nextStep:
                'Open referral details, confirm progress, and chase stale partner responses.',
            icon: <Inbox className="size-4" aria-hidden="true" />,
        },
        {
            key: 'coach-referrals',
            label: 'Coach referrals',
            value: panelOperations.coach.summary.active,
            href: '#advisor-coach-referrals',
            targetId: 'advisor-coach-referrals',
            tab: 'signals',
            priority:
                panelOperations.coach.summary.active > 0
                    ? 'warning'
                    : 'neutral',
            explanation:
                'Coach referrals track founder or client support hand-offs that need follow-through.',
            nextStep:
                'Open the hand-off, confirm the coach stage, and update the referral once progressed.',
            icon: <HeartHandshake className="size-4" aria-hidden="true" />,
        },
        {
            key: 'reference-data',
            label: 'Reference data',
            value: referenceDataActionCount,
            href: '#advisor-reference-data-tasks',
            targetId: 'advisor-reference-data-tasks',
            tab: 'signals',
            priority:
                referenceDataTasks.summary.missing +
                    referenceDataTasks.summary.overdue >
                0
                    ? 'warning'
                    : referenceDataActionCount > 0
                      ? 'warning'
                      : 'neutral',
            explanation:
                'Reference-data tasks show manual economic, valuation, WACC, and NPO benchmark figures that are due for refresh.',
            nextStep:
                'Open Reference Data and record a new value, then approve and implement it through the learning queue.',
            icon: <DatabaseZap className="size-4" aria-hidden="true" />,
        },
        {
            key: 'learning-queue',
            label: 'Learning queue',
            value: learningActionCount,
            href: '#advisor-learning-queue',
            targetId: 'advisor-learning-queue',
            tab: 'signals',
            priority: learningActionCount > 0 ? 'warning' : 'neutral',
            explanation:
                'Learning queue items are governed model, questionnaire, and methodology updates awaiting review.',
            nextStep:
                'Review staged updates before approving changes into the live advisory workflow.',
            icon: <Sparkles className="size-4" aria-hidden="true" />,
        },
    ];
}

function actionPriorityRank(priority: ActionPriority): number {
    if (priority === 'critical') {
        return 0;
    }

    if (priority === 'warning') {
        return 1;
    }

    return 2;
}

function actionBadgeVariant(
    item: ActionSummaryItem,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (item.value === 0) {
        return 'outline';
    }

    if (item.priority === 'critical') {
        return 'destructive';
    }

    if (item.priority === 'warning') {
        return 'secondary';
    }

    return 'outline';
}

function actionCardClasses(priority: ActionPriority, value: number): string {
    if (value === 0 || priority === 'neutral') {
        return 'bg-card hover:bg-white dark:hover:bg-card/90';
    }

    if (priority === 'critical') {
        return 'border-destructive/40 bg-destructive/5 hover:bg-destructive/10';
    }

    return 'border-amber-300 bg-amber-50/60 hover:bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10 dark:hover:bg-amber-500/15';
}

function initialDashboardTab(): DashboardTab {
    if (typeof window === 'undefined') {
        return 'priorities';
    }

    return signalPanelTargetIds.has(window.location.hash.replace('#', ''))
        ? 'signals'
        : 'priorities';
}

function ProposalStatusPanel({ payload }: { payload: ProposalStatusPayload }) {
    const statusOrder = ['draft', 'released', 'recalled', 'expired', 'renewed'];

    return (
        <section
            id="advisor-proposals"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <FileText className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Proposals</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {payload.summary.total} total
                    </Badge>
                    <Badge
                        variant={
                            payload.summary.expiring_soon > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.expiring_soon} expiring
                    </Badge>
                </div>
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                {statusOrder.map((status) => (
                    <PortfolioMetric
                        key={status}
                        label={formatLabel(status)}
                        value={(payload.statuses[status] ?? 0).toString()}
                    />
                ))}
            </div>

            {payload.expiry_alerts.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No released proposals are expiring soon.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.expiry_alerts.map((proposal) => (
                        <div
                            key={proposal.id}
                            className="grid gap-3 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <ClientNameLink
                                    name={proposal.client_name}
                                    href={proposal.client_url}
                                    className="truncate text-sm"
                                />
                                <div className="mt-1 text-xs text-muted-foreground">
                                    v{proposal.version} -{' '}
                                    {formatDate(proposal.expires_at)}
                                </div>
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <Link href={proposal.client_url}>Open</Link>
                            </Button>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

function PaymentStatusPanel({ payload }: { payload: PaymentStatusPayload }) {
    return (
        <section
            id="advisor-payments"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <CreditCard className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Payment exceptions</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant={
                            payload.summary.failed > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.failed} failed
                    </Badge>
                    <Badge variant="secondary">
                        {payload.summary.retryable} retryable
                    </Badge>
                </div>
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No failed or retrying payments.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((payment) => (
                        <article
                            key={payment.id}
                            className="grid gap-3 p-3 sm:grid-cols-[minmax(0,1fr)_auto]"
                        >
                            <InsightHoverCard
                                title={payment.client_name ?? 'Client payment'}
                                rows={[
                                    {
                                        label: 'Amount',
                                        value: formatMoney(
                                            payment.amount,
                                            payment.currency,
                                        ),
                                    },
                                    {
                                        label: 'Processed',
                                        value: formatDate(payment.processed_at),
                                    },
                                    {
                                        label: 'Failure reason',
                                        value:
                                            payment.failed_reason ??
                                            'Not recorded',
                                        tone: payment.failed_reason
                                            ? 'negative'
                                            : 'muted',
                                    },
                                    {
                                        label: 'Attempt',
                                        value: `#${payment.attempt}`,
                                    },
                                    {
                                        label: 'Next retry',
                                        value: formatDate(
                                            payment.automatic_next_retry_at,
                                        ),
                                        tone: payment.automatic_next_retry_at
                                            ? 'default'
                                            : 'muted',
                                    },
                                ]}
                                drillHref={payment.drill_url}
                                drillAriaLabel={`Open payment record for ${payment.client_name ?? 'client'}`}
                                footer={
                                    payment.manual_retry_available
                                        ? 'Manual retry available'
                                        : 'Retry unavailable'
                                }
                            >
                                <div className="min-w-0 space-y-1 text-left focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2">
                                    <span className="flex flex-wrap items-center gap-2">
                                        <Badge
                                            variant={paymentStatusVariant(
                                                payment.status,
                                            )}
                                        >
                                            {formatLabel(payment.status)}
                                        </Badge>
                                        <span className="text-sm font-medium">
                                            {formatMoney(
                                                payment.amount,
                                                payment.currency,
                                            )}
                                        </span>
                                    </span>
                                    <ClientNameLink
                                        name={payment.client_name}
                                        href={payment.client_url}
                                        className="block truncate text-sm"
                                    />
                                    <span className="block text-xs text-muted-foreground">
                                        Attempt {payment.attempt} -{' '}
                                        {formatDate(payment.processed_at)}
                                    </span>
                                </div>
                            </InsightHoverCard>
                            <Button asChild size="sm" variant="outline">
                                <Link href={payment.drill_url}>Open</Link>
                            </Button>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function NpoPendingConversions({
    payload,
}: {
    payload: NpoPendingConversionsPayload;
}) {
    return (
        <section
            id="advisor-npo-conversions"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <Clock className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Pending conversion</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {payload.summary.total} total
                    </Badge>
                    <Badge
                        variant={
                            payload.summary.nudge_due > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.nudge_due} nudge due
                    </Badge>
                </div>
            </div>

            <div className="grid gap-2 sm:grid-cols-3">
                <PortfolioMetric
                    label="Delivered"
                    value={payload.summary.report_delivered.toString()}
                />
                <PortfolioMetric
                    label="Declined"
                    value={payload.summary.declined.toString()}
                />
                <PortfolioMetric
                    label="Due"
                    value={payload.summary.nudge_due.toString()}
                />
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No Governance Review conversions are pending.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article
                            key={item.id}
                            className="grid gap-3 p-3 sm:grid-cols-[minmax(0,1fr)_auto]"
                        >
                            <div className="min-w-0 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge
                                        variant={
                                            item.status === 'declined'
                                                ? 'outline'
                                                : 'secondary'
                                        }
                                    >
                                        {item.status_label ??
                                            formatLabel(item.status ?? '')}
                                    </Badge>
                                    {item.next_nudge_day && (
                                        <Badge variant="destructive">
                                            {item.next_nudge_day}d
                                        </Badge>
                                    )}
                                </div>
                                <ClientNameLink
                                    name={item.client_name}
                                    href={item.client_url}
                                    className="truncate text-sm"
                                />
                                <div className="text-xs text-muted-foreground">
                                    Delivered{' '}
                                    {formatDate(item.report_delivered_at)} ·
                                    re-engage{' '}
                                    {formatDateOnly(item.reengagement_due_at)}
                                </div>
                                {item.decline_reason && (
                                    <div className="line-clamp-2 text-xs text-muted-foreground">
                                        {item.decline_reason}
                                    </div>
                                )}
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <Link href={item.client_url}>Open</Link>
                            </Button>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function NpoFundingPanel({ payload }: { payload: NpoFundingPayload }) {
    return (
        <section
            id="advisor-npo-funding"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <HeartHandshake className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">NPO funding</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {payload.summary.active_records} active
                    </Badge>
                    <Badge
                        variant={
                            payload.summary.critical_alerts > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.active_alerts} alerts
                    </Badge>
                </div>
            </div>

            {payload.alerts.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No funder deadlines are currently due.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.alerts.map((alert) => (
                        <article
                            key={alert.id}
                            className="grid gap-3 p-3 sm:grid-cols-[minmax(0,1fr)_auto]"
                        >
                            <div className="min-w-0 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge
                                        variant={
                                            alert.severity === 'critical'
                                                ? 'destructive'
                                                : 'outline'
                                        }
                                    >
                                        {formatLabel(alert.severity)}
                                    </Badge>
                                    <Badge variant="secondary">
                                        {formatLabel(alert.type)}
                                    </Badge>
                                </div>
                                <ClientNameLink
                                    name={alert.client_name}
                                    href={alert.client_url}
                                    className="truncate text-sm"
                                />
                                <div className="text-xs text-muted-foreground">
                                    {alert.funder_name ?? 'Funder'} - due{' '}
                                    {formatDateOnly(alert.due_on)}
                                </div>
                                <div className="line-clamp-2 text-xs text-muted-foreground">
                                    {alert.message}
                                </div>
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <Link href={alert.client_url}>Open</Link>
                            </Button>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function PracticeHealth({ payload }: { payload: PracticeHealthPayload }) {
    const topClients = [...payload.clients]
        .sort((a, b) => b.target_pv - a.target_pv)
        .slice(0, 4);

    return (
        <section
            id="advisor-practice-health"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <PieChart className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Practice health</h2>
                    </div>
                    <p className="mt-1 max-w-2xl text-xs text-muted-foreground">
                        Measures portfolio position: active clients, revenue
                        under management, target PV, released proposals,
                        generated reports, and open red flags.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {payload.summary.active_clients} active
                    </Badge>
                    <Badge
                        variant={
                            payload.phase_two.open_red_flags > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.phase_two.open_red_flags} red flags
                    </Badge>
                </div>
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                <PortfolioMetric
                    label="Modelled upside PV"
                    value={formatCurrency(payload.summary.target_pv)}
                />
                <PortfolioMetric
                    label="Revenue"
                    value={formatCurrency(
                        payload.summary.revenue_under_management,
                    )}
                />
                <PortfolioMetric
                    label="Released proposals"
                    value={payload.phase_two.released_proposals.toString()}
                />
                <PortfolioMetric
                    label="Reports"
                    value={payload.phase_two.generated_reports.toString()}
                />
            </div>

            {topClients.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No active client PV portfolio yet.
                </p>
            ) : (
                <div className="max-h-[280px] divide-y overflow-y-auto rounded-md border">
                    {topClients.map((client) => (
                        <div
                            key={client.client_id}
                            className="grid gap-2 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <ClientNameLink
                                    name={client.client_name}
                                    href={client.client_url}
                                    className="truncate text-sm"
                                />
                                <div className="mt-1 text-xs text-muted-foreground">
                                    Revenue{' '}
                                    {formatCurrency(
                                        client.revenue_under_management,
                                    )}
                                </div>
                            </div>
                            <div className="text-sm font-medium sm:text-right">
                                {formatCurrency(client.target_pv)}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

function QuestionnaireOptimisation({
    payload,
}: {
    payload: QuestionnaireOptimisationPayload;
}) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <Sparkles className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Questionnaire optimisation
                    </h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant={
                            payload.summary.detected_candidates > 0
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {payload.summary.detected_candidates} candidates
                    </Badge>
                    <Badge variant="outline">
                        {payload.summary.latest_candidates_created} latest
                    </Badge>
                </div>
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No governed candidates queued.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article key={item.id} className="space-y-2 p-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">
                                    {formatLabel(item.magnitude)}
                                </Badge>
                                <span className="text-xs text-muted-foreground">
                                    {formatPercent(item.confidence)} confidence
                                </span>
                            </div>
                            <h3 className="line-clamp-2 text-sm font-medium">
                                {item.questionnaire_title ??
                                    'Questionnaire candidate'}
                            </h3>
                            <p className="line-clamp-3 text-sm text-muted-foreground">
                                {item.summary}
                            </p>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function WellbeingAnalytics({
    payload,
}: {
    payload: WellbeingAnalyticsPayload;
}) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4 xl:col-span-2">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <HeartPulse className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Wellbeing trends</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {payload.summary.checkins} check-ins
                    </Badge>
                    <Badge
                        variant={
                            payload.summary.active_low_coping_signals > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.active_low_coping_signals} signals
                    </Badge>
                </div>
            </div>

            <div className="grid gap-2 sm:grid-cols-4">
                <PortfolioMetric
                    label="Confidence"
                    value={`${payload.summary.average_business_confidence}/5`}
                />
                <PortfolioMetric
                    label="Coping"
                    value={`${payload.summary.average_personal_coping}/5`}
                />
                <PortfolioMetric
                    label="Low coping"
                    value={payload.summary.low_personal_coping_checkins.toString()}
                />
                <PortfolioMetric
                    label="This month"
                    value={formatPercent(
                        payload.summary.current_period_completion_rate,
                    )}
                />
            </div>

            {payload.monthly.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No wellbeing check-ins captured yet.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.monthly.slice(-6).map((month) => (
                        <div
                            key={month.period_start}
                            className="grid gap-2 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <div className="text-sm font-medium">
                                    {formatDateOnly(month.period_start)}
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {month.checkins} check-ins -{' '}
                                    {month.low_personal_coping_checkins} low
                                    coping
                                </div>
                            </div>
                            <div className="text-sm text-muted-foreground sm:text-right">
                                <div>
                                    Confidence{' '}
                                    {month.average_business_confidence}/5
                                </div>
                                <div>
                                    Coping {month.average_personal_coping}/5
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

function CoachSignals({ payload }: { payload: CoachSignalsPayload }) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <HeartHandshake className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Coach signals</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {payload.summary.total} suggested
                    </Badge>
                    <Badge variant="outline">
                        {payload.summary.auto_referrals} auto
                    </Badge>
                </div>
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No coach signal suggestions surfaced.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.slice(0, 5).map((item) => (
                        <div key={item.id} className="space-y-2 p-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">
                                    {formatLabel(item.suggested_specialisation)}
                                </Badge>
                                <ClientNameLink
                                    name={item.client_name}
                                    href={item.client_url}
                                    className="text-sm"
                                />
                            </div>
                            <p className="text-xs leading-5 text-muted-foreground">
                                {item.rationale}
                            </p>
                            <div className="text-xs text-muted-foreground">
                                {formatLabel(item.signal_type ?? 'signal')} -{' '}
                                {item.threshold_ref}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

function FunnelAnalytics({ payload }: { payload: FunnelAnalyticsPayload }) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <BarChart3 className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Funnel analytics</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {payload.summary.events} events
                    </Badge>
                    <Badge
                        variant={
                            payload.summary.abandoned > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.abandoned} abandoned
                    </Badge>
                </div>
            </div>

            {payload.steps.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No funnel events captured yet.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.steps.slice(0, 6).map((step) => (
                        <InsightHoverCard
                            key={`${step.flow}-${step.step}`}
                            title={`${formatLabel(step.flow)} / ${formatLabel(step.step)}`}
                            rows={[
                                {
                                    label: 'Entered',
                                    value: String(step.entered),
                                },
                                {
                                    label: 'Dropped',
                                    value: String(step.dropped_count),
                                    tone:
                                        step.dropped_count > 0
                                            ? 'negative'
                                            : 'default',
                                },
                                {
                                    label: 'Last dropped',
                                    value: formatDate(step.last_dropped_at),
                                    tone: step.last_dropped_at
                                        ? 'default'
                                        : 'muted',
                                },
                                {
                                    label: 'Returned',
                                    value: String(step.returned_count),
                                    tone:
                                        step.returned_count > 0
                                            ? 'positive'
                                            : 'default',
                                },
                            ]}
                        >
                            <div
                                tabIndex={0}
                                className="grid gap-2 p-3 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none sm:grid-cols-[1fr_auto]"
                            >
                                <div className="min-w-0">
                                    <div className="text-sm font-medium">
                                        {formatLabel(step.flow)} /{' '}
                                        {formatLabel(step.step)}
                                    </div>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        {step.completed} of {step.entered}{' '}
                                        completed
                                    </div>
                                    {step.dropped_clients.length > 0 && (
                                        <details className="mt-2 text-xs">
                                            <summary className="cursor-pointer font-medium text-primary outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                                Open
                                            </summary>
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                {step.dropped_clients.map(
                                                    (client) => (
                                                        <Link
                                                            key={client.id}
                                                            href={
                                                                client.show_url
                                                            }
                                                            className="rounded-md border px-2 py-1 text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                                        >
                                                            {client.name}
                                                        </Link>
                                                    ),
                                                )}
                                            </div>
                                        </details>
                                    )}
                                </div>
                                <div className="text-sm font-medium sm:text-right">
                                    {formatPercent(step.drop_off_rate)} drop-off
                                </div>
                            </div>
                        </InsightHoverCard>
                    ))}
                </div>
            )}
        </section>
    );
}

function ScenarioPlanning({ payload }: { payload: ScenarioPlanningPayload }) {
    return (
        <section
            id="advisor-scenario-planning"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <TrendingUp className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Scenario planning</h2>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge variant="secondary">
                        {payload.summary.scenarios} scenarios
                    </Badge>
                    <Badge variant="outline">
                        {payload.summary.clients} clients
                    </Badge>
                </div>
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No scenarios prepared yet.
                </p>
            ) : (
                <div className="max-h-[280px] divide-y overflow-y-auto rounded-md border">
                    {payload.items.slice(0, 5).map((scenario) => (
                        <article
                            key={scenario.id}
                            className="grid gap-3 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h3 className="truncate text-sm font-medium">
                                        {scenario.name}
                                    </h3>
                                    <Badge variant="outline">
                                        {formatLabel(scenario.kind)}
                                    </Badge>
                                    {scenario.is_client_visible && (
                                        <Badge variant="secondary">
                                            Client
                                        </Badge>
                                    )}
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    <ClientNameLink
                                        name={scenario.client_name}
                                        href={scenario.client_url}
                                        className="text-xs"
                                    />
                                </div>
                            </div>
                            <div className="text-sm font-medium sm:text-right">
                                {formatCurrency(scenario.pv_impact)}
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function PvWaterfallPanel({ payload }: { payload: PvWaterfallPayload }) {
    const featuredClient =
        [...payload.clients].sort(
            (a, b) =>
                b.improvement_pv +
                b.risk_mitigation_pv -
                (a.improvement_pv + a.risk_mitigation_pv),
        )[0] ?? null;

    return (
        <section
            id="advisor-pv-waterfall"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <TrendingUp className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">
                            Portfolio PV waterfall
                        </h2>
                    </div>
                    <p className="mt-1 max-w-2xl text-xs text-muted-foreground">
                        Totals include all visible clients with PV data. The
                        chart highlights the client with the largest improvement
                        or risk-mitigation movement. Modelled upside includes a
                        +/-15% planning range and assumes surfaced improvements
                        and risk mitigations are fully captured.
                    </p>
                </div>
                <div className="flex flex-wrap justify-start gap-2 sm:justify-end">
                    <Badge variant="outline">
                        {payload.summary.clients} clients
                    </Badge>
                    <PvSummaryBadges
                        current={payload.summary.current_pv}
                        target={payload.summary.target_pv}
                        targetRange={payload.summary.target_pv_range}
                    />
                </div>
            </div>

            {featuredClient === null ? (
                <p className="text-sm text-muted-foreground">
                    No PV baseline has been calculated yet.
                </p>
            ) : (
                <div className="space-y-4">
                    <div>
                        <div className="text-sm font-medium">
                            Featured client:{' '}
                            <ClientNameLink
                                name={featuredClient.client_name}
                                href={featuredClient.client_url}
                                className="text-sm"
                            />
                        </div>
                        <div className="mt-1 text-xs text-muted-foreground">
                            {formatCurrency(featuredClient.improvement_pv)}{' '}
                            improvements +{' '}
                            {formatCurrency(featuredClient.risk_mitigation_pv)}{' '}
                            risk mitigation
                        </div>
                    </div>
                    <div className="max-h-[500px] overflow-y-auto pr-1">
                        <WaterfallChart steps={featuredClient.waterfall} />
                    </div>
                </div>
            )}
        </section>
    );
}

function RedFlagPanel({ payload }: { payload: RedFlagsPayload }) {
    const patch = (url: string) => {
        router.patch(url, {}, { preserveScroll: true });
    };

    return (
        <section
            id="advisor-red-flags"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <ShieldAlert className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">AI red flags</h2>
                </div>
                <div className="flex flex-wrap justify-end gap-2">
                    <Badge
                        variant={
                            payload.summary.unacknowledged > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.unacknowledged} new
                    </Badge>
                    <Badge variant="secondary">
                        {payload.summary.open} open
                    </Badge>
                </div>
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No open red flags.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((flag) => {
                        const openUrl = flag.finding_url ?? flag.client_url;

                        return (
                            <article key={flag.id} className="space-y-3 p-3">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <InsightHoverCard
                                        title={flag.headline}
                                        rows={[
                                            {
                                                label: 'Risk',
                                                value: flag.detail,
                                                tone: 'negative',
                                            },
                                            {
                                                label: 'Severity',
                                                value: formatLabel(
                                                    flag.severity,
                                                ),
                                                tone: 'negative',
                                            },
                                            {
                                                label: 'Detected',
                                                value: formatDate(
                                                    flag.surfaced_at,
                                                ),
                                            },
                                            {
                                                label: 'Trigger',
                                                value:
                                                    flag.trigger?.summary ??
                                                    'Source unavailable',
                                                tone: flag.trigger
                                                    ? 'default'
                                                    : 'muted',
                                            },
                                        ]}
                                        drillHref={
                                            flag.finding_url ?? undefined
                                        }
                                        drillAriaLabel={`Open finding for ${flag.headline}`}
                                        footer={
                                            flag.trigger
                                                ? `Source: ${flag.trigger.source_reference}`
                                                : undefined
                                        }
                                    >
                                        <div className="block min-w-0 space-y-1 text-left focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2">
                                            <span className="flex flex-wrap items-center gap-2">
                                                <Badge variant="destructive">
                                                    {formatLabel(flag.severity)}
                                                </Badge>
                                                <Badge variant="outline">
                                                    {formatLabel(flag.category)}
                                                </Badge>
                                                {flag.module && (
                                                    <Badge variant="secondary">
                                                        {formatLabel(
                                                            flag.module,
                                                        )}
                                                    </Badge>
                                                )}
                                            </span>
                                            <span className="block text-sm font-medium">
                                                {flag.headline}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                <ClientNameLink
                                                    name={flag.client_name}
                                                    href={flag.client_url}
                                                    className="text-xs text-muted-foreground"
                                                />{' '}
                                                - {formatDate(flag.surfaced_at)}
                                            </span>
                                        </div>
                                    </InsightHoverCard>
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={openUrl}>Open</Link>
                                    </Button>
                                </div>

                                <p className="line-clamp-3 text-sm text-muted-foreground">
                                    {flag.detail}
                                </p>

                                <div className="flex flex-wrap gap-2">
                                    {flag.acknowledged_at === null && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                patch(flag.acknowledge_url)
                                            }
                                        >
                                            <CheckCircle2
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Acknowledge
                                        </Button>
                                    )}
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => patch(flag.resolve_url)}
                                    >
                                        <CheckCircle2
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Resolve
                                    </Button>
                                </div>
                            </article>
                        );
                    })}
                </div>
            )}
        </section>
    );
}

function ClientNameLink({
    name,
    href,
    className,
}: {
    name: React.ReactNode;
    href?: string | null;
    className?: string;
}) {
    const content = name ?? 'Client';

    if (!href) {
        return <span className={cn('font-medium', className)}>{content}</span>;
    }

    return (
        <Link
            href={href}
            className={cn(
                'inline-block max-w-full font-medium text-foreground hover:underline focus-visible:underline focus-visible:outline-none',
                className,
            )}
        >
            {content}
        </Link>
    );
}

function PortfolioMetric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border px-3 py-2">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-sm font-medium">{value}</div>
        </div>
    );
}

function Metric({
    label,
    value,
    explanation,
    href,
}: {
    label: string;
    value: number | string;
    explanation: string;
    href: string;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <a
                    href={href}
                    className="rounded-md border bg-card px-4 py-3 shadow-card transition-colors outline-none hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 dark:hover:bg-card/90"
                >
                    <div className="text-xs text-muted-foreground">{label}</div>
                    <div className="mt-1 text-lg font-semibold">{value}</div>
                </a>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {explanation}
            </TooltipContent>
        </Tooltip>
    );
}

function MyClientsHealth({ payload }: { payload: ClientsHealthPayload }) {
    return (
        <section
            id="advisor-clients-health"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <UsersRound className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">My clients health</h2>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge variant="secondary">
                        {payload.summary.high} green
                    </Badge>
                    <Badge variant="outline">
                        {payload.summary.medium} amber
                    </Badge>
                    <Badge variant="destructive">
                        {payload.summary.needs_attention} attention
                    </Badge>
                </div>
            </div>

            {payload.clients.length === 0 ? (
                <p className="rounded-md border px-3 py-8 text-sm text-muted-foreground">
                    No assigned clients.
                </p>
            ) : (
                <div className="max-h-[460px] overflow-y-auto rounded-md border">
                    <table className="fsa-responsive-table">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Client
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Engagement
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Cash flow
                                </th>
                                <th className="px-3 py-2 font-medium">Flags</th>
                                <th className="px-3 py-2 font-medium">
                                    Activity
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Open
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {payload.clients.map((client) => (
                                <tr key={client.id} className="border-t">
                                    <td
                                        className="px-3 py-2"
                                        data-label="Client"
                                    >
                                        <Link
                                            href={client.show_url}
                                            className="font-medium hover:underline focus-visible:underline focus-visible:outline-none"
                                        >
                                            {client.legal_name}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {client.trading_name ??
                                                client.engagement_type_label}
                                        </div>
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Engagement"
                                    >
                                        <EngagementBadge
                                            engagement={client.engagement}
                                        />
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Cash flow"
                                    >
                                        <CashFlowBadge
                                            cashFlow={client.cash_flow}
                                        />
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Flags"
                                    >
                                        <Badge
                                            variant={
                                                client.open_document_flags_count >
                                                0
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                        >
                                            {client.open_document_flags_count}
                                        </Badge>
                                    </td>
                                    <td
                                        className="px-3 py-2 text-muted-foreground"
                                        data-label="Activity"
                                    >
                                        {formatDate(client.last_activity_at)}
                                    </td>
                                    <td
                                        className="px-3 py-2 text-left md:text-right"
                                        data-label="Open"
                                    >
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link href={client.show_url}>
                                                Open
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

function EngagementBadge({ engagement }: { engagement: EngagementScore }) {
    return (
        <InsightHoverCard
            title={`${engagement.score}% engagement`}
            rows={[
                {
                    label: 'Questionnaire',
                    value: `${engagement.scores.questionnaire_pct}%`,
                },
                {
                    label: 'Documents',
                    value: `${engagement.scores.documents_pct}%`,
                },
                {
                    label: 'Milestones',
                    value: `${engagement.scores.milestones_on_track_pct}% (${engagement.display.overdue_count} overdue / ${engagement.display.blocked_count} blocked)`,
                    tone:
                        engagement.display.overdue_count > 0 ||
                        engagement.display.blocked_count > 0
                            ? 'negative'
                            : 'default',
                },
                {
                    label: 'Last comms',
                    value:
                        engagement.display.last_comms_days === null
                            ? 'Never'
                            : `${engagement.display.last_comms_days} days`,
                },
            ]}
            drillHref={engagement.drill_url}
            drillAriaLabel={`Open ${engagementLabel(engagement.level)} engagement section`}
        >
            <button
                type="button"
                className="rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
            >
                <Badge variant={healthVariant(engagement.level)}>
                    {engagementLabel(engagement.level)} / {engagement.score}
                </Badge>
            </button>
        </InsightHoverCard>
    );
}

function CashFlowBadge({ cashFlow }: { cashFlow: CashFlowStatus }) {
    return (
        <InsightHoverCard
            title={`Cash flow: ${cashFlow.status_label}`}
            rows={[
                {
                    label: 'Status',
                    value: cashFlow.status_label,
                    tone:
                        cashFlow.status === 'negative'
                            ? 'negative'
                            : cashFlow.status === 'positive'
                              ? 'positive'
                              : cashFlow.status === 'unknown'
                                ? 'muted'
                                : 'default',
                },
                {
                    label: 'Reason',
                    value: cashFlow.reason,
                    tone:
                        cashFlow.status === 'negative'
                            ? 'negative'
                            : cashFlow.status === 'unknown'
                              ? 'muted'
                              : 'default',
                },
                {
                    label: 'Latest OCF',
                    value:
                        cashFlow.latest_operating_cash_flow === null
                            ? 'Not available'
                            : formatCurrency(
                                  cashFlow.latest_operating_cash_flow,
                              ),
                    tone:
                        cashFlow.latest_operating_cash_flow !== null &&
                        cashFlow.latest_operating_cash_flow < 0
                            ? 'negative'
                            : 'default',
                },
                {
                    label: 'Runway',
                    value: formatRunway(cashFlow),
                },
                {
                    label: 'Source',
                    value: cashFlow.source,
                },
            ]}
            drillHref={cashFlow.detail_url}
            drillAriaLabel={`Open cash flow context for ${cashFlow.client_name}`}
        >
            <button
                type="button"
                className="rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
            >
                <Badge variant={cashFlowBadgeVariant(cashFlow)}>
                    {cashFlow.status_label}
                </Badge>
            </button>
        </InsightHoverCard>
    );
}

function cashFlowBadgeVariant(
    cashFlow: CashFlowStatus,
): React.ComponentProps<typeof Badge>['variant'] {
    if (cashFlow.status === 'negative') {
        return 'destructive';
    }

    if (cashFlow.status === 'watch') {
        return 'secondary';
    }

    return 'outline';
}

function PendingTermsReacceptance({
    payload,
}: {
    payload: PendingTermsPayload;
}) {
    return (
        <section
            id="advisor-terms"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <ShieldAlert className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Pending terms re-acceptance
                    </h2>
                </div>
                <Badge variant={payload.total > 0 ? 'destructive' : 'outline'}>
                    {payload.total}
                </Badge>
            </div>

            {payload.latest_version && (
                <p className="text-xs text-muted-foreground">
                    Current version {payload.latest_version.version}
                </p>
            )}

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No client contacts are waiting on terms.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <div key={item.id} className="space-y-1 p-3">
                            <div className="text-sm font-medium">
                                {item.user_name ?? item.user_email}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                <ClientNameLink
                                    name={item.client_name}
                                    href={item.client_url}
                                    className="text-xs text-muted-foreground"
                                />{' '}
                                - {item.user_email}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

function CashFlowRiskPanel({ payload }: { payload: CashFlowStatusPayload }) {
    return (
        <section
            id="advisor-cash-flow-risks"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Banknote className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Cash flow risks</h2>
                </div>
                <div className="flex flex-wrap justify-end gap-2">
                    <Badge
                        variant={
                            payload.summary.negative > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.negative} negative
                    </Badge>
                    <Badge
                        variant={
                            payload.summary.watch > 0 ? 'secondary' : 'outline'
                        }
                    >
                        {payload.summary.watch} watch
                    </Badge>
                </div>
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No negative or watch cash-flow positions detected.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article
                            key={item.client_id}
                            className="grid gap-3 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant={cashFlowBadgeVariant(item)}>
                                        {item.status_label}
                                    </Badge>
                                    <ClientNameLink
                                        name={item.client_name}
                                        href={item.client_url}
                                        className="text-sm"
                                    />
                                </div>
                                <div className="mt-2 grid gap-2 text-xs text-muted-foreground sm:grid-cols-2">
                                    <div>
                                        <span className="font-medium text-foreground">
                                            Reason:{' '}
                                        </span>
                                        {item.reason}
                                    </div>
                                    <div>
                                        <span className="font-medium text-foreground">
                                            Source:{' '}
                                        </span>
                                        {item.source}
                                    </div>
                                    <div>
                                        <span className="font-medium text-foreground">
                                            Operating cash flow:{' '}
                                        </span>
                                        {item.latest_operating_cash_flow ===
                                        null
                                            ? 'Not available'
                                            : formatCurrency(
                                                  item.latest_operating_cash_flow,
                                              )}
                                    </div>
                                    <div>
                                        <span className="font-medium text-foreground">
                                            Runway:{' '}
                                        </span>
                                        {formatRunway(item)}
                                    </div>
                                </div>
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <Link href={item.detail_url}>Open client</Link>
                            </Button>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function StrategicPlanDeploymentPanel({
    payload,
}: {
    payload: StrategicPlanDeploymentsPayload;
}) {
    const generatePlan = (url: string) => {
        router.post(url, {}, { preserveScroll: false });
    };

    return (
        <section
            id="advisor-strategic-plan-deployments"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <ListChecks className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Strategic plan actions
                    </h2>
                </div>
                <div className="flex flex-wrap justify-end gap-2">
                    <Badge
                        variant={
                            payload.summary.ready_to_generate > 0
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {payload.summary.ready_to_generate} generate
                    </Badge>
                    <Badge
                        variant={
                            payload.summary.ready_to_deploy > 0
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {payload.summary.ready_to_deploy} deploy
                    </Badge>
                </div>
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No strategic plans are waiting for generation or deployment.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article
                            key={`${item.type}-${item.id}`}
                            className="flex flex-wrap items-center justify-between gap-3 p-3"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="outline">
                                        {item.type === 'generate'
                                            ? 'Generate'
                                            : 'Draft'}
                                    </Badge>
                                    {item.type === 'deploy' && (
                                        <Badge variant="secondary">
                                            {item.milestones_count} milestones
                                        </Badge>
                                    )}
                                    {item.proposal_version !== null && (
                                        <Badge variant="outline">
                                            Proposal v{item.proposal_version}
                                        </Badge>
                                    )}
                                    {item.budget_status_label && (
                                        <Badge variant="secondary">
                                            {item.budget_status_label}
                                        </Badge>
                                    )}
                                </div>
                                <ClientNameLink
                                    name={item.client_name}
                                    href={item.client_url}
                                    className="mt-2 text-sm"
                                />
                                <div className="text-xs text-muted-foreground">
                                    {item.type === 'generate'
                                        ? `Accepted ${formatDate(item.accepted_at)}`
                                        : `Generated ${formatDate(item.generated_at)}`}
                                </div>
                            </div>
                            {item.type === 'generate' && item.action_url ? (
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        generatePlan(item.action_url!)
                                    }
                                >
                                    {item.action_label}
                                </Button>
                            ) : (
                                <Button asChild size="sm" variant="outline">
                                    <Link href={item.detail_url}>
                                        {item.action_label}
                                    </Link>
                                </Button>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function EntrepreneurReviewPanel({
    payload,
}: {
    payload: EntrepreneurReviewsPayload;
}) {
    return (
        <section
            id="advisor-entrepreneur-reviews"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Lightbulb className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Entrepreneur reviews
                    </h2>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge
                        variant={
                            payload.summary.idea_validations > 0
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {payload.summary.idea_validations} idea
                    </Badge>
                    <Badge
                        variant={
                            payload.summary.business_plans > 0
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {payload.summary.business_plans} plan
                    </Badge>
                </div>
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No entrepreneur idea or business plan reviews are waiting.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article
                            key={`${item.type}:${item.id}`}
                            className="flex flex-wrap items-center justify-between gap-3 p-3"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="outline">
                                        {item.label}
                                    </Badge>
                                    <Badge variant="secondary">
                                        {item.status}
                                    </Badge>
                                </div>
                                <div className="mt-2 text-sm font-medium">
                                    {item.entrepreneur_name}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {item.entrepreneur_email ?? 'No email'} -{' '}
                                    {formatDate(item.submitted_at)}
                                </div>
                            </div>
                            {item.detail_url ? (
                                <Button asChild size="sm" variant="outline">
                                    <Link href={item.detail_url}>
                                        {item.action_label}
                                    </Link>
                                </Button>
                            ) : null}
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function ProspectInbox({ payload }: { payload: ProspectInboxPayload }) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Inbox className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Prospect inbox</h2>
                </div>
                <div className="flex items-center gap-2">
                    <Badge variant="secondary">{payload.total} total</Badge>
                    <Button asChild size="sm" variant="outline">
                        <Link href={payload.index_url}>Open</Link>
                    </Button>
                </div>
            </div>

            {!payload.triage_enabled && (
                <div className="flex items-center gap-2 rounded-md border px-3 py-2 text-xs text-muted-foreground">
                    <Clock className="size-3.5" aria-hidden="true" />
                    Triage pending website intake
                </div>
            )}

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No prospect leads captured.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((lead) => (
                        <div key={lead.id} className="space-y-1 p-3">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <div className="text-sm font-medium">
                                        {lead.name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {lead.company ?? lead.email}
                                    </div>
                                </div>
                                <div className="flex flex-wrap justify-end gap-2">
                                    <Badge variant="outline">
                                        {lead.source}
                                    </Badge>
                                    <Badge variant="secondary">
                                        {lead.status}
                                    </Badge>
                                </div>
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {formatDate(lead.created_at)}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

function EconomicIndicators({
    payload,
}: {
    payload: EconomicIndicatorsPayload;
}) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <TrendingUp className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Economic indicators</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant={
                            payload.summary.change_alerts > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.change_alerts} changes
                    </Badge>
                    <Badge variant="secondary">
                        {payload.summary.indicators} indicators
                    </Badge>
                </div>
            </div>

            {payload.indicators.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No economic indicators refreshed.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.indicators.map((indicator) => (
                        <InsightHoverCard
                            key={indicator.id}
                            title={indicator.label}
                            rows={economicIndicatorRows(indicator)}
                            drillHref={
                                indicator.exposure.drill_url ?? undefined
                            }
                            drillAriaLabel={`Open clients exposed to ${indicator.label}`}
                            footer={exposureFooter(indicator.exposure)}
                        >
                            <button
                                type="button"
                                className="grid w-full gap-2 p-3 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none sm:grid-cols-[1fr_auto]"
                            >
                                <span className="min-w-0">
                                    <span className="block text-sm font-medium">
                                        {indicator.label}
                                    </span>
                                    <span className="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                        <span>
                                            {formatDateOnly(
                                                indicator.period_date,
                                            )}
                                        </span>
                                        <Badge
                                            variant={
                                                indicator.degraded
                                                    ? 'outline'
                                                    : 'secondary'
                                            }
                                        >
                                            {formatLabel(
                                                indicator.source_badge,
                                            )}
                                        </Badge>
                                        <ExposureBadge
                                            exposure={indicator.exposure}
                                        />
                                    </span>
                                </span>
                                <span className="text-sm font-medium sm:text-right">
                                    {formatIndicatorValue(
                                        indicator.value,
                                        indicator.unit,
                                    )}
                                </span>
                            </button>
                        </InsightHoverCard>
                    ))}
                </div>
            )}

            {payload.exchange_rates.length > 0 && (
                <div className="grid gap-2 sm:grid-cols-2">
                    {payload.exchange_rates.map((rate) => (
                        <InsightHoverCard
                            key={rate.id}
                            title={`${rate.base_currency}/${rate.quote_currency}`}
                            rows={exchangeRateRows(rate)}
                            footer={exposureFooter(rate.exposure)}
                        >
                            <button
                                type="button"
                                className="w-full rounded-md border p-3 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            >
                                <span className="text-xs text-muted-foreground">
                                    {rate.base_currency}/{rate.quote_currency}
                                </span>
                                <span className="mt-1 block text-sm font-medium">
                                    {rate.rate.toFixed(4)}
                                </span>
                                <span className="mt-2 block">
                                    <ExposureBadge exposure={rate.exposure} />
                                </span>
                            </button>
                        </InsightHoverCard>
                    ))}
                </div>
            )}

            {payload.alerts.length > 0 && (
                <div className="space-y-2">
                    {payload.alerts.map((alert) => (
                        <div
                            key={alert.id}
                            className="flex gap-2 rounded-md border px-3 py-2 text-xs text-muted-foreground"
                        >
                            <AlertTriangle
                                className="mt-0.5 size-3.5 text-destructive"
                                aria-hidden="true"
                            />
                            <span>{alert.summary}</span>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

function ExposureBadge({ exposure }: { exposure: EconomicExposure }) {
    if (!exposure.supported) {
        return <Badge variant="outline">Exposure unavailable</Badge>;
    }

    return (
        <Badge variant="secondary">{exposure.exposed_count ?? 0} exposed</Badge>
    );
}

function economicIndicatorRows(indicator: EconomicIndicatorItem): Array<{
    label: string;
    value: string;
    tone?: 'default' | 'muted' | 'positive' | 'negative';
}> {
    return [
        {
            label: 'Current',
            value: formatIndicatorValue(indicator.value, indicator.unit),
        },
        {
            label: 'Previous',
            value:
                indicator.previous_value === null
                    ? 'No prior reading'
                    : formatIndicatorValue(
                          indicator.previous_value,
                          indicator.unit,
                      ),
            tone: indicator.previous_value === null ? 'muted' : 'default',
        },
        {
            label: 'Change',
            value: formatTrend(indicator.change_pct, indicator.direction),
            tone: trendTone(indicator.direction),
        },
        {
            label: 'Exposure',
            value: exposureSummary(indicator.exposure),
            tone: indicator.exposure.supported ? 'default' : 'muted',
        },
    ];
}

function exchangeRateRows(rate: ExchangeRateItem): Array<{
    label: string;
    value: string;
    tone?: 'default' | 'muted' | 'positive' | 'negative';
}> {
    return [
        {
            label: 'Current',
            value: rate.rate.toFixed(4),
        },
        {
            label: 'Previous',
            value:
                rate.previous_rate === null
                    ? 'No prior reading'
                    : rate.previous_rate.toFixed(4),
            tone: rate.previous_rate === null ? 'muted' : 'default',
        },
        {
            label: 'Change',
            value: formatTrend(rate.change_pct, rate.direction),
            tone: trendTone(rate.direction),
        },
        {
            label: 'Exposure',
            value: exposureSummary(rate.exposure),
            tone: 'muted',
        },
    ];
}

function exposureSummary(exposure: EconomicExposure): string {
    if (!exposure.supported) {
        return exposure.reason === 'classification_not_captured'
            ? 'Classification not captured'
            : 'Unavailable';
    }

    const exposed = exposure.exposed_count ?? 0;
    const unknown = exposure.unknown_count ?? 0;

    return unknown > 0
        ? `${exposed} exposed / ${unknown} unknown`
        : `${exposed} exposed`;
}

function exposureFooter(exposure: EconomicExposure): string | undefined {
    if (exposure.supported) {
        return exposure.unknown_count && exposure.unknown_count > 0
            ? 'Some clients lack enough financial data'
            : undefined;
    }

    return exposure.reason === 'classification_not_captured'
        ? 'Classification not captured'
        : 'Exposure unavailable';
}

function ReferenceDataTasksPanel({
    payload,
}: {
    payload: ReferenceDataTasksPayload;
}) {
    const needsRefresh =
        payload.summary.missing +
        payload.summary.overdue +
        payload.summary.due_soon;

    return (
        <section
            id="advisor-reference-data-tasks"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <DatabaseZap className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Reference data</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={needsRefresh > 0 ? 'outline' : 'secondary'}>
                        {needsRefresh} due
                    </Badge>
                    {payload.index_url && (
                        <Button asChild size="sm" variant="outline">
                            <Link href={payload.index_url}>Open</Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="grid gap-2 sm:grid-cols-3">
                <PortfolioMetric
                    label="Missing"
                    value={payload.summary.missing.toString()}
                />
                <PortfolioMetric
                    label="Overdue"
                    value={payload.summary.overdue.toString()}
                />
                <PortfolioMetric
                    label="Due soon"
                    value={payload.summary.due_soon.toString()}
                />
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Implemented reference data is current.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article
                            key={item.key}
                            className="grid gap-3 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="line-clamp-1 text-sm font-medium">
                                        {item.label}
                                    </span>
                                    <Badge
                                        variant={
                                            item.status === 'missing' ||
                                            item.status === 'overdue'
                                                ? 'destructive'
                                                : 'outline'
                                        }
                                    >
                                        {formatLabel(item.status)}
                                    </Badge>
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {item.last_as_at
                                        ? `Last ${formatDateOnly(item.last_as_at)}`
                                        : 'No implemented value'}{' '}
                                    - Due {formatDateOnly(item.due_at)}
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {formatLabel(item.dataset)}
                                    {item.indicator
                                        ? ` - ${formatLabel(item.indicator)}`
                                        : ''}
                                    {item.source ? ` - ${item.source}` : ''}
                                </div>
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <Link href={item.action_url}>Record</Link>
                            </Button>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function formatTrend(
    changePct: number | null,
    direction: TrendDirection,
): string {
    if (direction === 'none' || changePct === null) {
        return 'No prior reading';
    }

    return `${formatSignedPercent(changePct)} ${directionLabel(direction)}`;
}

function directionLabel(direction: TrendDirection): string {
    if (direction === 'up') {
        return 'up';
    }

    if (direction === 'down') {
        return 'down';
    }

    if (direction === 'flat') {
        return 'flat';
    }

    return 'no prior';
}

function trendTone(
    direction: TrendDirection,
): 'default' | 'muted' | 'positive' | 'negative' {
    if (direction === 'none' || direction === 'flat') {
        return 'muted';
    }

    return direction === 'up' ? 'positive' : 'negative';
}

function IntegrationHealth({ payload }: { payload: IntegrationHealthPayload }) {
    const dashboardUrl = payload.index_url;

    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <PlugZap className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Integration health</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {payload.summary.green} green
                    </Badge>
                    <Badge variant="outline">
                        {payload.summary.amber} amber
                    </Badge>
                    <Badge variant="destructive">
                        {payload.summary.red} red
                    </Badge>
                    {dashboardUrl && (
                        <Button asChild size="sm" variant="outline">
                            <Link href={dashboardUrl}>Open</Link>
                        </Button>
                    )}
                </div>
            </div>

            {payload.services.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No integration samples yet.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.services.map((service) => (
                        <div
                            key={service.id}
                            className="grid gap-2 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <HealthIcon health={service.health} />
                                    <span className="text-sm font-medium">
                                        {service.service}
                                    </span>
                                    <Badge
                                        variant={healthVariant(service.health)}
                                    >
                                        {service.health}
                                    </Badge>
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {formatDate(service.window_end)}
                                </div>
                            </div>
                            <div className="text-sm text-muted-foreground sm:text-right">
                                <div>
                                    {formatPercent(service.success_rate)}{' '}
                                    success
                                </div>
                                <div>
                                    p95{' '}
                                    {service.p95_latency_ms === null
                                        ? 'n/a'
                                        : `${service.p95_latency_ms}ms`}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

function PanelOperations({ payload }: { payload: PanelOperationsPayload }) {
    return (
        <div
            id="advisor-panel-operations"
            className="grid gap-4 xl:grid-cols-2 2xl:grid-cols-4"
        >
            <PanelApprovalQueuePanel payload={payload.approvals} />
            <PanelReferralQueuePanel
                id="advisor-broker-referrals"
                title="Broker referrals"
                description="Broker panel hand-offs and cover-placement progress."
                icon={<Inbox className="size-4" aria-hidden="true" />}
                payload={payload.broker}
                empty="No broker referrals in the current scope."
            />
            <PanelReferralQueuePanel
                id="advisor-coach-referrals"
                title="Coach referrals"
                description="Founder and client coaching hand-offs."
                icon={<HeartHandshake className="size-4" aria-hidden="true" />}
                payload={payload.coach}
                empty="No coach referrals in the current scope."
            />
            <LearningQueuePanel payload={payload.learning} />
        </div>
    );
}

function PanelApprovalQueuePanel({ payload }: { payload: PanelApprovalQueue }) {
    return (
        <section
            id="advisor-panel-approvals"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <UsersRound className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">
                            Partner approvals
                        </h2>
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Broker and coach applications waiting for review.
                    </p>
                </div>
                <Badge
                    variant={payload.summary.total > 0 ? 'default' : 'outline'}
                >
                    {payload.summary.total} pending
                </Badge>
            </div>

            <div className="grid gap-2 sm:grid-cols-3">
                <PortfolioMetric
                    label="Total"
                    value={payload.summary.total.toString()}
                />
                <PortfolioMetric
                    label="Brokers"
                    value={payload.summary.broker.toString()}
                />
                <PortfolioMetric
                    label="Coaches"
                    value={payload.summary.coach.toString()}
                />
            </div>

            {payload.review_url && (
                <Button asChild size="sm" variant="outline">
                    <Link href={payload.review_url}>Open approval queue</Link>
                </Button>
            )}

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No partner applications are waiting for approval.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article
                            key={item.id}
                            className="grid gap-3 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="truncate text-sm font-medium">
                                        {item.business_name}
                                    </span>
                                    <Badge variant="outline">
                                        {item.panel_label}
                                    </Badge>
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {item.contact_name} -{' '}
                                    {formatDate(item.applied_at)}
                                </div>
                                {item.email && (
                                    <div className="mt-1 truncate text-xs text-muted-foreground">
                                        {item.email}
                                    </div>
                                )}
                            </div>
                            {item.review_url && (
                                <Button asChild size="sm" variant="outline">
                                    <Link href={item.review_url}>Review</Link>
                                </Button>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function PanelReferralQueuePanel({
    id,
    title,
    description,
    icon,
    payload,
    empty,
}: {
    id: string;
    title: string;
    description: string;
    icon: React.ReactNode;
    payload: PanelReferralQueue;
    empty: string;
}) {
    const activeStages = Object.entries(payload.stage_counts).filter(
        ([, count]) => count > 0,
    );

    return (
        <section
            id={id}
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        {icon}
                        <h2 className="text-sm font-medium">{title}</h2>
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {description}
                    </p>
                </div>
                <Badge
                    variant={payload.summary.active > 0 ? 'default' : 'outline'}
                >
                    {payload.summary.active} active
                </Badge>
            </div>

            <div className="grid gap-2 sm:grid-cols-3">
                <PortfolioMetric
                    label="Total"
                    value={payload.summary.total.toString()}
                />
                <PortfolioMetric
                    label="Active"
                    value={payload.summary.active.toString()}
                />
                <PortfolioMetric
                    label="Closed"
                    value={payload.summary.terminal.toString()}
                />
            </div>

            {activeStages.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {activeStages.map(([stage, count]) => (
                        <Badge key={stage} variant="secondary">
                            {formatLabel(stage)} {count}
                        </Badge>
                    ))}
                </div>
            )}

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">{empty}</p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article
                            key={item.id}
                            className="grid gap-3 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="truncate text-sm font-medium">
                                        {item.subject_name}
                                    </span>
                                    <Badge variant="outline">
                                        {item.stage_label}
                                    </Badge>
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {item.panel_name} ·{' '}
                                    {formatDate(item.sent_at)}
                                </div>
                                {item.reason && (
                                    <p className="mt-2 line-clamp-2 text-sm">
                                        {item.reason}
                                    </p>
                                )}
                            </div>
                            {item.detail_url && (
                                <Button asChild size="sm" variant="outline">
                                    <Link href={item.detail_url}>Open</Link>
                                </Button>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function LearningQueuePanel({ payload }: { payload: LearningQueuePayload }) {
    return (
        <section
            id="advisor-learning-queue"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <Sparkles className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Learning queue</h2>
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Governed model, questionnaire, and methodology updates.
                    </p>
                </div>
                {payload.queue_url && (
                    <Button asChild size="sm" variant="outline">
                        <Link href={payload.queue_url}>Open queue</Link>
                    </Button>
                )}
            </div>

            <div className="grid gap-2 sm:grid-cols-4">
                <PortfolioMetric
                    label="Detected"
                    value={payload.summary.detected.toString()}
                />
                <PortfolioMetric
                    label="Staged"
                    value={payload.summary.staged.toString()}
                />
                <PortfolioMetric
                    label="Approved"
                    value={payload.summary.approved.toString()}
                />
                <PortfolioMetric
                    label="Live"
                    value={payload.summary.implemented.toString()}
                />
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No learning updates are waiting for review.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article
                            key={item.id}
                            className="grid gap-3 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="line-clamp-1 text-sm font-medium">
                                        {item.summary}
                                    </span>
                                    <Badge variant="outline">
                                        {formatLabel(item.status)}
                                    </Badge>
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {item.source_type
                                        ? formatLabel(item.source_type)
                                        : 'Learning update'}{' '}
                                    · {formatPercent(item.confidence)}{' '}
                                    confidence · {item.clients_affected} clients
                                </div>
                            </div>
                            {item.detail_url && (
                                <Button asChild size="sm" variant="outline">
                                    <Link href={item.detail_url}>Review</Link>
                                </Button>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function HealthIcon({ health }: { health: HealthLevel }) {
    if (health === 'green') {
        return (
            <CheckCircle2
                className="size-4 text-emerald-600"
                aria-hidden="true"
            />
        );
    }

    return (
        <AlertTriangle
            className={
                health === 'amber'
                    ? 'size-4 text-amber-600'
                    : 'size-4 text-destructive'
            }
            aria-hidden="true"
        />
    );
}

function engagementLabel(level: HealthLevel): string {
    return level.charAt(0).toUpperCase() + level.slice(1);
}

function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function paymentStatusVariant(
    status: string,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (status === 'failed') {
        return 'destructive';
    }

    if (status === 'retrying') {
        return 'secondary';
    }

    return 'outline';
}

function healthVariant(
    health: HealthLevel,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (health === 'green') {
        return 'secondary';
    }

    if (health === 'amber') {
        return 'outline';
    }

    return 'destructive';
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'No activity';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatDateOnly(value: string | null): string {
    if (!value) {
        return 'No period';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
}

function formatPercent(value: number): string {
    return `${Math.round(value * 1000) / 10}%`;
}

function formatSignedPercent(value: number): string {
    const prefix = value > 0 ? '+' : '';

    return `${prefix}${value.toFixed(2)}%`;
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'NZD',
        maximumFractionDigits: 0,
    }).format(value);
}

function formatRunway(cashFlow: CashFlowStatus): string {
    if (cashFlow.runway_open_ended) {
        return 'Open-ended';
    }

    if (cashFlow.runway_months !== null) {
        return `${Math.round(cashFlow.runway_months)} months`;
    }

    if (cashFlow.cash_flow_positive_year !== null) {
        return `Cash positive year ${cashFlow.cash_flow_positive_year}`;
    }

    return 'Not available';
}

function formatMoney(value: number, currency: string): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatIndicatorValue(value: number, unit: string): string {
    if (unit === 'percent') {
        return `${value.toFixed(1)}%`;
    }

    if (unit === 'nzd_per_hour') {
        return `$${value.toFixed(2)}/hr`;
    }

    return value.toLocaleString();
}

AdvisorDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
