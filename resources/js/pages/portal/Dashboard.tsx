import { Head, Link, useForm } from '@inertiajs/react';
import {
    Activity,
    Bell,
    BriefcaseBusiness,
    CalendarClock,
    ClipboardList,
    CircleDollarSign,
    FileSpreadsheet,
    FileText,
    HeartPulse,
    Lightbulb,
    MessageSquare,
    PieChart,
    Save,
    Target,
    TrendingUp,
    Upload,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import type { ComponentType, MouseEvent, ReactNode } from 'react';
import { DataQualityBadge } from '@/components/data-quality/DataQualityBadge';
import { ClientSupport } from '@/components/screen-share/ClientSupport';
import type { DataQualitySummary } from '@/components/data-quality/DataQualityBadge';
import FileDropzone from '@/components/file-dropzone';
import InputError from '@/components/input-error';
import { BusinessHealthRadar } from '@/components/insight/BusinessHealthRadar';
import type { BusinessHealthRadarPayload } from '@/components/insight/BusinessHealthRadar';
import { InspirationCard } from '@/components/inspiration/InspirationCard';
import type { InspirationPost } from '@/components/inspiration/InspirationCard';
import { NpoHealthPanel } from '@/components/npo/NpoHealthPanel';
import type { NpoHealthPayload } from '@/components/npo/NpoHealthPanel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { VerificationBadge } from '@/components/verification/Badge';
import type { VerificationOutcome } from '@/components/verification/Badge';
import { FlagBanner } from '@/components/verification/FlagBanner';
import { useDrillFocus } from '@/hooks/use-drill-focus';
import { cn } from '@/lib/utils';

type ClientPayload = {
    id: string;
    legal_name: string;
    trading_name: string | null;
    engagement_type: string;
    engagement_type_label: string;
    data_quality: string;
    data_quality_summary: DataQualitySummary;
    nzbn: string | null;
};

type Progress = {
    completed: number;
    total: number;
    percentage: number;
};

type Props = {
    client: ClientPayload;
    screenShare: {
        portal_context_token: string;
        connection_url: string;
        prompt_url: string;
        connection_heartbeat_url: string;
        response_url: string;
        browser_permission_url: string;
        ice_servers_url: string;
        active_url: string;
        signal_url: string;
        pending_signals_url: string;
        heartbeat_url: string;
        end_url: string;
        heartbeat_seconds: number;
        warning_at_minutes: number;
    } | null;
    progress: Progress;
    currentStep: string;
    onboardingUrl: string;
    notificationSummary: {
        unread: number;
        urgent: number;
    };
    wellbeing: {
        prompt_due: boolean;
        period_start: string;
        submitted_at: string | null;
        url: string;
    };
    businessHealth: BusinessHealthRadarPayload;
    healthFindings: HealthFindingDimension[];
    npoHealth: NpoHealthPayload | null;
    npoPortal: NpoPortalPayload | null;
    ddPlan: DdPlanPayload | null;
    postAcquisition: PostAcquisitionPayload | null;
    serviceActivations: ServiceActivationsPayload;
    strategicBudget: StrategicBudgetPayload;
    strategicPlan: StrategicPlanPayload | null;
    standardAdvisory: StandardAdvisoryPortalPayload | null;
    goals: GoalDashboard;
    documents: DocumentPayload[];
    documentUploadUrl: string;
    npoImpactMetricStoreUrl: string | null;
    scenarios: ScenarioPayload[];
    proposals: ProposalPayload[];
    reports: ReportPayload[];
    messageSummary: {
        threads_count: number;
        unread_count: number;
        latest_url: string;
    };
    messagesUrl: string;
    surveys: PendingSurveysPayload;
    outcomeFollowUps: PendingOutcomeFollowUpsPayload;
    welcomeMessage: WelcomeMessage;
    inspirationBoard: InspirationPost | null;
};

type WelcomeMessage = {
    has_message: boolean;
    html: string;
    version: number | null;
};

type DocumentPayload = {
    id: string;
    original_filename: string;
    category: string;
    uploaded_at: string | null;
    url: string;
    verification_state: VerificationOutcome;
    client_explanation: string;
    verifications: Array<{
        id: string;
        outcome: VerificationOutcome;
        claim_text: string;
        client_explanation: string;
        resolved_at: string | null;
    }>;
};

type NpoPortalPayload = {
    engagement_id: string;
    sub_type: string | null;
    legal_structure: string | null;
    funding: NpoFundingPayload;
    milestone_progress: {
        completed: number;
        total: number;
        percentage: number;
        cost_per_beneficiary: NpoCostPerBeneficiary | null;
    };
    accountability_reports_due: NpoFundingRecord[];
    impact_metrics: NpoImpactMetricPayload[];
    questionnaire_completion: {
        completed: boolean;
        submitted_at: string | null;
        answered_questions: number;
    };
};

type DdPlanPayload = {
    url: string;
    generated: boolean;
    status: string | null;
    plan_completed: boolean;
    business_advice_requested: boolean;
    updated_at: string | null;
    target_name: string;
    data_room_item_count: number;
    workstream_options: Array<{
        value: string;
        label: string;
    }>;
};

type PostAcquisitionPayload = {
    source_client_id: string;
    advisory_client_id: string;
    source_target_name: string | null;
    dd_pv_baseline: number;
    migrated_at: string | null;
    migrated_document_count: number;
    gap_questionnaire_url: string;
    gap_questionnaire: {
        submitted: boolean;
        submitted_at: string | null;
        answered_questions: number;
        total_questions: number;
        remaining_questions: number;
    };
    proposal: {
        id: string;
        status: string | null;
        status_label: string;
        suggested_mid: number | null;
        client_visible: boolean;
        signoff_url: string | null;
    } | null;
    dd_report: {
        id: string;
        title: string;
        generated_at: string | null;
    } | null;
    integration_actions: Array<{
        id: string;
        day: number;
        phase: string;
        action: string;
        owner: string | null;
        priority: string;
        status: string;
    }>;
};

type ServiceActivationsPayload = {
    request_url: string;
    options: Array<{
        service_type: 'due_diligence' | 'entrepreneur';
        label: string;
        description: string;
        available: boolean;
        start_url: string;
    }>;
    items: Array<{
        id: string;
        service_type: 'due_diligence' | 'entrepreneur';
        client_label: string;
        status: string;
        status_label: string;
        package_label: string | null;
        fixed_fee: number | null;
        currency: string;
        created_at: string | null;
        url: string;
        workspace_url: string | null;
    }>;
};

type StandardAdvisoryPortalPayload = {
    status: string;
    status_label: string;
    next_action: string;
    missing: string[];
    questionnaire_submitted: boolean;
    document_count: number;
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
    client_report: {
        id: string;
        title: string;
        type: string;
        type_label: string;
        generated_at: string | null;
        review_status: string;
        reviewed_at: string | null;
        view_url: string | null;
        download_url: string | null;
    } | null;
    latest_report_generated_at: string | null;
};

type StrategicBudgetPayload = {
    label: string;
    status: string;
    status_label: string;
    locked: boolean;
    readiness_score: number;
    progress_score: number;
    source_financials: {
        count?: number;
        system_review?: string;
    };
};

type StrategicPlanPayload = {
    id: string;
    title: string;
    status: string;
    status_label: string;
    summary: string | null;
    generated_at: string | null;
    deployed_at: string | null;
    progress_percent: number;
    completed_milestones: number;
    total_milestones: number;
    milestones: StrategicPlanMilestonePayload[];
};

type StrategicPlanMilestonePayload = {
    id: string;
    title: string;
    description: string | null;
    owner: 'client' | 'advisor' | 'joint';
    owner_label: string;
    due_date: string | null;
    status: 'pending' | 'in_progress' | 'completed' | 'blocked';
    status_label: string;
    progress_percent: number;
    evidence_notes: string | null;
    update_url: string;
};

type NpoFundingPayload = {
    summary: {
        active_records: number;
        active_amount: number;
        due_60_count: number;
        expiry_alerts_count: number;
    };
    records: NpoFundingRecord[];
    alerts: Array<{
        id: string;
        funder_name: string | null;
        type: string;
        severity: string;
        message: string;
        due_on: string | null;
    }>;
    concentration: {
        total_active_amount: number;
        largest_funder_amount: number;
        largest_funder_ratio: number;
        largest_funder_name: string | null;
        risk_level: string;
    };
    deadlines_60: NpoFundingRecord[];
};

type NpoFundingRecord = {
    id: string;
    funder_name: string | null;
    grant_name: string | null;
    grant_amount: number;
    currency?: string;
    reporting_deadline: string | null;
    grant_expiry_at?: string | null;
};

type NpoCostPerBeneficiary = {
    id: string;
    cost_per_beneficiary: number | null;
    benchmark_cost_per_beneficiary: number | null;
    additional_beneficiaries_mid: number | null;
    benchmark_note: string | null;
    rating: string;
    calculated_at: string | null;
};

type NpoImpactMetricPayload = {
    id: string;
    metric_key: string;
    metric_label: string;
    value: number;
    unit: string | null;
    platform_value: number | null;
    period_start: string | null;
    period_end: string | null;
    source: string;
    notes: string | null;
    recorded_at: string | null;
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
    target_date: string | null;
    target_growth_percent: number | null;
    status: string;
    achieved_at: string | null;
    measurement: GoalMeasurement;
    milestones: MilestoneSummary[];
};

type GoalMeasurement = {
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
};

type ScenarioPayload = {
    id: string;
    name: string;
    kind: string;
    pv_impact: number;
    position: number;
    economic_overlay: {
        applied_growth_rate: number | null;
        discount_method: string | null;
        indicators: Record<
            string,
            {
                value: number;
                unit: string;
                label: string;
            }
        >;
    };
};

type ProposalPayload = {
    id: string;
    version: number;
    status: string;
    status_label: string;
    suggested_mid: number | null;
    brief: string;
    signed_at: string | null;
    signoff_url: string;
};

type ReportPayload = {
    id: string;
    title: string;
    type: string;
    generated_at: string | null;
    view_url: string;
    download_url: string;
};

type PendingSurveysPayload = {
    total_open: number;
    index_url: string;
    items: PendingSurvey[];
};

type PendingSurvey = {
    id: string;
    survey_title: string;
    status: string;
    due_at: string | null;
    url: string;
};

type PendingOutcomeFollowUpsPayload = {
    total_open: number;
    items: PendingOutcomeFollowUp[];
};

type PendingOutcomeFollowUp = {
    id: string;
    subject_type: string;
    subject_label: string;
    subject_name: string;
    cadence_month: number;
    due_at: string | null;
    url: string;
};

type HealthFindingDimension = {
    dimension: string;
    label: string;
    anchor: string;
    state: string;
    message: string;
    findings: HealthFinding[];
};

type HealthFinding = {
    id: string;
    module: string | null;
    lens: string;
    severity: string;
    title: string;
    body: string;
    attributions: Array<{
        claim?: string;
        source_reference?: string;
        [key: string]: unknown;
    }>;
    created_at: string | null;
};

type PortalDashboardTab = 'actions' | 'information';

function WelcomeBanner({ welcomeMessage }: { welcomeMessage: WelcomeMessage }) {
    const storageKey = `fs-welcome-dismissed-v${welcomeMessage.version ?? 0}`;
    const [dismissed, setDismissed] = useState<boolean>(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        try {
            return window.localStorage.getItem(storageKey) === '1';
        } catch {
            return false;
        }
    });

    if (dismissed) {
        return null;
    }

    const dismiss = () => {
        setDismissed(true);

        try {
            window.localStorage.setItem(storageKey, '1');
        } catch {
            // Ignore storage failures — dismissal is best-effort.
        }
    };

    return (
        <section
            aria-label="Welcome message"
            className="rounded-md border border-[var(--fs-linen)] bg-[var(--fs-linen)]/50 p-5"
        >
            <div
                className="text-sm leading-relaxed text-foreground [&_a]:text-[var(--fs-admiralty)] [&_a]:underline [&_p]:mb-3 [&_p:last-child]:mb-0 [&_strong]:font-semibold"
                dangerouslySetInnerHTML={{ __html: welcomeMessage.html }}
            />
            <div className="mt-4 flex justify-end">
                <Button variant="ghost" size="sm" onClick={dismiss}>
                    Dismiss
                </Button>
            </div>
        </section>
    );
}

