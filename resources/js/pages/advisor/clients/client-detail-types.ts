import type { AdvisorCoBrowseConfig } from '@/components/co-browse/AdvisorCoBrowseAction';
import type { DataQualitySummary } from '@/components/data-quality/DataQualityBadge';
import type { NpoHealthPayload } from '@/components/npo/NpoHealthPanel';
import type {
    AdvisorServiceTabKey,
    DueDiligenceSummary,
    ProposalBudgetGuard,
    StrategicBudgetSummary,
    StrategicPlanDeploymentGuard,
} from './service-workspaces';
import type { ClientSummary } from './types';
export type ClientDetail = ClientSummary & {
    data_quality_summary: DataQualitySummary;
    wellbeing_trend: WellbeingPoint[] | null;
    offboarding: OffboardingSummary | null;
    status_options: StatusOption[];
    lifecycle_update_url: string;
    knowledge_assessment_store_url: string;
    knowledge_draft_store_url: string;
    latest_knowledge_assessment: KnowledgeAssessmentSummary | null;
    goal_store_url: string;
    goals: GoalDashboard;
    proposal_store_url: string;
    proposal_expiry_days: number;
    fee_calculations: FeeCalculationSummary[];
    proposals: ProposalSummary[];
    business_health_recompute_url: string;
    report_store_url: string;
    reports: ReportSummary[];
    meeting_store_url: string;
    meetings: MeetingSummary[];
    industry_briefings: IndustryBriefingSummary[];
    pre_meeting_briefs: PreMeetingBriefSummary[];
    address: Record<string, string | null> | null;
    directors: Array<Record<string, string | null>>;
    registry_sources: Record<string, string>;
    engagement_type_locked: boolean;
    accounting: AccountingPayload;
    payments: PaymentSummary[];
    created_at: string | null;
    analysis_findings: AnalysisFindingFeedback[];
    standard_advisory: StandardAdvisorySummary | null;
    founding_advisory: FoundingAdvisorySummary | null;
    due_diligence: DueDiligenceSummary | null;
    npo_conversion: NpoConversionSummary | null;
    npo_governance_review: NpoGovernanceReviewSummary | null;
    npo_configuration: NpoConfigurationSummary | null;
    npo_health: NpoHealthPayload | null;
    npo_funding: NpoFundingSummary | null;
    npo_values: NpoValueSummary | null;
    npo_social_enterprise: NpoSocialEnterpriseSummary | null;
    strategic_budget: StrategicBudgetSummary;
    strategic_plan: StrategicPlanSummary | null;
    strategic_plan_deployment_guard: StrategicPlanDeploymentGuard;
    proposal_budget_guard: ProposalBudgetGuard;
    invitation: {
        email: string;
        status: string;
        status_label: string;
        accepted_at: string | null;
        expires_at: string | null;
        resend_url: string | null;
        cancel_url: string | null;
    } | null;
};

export type ConflictDeclaration = {
    id: string;
    declaration: {
        referral_type?: string;
        existing_relationship?: boolean;
        details?: string | null;
    };
    declared_at: string;
} | null;

export type Props = {
    client: ClientDetail;
    conflictDeclaration: ConflictDeclaration;
    screenShare: {
        connection_url: string;
        connection_heartbeat_url: string;
        request_url: string;
        ice_servers_url: string;
        active_url: string;
        signal_url: string;
        pending_signals_url: string;
        heartbeat_url: string;
        end_url: string;
        heartbeat_seconds: number;
        participants: Array<{ id: string; name: string }>;
    };
    coBrowse: AdvisorCoBrowseConfig | null;
};

export type ClientDetailTab = 'actions' | 'information';

export type AnalysisFindingFilter = 'needs_review' | 'all' | 'reviewed';

