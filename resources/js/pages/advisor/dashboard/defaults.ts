import type {
    CoachSignalsPayload,
    EconomicIndicatorsPayload,
    FunnelAnalyticsPayload,
    IntegrationHealthPayload,
    PracticeHealthPayload,
    PvWaterfallPayload,
    QuestionnaireOptimisationPayload,
    ScenarioPlanningPayload,
    WellbeingAnalyticsPayload,
} from './types';

export const advisorSignalDeferredProps = [
    'integrationHealth',
    'economicIndicators',
    'questionnaireOptimisation',
    'wellbeingAnalytics',
    'coachSignals',
    'funnelAnalytics',
];

export const advisorPortfolioDeferredProps = [
    'pvWaterfall',
    'practiceHealth',
    'scenarioPlanning',
];

export const emptyIntegrationHealth: IntegrationHealthPayload = {
    summary: {
        total: 0,
        green: 0,
        amber: 0,
        red: 0,
    },
    index_url: null,
    services: [],
};

export const emptyEconomicIndicators: EconomicIndicatorsPayload = {
    summary: {
        indicators: 0,
        exchange_rates: 0,
        change_alerts: 0,
        latest_fetched_at: null,
    },
    indicators: [],
    exchange_rates: [],
    alerts: [],
};

export const emptyQuestionnaireOptimisation: QuestionnaireOptimisationPayload =
    {
        summary: {
            detected_candidates: 0,
            latest_run_at: null,
            latest_candidates_created: 0,
        },
        items: [],
    };

export const emptyWellbeingAnalytics: WellbeingAnalyticsPayload = {
    summary: {
        checkins: 0,
        clients: 0,
        average_business_confidence: 0,
        average_personal_coping: 0,
        low_personal_coping_checkins: 0,
        active_low_coping_signals: 0,
        current_period_completion_rate: 0,
    },
    monthly: [],
    signals: [],
};

export const emptyCoachSignals: CoachSignalsPayload = {
    summary: {
        total: 0,
        auto_referrals: 0,
    },
    items: [],
};

export const emptyFunnelAnalytics: FunnelAnalyticsPayload = {
    summary: {
        events: 0,
        abandoned: 0,
        completed: 0,
        worst_drop_off_rate: 0,
    },
    steps: [],
};

export const emptyPvWaterfall: PvWaterfallPayload = {
    summary: {
        clients: 0,
        current_pv: 0,
        improvement_pv: 0,
        risk_mitigation_pv: 0,
        target_pv: 0,
        target_pv_label: 'Target PV',
        target_pv_range: {
            low: 0,
            mid: 0,
            high: 0,
            range_percent: 0,
        },
    },
    clients: [],
};

export const emptyPracticeHealth: PracticeHealthPayload = {
    summary: {
        active_clients: 0,
        clients_with_pv: 0,
        current_pv: 0,
        improvement_pv: 0,
        risk_mitigation_pv: 0,
        target_pv: 0,
        revenue_under_management: 0,
    },
    phase_two: {
        released_proposals: 0,
        open_red_flags: 0,
        generated_reports: 0,
        funnel_events: 0,
        funnel_worst_drop_off_rate: 0,
        proposal_statuses: {},
    },
    clients: [],
    generated_at: '',
};

export const emptyScenarioPlanning: ScenarioPlanningPayload = {
    summary: {
        scenarios: 0,
        clients: 0,
    },
    items: [],
};

export const signalPanelTargetIds = new Set([
    'advisor-panel-operations',
    'advisor-panel-approvals',
    'advisor-broker-referrals',
    'advisor-coach-referrals',
    'advisor-learning-queue',
    'advisor-reference-data-tasks',
    'advisor-npo-funding',
    'advisor-npo-conversions',
]);