export default function PortalDashboard({
    client,
    screenShare,
    progress,
    onboardingUrl,
    notificationSummary,
    wellbeing,
    businessHealth,
    healthFindings,
    npoHealth,
    npoPortal,
    ddPlan,
    postAcquisition,
    serviceActivations,
    strategicBudget,
    strategicPlan,
    standardAdvisory,
    goals,
    documents: initialDocuments,
    documentUploadUrl,
    npoImpactMetricStoreUrl,
    scenarios,
    proposals,
    reports,
    messageSummary,
    surveys,
    outcomeFollowUps,
    welcomeMessage,
    inspirationBoard,
}: Props) {
    useDrillFocus();
    const [documents, setDocuments] =
        useState<DocumentPayload[]>(initialDocuments);
    const [file, setFile] = useState<File | null>(null);
    const [documentCategory, setDocumentCategory] = useState(
        npoPortal
            ? 'npo_board_record'
            : ddPlan
              ? 'dd_artifact'
              : 'client_portal_upload',
    );
    const [documentWorkstream, setDocumentWorkstream] = useState(
        ddPlan?.workstream_options[0]?.value ?? 'financial',
    );
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [uploadKey, setUploadKey] = useState(0);
    const [activeTab, setActiveTab] = useState<PortalDashboardTab>(() =>
        initialPortalDashboardTab(),
    );

    const uploadDocument = async () => {
        if (!file) {
            return;
        }

        setUploading(true);
        setUploadError(null);

        const formData = new FormData();
        formData.append('file', file);
        formData.append('category', documentCategory);

        if (ddPlan) {
            formData.append('workstream', documentWorkstream);
        }

        formData.append(
            'claim_value',
            'Document uploaded from the client dashboard.',
        );
        formData.append('question_prompt', 'Client dashboard document upload');

        const response = await fetch(documentUploadUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: formData,
        });

        setUploading(false);

        if (!response.ok) {
            const payload = (await response.json().catch(() => null)) as {
                message?: string;
            } | null;
            setUploadError(payload?.message ?? 'Upload failed.');

            return;
        }

        const payload = (await response.json()) as {
            document?: DocumentPayload;
        };

        if (!payload.document) {
            setUploadError('Upload response was missing document details.');

            return;
        }

        setDocuments((current) =>
            [
                payload.document as DocumentPayload,
                ...current.filter(
                    (document) => document.id !== payload.document?.id,
                ),
            ].slice(0, 12),
        );
        setFile(null);
        setUploadKey((key) => key + 1);
    };
    const unsignedProposalCount = proposals.filter(
        (proposal) => proposal.status !== 'signed',
    ).length;
    const actionProposal =
        proposals.find((proposal) => proposal.status !== 'signed') ??
        proposals[0] ??
        null;
    const documentReviewCount = documents.filter(isDocumentFlagged).length;
    const postAcquisitionProposalUrl =
        postAcquisition?.proposal?.client_visible &&
        postAcquisition.proposal.signoff_url
            ? postAcquisition.proposal.signoff_url
            : null;
    const postAcquisitionActionUrl = postAcquisition
        ? !postAcquisition.gap_questionnaire.submitted
            ? postAcquisition.gap_questionnaire_url
            : (postAcquisitionProposalUrl ?? '#section-post-acquisition')
        : '';
    const postAcquisitionActionLabel = postAcquisition
        ? !postAcquisition.gap_questionnaire.submitted
            ? 'Complete gaps'
            : postAcquisitionProposalUrl
              ? 'Review proposal'
              : 'Review handoff'
        : '';
    const postAcquisitionStatus = postAcquisition
        ? !postAcquisition.gap_questionnaire.submitted
            ? `${postAcquisition.gap_questionnaire.remaining_questions} gaps remaining`
            : postAcquisition.proposal?.client_visible
              ? postAcquisition.proposal.status_label
              : postAcquisition.proposal
                ? 'Proposal in prep'
                : `${postAcquisition.migrated_document_count} docs migrated`
        : '';
    const standardAdvisoryReport = reports.find(
        (report) => report.type === 'client',
    );
    const standardAdvisoryActionUrl = standardAdvisory
        ? standardAdvisory.status === 'waiting_questionnaire'
            ? onboardingUrl
            : (standardAdvisoryReport?.view_url ??
              standardAdvisoryReport?.download_url ??
              '#section-reports')
        : '';
    const standardAdvisoryActionLabel = standardAdvisory
        ? standardAdvisoryReport
            ? 'View report'
            : standardAdvisory.status === 'waiting_questionnaire'
              ? 'Continue'
              : 'View status'
        : '';
    const highHealthFindingCount = healthFindings.reduce(
        (total, dimension) =>
            total +
            dimension.findings.filter((finding) =>
                ['critical', 'high'].includes(finding.severity),
            ).length,
        0,
    );
    const strategicPlanOpenMilestoneCount = strategicPlan
        ? Math.max(
              0,
              strategicPlan.total_milestones -
                  strategicPlan.completed_milestones,
          )
        : 0;
    const nextSurvey = surveys.items[0] ?? null;
    const nextOutcomeFollowUp = outcomeFollowUps.items[0] ?? null;
    const focusDashboardSection = (
        sectionId: string,
        tab: PortalDashboardTab,
        event: MouseEvent<Element>,
    ) => {
        event.preventDefault();
        setActiveTab(tab);
        window.setTimeout(() => {
            const section = document.getElementById(sectionId);

            if (!section) {
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
            const previousTabIndex = section.getAttribute('tabindex');
            const hadTabIndex = section.hasAttribute('tabindex');

            section.setAttribute('tabindex', previousTabIndex ?? '-1');
            section.classList.add(...highlightClasses);
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            section.focus({ preventScroll: true });
            window.history.replaceState(null, '', `#${sectionId}`);

            window.setTimeout(() => {
                section.classList.remove(...highlightClasses);

                if (hadTabIndex && previousTabIndex !== null) {
                    section.setAttribute('tabindex', previousTabIndex);
                } else {
                    section.removeAttribute('tabindex');
                }
            }, 2200);
        }, 0);
    };
    const showActionSection = (sectionId: string, event: MouseEvent<Element>) =>
        focusDashboardSection(sectionId, 'actions', event);
    const showInformationSection = (
        sectionId: string,
        event: MouseEvent<Element>,
    ) => focusDashboardSection(sectionId, 'information', event);

    return (
        <>
            <Head title="Client portal" />
            <ClientSupport config={screenShare} />

            <main className="flex-1 space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {client.trading_name || client.legal_name}
                        </h1>
                        <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span>{client.engagement_type_label}</span>
                            <span aria-hidden="true">/</span>
                            <span>NZBN {client.nzbn ?? '-'}</span>
                        </div>
                    </div>
                    <Button asChild>
                        <Link href={onboardingUrl}>
                            <ClipboardList
                                className="size-4"
                                aria-hidden="true"
                            />
                            Continue onboarding
                        </Link>
                    </Button>
                </div>

                {welcomeMessage.has_message && progress.percentage < 100 ? (
                    <WelcomeBanner welcomeMessage={welcomeMessage} />
                ) : null}

                {inspirationBoard ? (
                    <InspirationCard post={inspirationBoard} />
                ) : null}

                <DashboardTabList
                    activeTab={activeTab}
                    onChange={setActiveTab}
                />

                {activeTab === 'actions' ? (
                    <>
                        <DashboardSection
                            title="Priority actions"
                            description="Start with the tiles that can block progress or need a response."
                        >
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <StatusPanel
                                    icon={ClipboardList}
                                    label="Onboarding"
                                    value={`${progress.percentage}% complete`}
                                    explanation="Onboarding collects the client details and evidence needed before advisor work can progress."
                                    href={onboardingUrl}
                                    actionLabel="Continue"
                                />
                                {surveys.total_open > 0 && nextSurvey ? (
                                    <StatusPanel
                                        icon={ClipboardList}
                                        label="Feedback survey"
                                        value={`${surveys.total_open} pending`}
                                        explanation={`Please complete ${nextSurvey.survey_title}. Your feedback helps us understand whether the work delivered was received, accessible, and useful.`}
                                        href={nextSurvey.url}
                                        actionLabel={
                                            surveys.total_open > 1
                                                ? 'Start first'
                                                : 'Start'
                                        }
                                    />
                                ) : null}
                                {outcomeFollowUps.total_open > 0 &&
                                nextOutcomeFollowUp ? (
                                    <StatusPanel
                                        icon={TrendingUp}
                                        label="Outcome follow-up"
                                        value={`${outcomeFollowUps.total_open} pending`}
                                        explanation={`Please complete the ${nextOutcomeFollowUp.cadence_month} month ${nextOutcomeFollowUp.subject_label.toLowerCase()} for ${nextOutcomeFollowUp.subject_name}. This lets us measure whether the advice changed the commercial outcome.`}
                                        href={nextOutcomeFollowUp.url}
                                        actionLabel={
                                            outcomeFollowUps.total_open > 1
                                                ? 'Start first'
                                                : 'Complete'
                                        }
                                    />
                                ) : null}
                                {standardAdvisory && (
                                    <StatusPanel
                                        icon={FileText}
                                        label="Advisory journey"
                                        value={`${standardAdvisory.momentum.percent}% complete`}
                                        explanation={standardAdvisory.momentum.next_action}
                                        href={standardAdvisoryActionUrl}
                                        actionLabel={
                                            standardAdvisoryActionLabel
                                        }
                                        onAction={
                                            standardAdvisoryActionUrl ===
                                            '#section-reports'
                                                ? (event) =>
                                                      showInformationSection(
                                                          'section-reports',
                                                          event,
                                                      )
                                                : undefined
                                        }
                                    />
                                )}
                                {ddPlan && (
                                    <StatusPanel
                                        icon={PieChart}
                                        label="Prepare Due Diligence"
                                        value={
                                            ddPlan.business_advice_requested
                                                ? 'Advice requested'
                                                : ddPlan.plan_completed
                                                  ? 'Plan completed'
                                                  : ddPlan.generated
                                                    ? `Updated ${formatDate(ddPlan.updated_at)}`
                                                    : 'Not generated'
                                        }
                                        explanation={`Builds the due diligence plan for the target acquisition (${ddPlan.target_name}) from DD questionnaire answers, uploaded evidence, workstream findings, and valuation context.`}
                                        href={ddPlan.url}
                                        actionLabel={
                                            ddPlan.generated
                                                ? 'Open'
                                                : 'Prepare'
                                        }
                                    />
                                )}
                                <StatusPanel
                                    icon={FileSpreadsheet}
                                    label={strategicBudget.label}
                                    value={
                                        strategicBudget.locked
                                            ? 'Locked'
                                            : `${strategicBudget.readiness_score}/100 ready`
                                    }
                                    explanation={
                                        strategicBudget.locked
                                            ? (strategicBudget.source_financials
                                                  .system_review ??
                                              'Upload a P&L or management accounts file to unlock the budget.')
                                            : `Status: ${strategicBudget.status_label}. Progress ${strategicBudget.progress_score}%.`
                                    }
                                    href="/portal/business-plan-budget"
                                    actionLabel={
                                        strategicBudget.locked
                                            ? 'Unlock'
                                            : 'Open'
                                    }
                                />
                                {strategicPlan && (
                                    <StatusPanel
                                        icon={ClipboardList}
                                        label="Strategic Plan milestones"
                                        value={
                                            strategicPlanOpenMilestoneCount > 0
                                                ? `${strategicPlanOpenMilestoneCount} open`
                                                : 'All complete'
                                        }
                                        explanation={`Track the deployed Strategic Plan. ${strategicPlan.completed_milestones}/${strategicPlan.total_milestones} milestones are complete and progress is ${strategicPlan.progress_percent}%.`}
                                        href="#section-strategic-plan-milestones"
                                        actionLabel="Update"
                                        onAction={(event) =>
                                            showInformationSection(
                                                'section-strategic-plan-milestones',
                                                event,
                                            )
                                        }
                                    />
                                )}
                                {postAcquisition && (
                                    <StatusPanel
                                        icon={TrendingUp}
                                        label="Post-acquisition"
                                        value={postAcquisitionStatus}
                                        explanation="This handoff uses DD evidence, the DD valuation baseline, and the post-close gap questionnaire to start the advisory engagement."
                                        href={postAcquisitionActionUrl}
                                        actionLabel={postAcquisitionActionLabel}
                                        onAction={
                                            postAcquisitionActionUrl ===
                                            '#section-post-acquisition'
                                                ? (event) =>
                                                      showActionSection(
                                                          'section-post-acquisition',
                                                          event,
                                                      )
                                                : undefined
                                        }
                                    />
                                )}
                                {serviceActivations.items
                                    .slice(0, 2)
                                    .map((activation) => (
                                        <StatusPanel
                                            key={activation.id}
                                            icon={
                                                activation.service_type ===
                                                'due_diligence'
                                                    ? BriefcaseBusiness
                                                    : Lightbulb
                                            }
                                            label={activation.client_label}
                                            value={activation.status_label}
                                            explanation={
                                                activation.package_label
                                                    ? `${activation.package_label}${activation.fixed_fee !== null ? ` / ${formatMoney(activation.fixed_fee, activation.currency)} ex GST` : ''}`
                                                    : 'Your advisor will select the package, scope, and GST-exclusive price from the active Admin Service Rates table.'
                                            }
                                            href={
                                                activation.workspace_url ??
                                                activation.url
                                            }
                                            actionLabel={
                                                activation.workspace_url
                                                    ? 'Open'
                                                    : 'Review'
                                            }
                                        />
                                    ))}
                                <StatusPanel
                                    icon={HeartPulse}
                                    label="Wellbeing"
                                    value={
                                        wellbeing.prompt_due
                                            ? 'Pulse due'
                                            : `Shared ${formatDate(wellbeing.submitted_at)}`
                                    }
                                    explanation="Wellbeing prompts help the advisory team understand founder pressure and support needs."
                                    href="#section-wellbeing"
                                    actionLabel={
                                        wellbeing.prompt_due ? 'Open' : 'View'
                                    }
                                    onAction={(event) =>
                                        showActionSection(
                                            'section-wellbeing',
                                            event,
                                        )
                                    }
                                />
                                <StatusPanel
                                    icon={Bell}
                                    label="Notifications"
                                    value={
                                        notificationSummary.urgent > 0
                                            ? `${notificationSummary.urgent} urgent`
                                            : `${notificationSummary.unread} unread`
                                    }
                                    explanation="Notifications include advisor updates, document checks, terms prompts, and other portal alerts."
                                    href="/notifications"
                                    actionLabel="Open"
                                />
                                {messageSummary.unread_count > 0 && (
                                    <StatusPanel
                                        icon={MessageSquare}
                                        label="Messages"
                                        value={`${messageSummary.unread_count} unread`}
                                        explanation="Unread advisor messages may need a response before the next advisory step can move forward."
                                        href={messageSummary.latest_url}
                                        actionLabel="Open"
                                    />
                                )}
                                <StatusPanel
                                    icon={FileText}
                                    label="Proposals"
                                    value={
                                        unsignedProposalCount > 0
                                            ? `${unsignedProposalCount} awaiting review`
                                            : `${proposals.length} released`
                                    }
                                    explanation="Proposal tiles link to released proposals that may need sign-off or review."
                                    href={
                                        unsignedProposalCount > 0 &&
                                        actionProposal?.signoff_url
                                            ? actionProposal.signoff_url
                                            : '#section-proposals'
                                    }
                                    actionLabel={
                                        unsignedProposalCount > 0
                                            ? 'Open'
                                            : 'View'
                                    }
                                    onAction={
                                        unsignedProposalCount > 0 &&
                                        actionProposal?.signoff_url
                                            ? undefined
                                            : (event) =>
                                                  showActionSection(
                                                      'section-proposals',
                                                      event,
                                                  )
                                    }
                                />
                                <StatusPanel
                                    icon={Upload}
                                    label="Documents"
                                    value={
                                        documentReviewCount > 0
                                            ? `${documentReviewCount} need review`
                                            : `${documents.length} uploaded`
                                    }
                                    explanation="Documents include uploaded evidence and any verification outcomes that need attention."
                                    href="#section-documents"
                                    actionLabel="Review"
                                    onAction={(event) =>
                                        showActionSection(
                                            'section-documents',
                                            event,
                                        )
                                    }
                                />
                                <StatusPanel
                                    icon={TrendingUp}
                                    label="Data quality"
                                    value={
                                        <DataQualityBadge
                                            summary={
                                                client.data_quality_summary
                                            }
                                        />
                                    }
                                    explanation="Data quality reflects how complete and usable the evidence in your client workspace is for advisory analysis."
                                    href="#section-health"
                                    actionLabel="Review"
                                    onAction={(event) =>
                                        showInformationSection(
                                            'section-health',
                                            event,
                                        )
                                    }
                                />
                                <StatusPanel
                                    icon={Activity}
                                    label="Health findings"
                                    value={`${highHealthFindingCount} high priority`}
                                    explanation="High-priority health findings are critical or high severity analysis signals surfaced by the advisory engine."
                                    href="#section-health"
                                    actionLabel="Open"
                                    onAction={(event) =>
                                        showInformationSection(
                                            'section-health',
                                            event,
                                        )
                                    }
                                />
                            </div>
                        </DashboardSection>

                        <DashboardSection
                            title="Action panel"
                            description="Complete open workflow tasks before reviewing broader context."
                        >
                            <section
                                id="section-onboarding"
                                className="rounded-md border bg-background p-4"
                                aria-labelledby="onboarding-progress-heading"
                            >
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h2
                                            id="onboarding-progress-heading"
                                            className="text-sm font-medium"
                                        >
                                            Onboarding progress
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {progress.completed} of{' '}
                                            {progress.total} steps complete
                                        </p>
                                    </div>
                                    <Badge variant="secondary">
                                        {progress.percentage}%
                                    </Badge>
                                </div>
                                <div
                                    className="mt-4 h-2 rounded-full bg-muted"
                                    role="progressbar"
                                    aria-valuenow={progress.percentage}
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-label="Onboarding completion"
                                >
                                    <div
                                        className="h-2 rounded-full bg-[var(--fs-admiralty)]"
                                        style={{
                                            width: `${progress.percentage}%`,
                                        }}
                                    />
                                </div>
                            </section>

                            <section
                                id="section-wellbeing"
                                className="rounded-md border bg-background p-4"
                                aria-labelledby="wellbeing-heading"
                            >
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex items-start gap-3">
                                        <HeartPulse
                                            className="mt-0.5 size-4 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <div>
                                            <h2
                                                id="wellbeing-heading"
                                                className="text-sm font-medium"
                                            >
                                                Wellbeing check-in
                                            </h2>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {wellbeing.prompt_due
                                                    ? 'Optional monthly pulse available.'
                                                    : `Shared ${formatDate(wellbeing.submitted_at)}.`}
                                            </p>
                                        </div>
                                    </div>
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={wellbeing.url}>
                                            {wellbeing.prompt_due
                                                ? 'Open pulse'
                                                : 'View pulse'}
                                        </Link>
                                    </Button>
                                </div>
                            </section>

                            <ProposalSignoffPanel proposals={proposals} />

                            {postAcquisition && (
                                <PostAcquisitionHandoffPanel
                                    payload={postAcquisition}
                                />
                            )}

                            <section
                                id="section-documents"
                                className="space-y-4 rounded-md border bg-background p-4"
                                aria-labelledby="documents-heading"
                            >
                                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div className="flex items-center gap-2">
                                        <FileText
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        <h2
                                            id="documents-heading"
                                            className="text-sm font-medium"
                                        >
                                            Documents
                                        </h2>
                                        <Badge variant="outline">
                                            {documents.length}
                                        </Badge>
                                    </div>
                                    <div className="grid w-full gap-2 lg:max-w-sm">
                                        {npoPortal ? (
                                            <Select
                                                value={documentCategory}
                                                onValueChange={
                                                    setDocumentCategory
                                                }
                                            >
                                                <SelectTrigger
                                                    size="sm"
                                                    className="w-full"
                                                    aria-label="Document category"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="npo_board_record">
                                                        Board record
                                                    </SelectItem>
                                                    <SelectItem value="npo_meeting_minutes">
                                                        Meeting minutes
                                                    </SelectItem>
                                                    <SelectItem value="other">
                                                        Other document
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        ) : ddPlan ? (
                                            <Select
                                                value={documentWorkstream}
                                                onValueChange={
                                                    setDocumentWorkstream
                                                }
                                            >
                                                <SelectTrigger
                                                    size="sm"
                                                    className="w-full"
                                                    aria-label="DD workstream"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {ddPlan.workstream_options.map(
                                                        (option) => (
                                                            <SelectItem
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        ) : null}
                                        <FileDropzone
                                            key={uploadKey}
                                            id="client_dashboard_document"
                                            files={file ? [file] : []}
                                            label="Upload document"
                                            onFilesChange={(files) =>
                                                setFile(files[0] ?? null)
                                            }
                                        />
                                        <InputError
                                            message={uploadError ?? undefined}
                                        />
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            disabled={!file || uploading}
                                            onClick={() =>
                                                void uploadDocument()
                                            }
                                        >
                                            <Upload
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            {uploading ? 'Uploading' : 'Upload'}
                                        </Button>
                                    </div>
                                </div>

                                {documents.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No documents uploaded yet.
                                    </p>
                                ) : (
                                    <div className="grid gap-3 md:grid-cols-2">
                                        {documents.map((document) => (
                                            <DocumentTile
                                                key={document.id}
                                                document={document}
                                            />
                                        ))}
                                    </div>
                                )}
                            </section>
                        </DashboardSection>
                    </>
                ) : (
                    <>
                        <DashboardSection
                            title="Insights & evidence"
                            description="Review the signals that shape next actions, including business health, goals, NPO position, and strategic plan progress."
                        >
                            <BusinessHealthPanel
                                businessHealth={businessHealth}
                                healthFindings={healthFindings}
                            />

                            {npoHealth && (
                                <NpoHealthPanel
                                    payload={npoHealth}
                                    title="NPO health"
                                />
                            )}

                            {npoPortal && (
                                <NpoPortalPanel
                                    payload={npoPortal}
                                    metricStoreUrl={npoImpactMetricStoreUrl}
                                    onboardingUrl={onboardingUrl}
                                />
                            )}

                            <GoalProgressPanel goals={goals} />

                            {strategicPlan && (
                                <StrategicPlanProgressPanel
                                    strategicPlan={strategicPlan}
                                />
                            )}
                        </DashboardSection>

                        <DashboardSection
                            title="Reports & shared outputs"
                            description="Review released reports and advisor-shared what-if scenarios after open actions are clear."
                        >
                            <section
                                className="space-y-4 rounded-md border bg-background p-4"
                                aria-labelledby="reports-heading"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <div className="flex items-center gap-2">
                                        <FileText
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        <h2
                                            id="reports-heading"
                                            className="text-sm font-medium"
                                        >
                                            Reports
                                        </h2>
                                    </div>
                                    <Badge variant="outline">
                                        {reports.length}
                                    </Badge>
                                </div>

                                {standardAdvisory &&
                                    !standardAdvisoryReport && (
                                        <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                                            <div className="font-medium text-foreground">
                                                {standardAdvisory.status_label}
                                            </div>
                                            <div className="mt-1">
                                                {standardAdvisory.next_action}
                                            </div>
                                            {standardAdvisory.missing.length >
                                                0 && (
                                                <ul className="mt-2 list-disc space-y-1 pl-5">
                                                    {standardAdvisory.missing.map(
                                                        (item) => (
                                                            <li key={item}>
                                                                {item}
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            )}
                                        </div>
                                    )}

                                {reports.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No client reports released yet.
                                    </p>
                                ) : (
                                    <div className="divide-y rounded-md border">
                                        {reports.map((report) => (
                                            <article
                                                key={report.id}
                                                className="flex flex-wrap items-center justify-between gap-3 p-3"
                                            >
                                                <div>
                                                    <div className="text-sm font-medium">
                                                        {report.title}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {formatDate(
                                                            report.generated_at,
                                                        )}
                                                    </div>
                                                </div>
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <a
                                                        href={
                                                            report.view_url ??
                                                            report.download_url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <FileText
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        View
                                                    </a>
                                                </Button>
                                            </article>
                                        ))}
                                    </div>
                                )}
                            </section>

                            {scenarios.length > 0 && (
                                <section
                                    className="space-y-4 rounded-md border bg-background p-4"
                                    aria-labelledby="scenarios-heading"
                                >
                                    <div className="flex items-center gap-2">
                                        <TrendingUp
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        <h2
                                            id="scenarios-heading"
                                            className="text-sm font-medium"
                                        >
                                            What-if scenarios
                                        </h2>
                                    </div>
                                    <ScenarioList scenarios={scenarios} />
                                </section>
                            )}
                        </DashboardSection>
                    </>
                )}
            </main>
        </>
    );
}

function NpoPortalPanel({
    payload,
    metricStoreUrl,
    onboardingUrl,
}: {
    payload: NpoPortalPayload;
    metricStoreUrl: string | null;
    onboardingUrl: string;
}) {
    const [metrics, setMetrics] = useState<NpoImpactMetricPayload[]>(
        payload.impact_metrics,
    );
    const [metricForm, setMetricForm] = useState({
        metric_key: 'beneficiaries_served',
        metric_label: 'Beneficiaries served',
        value: '',
        unit: 'people',
        platform_value: '',
        period_start: '',
        period_end: '',
        notes: '',
    });
    const [savingMetric, setSavingMetric] = useState(false);
    const [metricError, setMetricError] = useState<string | null>(null);

    const saveMetric = async () => {
        if (!metricStoreUrl) {
            return;
        }

        setSavingMetric(true);
        setMetricError(null);

        const response = await fetch(metricStoreUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                ...metricForm,
                platform_value:
                    metricForm.platform_value === ''
                        ? null
                        : metricForm.platform_value,
                period_start:
                    metricForm.period_start === ''
                        ? null
                        : metricForm.period_start,
                period_end:
                    metricForm.period_end === '' ? null : metricForm.period_end,
                notes: metricForm.notes === '' ? null : metricForm.notes,
            }),
        });

        setSavingMetric(false);

        if (!response.ok) {
            const payload = (await response.json().catch(() => null)) as {
                message?: string;
                errors?: Record<string, string[]>;
            } | null;
            const firstError = payload?.errors
                ? Object.values(payload.errors)[0]?.[0]
                : null;
            setMetricError(
                firstError ?? payload?.message ?? 'Metric not saved.',
            );

            return;
        }

        const saved = (await response.json()) as {
            metric?: NpoImpactMetricPayload;
        };

        if (saved.metric) {
            setMetrics((current) => [
                saved.metric as NpoImpactMetricPayload,
                ...current.filter((metric) => metric.id !== saved.metric?.id),
            ]);
            setMetricForm((current) => ({
                ...current,
                value: '',
                platform_value: '',
                notes: '',
            }));
        }
    };

    return (
        <section
            className="space-y-5 rounded-md border bg-background p-4"
            aria-labelledby="npo-portal-heading"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Users className="size-4" aria-hidden="true" />
                    <h2 id="npo-portal-heading" className="text-sm font-medium">
                        NPO workspace
                    </h2>
                    <Badge variant="outline">
                        {payload.sub_type
                            ? formatLabel(payload.sub_type)
                            : 'NPO'}
                    </Badge>
                </div>
                <Badge
                    variant={
                        payload.questionnaire_completion.completed
                            ? 'default'
                            : 'outline'
                    }
                >
                    {payload.questionnaire_completion.completed
                        ? 'Questionnaire complete'
                        : 'Questionnaire pending'}
                </Badge>
            </div>

            <div className="grid gap-4 lg:grid-cols-4">
                <NpoStat
                    icon={CircleDollarSign}
                    label="Active funding"
                    value={formatCurrency(
                        payload.funding.summary.active_amount,
                    )}
                    detail={`${payload.funding.summary.active_records} active grants`}
                />
                <NpoStat
                    icon={PieChart}
                    label="Concentration"
                    value={`${Math.round(payload.funding.concentration.largest_funder_ratio * 100)}%`}
                    detail={formatLabel(
                        payload.funding.concentration.risk_level,
                    )}
                />
                <NpoStat
                    icon={CalendarClock}
                    label="Reports due"
                    value={`${payload.accountability_reports_due.length}`}
                    detail={`${payload.funding.summary.due_60_count} inside 60 days`}
                />
                <NpoStat
                    icon={Target}
                    label="Milestones"
                    value={`${payload.milestone_progress.percentage}%`}
                    detail={`${payload.milestone_progress.completed} of ${payload.milestone_progress.total} complete`}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <article className="space-y-3 rounded-md border p-3">
                    <div className="flex items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Funding</h3>
                        <Badge variant="outline">
                            {payload.funding.alerts.length} alerts
                        </Badge>
                    </div>
                    {payload.funding.deadlines_60.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No funder deadlines inside 60 days.
                        </p>
                    ) : (
                        <div className="divide-y rounded-md border">
                            {payload.funding.deadlines_60.map((record) => (
                                <div key={record.id} className="p-3">
                                    <div className="text-sm font-medium">
                                        {record.funder_name ?? 'Funder'}
                                    </div>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        {record.grant_name ?? 'Grant'} /{' '}
                                        {formatOptionalDate(
                                            record.reporting_deadline,
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </article>

                <article className="space-y-3 rounded-md border p-3">
                    <div className="flex items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">
                            Cost per beneficiary
                        </h3>
                        <Badge variant="outline">
                            {payload.milestone_progress.cost_per_beneficiary
                                ?.rating
                                ? formatLabel(
                                      payload.milestone_progress
                                          .cost_per_beneficiary.rating,
                                  )
                                : 'Pending'}
                        </Badge>
                    </div>
                    {payload.milestone_progress.cost_per_beneficiary ? (
                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Current
                                </span>
                                <span className="font-medium">
                                    {formatCurrency(
                                        payload.milestone_progress
                                            .cost_per_beneficiary
                                            .cost_per_beneficiary ?? 0,
                                    )}
                                </span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Benchmark
                                </span>
                                <span className="font-medium">
                                    {payload.milestone_progress
                                        .cost_per_beneficiary
                                        .benchmark_cost_per_beneficiary
                                        ? formatCurrency(
                                              payload.milestone_progress
                                                  .cost_per_beneficiary
                                                  .benchmark_cost_per_beneficiary,
                                          )
                                        : 'Pending'}
                                </span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Capacity
                                </span>
                                <span className="font-medium">
                                    {payload.milestone_progress
                                        .cost_per_beneficiary
                                        .additional_beneficiaries_mid !== null
                                        ? formatNumber(
                                              payload.milestone_progress
                                                  .cost_per_beneficiary
                                                  .additional_beneficiaries_mid,
                                          )
                                        : 'Pending'}
                                </span>
                            </div>
                            {payload.milestone_progress.cost_per_beneficiary
                                .benchmark_note ? (
                                <p className="text-xs text-muted-foreground">
                                    {
                                        payload.milestone_progress
                                            .cost_per_beneficiary.benchmark_note
                                    }
                                </p>
                            ) : null}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No calculation recorded yet.
                        </p>
                    )}
                </article>

                <article className="space-y-3 rounded-md border p-3">
                    <div className="flex items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Questionnaire</h3>
                        <Badge variant="outline">
                            {
                                payload.questionnaire_completion
                                    .answered_questions
                            }
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {payload.questionnaire_completion.completed
                            ? `Submitted ${formatDate(payload.questionnaire_completion.submitted_at)}.`
                            : 'No submitted response yet.'}
                    </p>
                    <Button asChild variant="outline" size="sm">
                        <Link href={onboardingUrl}>
                            <ClipboardList
                                className="size-4"
                                aria-hidden="true"
                            />
                            Open questionnaire
                        </Link>
                    </Button>
                </article>
            </div>

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.8fr)]">
                <article className="space-y-3 rounded-md border p-3">
                    <div className="flex items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Impact metrics</h3>
                        <Badge variant="outline">{metrics.length}</Badge>
                    </div>
                    {metrics.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No impact metrics recorded yet.
                        </p>
                    ) : (
                        <div className="divide-y rounded-md border">
                            {metrics.slice(0, 6).map((metric) => (
                                <div
                                    key={metric.id}
                                    className="flex flex-wrap items-center justify-between gap-3 p-3"
                                >
                                    <div>
                                        <div className="text-sm font-medium">
                                            {metric.metric_label}
                                        </div>
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {formatOptionalDate(
                                                metric.period_end,
                                            )}
                                        </div>
                                    </div>
                                    <div className="text-sm font-medium">
                                        {formatMetricValue(metric)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </article>

                <article className="space-y-3 rounded-md border p-3">
                    <div className="flex items-center gap-2">
                        <Save className="size-4" aria-hidden="true" />
                        <h3 className="text-sm font-medium">Metric entry</h3>
                    </div>
                    <div className="grid gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="npo_metric_label">Metric</Label>
                            <Input
                                id="npo_metric_label"
                                value={metricForm.metric_label}
                                onChange={(event) =>
                                    setMetricForm((current) => ({
                                        ...current,
                                        metric_label: event.target.value,
                                    }))
                                }
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="npo_metric_value">Value</Label>
                                <Input
                                    id="npo_metric_value"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={metricForm.value}
                                    onChange={(event) =>
                                        setMetricForm((current) => ({
                                            ...current,
                                            value: event.target.value,
                                        }))
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="npo_metric_unit">Unit</Label>
                                <Input
                                    id="npo_metric_unit"
                                    value={metricForm.unit}
                                    onChange={(event) =>
                                        setMetricForm((current) => ({
                                            ...current,
                                            unit: event.target.value,
                                        }))
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="npo_metric_platform">
                                    Platform
                                </Label>
                                <Input
                                    id="npo_metric_platform"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={metricForm.platform_value}
                                    onChange={(event) =>
                                        setMetricForm((current) => ({
                                            ...current,
                                            platform_value: event.target.value,
                                        }))
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="npo_metric_period_end">
                                    Period end
                                </Label>
                                <Input
                                    id="npo_metric_period_end"
                                    type="date"
                                    value={metricForm.period_end}
                                    onChange={(event) =>
                                        setMetricForm((current) => ({
                                            ...current,
                                            period_end: event.target.value,
                                        }))
                                    }
                                />
                            </div>
                        </div>
                        <InputError message={metricError ?? undefined} />
                        <Button
                            type="button"
                            size="sm"
                            disabled={
                                !metricStoreUrl ||
                                savingMetric ||
                                metricForm.metric_label.trim() === '' ||
                                metricForm.value.trim() === ''
                            }
                            onClick={() => void saveMetric()}
                        >
                            <Save className="size-4" aria-hidden="true" />
                            {savingMetric ? 'Saving' : 'Save metric'}
                        </Button>
                    </div>
                </article>
            </div>
        </section>
    );
}

function DashboardSection({
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

function DashboardTabList({
    activeTab,
    onChange,
}: {
    activeTab: PortalDashboardTab;
    onChange: (tab: PortalDashboardTab) => void;
}) {
    return (
        <div
            className="inline-flex w-full max-w-md rounded-md border bg-muted/30 p-1"
            role="tablist"
            aria-label="Client portal dashboard sections"
        >
            <DashboardTabButton
                active={activeTab === 'actions'}
                onClick={() => onChange('actions')}
            >
                Actions
            </DashboardTabButton>
            <DashboardTabButton
                active={activeTab === 'information'}
                onClick={() => onChange('information')}
            >
                Insights
            </DashboardTabButton>
        </div>
    );
}

function DashboardTabButton({
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

function initialPortalDashboardTab(): PortalDashboardTab {
    if (
        typeof window !== 'undefined' &&
        [
            '#section-health',
            '#section-reports',
            '#section-strategic-plan-milestones',
        ].includes(window.location.hash)
    ) {
        return 'information';
    }

    return 'actions';
}

function NpoStat({
    icon: Icon,
    label,
    value,
    detail,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    label: string;
    value: string;
    detail: string;
}) {
    return (
        <article className="rounded-md border p-3">
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Icon className="size-4" aria-hidden={true} />
                {label}
            </div>
            <div className="mt-2 text-lg font-semibold">{value}</div>
            <div className="mt-1 text-xs text-muted-foreground">{detail}</div>
        </article>
    );
}

function BusinessHealthPanel({
    businessHealth,
    healthFindings,
}: {
    businessHealth: BusinessHealthRadarPayload;
    healthFindings: HealthFindingDimension[];
}) {
    const hasHealthAnalysis =
        businessHealth.captured_at !== null ||
        businessHealth.axes.some((axis) => typeof axis.score === 'number') ||
        healthFindings.some((dimension) => dimension.findings.length > 0);

    return (
        <section
            id="section-health"
            className="space-y-5 rounded-md border bg-background p-4"
            aria-labelledby="business-health-heading"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Activity className="size-4" aria-hidden="true" />
                    <h2
                        id="business-health-heading"
                        className="text-sm font-medium"
                    >
                        Business health overview
                    </h2>
                </div>
                <Badge variant="outline">
                    {businessHealth.captured_at
                        ? formatDate(businessHealth.captured_at)
                        : 'Pending'}
                </Badge>
            </div>

            {!hasHealthAnalysis ? (
                <div className="rounded-md border border-dashed bg-muted/20 p-4">
                    <div className="text-sm font-medium">
                        Waiting for analysis
                    </div>
                    <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                        This overview will populate once onboarding, financial
                        uploads, and advisor analysis provide enough evidence.
                        It will then show financial, operational, people,
                        strategic, and compliance signals in one place.
                    </p>
                </div>
            ) : (
                <BusinessHealthRadar payload={businessHealth} />
            )}

            <div className="grid gap-3 lg:grid-cols-5">
                {healthFindings.map((dimension) => (
                    <article
                        key={dimension.dimension}
                        id={dimension.anchor}
                        className="space-y-3 rounded-md border p-3"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <h3 className="text-sm font-medium">
                                {dimension.label}
                            </h3>
                            <Badge variant="outline">
                                {dimension.findings.length}
                            </Badge>
                        </div>

                        {dimension.findings.length === 0 ? (
                            <p className="text-xs text-muted-foreground">
                                {dimension.message}
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {dimension.findings.map((finding) => (
                                    <article
                                        key={finding.id}
                                        className="space-y-2 rounded-md bg-muted/40 p-3"
                                    >
                                        <div className="space-y-1">
                                            <div className="text-sm font-medium">
                                                {finding.title}
                                            </div>
                                            <div className="flex flex-wrap gap-1">
                                                <Badge
                                                    variant={severityVariant(
                                                        finding.severity,
                                                    )}
                                                >
                                                    {formatLabel(
                                                        finding.severity,
                                                    )}
                                                </Badge>
                                                {finding.module && (
                                                    <Badge variant="secondary">
                                                        {formatLabel(
                                                            finding.module,
                                                        )}
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {finding.body}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {attributionSummary(finding)}
                                        </p>
                                    </article>
                                ))}
                            </div>
                        )}
                    </article>
                ))}
            </div>
        </section>
    );
}

function ScenarioList({ scenarios }: { scenarios: ScenarioPayload[] }) {
    if (scenarios.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No scenarios released yet.
            </p>
        );
    }

    return (
        <div className="divide-y rounded-md border">
            {scenarios.map((scenario) => (
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
                        </div>
                        <div className="mt-1 text-xs text-muted-foreground">
                            {formatOverlay(scenario)}
                        </div>
                    </div>
                    <div className="text-sm font-medium sm:text-right">
                        {formatCurrency(scenario.pv_impact)}
                    </div>
                </article>
            ))}
        </div>
    );
}

function GoalProgressPanel({ goals }: { goals: GoalDashboard }) {
    return (
        <section
            id="section-goals"
            className="space-y-4 rounded-md border bg-background p-4"
            aria-labelledby="goals-heading"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Target className="size-4" aria-hidden="true" />
                    <h2 id="goals-heading" className="text-sm font-medium">
                        Goals
                    </h2>
                    <Badge variant="outline">{goals.active_goals} active</Badge>
                </div>
                <div className="text-sm font-medium">
                    {formatCurrency(goals.pv_realised_total)} realised
                </div>
            </div>

            {goals.goals.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No goals published yet.
                </p>
            ) : (
                <div className="space-y-3">
                    {goals.goals.map((goal) => (
                        <article
                            key={goal.id}
                            className="rounded-md border p-3"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="text-sm font-medium">
                                            {goal.title}
                                        </h3>
                                        <Badge variant="outline">
                                            {formatLabel(goal.status)}
                                        </Badge>
                                    </div>
                                    {goal.description && (
                                        <p className="text-sm text-muted-foreground">
                                            {goal.description}
                                        </p>
                                    )}
                                </div>
                                <div className="text-right">
                                    <div className="text-sm font-medium">
                                        {formatCurrency(goal.pv_target)}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        Target{' '}
                                        {formatOptionalDate(goal.target_date)}
                                    </div>
                                </div>
                            </div>
                            <div className="mt-3 grid gap-2 text-sm sm:grid-cols-2 xl:grid-cols-4">
                                <GoalMetric
                                    label="Baseline"
                                    value={
                                        goal.measurement.baseline_pv === null
                                            ? '-'
                                            : formatCurrency(
                                                  goal.measurement.baseline_pv,
                                              )
                                    }
                                    hint={formatOptionalDate(
                                        goal.measurement.baseline_as_at,
                                    )}
                                />
                                <GoalMetric
                                    label="Current"
                                    value={
                                        goal.measurement.current_pv === null
                                            ? '-'
                                            : formatCurrency(
                                                  goal.measurement.current_pv,
                                              )
                                    }
                                    hint={formatOptionalDate(
                                        goal.measurement.current_as_at,
                                    )}
                                />
                                <GoalMetric
                                    label="Movement"
                                    value={
                                        goal.measurement.pv_movement === null
                                            ? '-'
                                            : formatCurrency(
                                                  goal.measurement.pv_movement,
                                              )
                                    }
                                    hint={`${formatCurrency(goal.measurement.realised_pv)} verified`}
                                />
                                <GoalMetric
                                    label="Progress"
                                    value={
                                        goal.measurement.progress_percent ===
                                        null
                                            ? '-'
                                            : formatPercent(
                                                  goal.measurement
                                                      .progress_percent,
                                              )
                                    }
                                    hint={
                                        goal.measurement.target_gap === null
                                            ? 'Target gap pending'
                                            : `${formatCurrency(goal.measurement.target_gap)} to target`
                                    }
                                />
                            </div>
                            {goal.milestones.length > 0 && (
                                <div className="mt-3 divide-y rounded-md border">
                                    {goal.milestones.map((milestone) => (
                                        <div
                                            key={milestone.id}
                                            className="flex flex-wrap items-center justify-between gap-3 p-3"
                                        >
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="text-sm font-medium">
                                                        {milestone.title}
                                                    </span>
                                                    <Badge variant="outline">
                                                        {formatLabel(
                                                            milestone.status,
                                                        )}
                                                    </Badge>
                                                </div>
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    Due{' '}
                                                    {formatOptionalDate(
                                                        milestone.due_date,
                                                    )}
                                                </div>
                                            </div>
                                            <div className="text-sm font-medium">
                                                {formatCurrency(
                                                    milestone.pv_of_impact,
                                                )}
                                            </div>
                                        </div>
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

function GoalMetric({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint: string;
}) {
    return (
        <div className="rounded-md border bg-muted/20 p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-sm font-medium">{value}</div>
            <div className="mt-1 text-xs text-muted-foreground">{hint}</div>
        </div>
    );
}

function StrategicPlanProgressPanel({
    strategicPlan,
}: {
    strategicPlan: StrategicPlanPayload;
}) {
    return (
        <section
            id="section-strategic-plan-milestones"
            className="space-y-4 rounded-md border bg-background p-4"
            aria-labelledby="strategic-plan-heading"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex items-start gap-2">
                    <ClipboardList
                        className="mt-0.5 size-4"
                        aria-hidden="true"
                    />
                    <div>
                        <h2
                            id="strategic-plan-heading"
                            className="text-sm font-medium"
                        >
                            Strategic Plan milestones
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {strategicPlan.summary ??
                                'Track the deployed strategic plan milestones and evidence.'}
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">
                        {strategicPlan.completed_milestones}/
                        {strategicPlan.total_milestones} complete
                    </Badge>
                    <Badge variant="secondary">
                        {strategicPlan.progress_percent}% progress
                    </Badge>
                </div>
            </div>

            <div className="space-y-3">
                {strategicPlan.milestones.map((milestone) => (
                    <StrategicPlanMilestoneCard
                        key={milestone.id}
                        milestone={milestone}
                    />
                ))}
            </div>
        </section>
    );
}

function StrategicPlanMilestoneCard({
    milestone,
}: {
    milestone: StrategicPlanMilestonePayload;
}) {
    const form = useForm({
        status: milestone.status,
        progress_percent: String(milestone.progress_percent),
        evidence_notes: milestone.evidence_notes ?? '',
    });

    const save = () => {
        form.patch(milestone.update_url, { preserveScroll: true });
    };

    return (
        <article className="space-y-3 rounded-md border p-3">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-sm font-medium">
                            {milestone.title}
                        </h3>
                        <Badge variant="outline">{milestone.owner_label}</Badge>
                        <Badge variant="secondary">
                            {milestone.status_label}
                        </Badge>
                    </div>
                    {milestone.description && (
                        <p className="text-sm text-muted-foreground">
                            {milestone.description}
                        </p>
                    )}
                </div>
                <div className="text-sm text-muted-foreground">
                    Due {formatOptionalDate(milestone.due_date)}
                </div>
            </div>

            <div className="grid gap-3 md:grid-cols-[180px_180px_minmax(0,1fr)_auto]">
                <label className="grid gap-1 text-sm">
                    <span className="font-medium">Status</span>
                    <select
                        value={form.data.status}
                        onChange={(event) =>
                            form.setData(
                                'status',
                                event.target
                                    .value as StrategicPlanMilestonePayload['status'],
                            )
                        }
                        className="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="pending">Pending</option>
                        <option value="in_progress">In progress</option>
                        <option value="completed">Completed</option>
                        <option value="blocked">Blocked</option>
                    </select>
                </label>
                <label className="grid gap-1 text-sm">
                    <span className="font-medium">Progress %</span>
                    <Input
                        type="number"
                        min={0}
                        max={100}
                        value={form.data.progress_percent}
                        onChange={(event) =>
                            form.setData('progress_percent', event.target.value)
                        }
                    />
                </label>
                <label className="grid gap-1 text-sm">
                    <span className="font-medium">Evidence notes</span>
                    <Input
                        value={form.data.evidence_notes}
                        onChange={(event) =>
                            form.setData('evidence_notes', event.target.value)
                        }
                        placeholder="Record progress evidence or blockers"
                    />
                </label>
                <Button
                    type="button"
                    className="self-end"
                    disabled={form.processing}
                    onClick={save}
                >
                    <Save className="size-4" aria-hidden="true" />
                    Save
                </Button>
            </div>
            <InputError message={form.errors.status} />
            <InputError message={form.errors.progress_percent} />
            <InputError message={form.errors.evidence_notes} />
        </article>
    );
}

function PostAcquisitionHandoffPanel({
    payload,
}: {
    payload: PostAcquisitionPayload;
}) {
    const proposalUrl =
        payload.proposal?.client_visible && payload.proposal.signoff_url
            ? payload.proposal.signoff_url
            : null;

    return (
        <section
            id="section-post-acquisition"
            className="space-y-4 rounded-md border bg-background p-4"
            aria-labelledby="post-acquisition-heading"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex items-start gap-3">
                    <TrendingUp
                        className="mt-0.5 size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <div>
                        <h2
                            id="post-acquisition-heading"
                            className="text-sm font-medium"
                        >
                            Post-acquisition handoff
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            DD context for{' '}
                            {payload.source_target_name ??
                                'the acquired business'}{' '}
                            is ready for the advisory engagement.
                        </p>
                    </div>
                </div>
                <Badge variant="outline">
                    Migrated {formatDate(payload.migrated_at)}
                </Badge>
            </div>

            <div className="grid gap-3 md:grid-cols-4">
                <MetricBlock
                    label="DD valuation baseline"
                    value={formatCurrency(payload.dd_pv_baseline)}
                />
                <MetricBlock
                    label="Migrated documents"
                    value={`${payload.migrated_document_count}`}
                />
                <MetricBlock
                    label="Gap questionnaire"
                    value={
                        payload.gap_questionnaire.submitted
                            ? 'Submitted'
                            : `${payload.gap_questionnaire.remaining_questions} open`
                    }
                />
                <MetricBlock
                    label="Proposal"
                    value={payload.proposal?.status_label ?? 'Pending'}
                />
            </div>

            {payload.integration_actions.length > 0 ? (
                <div className="space-y-3 rounded-md border bg-muted/20 p-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 className="text-sm font-medium">
                                First 100-day action plan
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Advisor-led actions generated from the DD risk
                                register and integration plan.
                            </p>
                        </div>
                        <Badge variant="outline">
                            {payload.integration_actions.length} actions
                        </Badge>
                    </div>
                    <div className="grid gap-2">
                        {payload.integration_actions.map((item) => (
                            <div
                                key={item.id}
                                className="grid gap-3 rounded-md border bg-background p-3 text-sm md:grid-cols-[80px_1fr_auto]"
                            >
                                <div>
                                    <div className="text-xs text-muted-foreground">
                                        Day
                                    </div>
                                    <div className="font-medium">
                                        {item.day}
                                    </div>
                                </div>
                                <div>
                                    <div className="font-medium">
                                        {item.action}
                                    </div>
                                    <div className="mt-1 text-muted-foreground">
                                        {item.phase}
                                        {item.owner ? ` / ${item.owner}` : ''}
                                    </div>
                                </div>
                                <div className="flex flex-wrap items-center gap-2 md:justify-end">
                                    <Badge variant="secondary">
                                        {formatLabel(item.priority)}
                                    </Badge>
                                    <Badge variant="outline">
                                        {formatLabel(item.status)}
                                    </Badge>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            ) : null}

            <div className="flex flex-wrap gap-2">
                <Button asChild variant="outline" size="sm">
                    <Link href={payload.gap_questionnaire_url}>
                        <ClipboardList className="size-4" aria-hidden="true" />
                        {payload.gap_questionnaire.submitted
                            ? 'View questionnaire'
                            : 'Complete gaps'}
                    </Link>
                </Button>
                {proposalUrl ? (
                    <Button asChild variant="outline" size="sm">
                        <Link href={proposalUrl}>
                            <FileText className="size-4" aria-hidden="true" />
                            Review proposal
                        </Link>
                    </Button>
                ) : (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled
                                >
                                    <FileText
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Proposal pending
                                </Button>
                            </span>
                        </TooltipTrigger>
                        <TooltipContent side="bottom" className="max-w-xs">
                            The advisor can release the generated draft proposal
                            before client sign-off.
                        </TooltipContent>
                    </Tooltip>
                )}
                <Button asChild variant="outline" size="sm">
                    <a href="#section-documents">
                        <Upload className="size-4" aria-hidden="true" />
                        View documents
                    </a>
                </Button>
            </div>
        </section>
    );
}

function MetricBlock({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="rounded-md border p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-2 text-sm font-medium">{value}</div>
        </div>
    );
}

function ProposalSignoffPanel({ proposals }: { proposals: ProposalPayload[] }) {
    return (
        <section
            id="section-proposals"
            className="space-y-4 rounded-md border bg-background p-4"
            aria-labelledby="proposal-signoff-heading"
        >
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <FileText className="size-4" aria-hidden="true" />
                    <h2
                        id="proposal-signoff-heading"
                        className="text-sm font-medium"
                    >
                        Proposals
                    </h2>
                </div>
                <Badge variant="outline">{proposals.length}</Badge>
            </div>

            {proposals.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No released proposals yet.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {proposals.map((proposal) => (
                        <article
                            key={proposal.id}
                            className="flex flex-wrap items-center justify-between gap-3 p-3"
                        >
                            <div className="space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h3 className="text-sm font-medium">
                                        Proposal v{proposal.version}
                                    </h3>
                                    <Badge variant="outline">
                                        {proposal.status_label}
                                    </Badge>
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {formatCurrency(
                                        proposal.suggested_mid ?? 0,
                                    )}
                                </div>
                                <p className="max-w-3xl text-sm leading-5 text-muted-foreground">
                                    {proposal.brief}
                                </p>
                            </div>
                            <Button asChild variant="outline" size="sm">
                                <Link href={proposal.signoff_url}>
                                    {proposal.status === 'signed'
                                        ? 'View'
                                        : 'Open'}
                                </Link>
                            </Button>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function DocumentTile({ document }: { document: DocumentPayload }) {
    const flagged = isDocumentFlagged(document);

    return (
        <article className="space-y-3 rounded-md border p-3">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h3 className="truncate text-sm font-medium">
                        {document.original_filename}
                    </h3>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {document.category.replaceAll('_', ' ')}
                    </p>
                </div>
                <VerificationBadge outcome={document.verification_state} />
            </div>

            {flagged ? (
                <FlagBanner
                    outcome={document.verification_state}
                    title="Verification review"
                >
                    {document.client_explanation}
                </FlagBanner>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {document.client_explanation}
                </p>
            )}

            <Button asChild variant="outline" size="sm">
                <a
                    href={document.url}
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    View document
                </a>
            </Button>
        </article>
    );
}

function isDocumentFlagged(document: DocumentPayload): boolean {
    return (
        document.verification_state === 'advisory_flag' ||
        document.verification_state === 'accuracy_discrepancy' ||
        document.verification_state === 'verification_error'
    );
}

function formatDate(value: string | null) {
    if (!value) {
        return 'this month';
    }

    return new Intl.DateTimeFormat('en-NZ', {
        day: 'numeric',
        month: 'short',
    }).format(new Date(value));
}

function formatOptionalDate(value: string | null) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('en-NZ', {
        day: 'numeric',
        month: 'short',
    }).format(new Date(value));
}

function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function formatOverlay(scenario: ScenarioPayload): string {
    const growth = scenario.economic_overlay.applied_growth_rate;
    const method = scenario.economic_overlay.discount_method;

    if (growth === null && method === null) {
        return 'Economic overlay pending';
    }

    const growthLabel =
        growth === null ? 'growth n/a' : `${(growth * 100).toFixed(1)}% growth`;
    const methodLabel = method === null ? 'rate n/a' : formatLabel(method);

    return `${growthLabel} / ${methodLabel}`;
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'NZD',
        maximumFractionDigits: 0,
    }).format(value);
}

function formatPercent(value: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'percent',
        maximumFractionDigits: 1,
    }).format(value / 100);
}

function formatMoney(value: number, currency: string): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatNumber(value: number): string {
    return new Intl.NumberFormat(undefined, {
        maximumFractionDigits: 0,
    }).format(value);
}

function formatMetricValue(metric: NpoImpactMetricPayload): string {
    const unit = metric.unit ? ` ${metric.unit}` : '';

    return `${formatNumber(metric.value)}${unit}`;
}

function attributionSummary(finding: HealthFinding): string {
    const first = finding.attributions[0];

    if (!first) {
        return 'Cited source retained with the analysis finding.';
    }

    if (typeof first.claim === 'string' && first.claim !== '') {
        return first.source_reference
            ? `${first.claim} (${first.source_reference})`
            : first.claim;
    }

    if (
        typeof first.source_reference === 'string' &&
        first.source_reference !== ''
    ) {
        return first.source_reference;
    }

    return 'Cited source retained with the analysis finding.';
}

function severityVariant(
    severity: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (severity === 'critical' || severity === 'high') {
        return 'destructive';
    }

    if (severity === 'medium') {
        return 'secondary';
    }

    return 'outline';
}

function StatusPanel({
    icon: Icon,
    label,
    value,
    explanation,
    href,
    actionLabel,
    onAction,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    label: string;
    value: ReactNode;
    explanation: string;
    href: string;
    actionLabel: string;
    onAction?: (event: MouseEvent<Element>) => void;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Link
                    href={href}
                    onClick={onAction}
                    className="block rounded-md border bg-background p-4 text-left transition-colors hover:border-primary/50 hover:bg-muted/30 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Icon className="size-4" aria-hidden={true} />
                        {label}
                    </div>
                    <div className="mt-2 text-sm font-medium">{value}</div>
                    <span className="mt-3 inline-flex text-sm font-medium text-foreground">
                        {actionLabel}
                    </span>
                </Link>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {explanation}
            </TooltipContent>
        </Tooltip>
    );
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}
