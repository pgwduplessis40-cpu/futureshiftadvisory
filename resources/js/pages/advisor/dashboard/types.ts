import type React from 'react';
import type { WaterfallStep } from '@/components/pv/WaterfallChart';

export type HealthLevel = 'green' | 'amber' | 'red';
export type DashboardTab = 'priorities' | 'signals';
export type ActionPriority = 'critical' | 'warning' | 'neutral';
export type EngagementScoreKey =
    | 'questionnaire_pct'
    | 'documents_pct'
    | 'milestones_on_track_pct'
    | 'comms_recency_pct'
    | 'idea_validation_pct'
    | 'plan_progress_pct'
    | 'activity_recency_pct';

export type EngagementScore = {
    scoring_mode:
        | 'standard_advisory'
        | 'entrepreneur_validation'
        | 'entrepreneur_plan';
    level: HealthLevel;
    score: number;
    scores: {
        questionnaire_pct: number;
        documents_pct: number;
        milestones_on_track_pct: number;
        comms_recency_pct: number;
        idea_validation_pct?: number;
        plan_progress_pct?: number;
        activity_recency_pct?: number;
    };
    display: {
        overdue_count: number;
        blocked_count: number;
        last_comms_days: number | null;
        last_activity_days?: number | null;
        last_plan_activity_at?: string | null;
        last_idea_validation_at?: string | null;
        idea_validation_status?: string | null;
    };
    weakest_component: EngagementScoreKey;
    focus_section: string;
    drill_url: string;
};

export type CashFlowStatus = {
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

export type ClientHealth = {
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

export type ClientsHealthPayload = {
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

export type CashFlowStatusPayload = {
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

export type PendingTermsPayload = {
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

export type MessagesPendingPayload = {
    total: number;
    index_url: string;
};

export type ClientTransferQueuePayload = {
    available: boolean;
    total: number;
    action_url: string | null;
    action_label: string;
    can_review: boolean;
};

export type ServiceActivationRequestsPayload = {
    summary: {
        total: number;
        requested: number;
        dd_plan_budget: number;
    };
    items: Array<{
        id: string;
        client_id: string;
        client_name: string;
        client_url: string;
        service_type: string;
        client_label: string;
        status: string;
        status_label: string;
        requested_by_name: string | null;
        requested_by_email: string | null;
        advisor_name: string | null;
        package_label: string | null;
        requested_at: string | null;
        url: string;
        action_label: string;
        priority_label: string;
    }>;
    index_url: string;
};

export type EntrepreneurReviewsPayload = {
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

export type StrategicPlanDeploymentsPayload = {
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
        proposal_brief: string | null;
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

export type ProspectInboxPayload = {
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

export type IntegrationHealthPayload = {
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

export type OperationalHealthPayload = {
    summary: {
        status: 'passed' | 'warning' | 'failed' | null;
        total: number;
        passed: number;
        warning: number;
        failed: number;
    };
    index_url: string | null;
    latest_run: {
        id: string;
        started_at: string | null;
        finished_at: string | null;
        environment: string;
        release_version: string | null;
    } | null;
    latest_issue: {
        id: string;
        status: 'failed' | 'warning' | 'skipped';
        check_key: string;
        name: string;
        area: string;
        actual_status: number | null;
        issue_summary: string | null;
        consecutive_failures: number | null;
        failures_last_7_days: number | null;
    } | null;
};

export type AiOperationalAlertPayload = {
    available: boolean;
    total: number;
    reason: string | null;
    action_url: string | null;
};

export type EconomicIndicatorsPayload = {
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

export type TrendDirection = 'up' | 'down' | 'flat' | 'none';

export type EconomicExposure = {
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

export type EconomicIndicatorItem =
    EconomicIndicatorsPayload['indicators'][number];
export type ExchangeRateItem =
    EconomicIndicatorsPayload['exchange_rates'][number];

export type RedFlagsPayload = {
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

export type PvWaterfallPayload = {
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

export type PracticeHealthPayload = {
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

export type ProposalStatusPayload = {
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
        brief: string;
        expires_at: string | null;
        client_url: string;
    }>;
};

export type PaymentStatusPayload = {
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

export type ScenarioPlanningPayload = {
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

export type FunnelAnalyticsPayload = {
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

export type QuestionnaireOptimisationPayload = {
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

export type WellbeingAnalyticsPayload = {
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

export type CoachSignalsPayload = {
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

export type NpoPendingConversionsPayload = {
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

export type NpoFundingPayload = {
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

export type ReferenceDataTasksPayload = {
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

export type PanelReferralQueue = {
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

export type PanelApprovalQueue = {
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

export type LearningQueuePayload = {
    summary: {
        detected: number;
        staged: number;
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

export type PanelOperationsPayload = {
    broker: PanelReferralQueue;
    coach: PanelReferralQueue;
    learning: LearningQueuePayload;
    approvals: PanelApprovalQueue;
};

export type FeeStatusPayload = {
    free_access_mode: boolean;
    charging_enabled: boolean;
    current_rate_id: string | null;
    current_rate_effective_from: string | null;
    currency: string;
    can_manage: boolean;
    manage_url: string | null;
};

export type ActionSummaryItem = {
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
