import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    Brain,
    CalendarClock,
    CheckCircle2,
    CreditCard,
    Download,
    FileCheck2,
    FileText,
    HeartPulse,
    ListChecks,
    LockKeyhole,
    Mail,
    MessageSquare,
    MessageSquarePlus,
    PauseCircle,
    PencilLine,
    PlusCircle,
    PlugZap,
    RotateCcw,
    Send,
    Settings2,
    ShieldAlert,
    SlidersHorizontal,
    Star,
    Target,
    TrendingUp,
    Undo2,
    Unplug,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import type { ComponentType, FormEvent, MouseEvent, ReactNode } from 'react';
import { toast } from 'sonner';
import { DataQualityBadge } from '@/components/data-quality/DataQualityBadge';
import type { DataQualitySummary } from '@/components/data-quality/DataQualityBadge';
import FileDropzone from '@/components/file-dropzone';
import InputError from '@/components/input-error';
import { NpoHealthPanel } from '@/components/npo/NpoHealthPanel';
import type { NpoHealthPayload } from '@/components/npo/NpoHealthPanel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useDrillFocus } from '@/hooks/use-drill-focus';
import { cn } from '@/lib/utils';
import type { ClientSummary } from './types';

type ClientDetail = ClientSummary & {
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
    due_diligence: DueDiligenceSummary | null;
    npo_conversion: NpoConversionSummary | null;
    npo_governance_review: NpoGovernanceReviewSummary | null;
    npo_configuration: NpoConfigurationSummary | null;
    npo_health: NpoHealthPayload | null;
    npo_funding: NpoFundingSummary | null;
    npo_values: NpoValueSummary | null;
    npo_social_enterprise: NpoSocialEnterpriseSummary | null;
};

type ConflictDeclaration = {
    id: string;
    declaration: {
        referral_type?: string;
        existing_relationship?: boolean;
        details?: string | null;
    };
    declared_at: string;
} | null;

type Props = {
    client: ClientDetail;
    conflictDeclaration: ConflictDeclaration;
};

type ClientDetailTab = 'actions' | 'information';