export const clientSectionTabs: Record<string, ClientDetailTab> = {
    'section-advisory-service-access': 'actions',
    'section-analysis': 'actions',
    'section-due-diligence': 'actions',
    'section-founding-advisory': 'actions',
    'section-goals': 'actions',
    'section-lifecycle': 'actions',
    'section-npo-configuration': 'actions',
    'section-npo-conversion': 'actions',
    'section-npo-governance-review': 'actions',
    'section-overview': 'actions',
    'section-payments': 'actions',
    'section-proposals': 'actions',
    'section-standard-advisory': 'actions',
    'section-strategic-budget': 'actions',
    'section-strategic-budget-assessment': 'actions',
    'section-strategic-plan': 'actions',
    'section-accounting': 'information',
    'section-engagement': 'information',
    'section-knowledge': 'information',
    'section-meetings': 'information',
    'section-npo-funding': 'information',
    'section-npo-health': 'information',
    'section-npo-social-enterprise': 'information',
    'section-npo-value': 'information',
    'section-registry': 'information',
    'section-reports': 'information',
    'section-wellbeing': 'information',
};

export const clientSectionServiceTabs: Partial<
    Record<string, AdvisorServiceTabKey>
> = {
    'section-advisory-service-access': 'advisory_access',
    'section-due-diligence': 'due_diligence',
    'section-founding-advisory': 'founding_advisory',
    'section-npo-configuration': 'npo',
    'section-npo-conversion': 'npo',
    'section-npo-funding': 'npo',
    'section-npo-governance-review': 'npo',
    'section-npo-health': 'npo',
    'section-npo-social-enterprise': 'npo',
    'section-npo-value': 'npo',
    'section-standard-advisory': 'standard_advisory',
    'section-strategic-budget': 'business_plan_budget',
    'section-strategic-budget-assessment': 'business_plan_budget',
    'section-strategic-plan': 'strategic_plan',
};

export type WellbeingPoint = {
    id: string;
    period_start: string | null;
    business_confidence: number;
    personal_coping: number;
    notes: string | null;
    submitted_at: string | null;
    submitted_by: string | null;
};

export type OffboardingSummary = {
    id: string;
    triggered_at: string | null;
    reengagement_due: string | null;
    advisor_capacity_released: boolean;
};

export type StatusOption = {
    value: string;
    label: string;
};

export type KnowledgeAssessmentCalibration = {
    source: string;
    language_depth: string;
    financial_detail: string;
    strategic_framing: string;
    leadership_context: string;
    advisor_review_note: string;
    scores: {
        financial_literacy: number;
        strategic_awareness: number;
        leadership: number;
    };
};

export type KnowledgeAssessmentSummary = {
    id: string;
    financial_literacy: number;
    strategic_awareness: number;
    leadership: number;
    calibration: KnowledgeAssessmentCalibration;
    assessed_at: string | null;
};

export type GoalDashboard = {
    pv_realised_total: number;
    active_goals: number;
    goals: GoalSummary[];
};

export type GoalSummary = {
    id: string;
    title: string;
    description: string | null;
    pv_target: number;
    target_date: string | null;
    target_growth_percent: number | null;
    status: string;
    achieved_at: string | null;
    measurement: GoalMeasurement;
    milestone_store_url?: string;
    remeasure_url?: string;
    achieve_url?: string;
    milestones: MilestoneSummary[];
};

export type GoalMeasurement = {
    baseline_pv: number | null;
    baseline_as_at: string | null;
    baseline_business_valuation_id: string | null;
    baseline_pv_calculation_id: string | null;
    current_pv: number | null;
    current_as_at: string | null;
    current_business_valuation_id: string | null;
    current_pv_calculation_id: string | null;
    pv_movement: number | null;
    target_gap: number | null;
    progress_percent: number | null;
    realised_pv: number;
    realised_explains_percent: number | null;
    due_for_remeasurement: boolean;
};

export type MilestoneSummary = {
    id: string;
    title: string;
    recommendation_ref: string | null;
    pv_of_impact: number;
    status: string;
    due_date: string | null;
    completed_at: string | null;
    actions_count: number;
    proof_status: string | null;
    action_store_url?: string;
    proof_store_url?: string;
};

export type AccountingPayload = {
    providers: AccountingProvider[];
    connections: AccountingConnectionSummary[];
};

export type AccountingProvider = {
    provider: string;
    label: string;
    connected: boolean;
    connect_url: string;
};

export type AccountingConnectionSummary = {
    id: string;
    provider: string;
    provider_label: string;
    external_tenant_id: string | null;
    status: string;
    connected: boolean;
    connected_at: string | null;
    revoked_at: string | null;
    last_snapshot_at: string | null;
    pull_url: string;
    revoke_url: string;
    latest_snapshot: FinancialSnapshotSummary | null;
};

export type PaymentSummary = {
    id: string;
    client_id: string;
    client_name: string | null;
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
};

export type NpoConversionSummary = {
    id: string;
    client_id: string;
    client_name: string | null;
    status: string | null;
    status_label: string | null;
    decline_reason: string | null;
    report_delivered_at: string | null;
    reengagement_due_at: string | null;
    next_nudge_day: number | null;
    report_delivered_url: string;
    decline_url: string;
    convert_url: string;
};

export type NpoGovernanceFinding = {
    id: string;
    finding_key: string;
    category: string;
    severity: string;
    title: string;
    body: string;
    status: string;
    advisor_notes: string | null;
    review_url: string;
    reviewed_at: string | null;
};

export type NpoGovernanceReviewSummary = {
    id: string;
    run_url: string;
    findings_count: number;
    pending_review_count: number;
    reviewed_count: number;
    high_priority_count: number;
    can_generate_report: boolean;
    findings: NpoGovernanceFinding[];
};

export type NpoOption = {
    value: string;
    label: string;
};

export type NpoWeightingSuggestion = NpoOption & {
    commercial_weight: number;
    mission_weight: number;
};

export type NpoDecisionQuestion = {
    key: string;
    label: string;
};

export type NpoConfigurationSummary = {
    id: string;
    client_id: string;
    sub_type: string;
    sub_type_label: string;
    legal_structure: string;
    legal_structure_label: string;
    legal_structure_options: NpoOption[];
    tiriti_mode: string | null;
    tiriti_mode_label: string | null;
    tiriti_mode_options: NpoOption[];
    tiriti_decision_questions: NpoDecisionQuestion[];
    tiriti_decision_guide: Record<string, boolean>;
    tiriti_suggested_mode: string;
    social_enterprise: boolean;
    social_enterprise_type: string | null;
    social_enterprise_type_label: string | null;
    social_enterprise_type_options: NpoWeightingSuggestion[];
    commercial_weight: number | null;
    mission_weight: number | null;
    update_url: string;
};

export type NpoFundingRecord = {
    id: string;
    funder_id: string;
    funder_name: string | null;
    funder_needs_verification: boolean;
    grant_name: string | null;
    grant_amount: number;
    currency: string;
    period_start: string | null;
    period_end: string | null;
    reporting_deadline: string | null;
    next_application_window_opens_at: string | null;
    next_application_window_closes_at: string | null;
    grant_expiry_at: string | null;
    renewal_probability: number | null;
};

export type NpoFundingAlert = {
    id: string;
    client_id: string;
    record_id: string;
    funder_name: string | null;
    type: string;
    severity: string;
    message: string;
    due_on: string | null;
    triggered_at: string | null;
};

export type NpoFundingSummary = {
    records: NpoFundingRecord[];
    alerts: NpoFundingAlert[];
    concentration: {
        total_active_amount: number;
        largest_funder_amount: number;
        largest_funder_ratio: number;
        largest_funder_name: string | null;
        risk_level: string;
        source: string;
    };
};

export type NpoValueProjection = {
    key: string;
    label: string;
    unit: string;
    low: number;
    mid: number;
    high: number;
    uncertainty: {
        rate: number;
        label: string;
    };
};

export type NpoValueCalculation = {
    id: string;
    type: string;
    label: string;
    dimension_number: number;
    rating: string;
    projection_mid: number;
    projection_low: number;
    projection_high: number;
    mission_framing: string;
    stable_assumption_disclosure: string;
    impact_governance?: {
        verification_status: string;
        verification_label: string;
        theory_of_change_status: string;
        stakeholder_involvement_status: string;
    };
    projections: NpoValueProjection[];
    calculated_at: string | null;
};