const clientSectionTabs: Record<string, ClientDetailTab> = {
    'section-analysis': 'actions',
    'section-due-diligence': 'actions',
    'section-goals': 'actions',
    'section-lifecycle': 'actions',
    'section-npo-configuration': 'actions',
    'section-npo-conversion': 'actions',
    'section-npo-governance-review': 'actions',
    'section-overview': 'actions',
    'section-payments': 'actions',
    'section-proposals': 'actions',
    'section-standard-advisory': 'actions',
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

type WellbeingPoint = {
    id: string;
    period_start: string | null;
    business_confidence: number;
    personal_coping: number;
    notes: string | null;
    submitted_at: string | null;
    submitted_by: string | null;
};

type OffboardingSummary = {
    id: string;
    triggered_at: string | null;
    reengagement_due: string | null;
    advisor_capacity_released: boolean;
};

type StatusOption = {
    value: string;
    label: string;
};

type KnowledgeAssessmentSummary = {
    id: string;
    financial_literacy: number;
    strategic_awareness: number;
    leadership: number;
    calibration: Record<string, unknown>;
    assessed_at: string | null;
};

type GoalDashboard = {
    pv_realised_total: number;
    active_goals: number;
    goals: GoalSummary[];
};

type GoalSummary = {
    id: string;
    title: string;
    description: string | null;
    pv_target: number;
    status: string;
    milestone_store_url?: string;
    milestones: MilestoneSummary[];
};

type MilestoneSummary = {
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

type AccountingPayload = {
    providers: AccountingProvider[];
    connections: AccountingConnectionSummary[];
};

type AccountingProvider = {
    provider: string;
    label: string;
    connected: boolean;
    connect_url: string;
};

type AccountingConnectionSummary = {
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

type PaymentSummary = {
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

type DueDiligenceSummary = {
    id: string;
    status: string;
    target_name: string;
    target_details: Record<string, string | number | boolean | null>;
    questionnaire: {
        id: string;
        set: string;
        title: string;
    };
    standard_advisory_deferred: boolean;
    liability_disclaimer: string;
    disclaimer_acknowledged_at: string | null;
    acquisition_target_tab: boolean;
    data_room: {
        artifact_category: string;
        guest_upload_only: boolean;
        workstreams: Array<{
            key: string;
            label: string;
            item_count: number;
            active_guest_links: number;
            latest_item_at: string | null;
        }>;
    };
};

type NpoConversionSummary = {
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

type NpoGovernanceFinding = {
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

type NpoGovernanceReviewSummary = {
    id: string;
    run_url: string;
    findings_count: number;
    pending_review_count: number;
    reviewed_count: number;
    high_priority_count: number;
    can_generate_report: boolean;
    findings: NpoGovernanceFinding[];
};

type NpoOption = {
    value: string;
    label: string;
};

type NpoWeightingSuggestion = NpoOption & {
    commercial_weight: number;
    mission_weight: number;
};

type NpoDecisionQuestion = {
    key: string;
    label: string;
};

type NpoConfigurationSummary = {
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

type NpoFundingRecord = {
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

type NpoFundingAlert = {
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

type NpoFundingSummary = {
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

type NpoValueProjection = {
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

type NpoValueCalculation = {
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
    projections: NpoValueProjection[];
    calculated_at: string | null;
};

type NpoValueSummary = {
    npo_engagement_id: string;
    calculations: NpoValueCalculation[];
};

type NpoSocialEnterpriseAxis = {
    dimension: string;
    label: string;
    score: number | null;
    state?: string;
};

type NpoSocialEnterpriseSummary = {
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
        tensions: Array<{
            type: string;
            title: string;
            commercial_implication: string;
            mission_implication: string;
            strategic_options: string[];
            advisor_recommended_path: string;
            data_points: Array<Record<string, unknown>>;
        }>;
        generated_at: string | null;
    } | null;
};

type FinancialSnapshotSummary = {
    id: string;
    period_start: string | null;
    period_end: string | null;
    source: string;
    source_badge: string;
    degraded: boolean;
    metrics: Record<string, unknown>;
    pulled_at: string | null;
};

type FeeCalculationSummary = {
    id: string;
    method: string;
    suggested_mid: number;
    roi_ratio: number;
    created_at: string | null;
};

type ProposalSummary = {
    id: string;
    status: string;
    status_label: string;
    version: number;
    suggested_mid: number | null;
    roi_ratio: number;
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
};

type ReportSummary = {
    id: string;
    type: string;
    type_label: string;
    title: string;
    generated_at: string | null;
    pdf_byte_size: number | null;
    pptx_byte_size: number | null;
    download_url: string | null;
    pptx_url: string | null;
    review_status: string;
    reviewed_at: string | null;
    review_url: string;
    can_review: boolean;
    section_count: number;
    revision_count: number;
    comment_count: number;
};

type MeetingSummary = {
    id: string;
    title: string;
    scheduled_at: string | null;
    location: string | null;
    link: string | null;
    attendees: string[];
    calendar_synced: boolean;
    brief_status: string;
};

type IndustryBriefingSummary = {
    id: string;
    period: string | null;
    body: string;
    status: string;
    reviewed_at: string | null;
    sent_at: string | null;
    review_url: string;
    can_review: boolean;
};

type PreMeetingBriefSummary = {
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

type AnalysisFindingFeedback = {
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

type AnalysisFeedbackSummary = {
    id: string;
    decision: string;
    rating: number | null;
    note: string | null;
    has_correction: boolean;
    created_at: string | null;
    advisor_name: string | null;
};

type FeedbackPayload = {
    decision: string;
    rating: number | null;
    corrected_body: string | null;
    note: string | null;
};

type LifecycleForm = {
    status: string;
    reason: string;
};

type KnowledgeAssessmentForm = {
    financial_literacy: number;
    strategic_awareness: number;
    leadership: number;
};

type GoalForm = {
    title: string;
    description: string;
    annual_benefit: number | null;
    duration_years: number;
    pv_target: number | null;
};

type MilestoneForm = {
    title: string;
    recommendation_ref: string;
    annual_impact: number | null;
    duration_years: number;
    pv_of_impact: number | null;
    due_date: string;
};

type MilestoneActionForm = {
    title: string;
    due_date: string;
    priority: string;
};

type ProofForm = {
    proof: File | null;
    claim: string;
};

type ProposalForm = {
    fee_calculation_id: string;
    scope_summary: string;
    insurance_consent: string;
    coach_consent: string;
};

type MeetingForm = {
    title: string;
    scheduled_at: string;
    location: string;
    link: string;
    attendees: string;
};

type StandardAdvisoryReportSummary = {
    id: string;
    type: string;
    type_label: string;
    title: string;
    generated_at: string | null;
    review_status: string;
    reviewed_at: string | null;
    download_url: string | null;
    review_url: string;
} | null;

type StandardAdvisorySummary = {
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
        completed: boolean;
        completed_at: string | null;
    }>;
    analysis_completed: number;
    analysis_total: number;
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
    can_run_analysis: boolean;
    can_generate_pack: boolean;
    status: string;
    status_label: string;
    next_action: string;
    run_analysis_url: string;
    generate_pack_url: string;
};

export default function ClientsShow({ client, conflictDeclaration }: Props) {
    useDrillFocus();
    const [activeTab, setActiveTab] = useState<ClientDetailTab>(() =>
        initialClientDetailTab(),
    );
    const [generatingPack, setGeneratingPack] = useState(false);

    const lifecycleForm = useForm<LifecycleForm>({
        status: client.status,
        reason: '',
    });

    const submitLifecycle = (status: string) => {
        lifecycleForm.setData('status', status);
        lifecycleForm.transform((data) => ({
            ...data,
            status,
        }));
        lifecycleForm.patch(client.lifecycle_update_url, {
            preserveScroll: true,
            onFinish: () =>
                lifecycleForm.transform((data) => ({
                    ...data,
                })),
        });
    };

    const recomputeHealthRadar = () => {
        router.post(
            client.business_health_recompute_url,
            {},
            { preserveScroll: true },
        );
    };

    const runStandardAdvisoryAnalysis = () => {
        if (!client.standard_advisory?.can_run_analysis) {
            return;
        }

        router.post(
            client.standard_advisory.run_analysis_url,
            {},
            { preserveScroll: true },
        );
    };

    const generateStandardAdvisoryPack = () => {
        if (!client.standard_advisory?.can_generate_pack) {
            return;
        }

        router.post(
            client.standard_advisory.generate_pack_url,
            {},
            {
                preserveScroll: true,
                onStart: () => setGeneratingPack(true),
                onFinish: () => setGeneratingPack(false),
                onError: (errors) => {
                    const message =
                        errors.standard_advisory ??
                        'The advisory pack could not be generated.';

                    toast.error(message);
                },
            },
        );
    };

    const createKnowledgeDraft = () => {
        if (!client.offboarding) {
            return;
        }

        router.post(
            client.knowledge_draft_store_url,
            {},
            { preserveScroll: true },
        );
    };

    const jumpToSection = (sectionId: string, event?: MouseEvent<Element>) => {
        event?.preventDefault();
        setActiveTab(clientSectionTabs[sectionId] ?? 'actions');

        window.setTimeout(() => {
            const section = document.getElementById(sectionId);

            if (!section) {
                return;
            }

            if (!section.hasAttribute('tabindex')) {
                section.setAttribute('tabindex', '-1');
            }

            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            section.focus({ preventScroll: true });
            window.history.replaceState(null, '', `#${sectionId}`);
        }, 0);
    };

    const failedPaymentCount = client.payments.filter(
        (payment) => payment.status === 'failed',
    ).length;
    const draftProposalCount = client.proposals.filter((proposal) =>
        ['draft', 'generated'].includes(proposal.status),
    ).length;
    const npoConfigurationSummary = client.npo_configuration
        ? [
              client.npo_configuration.legal_structure_label,
              client.npo_configuration.tiriti_mode_label,
          ]
              .filter(Boolean)
              .join(' / ')
        : 'Not configured';
    const standardAdvisoryReportStatus =
        client.standard_advisory?.reports.client?.review_status === 'reviewed'
            ? 'Released'
            : client.standard_advisory?.reports.client?.review_status ===
                'pending_review'
              ? 'Awaiting release'
              : client.standard_advisory?.status_label;

    return (
        <>
            <Head title={client.legal_name} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {client.legal_name}
                        </h1>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span>{client.engagement_type_label}</span>
                            {client.is_npo && (
                                <Badge variant="secondary">NPO</Badge>
                            )}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            asChild
                            id="section-messages"
                            size="sm"
                            variant="outline"
                        >
                            <Link
                                href={`/advisor/clients/${client.id}/messages`}
                            >
                                <MessageSquare
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Messages
                            </Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link
                                href={`/advisor/clients/${client.id}/compose`}
                            >
                                <Mail className="size-4" aria-hidden="true" />
                                Email
                            </Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link
                                href={`/advisor/clients/${client.id}/surveys`}
                            >
                                <ListChecks
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Surveys
                            </Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link
                                href={`/advisor/clients/${client.id}/offboarding`}
                            >
                                <FileCheck2
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Offboard
                            </Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link href="/advisor/clients">
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Back
                            </Link>
                        </Button>
                    </div>
                </div>

                <ClientDetailTabList
                    activeTab={activeTab}
                    onChange={setActiveTab}
                />

                {activeTab === 'actions' ? (
                    <>
                        <ClientDetailSection
                            title="Priority actions"
                            description="Start with communication, lifecycle, client work, and commercial actions."
                        >
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <ActionTile
                                    icon={MessageSquare}
                                    title="Messages"
                                    value="Client thread"
                                    explanation="Open the secure client message thread and review the latest context."
                                    href={`/advisor/clients/${client.id}/messages`}
                                    actionLabel="Open"
                                />
                                <ActionTile
                                    icon={Mail}
                                    title="Email"
                                    value="Compose update"
                                    explanation="Send a structured advisory email to the client contact."
                                    href={`/advisor/clients/${client.id}/compose`}
                                    actionLabel="Compose"
                                />
                                <ActionTile
                                    icon={RotateCcw}
                                    title="Lifecycle"
                                    value={client.status_label}
                                    explanation="Change lifecycle state, pause access, suspend access, or restore the client."
                                    href="#section-lifecycle"
                                    actionLabel="Manage"
                                    onAction={(event) =>
                                        jumpToSection(
                                            'section-lifecycle',
                                            event,
                                        )
                                    }
                                />
                                {client.standard_advisory && (
                                    <ActionTile
                                        icon={ListChecks}
                                        title="Standard Advisory"
                                        value={standardAdvisoryReportStatus}
                                        explanation="Tracks questionnaire, evidence, analysis, advisory pack generation, and client report release."
                                        href="#section-standard-advisory"
                                        actionLabel="Review"
                                        onAction={(event) =>
                                            jumpToSection(
                                                'section-standard-advisory',
                                                event,
                                            )
                                        }
                                    />
                                )}
                                {client.is_npo && (
                                    <ActionTile
                                        icon={SlidersHorizontal}
                                        title="NPO configuration"
                                        value={npoConfigurationSummary}
                                        explanation="Review or update NPO classification, Te Tiriti mode, and social-enterprise weighting."
                                        href={
                                            client.npo_configuration
                                                ? '#section-npo-configuration'
                                                : '#section-overview'
                                        }
                                        actionLabel={
                                            client.npo_configuration
                                                ? 'Configure'
                                                : 'Review'
                                        }
                                        onAction={(event) =>
                                            jumpToSection(
                                                client.npo_configuration
                                                    ? 'section-npo-configuration'
                                                    : 'section-overview',
                                                event,
                                            )
                                        }
                                    />
                                )}
                                <ActionTile
                                    icon={Target}
                                    title="Goals"
                                    value={`${client.goals.active_goals} active`}
                                    explanation="Record goals, milestones, actions, and proof for realised platform value."
                                    href="#section-goals"
                                    actionLabel="Open"
                                    onAction={(event) =>
                                        jumpToSection('section-goals', event)
                                    }
                                />
                                <ActionTile
                                    icon={CreditCard}
                                    title="Payments"
                                    value={
                                        failedPaymentCount > 0
                                            ? `${failedPaymentCount} failed`
                                            : `${client.payments.length} records`
                                    }
                                    explanation="Review payment outcomes and retry failed payments where available."
                                    href="#section-payments"
                                    actionLabel="Review"
                                    onAction={(event) =>
                                        jumpToSection('section-payments', event)
                                    }
                                />
                                <ActionTile
                                    icon={FileText}
                                    title="Proposals"
                                    value={
                                        draftProposalCount > 0
                                            ? `${draftProposalCount} draft`
                                            : `${client.proposals.length} total`
                                    }
                                    explanation="Create, release, recall, or renew advisory proposals for this client."
                                    href="#section-proposals"
                                    actionLabel="Review"
                                    onAction={(event) =>
                                        jumpToSection(
                                            'section-proposals',
                                            event,
                                        )
                                    }
                                />
                                <ActionTile
                                    icon={MessageSquarePlus}
                                    title="Analysis"
                                    value={`${client.analysis_findings.length} findings`}
                                    explanation="Review analysis findings, add feedback, and recompute client health."
                                    href="#section-analysis"
                                    actionLabel="Review"
                                    onAction={(event) =>
                                        jumpToSection('section-analysis', event)
                                    }
                                />
                            </div>
                        </ClientDetailSection>

                        <ClientDetailSection
                            title="Client status"
                            description="Keep the top-level status signals visible before opening detailed workflow panels."
                        >
                            <div
                                id="section-overview"
                                className="grid gap-4 md:grid-cols-3"
                            >
                                <Metric
                                    label="NZBN"
                                    value={client.nzbn ?? '-'}
                                />
                                <Metric label="Lifecycle">
                                    <Badge
                                        variant={statusVariant(client.status)}
                                    >
                                        {client.status_label}
                                    </Badge>
                                </Metric>
                                <Metric label="Data quality">
                                    <div id="section-questionnaire">
                                        <div id="section-documents">
                                            <DataQualityBadge
                                                summary={
                                                    client.data_quality_summary
                                                }
                                            />
                                        </div>
                                    </div>
                                </Metric>
                            </div>
                        </ClientDetailSection>

                        <ClientDetailSection
                            title="Action panels"
                            description="Editable work areas and operational decisions sit here; use the Information tab for supporting context."
                        >
                            {client.standard_advisory && (
                                <StandardAdvisoryPanel
                                    summary={client.standard_advisory}
                                    onRunAnalysis={runStandardAdvisoryAnalysis}
                                    onGeneratePack={
                                        generateStandardAdvisoryPack
                                    }
                                    generatingPack={generatingPack}
                                />
                            )}

                            {client.due_diligence && (
                                <DueDiligenceTargetPanel
                                    payload={client.due_diligence}
                                />
                            )}

                            {client.npo_conversion && (
                                <NpoConversionPanel
                                    conversion={client.npo_conversion}
                                />
                            )}

                            {client.npo_governance_review && (
                                <NpoGovernanceReviewPanel
                                    summary={client.npo_governance_review}
                                />
                            )}

                            {client.npo_configuration && (
                                <NpoConfigurationPanel
                                    configuration={client.npo_configuration}
                                />
                            )}

                            <GoalsPanel client={client} />

                            <PaymentsPanel client={client} />

                            <ProposalsPanel client={client} />

                            <section
                                id="section-lifecycle"
                                className="space-y-4 rounded-md border p-4"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="flex items-center gap-2">
                                        <RotateCcw
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        <h2 className="text-sm font-medium">
                                            Lifecycle
                                        </h2>
                                        <Badge
                                            variant={statusVariant(
                                                client.status,
                                            )}
                                        >
                                            {client.status_label}
                                        </Badge>
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        Portal access is revoked while
                                        suspended.
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="lifecycle_reason">
                                        Reason
                                    </Label>
                                    <textarea
                                        id="lifecycle_reason"
                                        value={lifecycleForm.data.reason}
                                        onChange={(event) =>
                                            lifecycleForm.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                        rows={3}
                                        className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    />
                                    <InputError
                                        message={lifecycleForm.errors.reason}
                                    />
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {lifecycleActions(client.status).map(
                                        (action) => {
                                            const Icon = lifecycleIcon(
                                                action.status,
                                            );

                                            return (
                                                <Button
                                                    key={action.status}
                                                    type="button"
                                                    variant={
                                                        action.status ===
                                                        'suspended'
                                                            ? 'destructive'
                                                            : 'outline'
                                                    }
                                                    disabled={
                                                        lifecycleForm.processing
                                                    }
                                                    onClick={() =>
                                                        submitLifecycle(
                                                            action.status,
                                                        )
                                                    }
                                                >
                                                    <Icon
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    {action.label}
                                                </Button>
                                            );
                                        },
                                    )}
                                </div>
                                <InputError
                                    message={lifecycleForm.errors.status}
                                />
                            </section>

                            <section
                                id="section-analysis"
                                className="space-y-4 rounded-md border p-4"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="flex items-center gap-2">
                                        <MessageSquarePlus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        <h2 className="text-sm font-medium">
                                            Analysis findings
                                        </h2>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="outline">
                                            {client.analysis_findings.length}
                                        </Badge>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={recomputeHealthRadar}
                                        >
                                            <RotateCcw
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Recompute health
                                        </Button>
                                    </div>
                                </div>

                                {client.analysis_findings.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No analysis findings yet.
                                    </p>
                                ) : (
                                    <div className="space-y-4">
                                        {client.analysis_findings.map(
                                            (finding) => (
                                                <FindingFeedbackCard
                                                    key={finding.id}
                                                    finding={finding}
                                                />
                                            ),
                                        )}
                                    </div>
                                )}
                            </section>
                        </ClientDetailSection>
                    </>
                ) : (
                    <>
                        <ClientDetailSection
                            title="Client information"
                            description="Registry and engagement context used to interpret the active work."
                        >
                            <div className="grid gap-6 lg:grid-cols-2">
                                <section
                                    id="section-registry"
                                    className="space-y-4 rounded-md border p-4"
                                >
                                    <h2 className="text-sm font-medium">
                                        Registry
                                    </h2>
                                    <dl className="grid gap-3 text-sm">
                                        <Detail
                                            label="Entity"
                                            value={client.entity_type}
                                        />
                                        <Detail
                                            label="Filing"
                                            value={client.filing_status}
                                        />
                                        <Detail
                                            label="Trading"
                                            value={client.trading_name}
                                        />
                                    </dl>
                                    <div className="flex flex-wrap gap-2">
                                        {Object.entries(
                                            client.registry_sources,
                                        ).map(([service, badge]) => (
                                            <Badge
                                                key={service}
                                                variant="secondary"
                                            >
                                                {service}: {badge}
                                            </Badge>
                                        ))}
                                    </div>
                                </section>

                                <section
                                    id="section-engagement"
                                    className="space-y-4 rounded-md border p-4"
                                >
                                    <div className="flex items-center gap-2">
                                        <h2 className="text-sm font-medium">
                                            Engagement
                                        </h2>
                                        {client.engagement_type_locked && (
                                            <Badge variant="outline">
                                                <LockKeyhole
                                                    className="size-3"
                                                    aria-hidden="true"
                                                />
                                                locked
                                            </Badge>
                                        )}
                                    </div>
                                    <dl className="grid gap-3 text-sm">
                                        <Detail
                                            label="Type"
                                            value={client.engagement_type_label}
                                        />
                                        <Detail
                                            label="Status"
                                            value={client.status_label}
                                        />
                                        <Detail
                                            label="Conflict"
                                            value={
                                                conflictDeclaration
                                                    ? 'declared'
                                                    : 'missing'
                                            }
                                        />
                                        <Detail
                                            label="Offboarding"
                                            value={
                                                client.offboarding
                                                    ? formatDate(
                                                          client.offboarding
                                                              .triggered_at,
                                                      )
                                                    : 'not started'
                                            }
                                        />
                                        <Detail
                                            label="Relationship"
                                            value={
                                                conflictDeclaration?.declaration
                                                    .existing_relationship
                                                    ? 'yes'
                                                    : 'no'
                                            }
                                        />
                                    </dl>
                                    {client.offboarding && (
                                        <div className="flex justify-end">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={createKnowledgeDraft}
                                            >
                                                <Brain
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Draft insight
                                            </Button>
                                        </div>
                                    )}
                                </section>
                            </div>
                        </ClientDetailSection>

                        <ClientDetailSection
                            title="Decision context"
                            description="Review health, funding, value, reports, and operating history after action work is clear."
                        >
                            {client.npo_health && (
                                <div id="section-npo-health">
                                    <NpoHealthPanel
                                        payload={client.npo_health}
                                    />
                                </div>
                            )}

                            {client.npo_funding && (
                                <NpoFundingPanel funding={client.npo_funding} />
                            )}

                            {client.npo_values && (
                                <NpoValuePanel values={client.npo_values} />
                            )}

                            {client.npo_social_enterprise && (
                                <NpoSocialEnterprisePanel
                                    summary={client.npo_social_enterprise}
                                />
                            )}

                            <KnowledgeAssessmentPanel client={client} />

                            <AccountingConnectionsPanel client={client} />

                            <ReportsPanel client={client} />

                            <MeetingsBriefingsPanel client={client} />

                            {client.wellbeing_trend && (
                                <section
                                    id="section-wellbeing"
                                    className="space-y-4 rounded-md border p-4"
                                >
                                    <div className="flex items-center gap-2">
                                        <HeartPulse
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        <h2 className="text-sm font-medium">
                                            Wellbeing
                                        </h2>
                                    </div>
                                    <WellbeingTrend
                                        points={client.wellbeing_trend}
                                    />
                                </section>
                            )}
                        </ClientDetailSection>
                    </>
                )}
            </div>
        </>
    );
}

function GoalsPanel({ client }: { client: ClientDetail }) {
    const form = useForm<GoalForm>({
        title: '',
        description: '',
        annual_benefit: null,
        duration_years: 1,
        pv_target: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(client.goal_store_url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <section id="section-goals" className="space-y-4 rounded-md border p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Target className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Goals</h2>
                    <Badge variant="outline">
                        {client.goals.active_goals} active
                    </Badge>
                </div>
                <div className="text-sm font-medium">
                    {formatCurrency(client.goals.pv_realised_total)} realised
                </div>
            </div>

            <form onSubmit={submit} className="grid gap-4 lg:grid-cols-5">
                <div className="grid gap-2 lg:col-span-2">
                    <Label htmlFor="goal_title">Goal</Label>
                    <input
                        id="goal_title"
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.title} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="goal_benefit">Annual benefit</Label>
                    <input
                        id="goal_benefit"
                        type="number"
                        min={0}
                        step="0.01"
                        value={form.data.annual_benefit ?? ''}
                        onChange={(event) =>
                            form.setData(
                                'annual_benefit',
                                nullableNumber(event.target.value),
                            )
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.annual_benefit} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="goal_duration">Years</Label>
                    <input
                        id="goal_duration"
                        type="number"
                        min={1}
                        max={10}
                        value={form.data.duration_years}
                        onChange={(event) =>
                            form.setData(
                                'duration_years',
                                Number(event.target.value),
                            )
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.duration_years} />
                </div>
                <div className="flex items-end">
                    <Button
                        type="submit"
                        disabled={form.processing}
                        className="w-full"
                    >
                        <PlusCircle className="size-4" aria-hidden="true" />
                        Add goal
                    </Button>
                </div>
                <div className="grid gap-2 lg:col-span-5">
                    <Label htmlFor="goal_description">Description</Label>
                    <textarea
                        id="goal_description"
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                        rows={2}
                        className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.description} />
                </div>
            </form>

            {client.goals.goals.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No goals recorded yet.
                </p>
            ) : (
                <div className="space-y-4">
                    {client.goals.goals.map((goal) => (
                        <GoalRow key={goal.id} goal={goal} />
                    ))}
                </div>
            )}
        </section>
    );
}

function GoalRow({ goal }: { goal: GoalSummary }) {
    return (
        <article className="space-y-4 rounded-md border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-sm font-medium">{goal.title}</h3>
                        <Badge variant={goalStatusVariant(goal.status)}>
                            {formatLabel(goal.status)}
                        </Badge>
                    </div>
                    {goal.description && (
                        <p className="text-sm text-muted-foreground">
                            {goal.description}
                        </p>
                    )}
                </div>
                <div className="text-sm font-medium">
                    {formatCurrency(goal.pv_target)}
                </div>
            </div>

            {goal.milestone_store_url && <MilestoneFormPanel goal={goal} />}

            {goal.milestones.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No milestones yet.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {goal.milestones.map((milestone) => (
                        <MilestoneRow
                            key={milestone.id}
                            milestone={milestone}
                        />
                    ))}
                </div>
            )}
        </article>
    );
}

function MilestoneFormPanel({ goal }: { goal: GoalSummary }) {
    const form = useForm<MilestoneForm>({
        title: '',
        recommendation_ref: '',
        annual_impact: null,
        duration_years: 1,
        pv_of_impact: null,
        due_date: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!goal.milestone_store_url) {
            return;
        }

        form.post(goal.milestone_store_url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-3 lg:grid-cols-6">
            <div className="grid gap-2 lg:col-span-2">
                <Label htmlFor={`milestone_title_${goal.id}`}>Milestone</Label>
                <input
                    id={`milestone_title_${goal.id}`}
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={form.errors.title} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`milestone_impact_${goal.id}`}>Impact</Label>
                <input
                    id={`milestone_impact_${goal.id}`}
                    type="number"
                    min={0}
                    step="0.01"
                    value={form.data.annual_impact ?? ''}
                    onChange={(event) =>
                        form.setData(
                            'annual_impact',
                            nullableNumber(event.target.value),
                        )
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={form.errors.annual_impact} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`milestone_due_${goal.id}`}>Due</Label>
                <input
                    id={`milestone_due_${goal.id}`}
                    type="date"
                    value={form.data.due_date}
                    onChange={(event) =>
                        form.setData('due_date', event.target.value)
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={form.errors.due_date} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`milestone_ref_${goal.id}`}>Reference</Label>
                <input
                    id={`milestone_ref_${goal.id}`}
                    value={form.data.recommendation_ref}
                    onChange={(event) =>
                        form.setData('recommendation_ref', event.target.value)
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={form.errors.recommendation_ref} />
            </div>
            <div className="flex items-end">
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                    className="w-full"
                >
                    <ListChecks className="size-4" aria-hidden="true" />
                    Add
                </Button>
            </div>
        </form>
    );
}

function MilestoneRow({ milestone }: { milestone: MilestoneSummary }) {
    return (
        <article className="space-y-4 p-3">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <h4 className="text-sm font-medium">
                            {milestone.title}
                        </h4>
                        <Badge
                            variant={milestoneStatusVariant(milestone.status)}
                        >
                            {formatLabel(milestone.status)}
                        </Badge>
                        {milestone.proof_status && (
                            <Badge
                                variant={proofStatusVariant(
                                    milestone.proof_status,
                                )}
                            >
                                {formatLabel(milestone.proof_status)}
                            </Badge>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                        <span>{formatCurrency(milestone.pv_of_impact)}</span>
                        <span>{formatDate(milestone.due_date)}</span>
                        <span>{milestone.actions_count} actions</span>
                        {milestone.recommendation_ref && (
                            <span>{milestone.recommendation_ref}</span>
                        )}
                    </div>
                </div>
                <div className="text-xs text-muted-foreground">
                    {milestone.completed_at
                        ? `Completed ${formatDate(milestone.completed_at)}`
                        : 'Open'}
                </div>
            </div>

            <div className="grid gap-3 lg:grid-cols-2">
                {milestone.action_store_url && (
                    <MilestoneActionFormPanel milestone={milestone} />
                )}
                {milestone.proof_store_url && (
                    <ProofUploadFormPanel milestone={milestone} />
                )}
            </div>
        </article>
    );
}

function MilestoneActionFormPanel({
    milestone,
}: {
    milestone: MilestoneSummary;
}) {
    const form = useForm<MilestoneActionForm>({
        title: '',
        due_date: '',
        priority: 'normal',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!milestone.action_store_url) {
            return;
        }

        form.post(milestone.action_store_url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-3 sm:grid-cols-3">
            <div className="grid gap-2 sm:col-span-3">
                <Label htmlFor={`action_title_${milestone.id}`}>Action</Label>
                <input
                    id={`action_title_${milestone.id}`}
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={form.errors.title} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`action_due_${milestone.id}`}>Due</Label>
                <input
                    id={`action_due_${milestone.id}`}
                    type="date"
                    value={form.data.due_date}
                    onChange={(event) =>
                        form.setData('due_date', event.target.value)
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={form.errors.due_date} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`action_priority_${milestone.id}`}>
                    Priority
                </Label>
                <select
                    id={`action_priority_${milestone.id}`}
                    value={form.data.priority}
                    onChange={(event) =>
                        form.setData('priority', event.target.value)
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="normal">Normal</option>
                    <option value="high">High</option>
                    <option value="low">Low</option>
                </select>
                <InputError message={form.errors.priority} />
            </div>
            <div className="flex items-end">
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                    className="w-full"
                >
                    <PlusCircle className="size-4" aria-hidden="true" />
                    Add
                </Button>
            </div>
        </form>
    );
}

function ProofUploadFormPanel({ milestone }: { milestone: MilestoneSummary }) {
    const form = useForm<ProofForm>({
        proof: null,
        claim: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!milestone.proof_store_url) {
            return;
        }

        form.post(milestone.proof_store_url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-3 sm:grid-cols-3">
            <div className="grid gap-2 sm:col-span-3">
                <FileDropzone
                    id={`proof_file_${milestone.id}`}
                    files={form.data.proof ? [form.data.proof] : []}
                    label="Proof"
                    description="Drag proof here or browse"
                    onFilesChange={(files) =>
                        form.setData('proof', files[0] ?? null)
                    }
                />
                <InputError message={form.errors.proof} />
            </div>
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor={`proof_claim_${milestone.id}`}>Claim</Label>
                <input
                    id={`proof_claim_${milestone.id}`}
                    value={form.data.claim}
                    onChange={(event) =>
                        form.setData('claim', event.target.value)
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={form.errors.claim} />
            </div>
            <div className="flex items-end">
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                    className="w-full"
                >
                    <Upload className="size-4" aria-hidden="true" />
                    Upload
                </Button>
            </div>
        </form>
    );
}

function NpoConversionPanel({
    conversion,
}: {
    conversion: NpoConversionSummary;
}) {
    const reportDeliveredForm = useForm<Record<string, never>>({});
    const declineForm = useForm<{ reason: string }>({
        reason: conversion.decline_reason ?? '',
    });
    const convertForm = useForm<Record<string, never>>({});
    const isConverted = conversion.status === 'converted';

    const submitDecline = (event: FormEvent) => {
        event.preventDefault();
        declineForm.patch(conversion.decline_url, { preserveScroll: true });
    };

    return (
        <section
            id="section-npo-conversion"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <CalendarClock className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Governance Review conversion
                    </h2>
                    <Badge
                        variant={
                            conversion.status === 'declined'
                                ? 'outline'
                                : 'secondary'
                        }
                    >
                        {conversion.status_label ??
                            formatLabel(conversion.status ?? '')}
                    </Badge>
                    {conversion.next_nudge_day && (
                        <Badge variant="destructive">
                            {conversion.next_nudge_day}d nudge due
                        </Badge>
                    )}
                </div>
                <div className="text-xs text-muted-foreground">
                    Re-engagement {formatDate(conversion.reengagement_due_at)}
                </div>
            </div>

            <dl className="grid gap-3 text-sm sm:grid-cols-3">
                <Detail
                    label="Report delivered"
                    value={formatDate(conversion.report_delivered_at)}
                />
                <Detail
                    label="Next review"
                    value={formatDate(conversion.reengagement_due_at)}
                />
                <Detail
                    label="Follow-up"
                    value={
                        conversion.next_nudge_day
                            ? `${conversion.next_nudge_day} days`
                            : 'Current'
                    }
                />
            </dl>

            {conversion.decline_reason && (
                <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                    {conversion.decline_reason}
                </div>
            )}

            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    disabled={reportDeliveredForm.processing || isConverted}
                    onClick={() =>
                        reportDeliveredForm.patch(
                            conversion.report_delivered_url,
                            { preserveScroll: true },
                        )
                    }
                >
                    <FileText className="size-4" aria-hidden="true" />
                    Report delivered
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={convertForm.processing || isConverted}
                    onClick={() =>
                        convertForm.patch(conversion.convert_url, {
                            preserveScroll: true,
                        })
                    }
                >
                    <CheckCircle2 className="size-4" aria-hidden="true" />
                    Convert
                </Button>
            </div>

            {!isConverted && (
                <form onSubmit={submitDecline} className="grid gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="npo_conversion_reason">
                            Decline reason
                        </Label>
                        <textarea
                            id="npo_conversion_reason"
                            value={declineForm.data.reason}
                            onChange={(event) =>
                                declineForm.setData(
                                    'reason',
                                    event.target.value,
                                )
                            }
                            rows={3}
                            className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <InputError message={declineForm.errors.reason} />
                    </div>
                    <div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={declineForm.processing}
                        >
                            <Ban className="size-4" aria-hidden="true" />
                            Save decline
                        </Button>
                    </div>
                </form>
            )}
        </section>
    );
}

function NpoGovernanceReviewPanel({
    summary,
}: {
    summary: NpoGovernanceReviewSummary;
}) {
    const runForm = useForm<Record<string, never>>({});

    return (
        <section
            id="section-npo-governance-review"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <FileCheck2 className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Governance Review workflow
                    </h2>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Badge variant="secondary">
                                {summary.pending_review_count} pending
                            </Badge>
                        </TooltipTrigger>
                        <TooltipContent side="bottom" className="max-w-xs">
                            Advisor review is required before governance
                            findings can be used in a client-facing report.
                        </TooltipContent>
                    </Tooltip>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={runForm.processing}
                    onClick={() =>
                        runForm.post(summary.run_url, { preserveScroll: true })
                    }
                >
                    <Brain className="size-4" aria-hidden="true" />
                    Run analysis
                </Button>
            </div>

            <dl className="grid gap-3 text-sm sm:grid-cols-4">
                <Detail
                    label="Findings"
                    value={summary.findings_count.toString()}
                />
                <Detail
                    label="High priority"
                    value={summary.high_priority_count.toString()}
                />
                <Detail
                    label="Reviewed"
                    value={summary.reviewed_count.toString()}
                />
                <Detail
                    label="Report ready"
                    value={summary.can_generate_report ? 'Yes' : 'No'}
                />
            </dl>

            {summary.findings.length === 0 ? (
                <p className="rounded-md border px-3 py-6 text-sm text-muted-foreground">
                    No governance findings generated yet.
                </p>
            ) : (
                <div className="grid gap-3">
                    {summary.findings.map((finding) => (
                        <NpoGovernanceFindingCard
                            key={finding.id}
                            finding={finding}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}

function NpoGovernanceFindingCard({
    finding,
}: {
    finding: NpoGovernanceFinding;
}) {
    const form = useForm({ advisor_notes: finding.advisor_notes ?? '' });

    return (
        <article className="grid gap-3 rounded-md border p-3 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.55fr)]">
            <div className="space-y-2">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={severityVariant(finding.severity)}>
                        {formatLabel(finding.severity)}
                    </Badge>
                    <Badge variant="outline">
                        {formatLabel(finding.status)}
                    </Badge>
                    <span className="text-xs text-muted-foreground">
                        {formatLabel(finding.category)}
                    </span>
                </div>
                <h3 className="text-sm font-medium">{finding.title}</h3>
                <p className="text-sm text-muted-foreground">{finding.body}</p>
            </div>
            <div className="grid gap-2">
                <textarea
                    value={form.data.advisor_notes}
                    onChange={(event) =>
                        form.setData('advisor_notes', event.target.value)
                    }
                    className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    placeholder="Advisor review notes"
                />
                <Button
                    type="button"
                    size="sm"
                    disabled={form.processing || finding.status === 'reviewed'}
                    onClick={() =>
                        form.patch(finding.review_url, {
                            preserveScroll: true,
                        })
                    }
                >
                    <CheckCircle2 className="size-4" aria-hidden="true" />
                    Mark reviewed
                </Button>
            </div>
        </article>
    );
}

function NpoConfigurationPanel({
    configuration,
}: {
    configuration: NpoConfigurationSummary;
}) {
    const defaultSocialType =
        configuration.social_enterprise_type ??
        configuration.social_enterprise_type_options[0]?.value ??
        '';
    const selectedSuggestion =
        configuration.social_enterprise_type_options.find(
            (option) => option.value === defaultSocialType,
        ) ?? configuration.social_enterprise_type_options[0];
    const form = useForm({
        legal_structure: configuration.legal_structure,
        tiriti_decision_guide: configuration.tiriti_decision_guide,
        tiriti_mode:
            configuration.tiriti_mode ?? configuration.tiriti_suggested_mode,
        social_enterprise: configuration.social_enterprise,
        social_enterprise_type: defaultSocialType,
        commercial_weight:
            configuration.commercial_weight ??
            selectedSuggestion?.commercial_weight ??
            50,
        mission_weight:
            configuration.mission_weight ??
            selectedSuggestion?.mission_weight ??
            50,
    });
    const errors = form.errors as Record<string, string | undefined>;
    const suggestedMode = Object.values(form.data.tiriti_decision_guide).some(
        Boolean,
    )
        ? 'standalone'
        : 'woven';

    const setGuideAnswer = (key: string, checked: boolean) => {
        const nextGuide = {
            ...form.data.tiriti_decision_guide,
            [key]: checked,
        };
        const nextSuggestedMode = Object.values(nextGuide).some(Boolean)
            ? 'standalone'
            : 'woven';

        form.setData({
            ...form.data,
            tiriti_decision_guide: nextGuide,
            tiriti_mode: nextSuggestedMode,
        });
    };

    const setSocialEnterpriseType = (value: string) => {
        const suggestion = configuration.social_enterprise_type_options.find(
            (option) => option.value === value,
        );

        form.setData({
            ...form.data,
            social_enterprise_type: value,
            commercial_weight:
                suggestion?.commercial_weight ?? form.data.commercial_weight,
            mission_weight:
                suggestion?.mission_weight ?? form.data.mission_weight,
        });
    };

    const applyWeighting = (suggestion: NpoWeightingSuggestion) => {
        form.setData({
            ...form.data,
            social_enterprise: true,
            social_enterprise_type: suggestion.value,
            commercial_weight: suggestion.commercial_weight,
            mission_weight: suggestion.mission_weight,
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.patch(configuration.update_url, { preserveScroll: true });
    };

    return (
        <section
            id="section-npo-configuration"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Settings2 className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">NPO configuration</h2>
                    <Badge variant="secondary">
                        {configuration.sub_type_label}
                    </Badge>
                    <Badge variant="outline">
                        {configuration.tiriti_mode_label ??
                            formatLabel(form.data.tiriti_mode)}
                    </Badge>
                </div>
                <div className="text-xs text-muted-foreground">
                    Suggested {formatLabel(suggestedMode)}
                </div>
            </div>

            <form onSubmit={submit} className="space-y-5">
                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-3 border-l pl-3">
                        <div className="flex items-center gap-2">
                            <Badge variant="outline">1</Badge>
                            <h3 className="text-sm font-medium">
                                Legal structure
                            </h3>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="npo_legal_structure">
                                Structure
                            </Label>
                            <Select
                                value={form.data.legal_structure}
                                onValueChange={(value) =>
                                    form.setData('legal_structure', value)
                                }
                            >
                                <SelectTrigger id="npo_legal_structure">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {configuration.legal_structure_options.map(
                                        (option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.legal_structure} />
                        </div>
                    </div>

                    <div className="space-y-3 border-l pl-3">
                        <div className="flex items-center gap-2">
                            <Badge variant="outline">2</Badge>
                            <h3 className="text-sm font-medium">
                                Te Tiriti mode
                            </h3>
                        </div>
                        <div className="grid gap-3">
                            {configuration.tiriti_decision_questions.map(
                                (question) => (
                                    <label
                                        key={question.key}
                                        htmlFor={`tiriti_${question.key}`}
                                        className="flex gap-2 text-sm leading-5"
                                    >
                                        <Checkbox
                                            id={`tiriti_${question.key}`}
                                            checked={
                                                form.data.tiriti_decision_guide[
                                                    question.key
                                                ] ?? false
                                            }
                                            onCheckedChange={(checked) =>
                                                setGuideAnswer(
                                                    question.key,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <span>{question.label}</span>
                                    </label>
                                ),
                            )}
                            <InputError
                                message={errors.tiriti_decision_guide}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="tiriti_mode">Mode</Label>
                            <Select
                                value={form.data.tiriti_mode}
                                onValueChange={(value) =>
                                    form.setData('tiriti_mode', value)
                                }
                            >
                                <SelectTrigger id="tiriti_mode">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {configuration.tiriti_mode_options.map(
                                        (option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.tiriti_mode} />
                        </div>
                    </div>

                    <div className="space-y-3 border-l pl-3">
                        <div className="flex items-center gap-2">
                            <Badge variant="outline">3</Badge>
                            <h3 className="text-sm font-medium">
                                Social enterprise
                            </h3>
                        </div>
                        <label
                            htmlFor="npo_social_enterprise"
                            className="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                id="npo_social_enterprise"
                                checked={form.data.social_enterprise}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'social_enterprise',
                                        checked === true,
                                    )
                                }
                            />
                            <span>Dual commercial and mission scorecard</span>
                        </label>
                        <InputError message={form.errors.social_enterprise} />

                        <div className="grid gap-2">
                            <Label htmlFor="social_enterprise_type">Type</Label>
                            <Select
                                value={form.data.social_enterprise_type}
                                onValueChange={setSocialEnterpriseType}
                                disabled={!form.data.social_enterprise}
                            >
                                <SelectTrigger id="social_enterprise_type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {configuration.social_enterprise_type_options.map(
                                        (option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <InputError
                                message={form.errors.social_enterprise_type}
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="commercial_weight">
                                    Commercial
                                </Label>
                                <Input
                                    id="commercial_weight"
                                    type="number"
                                    min={0}
                                    max={100}
                                    value={form.data.commercial_weight}
                                    disabled={!form.data.social_enterprise}
                                    onChange={(event) =>
                                        form.setData(
                                            'commercial_weight',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.commercial_weight}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="mission_weight">Mission</Label>
                                <Input
                                    id="mission_weight"
                                    type="number"
                                    min={0}
                                    max={100}
                                    value={form.data.mission_weight}
                                    disabled={!form.data.social_enterprise}
                                    onChange={(event) =>
                                        form.setData(
                                            'mission_weight',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.mission_weight}
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="overflow-hidden rounded-md border">
                    <table className="fsa-responsive-table">
                        <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">
                                    Commercial
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Mission
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Apply
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {configuration.social_enterprise_type_options.map(
                                (suggestion) => (
                                    <tr
                                        key={suggestion.value}
                                        className="border-t"
                                    >
                                        <td
                                            className="px-3 py-2"
                                            data-label="Type"
                                        >
                                            {suggestion.label}
                                        </td>
                                        <td
                                            className="px-3 py-2"
                                            data-label="Commercial"
                                        >
                                            {suggestion.commercial_weight}%
                                        </td>
                                        <td
                                            className="px-3 py-2"
                                            data-label="Mission"
                                        >
                                            {suggestion.mission_weight}%
                                        </td>
                                        <td
                                            className="px-3 py-2 text-left md:text-right"
                                            data-label="Apply"
                                        >
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    applyWeighting(suggestion)
                                                }
                                            >
                                                <SlidersHorizontal
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Apply
                                            </Button>
                                        </td>
                                    </tr>
                                ),
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        <CheckCircle2 className="size-4" aria-hidden="true" />
                        Save configuration
                    </Button>
                </div>
            </form>
        </section>
    );
}

function NpoFundingPanel({ funding }: { funding: NpoFundingSummary }) {
    return (
        <section
            id="section-npo-funding"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <CreditCard className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">NPO funding</h2>
                    <Badge variant="secondary">
                        {funding.records.length} records
                    </Badge>
                    {funding.alerts.length > 0 && (
                        <Badge variant="destructive">
                            {funding.alerts.length} alerts
                        </Badge>
                    )}
                </div>
                <Badge variant="outline">
                    {formatLabel(funding.concentration.risk_level)} risk
                </Badge>
            </div>

            <dl className="grid gap-3 text-sm sm:grid-cols-3">
                <Detail
                    label="Active funding"
                    value={formatCurrency(
                        funding.concentration.total_active_amount,
                    )}
                />
                <Detail
                    label="Largest funder"
                    value={funding.concentration.largest_funder_name ?? '-'}
                />
                <Detail
                    label="Concentration"
                    value={new Intl.NumberFormat(undefined, {
                        style: 'percent',
                        maximumFractionDigits: 1,
                    }).format(funding.concentration.largest_funder_ratio)}
                />
            </dl>

            {funding.alerts.length > 0 && (
                <div className="grid gap-2">
                    {funding.alerts.map((alert) => (
                        <div
                            key={alert.id}
                            className="flex flex-col gap-1 rounded-md border bg-muted/30 p-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <div className="font-medium">
                                    {alert.funder_name ?? 'Funder'}
                                </div>
                                <div className="text-muted-foreground">
                                    {alert.message}
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge
                                    variant={
                                        alert.severity === 'critical'
                                            ? 'destructive'
                                            : 'outline'
                                    }
                                >
                                    {formatLabel(alert.severity)}
                                </Badge>
                                <span className="text-xs text-muted-foreground">
                                    {formatDate(alert.due_on)}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <div className="overflow-hidden rounded-md border">
                <table className="fsa-responsive-table">
                    <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="px-3 py-2 font-medium">Funder</th>
                            <th className="px-3 py-2 font-medium">Amount</th>
                            <th className="px-3 py-2 font-medium">Report</th>
                            <th className="px-3 py-2 font-medium">Renewal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {funding.records.map((record) => (
                            <tr key={record.id} className="border-t">
                                <td className="px-3 py-2" data-label="Funder">
                                    <div className="font-medium">
                                        {record.funder_name ?? 'Funder'}
                                    </div>
                                    {record.funder_needs_verification && (
                                        <Badge variant="outline">Verify</Badge>
                                    )}
                                </td>
                                <td className="px-3 py-2" data-label="Amount">
                                    {formatMoney(
                                        record.grant_amount,
                                        record.currency,
                                    )}
                                </td>
                                <td className="px-3 py-2" data-label="Report">
                                    {formatDate(record.reporting_deadline)}
                                </td>
                                <td className="px-3 py-2" data-label="Renewal">
                                    {record.renewal_probability === null
                                        ? '-'
                                        : `${record.renewal_probability}%`}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function NpoValuePanel({ values }: { values: NpoValueSummary }) {
    return (
        <section
            id="section-npo-value"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <TrendingUp className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        NPO value calculations
                    </h2>
                    <Badge variant="secondary">
                        {values.calculations.length} latest
                    </Badge>
                </div>
            </div>

            <div className="grid gap-3 lg:grid-cols-2">
                {values.calculations.map((calculation) => (
                    <article
                        key={calculation.id}
                        className="space-y-3 rounded-md border bg-muted/20 p-3"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="font-medium">
                                {calculation.label}
                            </div>
                            <Badge
                                variant={
                                    calculation.rating === 'critical' ||
                                    calculation.rating === 'high_cost'
                                        ? 'destructive'
                                        : 'outline'
                                }
                            >
                                {formatLabel(calculation.rating)}
                            </Badge>
                        </div>
                        <div className="grid gap-2 text-sm sm:grid-cols-3">
                            <Detail
                                label="Low"
                                value={formatProjectionValue(
                                    calculation.projection_low,
                                    calculation.projections[0]?.unit,
                                )}
                            />
                            <Detail
                                label="Mid"
                                value={formatProjectionValue(
                                    calculation.projection_mid,
                                    calculation.projections[0]?.unit,
                                )}
                            />
                            <Detail
                                label="High"
                                value={formatProjectionValue(
                                    calculation.projection_high,
                                    calculation.projections[0]?.unit,
                                )}
                            />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {calculation.mission_framing}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {calculation.stable_assumption_disclosure}
                        </p>
                    </article>
                ))}
            </div>
        </section>
    );
}

function formatProjectionValue(value: number, unit?: string): string {
    if (unit === 'beneficiaries') {
        return `${new Intl.NumberFormat(undefined, {
            maximumFractionDigits: 1,
        }).format(value)} beneficiaries`;
    }

    return formatCurrency(value);
}

function NpoSocialEnterprisePanel({
    summary,
}: {
    summary: NpoSocialEnterpriseSummary;
}) {
    const { scorecard, tension_analysis } = summary;

    return (
        <section
            id="section-npo-social-enterprise"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <SlidersHorizontal className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Social enterprise scorecard
                    </h2>
                </div>
                <Badge variant="secondary">
                    Blended {scorecard.blended_score.toFixed(1)}
                </Badge>
            </div>

            <div className="grid gap-3 md:grid-cols-3">
                <Detail
                    label={`Commercial (${scorecard.commercial_weight}%)`}
                    value={`${scorecard.commercial_score}/100`}
                />
                <Detail
                    label={`Mission (${scorecard.mission_weight}%)`}
                    value={`${scorecard.mission_score}/100`}
                />
                <Detail
                    label="Blended"
                    value={`${scorecard.blended_score.toFixed(1)}/100`}
                />
            </div>

            <div className="grid gap-3 lg:grid-cols-2">
                <AxisList
                    title="Commercial radar"
                    axes={scorecard.commercial_axes}
                />
                <AxisList title="Mission radar" axes={scorecard.mission_axes} />
            </div>

            {tension_analysis && (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <h3 className="text-sm font-medium">Tensions</h3>
                        <Badge
                            variant={
                                tension_analysis.is_releasable
                                    ? 'secondary'
                                    : 'outline'
                            }
                        >
                            {formatLabel(tension_analysis.review_status)}
                        </Badge>
                    </div>
                    <div className="grid gap-3">
                        {tension_analysis.tensions.map((tension) => (
                            <article
                                key={`${tension.type}-${tension.title}`}
                                className="rounded-md border bg-muted/20 p-3"
                            >
                                <div className="mb-2 flex flex-wrap items-center gap-2">
                                    <Badge variant="outline">
                                        {formatLabel(tension.type)}
                                    </Badge>
                                    <div className="font-medium">
                                        {tension.title}
                                    </div>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {tension.commercial_implication}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {tension.mission_implication}
                                </p>
                            </article>
                        ))}
                    </div>
                </div>
            )}
        </section>
    );
}

function AxisList({
    title,
    axes,
}: {
    title: string;
    axes: NpoSocialEnterpriseAxis[];
}) {
    return (
        <div className="space-y-2 rounded-md border p-3">
            <h3 className="text-sm font-medium">{title}</h3>
            <div className="grid gap-2 text-sm">
                {axes.map((axis) => (
                    <div
                        key={`${title}-${axis.dimension}`}
                        className="flex items-center justify-between gap-3"
                    >
                        <span className="text-muted-foreground">
                            {axis.label}
                        </span>
                        <span className="font-medium">
                            {axis.score === null ? '-' : `${axis.score}/100`}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function DueDiligenceTargetPanel({
    payload,
}: {
    payload: DueDiligenceSummary;
}) {
    return (
        <section
            id="section-due-diligence"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <ShieldAlert className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Acquisition target</h2>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge variant="secondary">
                        {formatLabel(payload.status)}
                    </Badge>
                    <Badge variant="outline">{payload.questionnaire.set}</Badge>
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.8fr)]">
                <dl className="grid gap-3 text-sm">
                    <Detail label="Target" value={payload.target_name} />
                    <Detail
                        label="Vendor"
                        value={stringDetail(payload.target_details.vendor_name)}
                    />
                    <Detail
                        label="Industry"
                        value={stringDetail(payload.target_details.industry)}
                    />
                    <Detail
                        label="Target NZBN"
                        value={stringDetail(payload.target_details.nzbn)}
                    />
                    <Detail
                        label="Questionnaire"
                        value={payload.questionnaire.title}
                    />
                </dl>
                <div className="rounded-md border bg-muted/20 p-3 text-xs leading-5 text-muted-foreground">
                    {payload.liability_disclaimer}
                </div>
            </div>

            <div className="space-y-3 border-t pt-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <ListChecks className="size-4" aria-hidden="true" />
                        <h3 className="text-sm font-medium">Data room</h3>
                    </div>
                    <Badge variant="outline">
                        {formatLabel(payload.data_room.artifact_category)}
                    </Badge>
                </div>
                <div className="divide-y text-sm">
                    {payload.data_room.workstreams.map((workstream) => (
                        <div
                            key={workstream.key}
                            className="grid gap-2 py-2 sm:grid-cols-[minmax(0,1fr)_auto_auto]"
                        >
                            <span>{workstream.label}</span>
                            <span className="text-muted-foreground">
                                {workstream.item_count} item
                                {workstream.item_count === 1 ? '' : 's'}
                            </span>
                            <span className="text-muted-foreground">
                                {workstream.active_guest_links} active link
                                {workstream.active_guest_links === 1 ? '' : 's'}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function AccountingConnectionsPanel({ client }: { client: ClientDetail }) {
    const pullSnapshot = (url: string) => {
        router.post(url, {}, { preserveScroll: true });
    };

    const revokeConnection = (url: string) => {
        router.patch(url, {}, { preserveScroll: true });
    };

    return (
        <section
            id="section-accounting"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <PlugZap className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Accounting connections
                    </h2>
                </div>
                <Badge variant="outline">
                    {
                        client.accounting.connections.filter(
                            (connection) => connection.connected,
                        ).length
                    }
                </Badge>
            </div>

            <div className="flex flex-wrap gap-2">
                {client.accounting.providers.map((provider) => (
                    <Button
                        key={provider.provider}
                        asChild
                        size="sm"
                        variant={provider.connected ? 'outline' : 'default'}
                    >
                        <Link href={provider.connect_url}>
                            <PlugZap className="size-4" aria-hidden="true" />
                            {provider.connected ? 'Reconnect' : 'Connect'}{' '}
                            {provider.label}
                        </Link>
                    </Button>
                ))}
            </div>

            {client.accounting.connections.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No accounting connections yet.
                </p>
            ) : (
                <div className="space-y-3">
                    {client.accounting.connections.map((connection) => (
                        <article
                            key={connection.id}
                            className="space-y-3 rounded-md border p-3"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="text-sm font-medium">
                                            {connection.provider_label}
                                        </h3>
                                        <Badge
                                            variant={
                                                connection.connected
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {formatLabel(connection.status)}
                                        </Badge>
                                        {connection.latest_snapshot && (
                                            <Badge variant="outline">
                                                {
                                                    connection.latest_snapshot
                                                        .source_badge
                                                }
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {connection.external_tenant_id ?? '-'}
                                    </div>
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={!connection.connected}
                                        onClick={() =>
                                            pullSnapshot(connection.pull_url)
                                        }
                                    >
                                        <RotateCcw
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Pull
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={!connection.connected}
                                        onClick={() =>
                                            revokeConnection(
                                                connection.revoke_url,
                                            )
                                        }
                                    >
                                        <Unplug
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Revoke
                                    </Button>
                                </div>
                            </div>

                            <dl className="grid gap-2 text-sm md:grid-cols-3">
                                <Metric
                                    label="Connected"
                                    value={formatDate(connection.connected_at)}
                                />
                                <Metric
                                    label="Last pull"
                                    value={formatDate(
                                        connection.last_snapshot_at,
                                    )}
                                />
                                <Metric
                                    label="Period"
                                    value={
                                        connection.latest_snapshot
                                            ?.period_end ?? '-'
                                    }
                                />
                            </dl>

                            {connection.latest_snapshot && (
                                <div className="flex flex-wrap gap-2">
                                    {Object.entries(
                                        connection.latest_snapshot.metrics,
                                    )
                                        .slice(0, 4)
                                        .map(([metric, value]) => (
                                            <Badge
                                                key={metric}
                                                variant="secondary"
                                            >
                                                {formatLabel(metric)}:{' '}
                                                {formatMetric(value)}
                                            </Badge>
                                        ))}
                                </div>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function PaymentsPanel({ client }: { client: ClientDetail }) {
    const retryPayment = (payment: PaymentSummary) => {
        if (!payment.manual_retry_available) {
            return;
        }

        router.post(payment.retry_url, {}, { preserveScroll: true });
    };

    return (
        <section
            id="section-payments"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <CreditCard className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Payments</h2>
                </div>
                <Badge variant="outline">{client.payments.length}</Badge>
            </div>

            {client.payments.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No payments recorded yet.
                </p>
            ) : (
                <div className="space-y-3">
                    {client.payments.map((payment) => (
                        <article
                            key={payment.id}
                            id={payment.id}
                            className="space-y-3 rounded-md border p-3"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="text-sm font-medium">
                                            {formatMoney(
                                                payment.amount,
                                                payment.currency,
                                            )}
                                        </h3>
                                        <Badge
                                            variant={paymentStatusVariant(
                                                payment.status,
                                            )}
                                        >
                                            {formatLabel(payment.status)}
                                        </Badge>
                                        <Badge variant="outline">
                                            Attempt {payment.attempt}
                                        </Badge>
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        Processed{' '}
                                        {formatDate(payment.processed_at)}
                                    </div>
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={payment.contact_url}>
                                            <Mail
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Contact
                                        </Link>
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={
                                            !payment.manual_retry_available
                                        }
                                        onClick={() => retryPayment(payment)}
                                    >
                                        <RotateCcw
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Retry
                                    </Button>
                                </div>
                            </div>

                            <dl className="grid gap-2 text-sm md:grid-cols-3">
                                <Metric
                                    label="Amount"
                                    value={formatMoney(
                                        payment.amount,
                                        payment.currency,
                                    )}
                                />
                                <Metric
                                    label="Next retry"
                                    value={formatDate(
                                        payment.automatic_next_retry_at,
                                    )}
                                />
                                <Metric
                                    label="Retry"
                                    value={
                                        payment.manual_retry_available
                                            ? 'available'
                                            : 'unavailable'
                                    }
                                />
                            </dl>

                            {payment.failed_reason && (
                                <div className="rounded-md border bg-muted/20 p-3 text-sm text-muted-foreground">
                                    {payment.failed_reason}
                                </div>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function ProposalsPanel({ client }: { client: ClientDetail }) {
    const form = useForm<ProposalForm>({
        fee_calculation_id: client.fee_calculations[0]?.id ?? '',
        scope_summary: '',
        insurance_consent: 'undecided',
        coach_consent: 'undecided',
    });

    const submit = () => {
        form.post(client.proposal_store_url, {
            preserveScroll: true,
            onSuccess: () => form.reset('scope_summary'),
        });
    };

    const release = (proposal: ProposalSummary) => {
        router.patch(
            proposal.release_url,
            { expiry_days: client.proposal_expiry_days },
            { preserveScroll: true },
        );
    };

    const recall = (proposal: ProposalSummary) => {
        router.patch(proposal.recall_url, {}, { preserveScroll: true });
    };

    const renew = (proposal: ProposalSummary) => {
        router.patch(proposal.renew_url, {}, { preserveScroll: true });
    };

    return (
        <section
            id="section-proposals"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <FileText className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Proposals</h2>
                </div>
                <Badge variant="outline">{client.proposals.length}</Badge>
            </div>

            {client.fee_calculations.length > 0 && (
                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(220px,0.45fr)]">
                    <div className="grid gap-2">
                        <Label htmlFor="proposal_scope">Scope</Label>
                        <textarea
                            id="proposal_scope"
                            value={form.data.scope_summary}
                            onChange={(event) =>
                                form.setData(
                                    'scope_summary',
                                    event.target.value,
                                )
                            }
                            rows={3}
                            className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <InputError message={form.errors.scope_summary} />
                    </div>

                    <div className="grid gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="proposal_fee">Fee</Label>
                            <select
                                id="proposal_fee"
                                value={form.data.fee_calculation_id}
                                onChange={(event) =>
                                    form.setData(
                                        'fee_calculation_id',
                                        event.target.value,
                                    )
                                }
                                className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                {client.fee_calculations.map((calculation) => (
                                    <option
                                        key={calculation.id}
                                        value={calculation.id}
                                    >
                                        {formatLabel(calculation.method)} -{' '}
                                        {formatCurrency(
                                            calculation.suggested_mid,
                                        )}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={form.errors.fee_calculation_id}
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <ConsentSelect
                                id="insurance_consent"
                                label="Insurance"
                                value={form.data.insurance_consent}
                                error={form.errors.insurance_consent}
                                onChange={(value) =>
                                    form.setData('insurance_consent', value)
                                }
                            />
                            <ConsentSelect
                                id="coach_consent"
                                label="Coach"
                                value={form.data.coach_consent}
                                error={form.errors.coach_consent}
                                onChange={(value) =>
                                    form.setData('coach_consent', value)
                                }
                            />
                        </div>

                        <Button
                            type="button"
                            disabled={
                                form.processing ||
                                form.data.fee_calculation_id === ''
                            }
                            onClick={submit}
                        >
                            <FileText className="size-4" aria-hidden="true" />
                            Generate
                        </Button>
                    </div>
                </div>
            )}

            {client.proposals.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No proposals yet.
                </p>
            ) : (
                <div className="space-y-3">
                    {client.proposals.map((proposal) => (
                        <article
                            key={proposal.id}
                            className="space-y-3 rounded-md border p-3"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="space-y-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="text-sm font-medium">
                                            Proposal v{proposal.version}
                                        </h3>
                                        <Badge
                                            variant={proposalStatusVariant(
                                                proposal.status,
                                            )}
                                        >
                                            {proposal.status_label}
                                        </Badge>
                                        {proposal.days_to_expiry !== null && (
                                            <Badge variant="outline">
                                                {proposal.days_to_expiry}d
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {formatCurrency(
                                            proposal.suggested_mid ?? 0,
                                        )}{' '}
                                        mid fee
                                    </div>
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={!proposal.can_release}
                                        onClick={() => release(proposal)}
                                    >
                                        <Send
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Release
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={!proposal.can_recall}
                                        onClick={() => recall(proposal)}
                                    >
                                        <Undo2
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Recall
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={!proposal.can_renew}
                                        onClick={() => renew(proposal)}
                                    >
                                        <RotateCcw
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Renew
                                    </Button>
                                </div>
                            </div>

                            <dl className="grid gap-2 text-sm md:grid-cols-3">
                                <Metric
                                    label="Released"
                                    value={formatDate(proposal.released_at)}
                                />
                                <Metric
                                    label="Expires"
                                    value={formatDate(proposal.expires_at)}
                                />
                                <Metric
                                    label="ROI"
                                    value={formatMetric(proposal.roi_ratio)}
                                />
                            </dl>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function StandardAdvisoryPanel({
    summary,
    onRunAnalysis,
    onGeneratePack,
    generatingPack,
}: {
    summary: StandardAdvisorySummary;
    onRunAnalysis: () => void;
    onGeneratePack: () => void;
    generatingPack: boolean;
}) {
    const clientReport = summary.reports.client;
    const releaseClientReport = () => {
        if (!clientReport || clientReport.review_status !== 'pending_review') {
            return;
        }

        router.patch(clientReport.review_url, {}, { preserveScroll: true });
    };

    return (
        <section
            id="section-standard-advisory"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <ListChecks className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Standard Advisory workflow
                    </h2>
                    <Badge
                        variant={standardAdvisoryStatusVariant(summary.status)}
                    >
                        {summary.status_label}
                    </Badge>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={!summary.can_run_analysis}
                                    onClick={onRunAnalysis}
                                >
                                    <RotateCcw
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Run analysis
                                </Button>
                            </span>
                        </TooltipTrigger>
                        <TooltipContent side="bottom" className="max-w-xs">
                            Runs the Standard Advisory analysis modules and
                            refreshes the business health radar.
                        </TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span>
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={
                                        !summary.can_generate_pack ||
                                        generatingPack
                                    }
                                    onClick={onGeneratePack}
                                >
                                    <FileText
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {generatingPack
                                        ? 'Generating...'
                                        : 'Generate pack'}
                                </Button>
                            </span>
                        </TooltipTrigger>
                        <TooltipContent side="bottom" className="max-w-xs">
                            Creates advisor, client, stakeholder, and trajectory
                            reports from the latest analysis.
                        </TooltipContent>
                    </Tooltip>
                    {clientReport?.review_status === 'pending_review' && (
                        <>
                            {clientReport.download_url && (
                                <Button asChild size="sm" variant="outline">
                                    <a
                                        href={clientReport.download_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <FileText
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Review client report
                                    </a>
                                </Button>
                            )}
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={releaseClientReport}
                            >
                                <CheckCircle2
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Release client report
                            </Button>
                        </>
                    )}
                </div>
            </div>

            <p className="text-sm text-muted-foreground">
                {summary.next_action}
            </p>

            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <Metric
                    label="Questionnaire"
                    value={
                        summary.questionnaire_submitted
                            ? `Submitted ${formatDate(summary.questionnaire_submitted_at)}`
                            : 'Not submitted'
                    }
                />
                <Metric
                    label="Evidence"
                    value={`${summary.document_count} uploaded / ${summary.verified_document_count} verified`}
                />
                <Metric
                    label="Analysis"
                    value={`${summary.analysis_completed}/${summary.analysis_total} modules complete`}
                />
                <Metric
                    label="Client report"
                    value={
                        clientReport
                            ? formatLabel(clientReport.review_status)
                            : 'Not generated'
                    }
                />
            </div>

            {summary.missing.length > 0 ? (
                <div className="rounded-md border bg-muted/30 p-3">
                    <div className="text-sm font-medium">Readiness gaps</div>
                    <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                        {summary.missing.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ul>
                </div>
            ) : (
                <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                    Standard Advisory workflow is ready for the client
                    conversation.
                </div>
            )}

            <div className="grid gap-4 lg:grid-cols-2">
                <div className="rounded-md border p-3">
                    <div className="text-sm font-medium">Analysis modules</div>
                    <div className="mt-3 grid gap-2">
                        {summary.analysis_modules.map((module) => (
                            <div
                                key={module.module}
                                className="flex items-center justify-between gap-3 text-sm"
                            >
                                <span>{module.label}</span>
                                <Badge
                                    variant={
                                        module.completed
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    {module.completed
                                        ? 'Completed'
                                        : formatLabel(module.status)}
                                </Badge>
                            </div>
                        ))}
                    </div>
                </div>
                <div className="rounded-md border p-3">
                    <div className="text-sm font-medium">Report pack</div>
                    <div className="mt-3 grid gap-2">
                        {Object.entries(summary.reports).map(
                            ([key, report]) => (
                                <div
                                    key={key}
                                    className="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span>{formatLabel(key)}</span>
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline">
                                            {report
                                                ? formatLabel(
                                                      report.review_status,
                                                  )
                                                : 'Not generated'}
                                        </Badge>
                                        {report?.download_url && (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="ghost"
                                                className="h-7 px-2"
                                            >
                                                <a
                                                    href={report.download_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    aria-label={`View ${formatLabel(key)} report PDF`}
                                                >
                                                    <FileText
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    View
                                                </a>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ),
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}

function ReportsPanel({ client }: { client: ClientDetail }) {
    const generate = (
        type:
            | 'client'
            | 'advisor'
            | 'stakeholder'
            | 'trajectory'
            | 'due_diligence'
            | 'post_acquisition_gap_report'
            | 'governance_review_report'
            | 'npo_health_report'
            | 'npo_advisor_report'
            | 'social_enterprise_dual_report',
    ) => {
        router.post(
            client.report_store_url,
            { type },
            { preserveScroll: true },
        );
    };

    const review = (report: ReportSummary) => {
        router.patch(report.review_url, {}, { preserveScroll: true });
    };

    return (
        <section
            id="section-reports"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <FileText className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Reports</h2>
                </div>
                <Badge variant="outline">{client.reports.length}</Badge>
            </div>

            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => generate('client')}
                >
                    <FileText className="size-4" aria-hidden="true" />
                    Client
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => generate('advisor')}
                >
                    <FileText className="size-4" aria-hidden="true" />
                    Advisor
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => generate('stakeholder')}
                >
                    <FileText className="size-4" aria-hidden="true" />
                    Stakeholder
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => generate('trajectory')}
                >
                    <TrendingUp className="size-4" aria-hidden="true" />
                    Trajectory
                </Button>
                {client.due_diligence && (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => generate('due_diligence')}
                    >
                        <FileText className="size-4" aria-hidden="true" />
                        Due Diligence
                    </Button>
                )}
                {client.engagement_type === 'post_acquisition_advisory' && (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => generate('post_acquisition_gap_report')}
                    >
                        <FileText className="size-4" aria-hidden="true" />
                        Gap Report
                    </Button>
                )}
                {client.is_npo && (
                    <>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => generate('governance_review_report')}
                        >
                            <FileCheck2 className="size-4" aria-hidden="true" />
                            Governance
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => generate('npo_health_report')}
                        >
                            <HeartPulse className="size-4" aria-hidden="true" />
                            NPO Health
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => generate('npo_advisor_report')}
                        >
                            <LockKeyhole
                                className="size-4"
                                aria-hidden="true"
                            />
                            NPO Advisor
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                generate('social_enterprise_dual_report')
                            }
                        >
                            <Star className="size-4" aria-hidden="true" />
                            Dual Impact
                        </Button>
                    </>
                )}
            </div>

            {client.reports.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No reports generated yet.
                </p>
            ) : (
                <div className="space-y-3">
                    {client.reports.map((report) => (
                        <article
                            key={report.id}
                            className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-3"
                        >
                            <div className="space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h3 className="text-sm font-medium">
                                        {report.type_label}
                                    </h3>
                                    <Badge variant="outline">
                                        {formatDate(report.generated_at)}
                                    </Badge>
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {report.title}
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                <span>
                                    PDF {formatBytes(report.pdf_byte_size)}
                                    {report.pptx_byte_size
                                        ? ` / PPTX ${formatBytes(report.pptx_byte_size)}`
                                        : ''}
                                </span>
                                {report.download_url && (
                                    <Button asChild size="sm" variant="outline">
                                        <a
                                            href={report.download_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <FileText
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            View PDF
                                        </a>
                                    </Button>
                                )}
                                {report.pptx_url && (
                                    <Button asChild size="sm" variant="outline">
                                        <a href={report.pptx_url}>
                                            <Download
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            PPTX
                                        </a>
                                    </Button>
                                )}
                                {report.review_status === 'pending_review' && (
                                    <Badge variant="secondary">Review</Badge>
                                )}
                                {(report.revision_count > 0 ||
                                    report.comment_count > 0) && (
                                    <Badge variant="outline">
                                        {report.revision_count} edits /{' '}
                                        {report.comment_count} comments
                                    </Badge>
                                )}
                                {report.can_review && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => review(report)}
                                    >
                                        <CheckCircle2
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {report.type === 'client'
                                            ? 'Release to client'
                                            : 'Mark reviewed'}
                                    </Button>
                                )}
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function MeetingsBriefingsPanel({ client }: { client: ClientDetail }) {
    const form = useForm<MeetingForm>({
        title: '',
        scheduled_at: '',
        location: '',
        link: '',
        attendees: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(client.meeting_store_url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const review = (url: string) => {
        router.patch(url, {}, { preserveScroll: true });
    };

    return (
        <section
            id="section-meetings"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <CalendarClock className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Meetings and briefs</h2>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge variant="outline">
                        {client.meetings.length} meetings
                    </Badge>
                    <Badge variant="secondary">
                        {client.pre_meeting_briefs.length} briefs
                    </Badge>
                </div>
            </div>

            <form onSubmit={submit} className="grid gap-3 lg:grid-cols-4">
                <div className="grid gap-2">
                    <Label htmlFor="meeting_title">Title</Label>
                    <input
                        id="meeting_title"
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.title} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="meeting_scheduled_at">Scheduled</Label>
                    <input
                        id="meeting_scheduled_at"
                        type="datetime-local"
                        value={form.data.scheduled_at}
                        onChange={(event) =>
                            form.setData('scheduled_at', event.target.value)
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.scheduled_at} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="meeting_location">Location</Label>
                    <input
                        id="meeting_location"
                        value={form.data.location}
                        onChange={(event) =>
                            form.setData('location', event.target.value)
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.location} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="meeting_attendees">Attendees</Label>
                    <input
                        id="meeting_attendees"
                        value={form.data.attendees}
                        onChange={(event) =>
                            form.setData('attendees', event.target.value)
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.attendees} />
                </div>
                <div className="grid gap-2 lg:col-span-3">
                    <Label htmlFor="meeting_link">Link</Label>
                    <input
                        id="meeting_link"
                        value={form.data.link}
                        onChange={(event) =>
                            form.setData('link', event.target.value)
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.link} />
                </div>
                <div className="flex items-end">
                    <Button
                        type="submit"
                        size="sm"
                        disabled={form.processing}
                        className="w-full"
                    >
                        <CalendarClock className="size-4" aria-hidden="true" />
                        Add meeting
                    </Button>
                </div>
            </form>

            <div className="grid gap-4 xl:grid-cols-3">
                <BriefList
                    title="Upcoming meetings"
                    empty="No upcoming meetings."
                    items={client.meetings.map((meeting) => ({
                        id: meeting.id,
                        heading: meeting.title,
                        detail: [
                            formatDate(meeting.scheduled_at),
                            meeting.location,
                            meeting.calendar_synced
                                ? 'Calendar synced'
                                : undefined,
                            formatLabel(meeting.brief_status),
                        ]
                            .filter(Boolean)
                            .join(' - '),
                    }))}
                />
                <BriefList
                    title="Industry briefings"
                    empty="No industry briefings yet."
                    items={client.industry_briefings.map((briefing) => ({
                        id: briefing.id,
                        heading: formatMonth(briefing.period),
                        detail: `${formatLabel(briefing.status)} - ${truncate(briefing.body, 130)}`,
                        action: briefing.can_review
                            ? {
                                  label: 'Review and send',
                                  onClick: () => review(briefing.review_url),
                              }
                            : undefined,
                    }))}
                />
                <BriefList
                    title="Pre-meeting briefs"
                    empty="No pre-meeting briefs yet."
                    items={client.pre_meeting_briefs.map((brief) => ({
                        id: brief.id,
                        heading: brief.meeting_title ?? 'Meeting brief',
                        detail: `${formatDate(brief.meeting_at)} - ${brief.red_flag_count} red flags`,
                        action: brief.can_review
                            ? {
                                  label: 'Review and send',
                                  onClick: () => review(brief.review_url),
                              }
                            : undefined,
                    }))}
                />
            </div>
        </section>
    );
}

function BriefList({
    title,
    empty,
    items,
}: {
    title: string;
    empty: string;
    items: Array<{
        id: string;
        heading: string;
        detail: string;
        action?: {
            label: string;
            onClick: () => void;
        };
    }>;
}) {
    return (
        <div className="space-y-3">
            <h3 className="text-xs font-medium text-muted-foreground uppercase">
                {title}
            </h3>
            {items.length === 0 ? (
                <p className="rounded-md border p-3 text-sm text-muted-foreground">
                    {empty}
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {items.map((item) => (
                        <article key={item.id} className="space-y-3 p-3">
                            <div>
                                <div className="text-sm font-medium">
                                    {item.heading}
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {item.detail}
                                </div>
                            </div>
                            {item.action && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={item.action.onClick}
                                >
                                    <Send
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {item.action.label}
                                </Button>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </div>
    );
}

function ConsentSelect({
    id,
    label,
    value,
    error,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="undecided">Undecided</option>
                <option value="opt_in">Opt in</option>
                <option value="opt_out">Opt out</option>
            </select>
            <InputError message={error} />
        </div>
    );
}

function KnowledgeAssessmentPanel({ client }: { client: ClientDetail }) {
    const latest = client.latest_knowledge_assessment;
    const form = useForm<KnowledgeAssessmentForm>({
        financial_literacy: latest?.financial_literacy ?? 3,
        strategic_awareness: latest?.strategic_awareness ?? 3,
        leadership: latest?.leadership ?? 3,
    });

    const submit = () => {
        form.post(client.knowledge_assessment_store_url, {
            preserveScroll: true,
        });
    };

    return (
        <section
            id="section-knowledge"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Brain className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Knowledge assessment
                    </h2>
                </div>
                {latest && (
                    <Badge variant="outline">
                        {formatDate(latest.assessed_at)}
                    </Badge>
                )}
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                <ScoreInput
                    id="financial_literacy"
                    label="Financial literacy"
                    value={form.data.financial_literacy}
                    error={form.errors.financial_literacy}
                    onChange={(value) =>
                        form.setData('financial_literacy', value)
                    }
                />
                <ScoreInput
                    id="strategic_awareness"
                    label="Strategic awareness"
                    value={form.data.strategic_awareness}
                    error={form.errors.strategic_awareness}
                    onChange={(value) =>
                        form.setData('strategic_awareness', value)
                    }
                />
                <ScoreInput
                    id="leadership"
                    label="Leadership"
                    value={form.data.leadership}
                    error={form.errors.leadership}
                    onChange={(value) => form.setData('leadership', value)}
                />
            </div>

            {latest && (
                <div className="flex flex-wrap gap-2">
                    <Badge variant="secondary">
                        {formatLabel(
                            String(
                                latest.calibration.language_depth ?? 'standard',
                            ),
                        )}
                    </Badge>
                    <Badge variant="outline">
                        {formatLabel(
                            String(
                                latest.calibration.financial_detail ??
                                    'balanced',
                            ),
                        )}
                    </Badge>
                    <Badge variant="outline">
                        {formatLabel(
                            String(
                                latest.calibration.leadership_context ??
                                    'standard',
                            ),
                        )}
                    </Badge>
                </div>
            )}

            <div className="flex justify-end">
                <Button
                    type="button"
                    variant="outline"
                    disabled={form.processing}
                    onClick={submit}
                >
                    Save assessment
                </Button>
            </div>
        </section>
    );
}

function ScoreInput({
    id,
    label,
    value,
    error,
    onChange,
}: {
    id: string;
    label: string;
    value: number;
    error?: string;
    onChange: (value: number) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <input
                id={id}
                type="number"
                min={1}
                max={5}
                value={value}
                onChange={(event) => onChange(Number(event.target.value))}
                className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <InputError message={error} />
        </div>
    );
}

function FindingFeedbackCard({
    finding,
}: {
    finding: AnalysisFindingFeedback;
}) {
    const feedbackForm = useForm<FeedbackPayload>({
        decision: 'confirm',
        rating: null,
        corrected_body: '',
        note: '',
    });

    const submitFeedback = (payload: FeedbackPayload) => {
        feedbackForm.transform(() => payload);
        feedbackForm.post(finding.feedback_store_url, {
            preserveScroll: true,
            onSuccess: () => {
                feedbackForm.reset();
                feedbackForm.setData({
                    decision: 'confirm',
                    rating: null,
                    corrected_body: '',
                    note: '',
                });
            },
            onFinish: () => feedbackForm.transform((data) => data),
        });
    };

    return (
        <article id={finding.id} className="space-y-4 rounded-md border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-2">
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="secondary">
                            {formatLabel(finding.module ?? 'analysis')}
                        </Badge>
                        <Badge variant="outline">
                            {formatLabel(finding.lens)}
                        </Badge>
                        <Badge variant={severityVariant(finding.severity)}>
                            {formatLabel(finding.severity)}
                        </Badge>
                    </div>
                    <h3 className="text-sm font-medium">{finding.title}</h3>
                </div>
                <div className="text-xs text-muted-foreground">
                    {formatDate(finding.created_at)}
                </div>
            </div>

            <p className="text-sm leading-6 text-muted-foreground">
                {finding.body}
            </p>

            <div className="flex flex-wrap gap-2">
                <Badge variant="outline">
                    {formatLabel(finding.document_support)}
                </Badge>
                {finding.uncertainty && (
                    <Badge variant="outline">
                        {formatLabel(finding.uncertainty)} uncertainty
                    </Badge>
                )}
                {finding.attributions.slice(0, 3).map((attribution, index) => (
                    <Badge key={index} variant="outline">
                        {attribution.source_reference ?? 'source'}
                    </Badge>
                ))}
            </div>

            {finding.data_quality_disclaimer && (
                <p className="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
                    {finding.data_quality_disclaimer}
                </p>
            )}

            {finding.latest_feedback.length > 0 && (
                <div className="space-y-2 text-xs text-muted-foreground">
                    {finding.latest_feedback.map((feedback) => (
                        <div
                            key={feedback.id}
                            className="flex flex-wrap items-center gap-2"
                        >
                            <Badge variant="outline">
                                {formatLabel(feedback.decision)}
                            </Badge>
                            {feedback.rating && (
                                <span>{feedback.rating}/5</span>
                            )}
                            {feedback.has_correction && <span>corrected</span>}
                            {feedback.note && <span>{feedback.note}</span>}
                            <span>{feedback.advisor_name ?? 'Advisor'}</span>
                        </div>
                    ))}
                </div>
            )}

            <div className="grid gap-4 lg:grid-cols-2">
                <div className="space-y-3">
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            disabled={feedbackForm.processing}
                            onClick={() =>
                                submitFeedback({
                                    decision: 'confirm',
                                    rating: null,
                                    corrected_body: null,
                                    note: null,
                                })
                            }
                        >
                            <CheckCircle2
                                className="size-4"
                                aria-hidden="true"
                            />
                            Confirm
                        </Button>
                        {[1, 2, 3, 4, 5].map((rating) => (
                            <Button
                                key={rating}
                                type="button"
                                size="icon"
                                variant={
                                    feedbackForm.data.rating === rating
                                        ? 'secondary'
                                        : 'outline'
                                }
                                disabled={feedbackForm.processing}
                                onClick={() =>
                                    submitFeedback({
                                        decision: 'rate',
                                        rating,
                                        corrected_body: null,
                                        note: null,
                                    })
                                }
                                aria-label={`Rate ${rating}`}
                            >
                                <Star className="size-4" aria-hidden="true" />
                            </Button>
                        ))}
                    </div>
                    <InputError message={feedbackForm.errors.rating} />
                </div>

                <div className="grid gap-3">
                    <Label htmlFor={`correction_${finding.id}`}>
                        Correction
                    </Label>
                    <textarea
                        id={`correction_${finding.id}`}
                        value={feedbackForm.data.corrected_body ?? ''}
                        onChange={(event) =>
                            feedbackForm.setData(
                                'corrected_body',
                                event.target.value,
                            )
                        }
                        rows={3}
                        className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={feedbackForm.processing}
                            onClick={() =>
                                submitFeedback({
                                    decision: 'correct',
                                    rating: null,
                                    corrected_body:
                                        feedbackForm.data.corrected_body,
                                    note: null,
                                })
                            }
                        >
                            <PencilLine className="size-4" aria-hidden="true" />
                            Save correction
                        </Button>
                    </div>
                    <InputError message={feedbackForm.errors.corrected_body} />
                </div>

                <div className="grid gap-3 lg:col-span-2">
                    <Label htmlFor={`context_${finding.id}`}>Context</Label>
                    <textarea
                        id={`context_${finding.id}`}
                        value={feedbackForm.data.note ?? ''}
                        onChange={(event) =>
                            feedbackForm.setData('note', event.target.value)
                        }
                        rows={2}
                        className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={feedbackForm.processing}
                            onClick={() =>
                                submitFeedback({
                                    decision: 'add_context',
                                    rating: null,
                                    corrected_body: null,
                                    note: feedbackForm.data.note,
                                })
                            }
                        >
                            <MessageSquare
                                className="size-4"
                                aria-hidden="true"
                            />
                            Add context
                        </Button>
                    </div>
                    <InputError message={feedbackForm.errors.note} />
                </div>
            </div>
        </article>
    );
}

function ClientDetailSection({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
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

function ClientDetailTabList({
    activeTab,
    onChange,
}: {
    activeTab: ClientDetailTab;
    onChange: (tab: ClientDetailTab) => void;
}) {
    return (
        <div
            className="inline-flex w-full max-w-md rounded-md border bg-muted/30 p-1"
            role="tablist"
            aria-label="Client detail sections"
        >
            <ClientDetailTabButton
                active={activeTab === 'actions'}
                onClick={() => onChange('actions')}
            >
                Actions
            </ClientDetailTabButton>
            <ClientDetailTabButton
                active={activeTab === 'information'}
                onClick={() => onChange('information')}
            >
                Information
            </ClientDetailTabButton>
        </div>
    );
}

function ClientDetailTabButton({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            className={cn(
                'flex-1 rounded-sm px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                active && 'bg-background text-foreground shadow-xs',
            )}
            onClick={onClick}
        >
            {children}
        </button>
    );
}

function ActionTile({
    icon: Icon,
    title,
    value,
    explanation,
    href,
    actionLabel,
    onAction,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    title: string;
    value: ReactNode;
    explanation: string;
    href: string;
    actionLabel: string;
    onAction?: (event: MouseEvent<Element>) => void;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <section className="rounded-md border bg-background p-4">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Icon className="size-4" aria-hidden={true} />
                        {title}
                    </div>
                    <div className="mt-2 text-sm font-medium">{value}</div>
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="mt-3 px-0"
                    >
                        <Link href={href} onClick={onAction}>
                            {actionLabel}
                        </Link>
                    </Button>
                </section>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {explanation}
            </TooltipContent>
        </Tooltip>
    );
}

function initialClientDetailTab(): ClientDetailTab {
    if (typeof window === 'undefined') {
        return 'actions';
    }

    return clientSectionTabs[window.location.hash.slice(1)] ?? 'actions';
}

function Metric({
    label,
    value,
    children,
}: {
    label: string;
    value?: string;
    children?: ReactNode;
}) {
    return (
        <div className="rounded-md border p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-2 text-sm font-medium">{children ?? value}</div>
        </div>
    );
}

function Detail({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="grid grid-cols-[120px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value || '-'}</dd>
        </div>
    );
}

function WellbeingTrend({ points }: { points: WellbeingPoint[] }) {
    if (points.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No wellbeing check-ins yet.
            </p>
        );
    }

    return (
        <div className="space-y-3">
            {points.map((point) => (
                <article key={point.id} className="grid gap-2 text-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="font-medium">
                            {formatMonth(point.period_start)}
                        </div>
                        <div className="text-muted-foreground">
                            {point.submitted_by ?? 'Client'}
                        </div>
                    </div>
                    <ScoreBar
                        label="Business confidence"
                        value={point.business_confidence}
                    />
                    <ScoreBar
                        label="Personal coping"
                        value={point.personal_coping}
                    />
                    {point.notes && (
                        <p className="rounded-md bg-muted px-3 py-2 text-muted-foreground">
                            {point.notes}
                        </p>
                    )}
                </article>
            ))}
        </div>
    );
}

function ScoreBar({ label, value }: { label: string; value: number }) {
    const width = `${Math.max(0, Math.min(100, (value / 5) * 100))}%`;

    return (
        <div className="grid gap-1">
            <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span>{label}</span>
                <span>{value}/5</span>
            </div>
            <div className="h-2 rounded-full bg-muted">
                <div
                    className="h-2 rounded-full bg-[var(--fs-admiralty)]"
                    style={{ width }}
                />
            </div>
        </div>
    );
}

function formatMonth(value: string | null) {
    if (!value) {
        return 'Current period';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

function formatDate(value: string | null) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
    }).format(new Date(value));
}

function formatLabel(value: string) {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function standardAdvisoryStatusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'client_report_released') {
        return 'secondary';
    }

    if (status === 'verification_blocked') {
        return 'destructive';
    }

    return 'outline';
}

function stringDetail(value: string | number | boolean | null | undefined) {
    return value === null || value === undefined ? null : String(value);
}

function formatMetric(value: unknown) {
    if (typeof value !== 'number') {
        return String(value ?? '-');
    }

    if (Math.abs(value) <= 1) {
        return new Intl.NumberFormat(undefined, {
            style: 'percent',
            maximumFractionDigits: 1,
        }).format(value);
    }

    return new Intl.NumberFormat(undefined, {
        maximumFractionDigits: 2,
    }).format(value);
}

function formatCurrency(value: number) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'NZD',
        maximumFractionDigits: 0,
    }).format(value);
}

function formatMoney(value: number, currency: string) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatBytes(value: number | null) {
    if (!value) {
        return '-';
    }

    if (value < 1024) {
        return `${value} B`;
    }

    return `${(value / 1024).toFixed(1)} KB`;
}

function nullableNumber(value: string): number | null {
    if (value.trim() === '') {
        return null;
    }

    return Number(value);
}

function truncate(value: string, limit: number) {
    if (value.length <= limit) {
        return value;
    }

    return `${value.slice(0, Math.max(0, limit - 1))}...`;
}

function statusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'suspended') {
        return 'destructive';
    }

    if (status === 'active') {
        return 'secondary';
    }

    return 'outline';
}

function goalStatusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'active' || status === 'achieved') {
        return 'secondary';
    }

    if (status === 'abandoned') {
        return 'destructive';
    }

    return 'outline';
}

function milestoneStatusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'completed') {
        return 'secondary';
    }

    if (status === 'blocked') {
        return 'destructive';
    }

    return 'outline';
}

function proofStatusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'verified') {
        return 'secondary';
    }

    if (status === 'flagged') {
        return 'destructive';
    }

    return 'outline';
}

function proposalStatusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'expired') {
        return 'destructive';
    }

    if (status === 'released' || status === 'renewed') {
        return 'secondary';
    }

    return 'outline';
}

function paymentStatusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'failed') {
        return 'destructive';
    }

    if (status === 'retrying' || status === 'succeeded') {
        return 'secondary';
    }

    return 'outline';
}

function severityVariant(
    severity: string,
): 'secondary' | 'destructive' | 'outline' {
    if (severity === 'critical' || severity === 'high') {
        return 'destructive';
    }

    if (severity === 'medium') {
        return 'secondary';
    }

    return 'outline';
}

function lifecycleActions(status: string) {
    if (status === 'active') {
        return [
            { status: 'paused', label: 'Pause' },
            { status: 'suspended', label: 'Suspend' },
            { status: 'offboarded', label: 'Mark offboarded' },
        ];
    }

    if (status === 'paused') {
        return [
            { status: 'active', label: 'Restore' },
            { status: 'suspended', label: 'Suspend' },
            { status: 'offboarded', label: 'Mark offboarded' },
        ];
    }

    return [{ status: 'active', label: 'Restore' }];
}

function lifecycleIcon(status: string) {
    if (status === 'paused') {
        return PauseCircle;
    }

    if (status === 'suspended') {
        return Ban;
    }

    if (status === 'offboarded') {
        return CheckCircle2;
    }

    return RotateCcw;
}

ClientsShow.layout = {
    breadcrumbs: [
        {
            title: 'Clients',
            href: '/advisor/clients',
        },
    ],
};