export type NpoValueSummary = {
    npo_engagement_id: string;
    calculations: NpoValueCalculation[];
};

export type NpoSocialEnterpriseAxis = {
    dimension: string;
    label: string;
    score: number | null;
    state?: string;
};

export type NpoTensionDataPoint = {
    key: string;
    label: string;
    value: number | string;
    source_reference: string;
};

export type NpoSocialEnterpriseTension = {
    type: string;
    title: string;
    commercial_implication: string;
    mission_implication: string;
    strategic_options: string[];
    advisor_recommended_path: string;
    data_points: NpoTensionDataPoint[];
};

export type NpoSocialEnterpriseSummary = {
    scorecard: {
        id: string;
        commercial_score: number;
        mission_score: number;
        commercial_weight: number;
        mission_weight: number;
        blended_score: number;
        commercial_axes: NpoSocialEnterpriseAxis[];
        mission_axes: NpoSocialEnterpriseAxis[];
        calculated_at: string | null;
    };
    tension_analysis: {
        id: string;
        review_status: string;
        reviewed_at: string | null;
        is_releasable: boolean;
        tensions: NpoSocialEnterpriseTension[];
        generated_at: string | null;
    } | null;
};

export type FinancialSnapshotMetrics = Record<string, number>;

export type FinancialSnapshotSummary = {
    id: string;
    period_start: string | null;
    period_end: string | null;
    source: string;
    source_badge: string;
    degraded: boolean;
    metrics: FinancialSnapshotMetrics;
    pulled_at: string | null;
};

export type FeeCalculationSummary = {
    id: string;
    method: string;
    suggested_mid: number;
    roi_ratio: number;
    created_at: string | null;
    proposal_scope_summary: string | null;
    strategic_plan_duration_months: number;
    strategic_plan_duration_label: string;
    strategic_plan_complexity_band: string;
    strategic_plan_complexity_label: string;
};

export type ProposalSummary = {
    id: string;
    status: string;
    status_label: string;
    version: number;
    fee_method_label: string;
    brief: string;
    suggested_mid: number | null;
    roi_ratio: number;
    strategic_plan_duration_months: number;
    strategic_plan_duration_label: string;
    strategic_plan_complexity_band: string;
    strategic_plan_complexity_label: string;
    released_at: string | null;
    expires_at: string | null;
    days_to_expiry: number | null;
    pdf_byte_size: number | null;
    can_release: boolean;
    can_recall: boolean;
    can_renew: boolean;
    release_url: string;
    recall_url: string;
    renew_url: string;
    view_url: string;
    download_url: string | null;
    strategic_plan_generate_url: string | null;
};

export type StrategicPlanSection = {
    key: string;
    title: string;
    body: string;
};

export type StrategicPlanMilestone = {
    id: string;
    title: string;
    description: string | null;
    owner: 'client' | 'advisor' | 'joint';
    owner_label: string;
    due_offset_days: number;
    due_date: string | null;
    status: 'pending' | 'in_progress' | 'completed' | 'blocked';
    status_label: string;
    progress_percent: number;
    evidence_notes: string | null;
    advisor_notes: string | null;
};

export type StrategicPlanSummary = {
    id: string;
    title: string;
    status: string;
    status_label: string;
    duration_months: number;
    duration_label: string;
    complexity_band: string;
    complexity_label: string;
    duration_rationale: string[];
    summary: string | null;
    sections: StrategicPlanSection[];
    generated_at: string | null;
    deployed_at: string | null;
    progress_percent: number;
    completed_milestones: number;
    total_milestones: number;
    milestones: StrategicPlanMilestone[];
    pdf_url: string;
    update_url: string;
    deploy_url: string;
};

export type StrategicPlanForm = {
    summary: string;
    sections: StrategicPlanSection[];
    milestones: Array<
        StrategicPlanMilestone & {
            description: string;
            advisor_notes: string;
        }
    >;
};

export type FoundingRoadmapMilestone = {
    title: string;
    owner: 'client' | 'advisor' | 'joint';
    due_day: number;
};

export type FoundingRoadmapHorizon = {
    key: string;
    label: string;
    commitment: 'committed' | 'provisional' | 'indicative';
    starts_on: string;
    ends_on: string;
    outcomes: string[];
    milestones: FoundingRoadmapMilestone[];
};

export type FoundingRoadmapVersion = {
    id: string;
    version: number;
    status: string;
    status_label: string;
    planning_start_date: string | null;
    planning_through_date: string | null;
    agenda: {
        generated_from?: string;
        replan_focus?: string[];
        horizons?: FoundingRoadmapHorizon[];
    };
    replan_input: Record<string, string>;
    change_summary: {
        reason?: string;
        changes?: string[];
    };
    generated_at: string | null;
    published_at: string | null;
    publish_url: string | null;
};

export type FoundingAdvisorySummary = {
    id: string;
    status: string;
    status_label: string;
    baseline: {
        version: number;
        captured_at: string | null;
        concept_summary: string | null;
        assessment_score: number | null;
        assessment_grade: string | null;
        plan_title: string | null;
        budget: {
            expected_runway_months?: number | null;
        } | null;
    };
    accepted_at: string | null;
    started_at: string | null;
    replan_due_at: string | null;
    replan_due: boolean;
    transition_review_at: string | null;
    current_version: FoundingRoadmapVersion | null;
    draft_version: FoundingRoadmapVersion | null;
    versions: FoundingRoadmapVersion[];
    can_replan: boolean;
    replan_url: string | null;
};

export type ReportSummary = {
    id: string;
    type: string;
    type_label: string;
    title: string;
    generated_at: string | null;
    pdf_byte_size: number | null;
    pptx_byte_size: number | null;
    view_url: string | null;
    download_url: string | null;
    pptx_url: string | null;
    review_status: string;
    reviewed_at: string | null;
    review_url: string;
    release_url: string | null;
    can_review: boolean;
    section_count: number;
    revision_count: number;
    comment_count: number;
};

export type MeetingSummary = {
    id: string;
    title: string;
    scheduled_at: string | null;
    location: string | null;
    link: string | null;
    attendees: string[];
    calendar_synced: boolean;
    brief_status: string;
};

export type IndustryBriefingSummary = {
    id: string;
    period: string | null;
    body: string;
    status: string;
    reviewed_at: string | null;
    sent_at: string | null;
    review_url: string;
    can_review: boolean;
};

export type PreMeetingBriefSummary = {
    id: string;
    meeting_title: string | null;
    meeting_at: string | null;
    body: string;
    red_flag_count: number;
    generated_at: string | null;
    reviewed_at: string | null;
    sent_at: string | null;
    review_url: string;
    can_review: boolean;
};

export type AnalysisFindingFeedback = {
    id: string;
    analysis_run_id: string;
    module: string | null;
    status: string | null;
    lens: string;
    severity: string;
    title: string;
    body: string;
    attributions: Array<{
        claim?: string;
        source_reference?: string;
    }>;
    document_support: string;
    uncertainty: string | null;
    data_quality_disclaimer: string | null;
    created_at: string | null;
    feedback_store_url: string;
    feedback_count: number;
    latest_feedback: AnalysisFeedbackSummary[];
};

export type AnalysisFeedbackSummary = {
    id: string;
    decision: string;
    rating: number | null;
    note: string | null;
    has_correction: boolean;
    created_at: string | null;
    advisor_name: string | null;
};

export type FeedbackPayload = {
    decision: string;
    rating: number | null;
    corrected_body: string | null;
    note: string | null;
};

export type LifecycleForm = {
    status: string;
    reason: string;
};

export type KnowledgeAssessmentForm = {
    financial_literacy: number;
    strategic_awareness: number;
    leadership: number;
};

export type GoalForm = {
    title: string;
    description: string;
    annual_benefit: number | null;
    duration_years: number;
    pv_target: number | null;
    target_date: string;
    target_growth_percent: number | null;
};

export type MilestoneForm = {
    title: string;
    recommendation_ref: string;
    annual_impact: number | null;
    duration_years: number;
    pv_of_impact: number | null;
    due_date: string;
};

export type MilestoneActionForm = {
    title: string;
    due_date: string;
    priority: string;
};

export type ProofForm = {
    proof: File | null;
    claim: string;
};

export type ProposalForm = {
    fee_calculation_id: string;
    scope_summary: string;
    insurance_consent: string;
    coach_consent: string;
    budget_override_category: string;
    budget_override_notes: string;
};

export type MeetingForm = {
    title: string;
    scheduled_at: string;
    location: string;
    link: string;
    attendees: string;
};

export type StandardAdvisoryReportSummary = {
    id: string;
    type: string;
    type_label: string;
    title: string;
    generated_at: string | null;
    review_status: string;
    reviewed_at: string | null;
    view_url: string | null;
    download_url: string | null;
    review_url: string;
    release_url: string | null;
} | null;

export type StandardAdvisoryPackWaiverSummary = {
    id: string;
    modules: string[];
    reason: string;
    waived_at: string | null;
    waived_by: {
        id: string;
        name: string;
        email: string;
    } | null;
};

export type StandardAdvisoryGeneratePayload = {
    waiver_reason?: string;
    waiver_modules?: string[];
};

export type StandardAdvisorySummary = {
    questionnaire_submitted: boolean;
    questionnaire_submitted_at: string | null;
    answered_questions: number;
    total_questions: number;
    document_count: number;
    verified_document_count: number;
    blocking_verification_count: number;
    data_quality: {
        level: string;
        score: number;
        summary: DataQualitySummary;
    };
    analysis_modules: Array<{
        module: string;
        label: string;
        status: string;
        state: string;
        raw_status: string | null;
        completed: boolean;
        stale: boolean;
        waived: boolean;
        ready_for_pack: boolean;
        waivable: boolean;
        waiver: StandardAdvisoryPackWaiverSummary | null;
        dropped_findings: {
            missing_attribution: number;
        };
        completed_at: string | null;
    }>;
    analysis_completed: number;
    analysis_waived: number;
    analysis_dropped_findings: number;
    analysis_total: number;
    analysis_ready_for_pack: boolean;
    analysis_readiness: {
        level: 'red' | 'amber' | 'green';
        label: string;
        description: string;
    };
    momentum: {
        completed: number;
        total: number;
        percent: number;
        next_action: string;
        items: Array<{
            key: string;
            label: string;
            description: string;
            status:
                | 'complete'
                | 'in_progress'
                | 'waiting_advisor'
                | 'not_required';
            owner: 'client' | 'advisor';
        }>;
    };
    pack_waivers: StandardAdvisoryPackWaiverSummary[];
    waivable_modules: string[];
    website_audit: {
        status: string;
        status_label: string;
        next_action: string;
        has_url: boolean;
        has_website_page_evidence: boolean;
        has_product_service_evidence: boolean;
        has_seo_evidence: boolean;
        confirmed_url: string | null;
        fetch_status: string | null;
        confirm_url: string;
        candidates: Array<{
            url: string;
            answer_id: string | null;
            source: 'client' | 'questionnaire';
        }>;
    };
    health_recomputed_at: string | null;
    valuation_ready: boolean;
    valuation_as_at: string | null;
    reports: {
        client: StandardAdvisoryReportSummary;
        advisor: StandardAdvisoryReportSummary;
        stakeholder: StandardAdvisoryReportSummary;
        trajectory: StandardAdvisoryReportSummary;
    };
    latest_report_generated_at: string | null;
    missing: string[];
    warnings: string[];
    can_run_analysis: boolean;
    can_generate_pack: boolean;
    can_record_pack_waiver: boolean;
    status: string;
    status_label: string;
    next_action: string;
    run_analysis_url: string;
    generate_pack_url: string;
};
