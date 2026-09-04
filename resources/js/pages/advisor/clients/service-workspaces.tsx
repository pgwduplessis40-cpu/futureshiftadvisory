import { Link, router } from '@inertiajs/react';
import {
    Brain,
    CheckCircle2,
    FileCheck2,
    FileSpreadsheet,
    FileText,
    ListChecks,
    MessageSquarePlus,
    RefreshCw,
    RotateCcw,
    Send,
    ShieldAlert,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { ComponentType, ReactNode } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatNzdCurrency, formatNzDate } from '@/lib/formatters';
import { cn } from '@/lib/utils';
type AdvisorServiceTabKey =
    | 'overview'
    | 'due_diligence'
    | 'business_plan_budget'
    | 'advisory_access'
    | 'standard_advisory'
    | 'founding_advisory'
    | 'npo'
    | 'strategic_plan';

type AdvisorServiceTab = {
    key: AdvisorServiceTabKey;
    label: string;
    description: string;
    status?: ReactNode;
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
};

type DueDiligenceDecisionReadiness = {
    ready: boolean;
    client_release_ready: boolean;
    label: string;
    decision_label: string;
    decision_headline: string;
    decision_status: string;
    confidence: string;
    confidence_reason: string;
    recommendation: string;
    recommendation_rationale: string;
    completed_workstreams: number;
    required_workstreams: number;
    evidence_item_count: number;
    finding_count: number;
    verified_finding_count: number;
    flagged_finding_count: number;
    material_risk_count: number;
    deal_killer_risk_count: number;
    major_risk_count: number;
    total_risk_count: number;
    price_adjustment_nzd: number;
    valuation_midpoint_nzd: number | null;
    review_status: string | null;
    release_label: string;
    gates: Array<{
        key: string;
        label: string;
        passed: boolean;
        detail: string;
    }>;
    blockers: string[];
    decision_questions: Array<{
        question: string;
        answer: string;
        status: string;
    }>;
};

type DueDiligenceFeedbackPriority = {
    rank: number;
    key: string;
    title: string;
    score: number;
    summary: string;
    suggested_next_step: string;
    status: string;
    status_label: string;
};

type DueDiligenceSuggestedReply = {
    id: string | null;
    status: string;
    status_label: string;
    advisor_feedback: string;
    proposed_reply: string;
    suggested_feedback: string;
    suggested_reply: string;
    priorities: DueDiligenceFeedbackPriority[];
    saved_at: string | null;
    sent_at: string | null;
    can_save: boolean;
    can_send: boolean;
    action_url: string;
    message_url: string | null;
};

type DueDiligenceReportVersion = {
    id: string;
    version: number;
    type: string;
    type_label: string;
    title: string;
    generated_at: string | null;
    review_status: string | null;
    review_status_label: string;
    render_status: string | null;
    render_status_label: string;
    decision_label: string;
    confidence: string;
    recommendation: string;
    recommendation_label: string;
    gates_passed: number;
    gates_total: number;
    sections_count: number;
    report_url: string;
    feedback_status: string;
    feedback_status_label: string;
    feedback_sent_at: string | null;
    suggested_reply_excerpt: string;
    message_url: string | null;
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
    assessment_ready: boolean;
    assessment_status_label: string;
    assessment_summary: string;
    decision_readiness: DueDiligenceDecisionReadiness;
    report_title: string | null;
    report_generated_at: string | null;
    report_review_status: string | null;
    report_url: string | null;
    report_review_url: string | null;
    suggested_reply: DueDiligenceSuggestedReply;
    report_versions: DueDiligenceReportVersion[];
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

type StrategicBudgetSourceFinancial = {
    id: string;
    filename: string;
    detected_as: string;
    uploaded_at: string | null;
};

type StrategicBudgetFlag = {
    key: string;
    title: string;
    message: string;
    severity: string;
};

type StrategicBudgetFrameworkReadout = {
    summary?: string;
    explanation?: string;
    findings?: string[];
};

type StrategicBudgetAnalytics = {
    descriptive?: StrategicBudgetFrameworkReadout;
    diagnostic?: StrategicBudgetFrameworkReadout;
    predictive?: StrategicBudgetFrameworkReadout;
    prescriptive?: StrategicBudgetFrameworkReadout & {
        advisor_decision_points?: string[];
        actions?: Array<{
            priority?: string;
            action?: string;
            reason?: string;
        }>;
    };
};

type StrategicBudgetAssessmentCriterion = {
    key: string;
    title: string;
    status: 'met' | 'review' | 'missing';
    status_label: string;
    score: number;
    summary: string;
    evidence: string[];
};

type StrategicBudgetAssessmentPriority = {
    rank: number;
    key: string;
    title: string;
    score: number;
    status: string;
    status_label: string;
    summary: string;
    evidence: string[];
    suggested_next_step: string;
};

type StrategicBudgetAssessmentFeedback = {
    id: string | null;
    version: number | null;
    status: string;
    status_label: string;
    advisor_feedback: string;
    proposed_reply: string;
    suggested_feedback: string;
    suggested_reply: string;
    priorities: StrategicBudgetAssessmentPriority[];
    sent_at: string | null;
    saved_at: string | null;
    can_save: boolean;
    can_send: boolean;
    action_url: string;
    message_url: string | null;
};

type StrategicBudgetAssessmentHistoryRow = {
    id: string;
    version: number;
    status: string;
    status_label: string;
    submitted_at: string | null;
    assessed_at: string | null;
    feedback_sent_at: string | null;
    approved_at: string | null;
    readiness_score: number | null;
    business_plan_score: number | null;
    budget_confidence_score: number | null;
    score_delta: number | null;
    priorities: StrategicBudgetAssessmentPriority[];
    suggested_reply_excerpt: string;
    message_url: string | null;
    snapshot_available: boolean;
    snapshot_captured_at: string | null;
};

type StrategicBudgetSummary = {
    id: string;
    label: string;
    pathway: string;
    status: string;
    status_label: string;
    locked: boolean;
    horizon_months: number;
    expected_runway_months: number | null;
    source_financials: {
        count?: number;
        system_review?: string;
        items?: StrategicBudgetSourceFinancial[];
    };
    business_plan_readiness_score: number;
    business_plan_ready: boolean;
    business_plan_submitted_at: string | null;
    business_plan_approved_at: string | null;
    computed: {
        total_launch_costs?: number;
        monthly_fixed_costs?: number;
        total_funding?: number;
        available_after_launch?: number;
        break_even_year?: number | null;
        cash_flow_positive_year?: number | null;
        runway_months?: number | null;
        runway_open_ended?: boolean;
    };
    flags: StrategicBudgetFlag[];
    analytics: StrategicBudgetAnalytics;
    assessment_criteria: StrategicBudgetAssessmentCriterion[];
    confidence: {
        score?: number;
        progress_score?: number;
        overall?: string;
        message?: string;
    };
    readiness_score: number;
    progress_score: number;
    submitted_at: string | null;
    approved_at: string | null;
    used_in_proposal_at: string | null;
    accepted_snapshot_at: string | null;
    approve_url: string;
    run_assessment_url: string;
    can_run_assessment: boolean;
    assessment_ready_for_approval: boolean;
    assessment_action_label: string;
    assessment_feedback: StrategicBudgetAssessmentFeedback;
    assessment_history: StrategicBudgetAssessmentHistoryRow[];
    review_submitted_or_later: boolean;
    review_approved_or_later: boolean;
};

type StrategicPlanDeploymentGuard = {
    allowed: boolean;
    missing: string[];
    message: string | null;
};

type ProposalBudgetGuard = {
    id: string;
    status: string;
    status_label: string;
    approved: boolean;
    confidence_score: number;
    warning: string | null;
};

function DueDiligenceTargetPanel({
    payload,
    reportStoreUrl,
}: {
    payload: DueDiligenceSummary;
    reportStoreUrl: string;
}) {
    const [queuedReportType, setQueuedReportType] = useState<
        'due_diligence' | 'acquisition_go_no_go_report' | null
    >(null);
    const decisionReadiness = payload.decision_readiness;
    const evidenceCount = payload.data_room.workstreams.reduce(
        (total, workstream) => total + workstream.item_count,
        0,
    );
    const reportReviewed = ['reviewed', 'not_required'].includes(
        payload.report_review_status ?? '',
    );
    const canMarkReportReviewed = Boolean(
        payload.report_review_url &&
        payload.report_review_status === 'pending_review',
    );
    const generateDdReport = (
        type: 'due_diligence' | 'acquisition_go_no_go_report',
    ) => {
        const copy =
            type === 'due_diligence'
                ? {
                      starting: 'Queueing DD assessment...',
                      success:
                          'DD assessment has been queued for background generation. Refresh this workspace in a minute if the report is still rendering.',
                      error: 'The DD assessment could not be started.',
                  }
                : {
                      starting: 'Queueing DD decision report...',
                      success:
                          'DD decision report has been queued for background generation. Refresh this workspace in a minute if the report is still rendering.',
                      error: 'The DD decision report could not be started.',
                  };

        router.post(
            reportStoreUrl,
            { type },
            {
                preserveScroll: true,
                onStart: () => {
                    setQueuedReportType(type);
                    toast.message(copy.starting);
                },
                onSuccess: () => toast.success(copy.success),
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];

                    toast.error(
                        typeof firstError === 'string'
                            ? firstError
                            : copy.error,
                    );
                },
                onFinish: () => setQueuedReportType(null),
            },
        );
    };
    const markReportReviewed = () => {
        if (!payload.report_review_url) {
            return;
        }

        router.patch(payload.report_review_url, {}, { preserveScroll: true });
    };

    return (
        <section
            id="section-due-diligence"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="space-y-3 rounded-md border bg-muted/20 p-3">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <ListChecks className="size-4" aria-hidden="true" />
                            <h3 className="text-sm font-medium">
                                Action panel
                            </h3>
                            <Badge variant="outline">
                                DD assessment actions
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Generate the DD assessment, produce the buyer
                            decision report, and complete advisor review from
                            one place.
                        </p>
                    </div>
                </div>

                <div className="grid gap-3 lg:grid-cols-3">
                    <article className="space-y-3 rounded-md border bg-background p-3">
                        <div className="space-y-1">
                            <h4 className="text-sm font-medium">
                                DD assessment
                            </h4>
                            <p className="text-xs text-muted-foreground">
                                Refresh the evidence, valuation, risk register,
                                recommendation, and buyer-readiness gates.
                            </p>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={queuedReportType !== null}
                            aria-busy={queuedReportType === 'due_diligence'}
                            onClick={() => generateDdReport('due_diligence')}
                        >
                            <RefreshCw
                                className={cn(
                                    'size-4',
                                    queuedReportType === 'due_diligence' &&
                                        'animate-spin',
                                )}
                                aria-hidden="true"
                            />
                            {queuedReportType === 'due_diligence'
                                ? 'Queueing DD assessment'
                                : 'Run DD assessment'}
                        </Button>
                    </article>

                    <article className="space-y-3 rounded-md border bg-background p-3">
                        <div className="space-y-1">
                            <h4 className="text-sm font-medium">
                                DD decision report
                            </h4>
                            <p className="text-xs text-muted-foreground">
                                Generate the client-facing buy / renegotiate /
                                walk-away decision report.
                            </p>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            disabled={queuedReportType !== null}
                            aria-busy={
                                queuedReportType ===
                                'acquisition_go_no_go_report'
                            }
                            onClick={() =>
                                generateDdReport('acquisition_go_no_go_report')
                            }
                        >
                            <FileText
                                className={cn(
                                    'size-4',
                                    queuedReportType ===
                                        'acquisition_go_no_go_report' &&
                                        'animate-pulse',
                                )}
                                aria-hidden="true"
                            />
                            {queuedReportType === 'acquisition_go_no_go_report'
                                ? 'Queueing decision report'
                                : 'Generate DD decision report'}
                        </Button>
                    </article>

                    <article className="space-y-3 rounded-md border bg-background p-3">
                        <div className="space-y-1">
                            <h4 className="text-sm font-medium">
                                Advisor review
                            </h4>
                            <p className="text-xs text-muted-foreground">
                                {reportReviewed
                                    ? 'The latest DD report has already been marked reviewed.'
                                    : 'Mark the latest DD report reviewed only after advisor review is complete.'}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {payload.report_url ? (
                                <Button asChild size="sm" variant="outline">
                                    <a
                                        href={payload.report_url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <FileText
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        View DD PDF
                                    </a>
                                </Button>
                            ) : null}
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={!canMarkReportReviewed}
                                onClick={markReportReviewed}
                            >
                                <ListChecks
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {reportReviewed
                                    ? 'DD report reviewed'
                                    : 'Mark DD report reviewed'}
                            </Button>
                        </div>
                    </article>
                </div>
            </div>

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <div className="flex items-center gap-2">
                        <ShieldAlert className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Due Diligence</h2>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Assess the acquisition target, DD evidence, and the
                        reviewed DD report before relying on the Business Plan
                        &amp; Budget.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {formatLabel(payload.status)}
                    </Badge>
                    <Badge variant="outline">{payload.questionnaire.set}</Badge>
                    <Badge
                        variant={
                            payload.assessment_ready ? 'secondary' : 'outline'
                        }
                    >
                        {payload.assessment_status_label}
                    </Badge>
                    <Badge
                        variant={
                            decisionReadiness.client_release_ready
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {decisionReadiness.label}
                    </Badge>
                </div>
            </div>

            <div className="grid gap-3 md:grid-cols-5">
                <Metric label="Target" value={payload.target_name} />
                <Metric
                    label="Questionnaire"
                    value={payload.questionnaire.title}
                />
                <Metric label="Evidence" value={`${evidenceCount} items`} />
                <Metric
                    label="Buyer decision"
                    value={decisionReadiness.decision_label}
                    hint={`${formatLabel(decisionReadiness.confidence)} confidence`}
                />
                <Metric
                    label="Assessment"
                    value={
                        payload.assessment_ready
                            ? 'Ready'
                            : payload.assessment_status_label
                    }
                    hint={formatDate(payload.report_generated_at)}
                />
            </div>

            <div
                className={cn(
                    'rounded-md border p-3 text-sm',
                    payload.assessment_ready
                        ? 'bg-emerald-50 text-emerald-950'
                        : 'bg-muted/30 text-muted-foreground',
                )}
            >
                {payload.assessment_summary}
            </div>

            <div className="space-y-3 rounded-md border bg-muted/20 p-3 text-sm">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <h3 className="font-medium">
                            Buyer decision readiness
                        </h3>
                        <p className="text-muted-foreground">
                            The DD report must help the client make an informed
                            buy, renegotiate, pause, or walk-away decision.
                        </p>
                    </div>
                    <Badge
                        variant={
                            decisionReadiness.client_release_ready
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {decisionReadiness.release_label}
                    </Badge>
                </div>
                <p>{decisionReadiness.decision_headline}</p>
                <div className="grid gap-3 md:grid-cols-3">
                    <Metric
                        label="Workstreams"
                        value={`${decisionReadiness.completed_workstreams}/${decisionReadiness.required_workstreams}`}
                        hint="Completed DD workstreams"
                    />
                    <Metric
                        label="Evidence quality"
                        value={`${decisionReadiness.verified_finding_count} verified`}
                        hint={`${decisionReadiness.flagged_finding_count} unresolved flags`}
                    />
                    <Metric
                        label="Risk and price"
                        value={`${decisionReadiness.material_risk_count} material risks`}
                        hint={`${formatCurrency(decisionReadiness.price_adjustment_nzd)} DD adjustment`}
                    />
                </div>
                <div className="grid gap-2 md:grid-cols-2">
                    {decisionReadiness.gates.map((gate) => (
                        <div
                            key={gate.key}
                            className="rounded-md border bg-background p-3"
                        >
                            <div className="flex items-start gap-2">
                                {gate.passed ? (
                                    <CheckCircle2
                                        className="mt-0.5 size-4 text-emerald-600"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <XCircle
                                        className="mt-0.5 size-4 text-destructive"
                                        aria-hidden="true"
                                    />
                                )}
                                <div>
                                    <p className="font-medium">{gate.label}</p>
                                    <p className="text-muted-foreground">
                                        {gate.detail}
                                    </p>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
                {decisionReadiness.blockers.length > 0 ? (
                    <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-950">
                        Open DD decision gaps:{' '}
                        {decisionReadiness.blockers.join(', ')}.
                    </div>
                ) : (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-emerald-950">
                        All DD decision-readiness gates are satisfied.
                    </div>
                )}
            </div>

            <DueDiligenceSuggestedReplyPanel
                feedback={payload.suggested_reply}
            />

            <DueDiligenceReportVersionsTable
                versions={payload.report_versions}
            />

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

function DueDiligenceSuggestedReplyPanel({
    feedback,
}: {
    feedback: DueDiligenceSuggestedReply;
}) {
    const feedbackKey = [
        feedback.id,
        feedback.advisor_feedback,
        feedback.proposed_reply,
        feedback.sent_at,
        feedback.saved_at,
    ].join(':');
    const [draft, setDraft] = useState<{
        key: string;
        advisorFeedback: string;
        proposedReply: string;
    } | null>(null);
    const [pending, setPending] = useState(false);
    const [errors, setErrors] = useState<Record<string, string | undefined>>(
        {},
    );
    const advisorFeedback =
        draft?.key === feedbackKey
            ? draft.advisorFeedback
            : feedback.advisor_feedback;
    const proposedReply =
        draft?.key === feedbackKey
            ? draft.proposedReply
            : feedback.proposed_reply;
    const updateDraft = (nextFeedback: string, nextReply: string) => {
        setDraft({
            key: feedbackKey,
            advisorFeedback: nextFeedback,
            proposedReply: nextReply,
        });
    };
    const resetToSuggested = () => {
        updateDraft(feedback.suggested_feedback, feedback.suggested_reply);
        setErrors({});
        toast.success('Suggested DD reply loaded into the draft.');
    };
    const saveFeedback = (sendToClient: boolean) => {
        if (!feedback.can_save || pending || feedback.action_url === '') {
            return;
        }

        router.patch(
            feedback.action_url,
            {
                advisor_feedback: advisorFeedback,
                proposed_reply: proposedReply,
                send_to_client: sendToClient,
            },
            {
                preserveScroll: true,
                onStart: () => {
                    setPending(true);
                    setErrors({});
                },
                onError: (formErrors) => {
                    setErrors({
                        advisor_feedback:
                            typeof formErrors.advisor_feedback === 'string'
                                ? formErrors.advisor_feedback
                                : undefined,
                        proposed_reply:
                            typeof formErrors.proposed_reply === 'string'
                                ? formErrors.proposed_reply
                                : undefined,
                    });
                },
                onSuccess: () => {
                    setDraft(null);
                    toast.success(
                        sendToClient
                            ? 'DD feedback sent to the client.'
                            : 'DD feedback draft saved.',
                    );
                },
                onFinish: () => setPending(false),
            },
        );
    };
    const canSubmit =
        feedback.can_save &&
        advisorFeedback.trim().length >= 10 &&
        proposedReply.trim().length >= 10 &&
        !pending;

    return (
        <div className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <MessageSquarePlus
                            className="size-4"
                            aria-hidden="true"
                        />
                        <h3 className="text-sm font-medium">
                            Suggested client reply
                        </h3>
                        <Badge
                            variant={assessmentVersionStatusVariant(
                                feedback.status,
                            )}
                        >
                            {feedback.status_label}
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Generated from the latest DD assessment and buyer
                        decision-readiness gates. The advisor can edit it, save
                        a private draft, or send it as a normal client message.
                    </p>
                </div>
                {feedback.message_url ? (
                    <Button asChild size="sm" variant="outline">
                        <Link href={feedback.message_url}>Open message</Link>
                    </Button>
                ) : null}
            </div>

            {!feedback.can_save ? (
                <div className="rounded-md bg-muted/30 p-3 text-sm text-muted-foreground">
                    Generate the DD assessment or DD decision report first. The
                    suggested reply will appear here once a DD report version
                    exists.
                </div>
            ) : (
                <>
                    {feedback.priorities.length > 0 && (
                        <div className="grid gap-3 lg:grid-cols-3">
                            {feedback.priorities.map((priority) => (
                                <article
                                    key={`${priority.rank}-${priority.key}`}
                                    className="space-y-2 rounded-md bg-muted/30 p-3 text-sm"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <h4 className="font-medium">
                                            {priority.rank}. {priority.title}
                                        </h4>
                                        <Badge
                                            variant={
                                                priority.status === 'met'
                                                    ? 'secondary'
                                                    : priority.status ===
                                                        'missing'
                                                      ? 'destructive'
                                                      : 'outline'
                                            }
                                        >
                                            {priority.score}/100
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground">
                                        {priority.summary}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {priority.suggested_next_step}
                                    </p>
                                </article>
                            ))}
                        </div>
                    )}

                    <div className="grid gap-4 xl:grid-cols-2">
                        <label className="grid gap-2 text-sm font-medium">
                            Private advisor summary
                            <textarea
                                value={advisorFeedback}
                                onChange={(event) =>
                                    updateDraft(
                                        event.target.value,
                                        proposedReply,
                                    )
                                }
                                rows={10}
                                aria-invalid={Boolean(errors.advisor_feedback)}
                                className="min-h-44 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError message={errors.advisor_feedback} />
                        </label>

                        <label className="grid gap-2 text-sm font-medium">
                            Proposed reply to client
                            <textarea
                                value={proposedReply}
                                onChange={(event) =>
                                    updateDraft(
                                        advisorFeedback,
                                        event.target.value,
                                    )
                                }
                                rows={10}
                                aria-invalid={Boolean(errors.proposed_reply)}
                                className="min-h-44 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError message={errors.proposed_reply} />
                        </label>
                    </div>

                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={pending}
                            onClick={resetToSuggested}
                        >
                            <RotateCcw className="size-4" aria-hidden="true" />
                            Reset to suggested draft
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={!canSubmit}
                            onClick={() => saveFeedback(false)}
                        >
                            <FileCheck2 className="size-4" aria-hidden="true" />
                            {pending ? 'Saving' : 'Save draft'}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            disabled={!canSubmit}
                            onClick={() => saveFeedback(true)}
                        >
                            <Send className="size-4" aria-hidden="true" />
                            {pending ? 'Sending' : 'Send to client'}
                        </Button>
                    </div>
                </>
            )}
        </div>
    );
}

function DueDiligenceReportVersionsTable({
    versions,
}: {
    versions: DueDiligenceReportVersion[];
}) {
    return (
        <div className="space-y-3 rounded-md border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <FileText className="size-4" aria-hidden="true" />
                        <h3 className="text-sm font-medium">DD versions</h3>
                        <Badge variant="outline">
                            {versions.length}{' '}
                            {versions.length === 1 ? 'version' : 'versions'}
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Each row is a generated DD assessment or decision report
                        version, with review state, decision-readiness, output,
                        and client-feedback state kept for audit and comparison.
                    </p>
                </div>
            </div>

            {versions.length === 0 ? (
                <div className="rounded-md bg-muted/30 p-3 text-sm text-muted-foreground">
                    No DD report versions have been generated yet.
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[980px] text-left text-sm">
                        <thead className="border-b text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Version
                                </th>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">
                                    Generated
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Review / render
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Decision
                                </th>
                                <th className="px-3 py-2 font-medium">Gates</th>
                                <th className="px-3 py-2 font-medium">
                                    Feedback
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Output
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {versions.map((version) => (
                                <tr key={version.id} className="align-top">
                                    <td className="px-3 py-3 font-medium">
                                        Version {version.version}
                                    </td>
                                    <td className="px-3 py-3">
                                        {version.type_label}
                                    </td>
                                    <td className="px-3 py-3 text-muted-foreground">
                                        {formatDate(version.generated_at)}
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="flex flex-wrap gap-2">
                                            <Badge
                                                variant={assessmentVersionStatusVariant(
                                                    version.review_status ?? '',
                                                )}
                                            >
                                                {version.review_status_label}
                                            </Badge>
                                            <Badge variant="outline">
                                                {version.render_status_label}
                                            </Badge>
                                        </div>
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="font-medium">
                                            {version.decision_label}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {version.recommendation_label} ·{' '}
                                            {formatLabel(version.confidence)}{' '}
                                            confidence
                                        </div>
                                    </td>
                                    <td className="px-3 py-3">
                                        {version.gates_passed}/
                                        {version.gates_total} passed
                                        <div className="text-xs text-muted-foreground">
                                            {version.sections_count} sections
                                        </div>
                                    </td>
                                    <td className="px-3 py-3">
                                        <Badge
                                            variant={assessmentVersionStatusVariant(
                                                version.feedback_status,
                                            )}
                                        >
                                            {version.feedback_status_label}
                                        </Badge>
                                        {version.suggested_reply_excerpt ? (
                                            <div className="mt-2 max-w-sm text-xs text-muted-foreground">
                                                {
                                                    version.suggested_reply_excerpt
                                                }
                                            </div>
                                        ) : null}
                                        {version.message_url ? (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                                className="mt-2"
                                            >
                                                <Link
                                                    href={version.message_url}
                                                >
                                                    Open message
                                                </Link>
                                            </Button>
                                        ) : null}
                                    </td>
                                    <td className="px-3 py-3">
                                        {version.render_status ===
                                        'composing' ? (
                                            <Badge variant="outline">
                                                PDF rendering
                                            </Badge>
                                        ) : version.render_status ===
                                          'render_failed' ? (
                                            <Badge variant="destructive">
                                                Render failed
                                            </Badge>
                                        ) : (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <a
                                                    href={version.report_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    View PDF
                                                </a>
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function BusinessPlanBudgetActionPanel({
    budget,
}: {
    budget: StrategicBudgetSummary;
}) {
    const [assessing, setAssessing] = useState(false);
    const [approving, setApproving] = useState(false);
    const canApprove =
        !budget.locked &&
        budget.business_plan_ready &&
        budget.review_submitted_or_later &&
        budget.assessment_ready_for_approval &&
        !budget.review_approved_or_later;
    const actionStatus = budget.review_approved_or_later
        ? 'Approved'
        : budget.review_submitted_or_later
          ? 'Submitted for review'
          : 'Waiting for client';
    const runBlockedReason = budget.locked
        ? 'Waiting for verified financial evidence before the assessment can run.'
        : !budget.business_plan_ready
          ? 'Waiting for the client to complete every BP&B section.'
          : !budget.review_submitted_or_later
            ? 'Waiting for the client to submit BP&B for advisor review.'
            : 'Assessment can be refreshed from the latest plan, DD context, evidence, and budget assumptions.';
    const approveBlockedReason = budget.review_approved_or_later
        ? 'The BP&B assessment is already approved.'
        : !budget.review_submitted_or_later
          ? 'Approval unlocks after the client submits BP&B for advisor review.'
          : !budget.business_plan_ready
            ? 'Approval unlocks after every BP&B section is complete.'
            : budget.locked
              ? 'Approval unlocks after verified financial evidence is available.'
              : !budget.assessment_ready_for_approval
                ? 'Approval unlocks after the BP&B assessment has been run.'
                : 'Approve only after the assessment has been reviewed.';
    const feedbackStatus = budget.assessment_feedback.sent_at
        ? 'Sent to client'
        : budget.assessment_feedback.saved_at
          ? 'Draft saved'
          : budget.assessment_feedback.can_save
            ? 'Draft ready'
            : 'Run assessment first';

    const runAssessment = () => {
        if (!budget.can_run_assessment || assessing) {
            return;
        }

        router.post(
            budget.run_assessment_url,
            {},
            {
                preserveScroll: true,
                onStart: () => setAssessing(true),
                onFinish: () => setAssessing(false),
                onSuccess: () =>
                    toast.success(
                        'Business Plan & Budget assessment refreshed.',
                    ),
            },
        );
    };

    const approveBudget = () => {
        if (!canApprove || approving) {
            return;
        }

        router.patch(
            budget.approve_url,
            {},
            {
                preserveScroll: true,
                onStart: () => setApproving(true),
                onFinish: () => setApproving(false),
                onSuccess: () =>
                    toast.success(
                        'Business Plan & Budget assessment approved.',
                    ),
            },
        );
    };

    return (
        <section
            id="section-strategic-budget-actions"
            className="space-y-4 rounded-md border bg-muted/20 p-4"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <ListChecks className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Action panel</h2>
                        <Badge
                            variant={
                                budget.review_approved_or_later
                                    ? 'secondary'
                                    : 'outline'
                            }
                        >
                            {actionStatus}
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        BP&amp;B-specific advisor actions sit here so assessment
                        and approval do not get mixed with advisory-service
                        goals.
                    </p>
                </div>
            </div>

            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <BusinessPlanBudgetActionTile
                    icon={Brain}
                    title="Run assessment"
                    value={budget.assessment_action_label}
                    explanation={runBlockedReason}
                >
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={!budget.can_run_assessment || assessing}
                        onClick={runAssessment}
                    >
                        <RefreshCw
                            className={cn(
                                'size-4',
                                assessing && 'animate-spin',
                            )}
                            aria-hidden="true"
                        />
                        {assessing
                            ? 'Running assessment'
                            : budget.assessment_action_label}
                    </Button>
                </BusinessPlanBudgetActionTile>

                <BusinessPlanBudgetActionTile
                    icon={FileText}
                    title="Review assessment"
                    value={`${budget.readiness_score}/100 readiness`}
                    explanation="Open the plain-English assessment record, including what we have, what needs judgement, funding readiness, and next advisor actions."
                >
                    <Button asChild size="sm" variant="outline">
                        <a href="#section-strategic-budget-assessment">
                            View assessment
                        </a>
                    </Button>
                </BusinessPlanBudgetActionTile>

                <BusinessPlanBudgetActionTile
                    icon={CheckCircle2}
                    title="Approve assessment"
                    value={
                        budget.review_approved_or_later
                            ? 'Approved'
                            : 'Advisor decision'
                    }
                    explanation={approveBlockedReason}
                >
                    <Button
                        type="button"
                        size="sm"
                        disabled={!canApprove || approving}
                        onClick={approveBudget}
                    >
                        <CheckCircle2 className="size-4" aria-hidden="true" />
                        {budget.review_approved_or_later
                            ? 'Assessment approved'
                            : approving
                              ? 'Approving'
                              : 'Approve assessment'}
                    </Button>
                </BusinessPlanBudgetActionTile>

                <BusinessPlanBudgetActionTile
                    icon={MessageSquarePlus}
                    title="Suggested reply"
                    value={feedbackStatus}
                    explanation="Use the BP&B assessment criteria to prepare a plain-English client reply, then save it or send it as a message."
                >
                    {budget.assessment_feedback.can_save ? (
                        <Button asChild size="sm" variant="outline">
                            <a href="#section-strategic-budget-feedback">
                                Open reply draft
                            </a>
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled
                        >
                            Open reply draft
                        </Button>
                    )}
                </BusinessPlanBudgetActionTile>
            </div>
        </section>
    );
}

function BusinessPlanBudgetActionTile({
    icon: Icon,
    title,
    value,
    explanation,
    children,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    title: string;
    value: ReactNode;
    explanation: string;
    children: ReactNode;
}) {
    return (
        <article className="flex h-full flex-col justify-between gap-3 rounded-md border bg-background p-4">
            <div className="space-y-2">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Icon className="size-4" aria-hidden={true} />
                    <span>{title}</span>
                </div>
                <div className="text-sm font-medium">{value}</div>
                <p className="text-xs text-muted-foreground">{explanation}</p>
            </div>
            <div>{children}</div>
        </article>
    );
}

function StrategicBudgetPanel({ budget }: { budget: StrategicBudgetSummary }) {
    const sourceItems = budget.source_financials.items ?? [];
    const confidenceScore = budget.confidence.score ?? 0;
    const assessmentStatus = budget.review_approved_or_later
        ? 'Assessment approved'
        : budget.status === 'submitted_for_review'
          ? 'Ready for assessment'
          : budget.business_plan_ready
            ? 'Ready when submitted'
            : 'Client input needed';
    const decisionPoints =
        budget.analytics.prescriptive?.advisor_decision_points ?? [];

    return (
        <section
            id="section-strategic-budget"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <FileSpreadsheet
                            className="size-4"
                            aria-hidden="true"
                        />
                        <h2 className="text-sm font-medium">{budget.label}</h2>
                        <Badge variant={budgetStatusVariant(budget.status)}>
                            {budget.status_label}
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Figures are maintained ex GST. GST is added only at
                        final Stripe collection.
                    </p>
                </div>
            </div>

            {budget.locked && (
                <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                    The client can see this task, but it stays locked until a
                    P&amp;L or management accounts file is uploaded and tagged
                    as financial evidence.
                </div>
            )}

            {!budget.business_plan_ready && (
                <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                    The client still needs to complete every plan section before
                    this combined plan and budget can be approved for proposal
                    readiness.
                </div>
            )}

            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <BudgetScore
                    label={
                        budget.pathway === 'npo'
                            ? 'Operating Plan'
                            : 'Business Plan'
                    }
                    value={budget.business_plan_readiness_score}
                />
                <BudgetScore label="Progress" value={budget.progress_score} />
                <BudgetScore label="Readiness" value={budget.readiness_score} />
                <Metric
                    label="Confidence"
                    value={`${confidenceScore}/100 ${budget.confidence.overall ?? ''}`.trim()}
                />
                <Metric
                    label="Horizon"
                    value={`${budget.horizon_months} months`}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                <div className="space-y-3 rounded-md border p-3">
                    <div className="flex items-center justify-between gap-3">
                        <h3 className="text-sm font-medium">
                            Source financials
                        </h3>
                        <Badge variant="outline">
                            {budget.source_financials.count ?? 0}
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {budget.source_financials.system_review ??
                            'No system review yet.'}
                    </p>
                    {sourceItems.length > 0 ? (
                        <div className="space-y-2">
                            {sourceItems.map((item) => (
                                <div
                                    key={item.id}
                                    className="rounded-md bg-muted/30 px-3 py-2 text-sm"
                                >
                                    <div className="font-medium">
                                        {item.filename}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {formatLabel(item.detected_as)} ·{' '}
                                        {formatDate(item.uploaded_at)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Waiting on a P&amp;L or management accounts upload.
                        </p>
                    )}
                </div>

                <div className="grid gap-3 rounded-md border p-3 text-sm md:grid-cols-2">
                    <Metric
                        label="Implementation costs"
                        value={formatCurrency(
                            budget.computed.total_launch_costs ?? 0,
                        )}
                    />
                    <Metric
                        label="Monthly fixed costs"
                        value={formatCurrency(
                            budget.computed.monthly_fixed_costs ?? 0,
                        )}
                    />
                    <Metric
                        label="Funding available"
                        value={formatCurrency(
                            budget.computed.total_funding ?? 0,
                        )}
                    />
                    <Metric
                        label="Runway"
                        value={
                            budget.computed.runway_open_ended
                                ? 'Open ended'
                                : budget.computed.runway_months !== null &&
                                    budget.computed.runway_months !== undefined
                                  ? `${budget.computed.runway_months} months`
                                  : '-'
                        }
                    />
                </div>
            </div>

            {budget.flags.length > 0 && (
                <div className="space-y-2 rounded-md border p-3">
                    <h3 className="text-sm font-medium">Readiness signals</h3>
                    <div className="grid gap-2 lg:grid-cols-2">
                        {budget.flags.map((flag) => (
                            <div
                                key={flag.key}
                                className="rounded-md bg-muted/30 p-3 text-sm"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">
                                        {flag.title}
                                    </span>
                                    <Badge
                                        variant={
                                            flag.severity === 'critical'
                                                ? 'destructive'
                                                : 'outline'
                                        }
                                    >
                                        {formatLabel(flag.severity)}
                                    </Badge>
                                </div>
                                <p className="mt-1 text-muted-foreground">
                                    {flag.message}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div
                id="section-strategic-budget-assessment"
                className="space-y-4 rounded-md border p-4"
            >
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <Brain className="size-4" aria-hidden="true" />
                            <h3 className="text-sm font-medium">
                                Business Plan &amp; Budget assessment
                            </h3>
                            <Badge
                                variant={
                                    budget.review_approved_or_later
                                        ? 'secondary'
                                        : 'outline'
                                }
                            >
                                {assessmentStatus}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            This is the advisor assessment record: plain-English
                            findings from the client plan, DD-sourced context,
                            financial evidence, budget assumptions, and funding
                            readiness.
                        </p>
                    </div>
                    <div className="text-sm text-muted-foreground">
                        {budget.readiness_score}/100 readiness
                    </div>
                </div>

                <AssessmentCriteriaPanel
                    criteria={budget.assessment_criteria}
                />

                <div className="grid gap-3 lg:grid-cols-2">
                    <AssessmentReadout
                        title="What we have"
                        readout={budget.analytics.descriptive}
                    />
                    <AssessmentReadout
                        title="What needs judgement"
                        readout={budget.analytics.diagnostic}
                    />
                    <AssessmentReadout
                        title="Funding readiness"
                        readout={budget.analytics.predictive}
                    />
                    <AssessmentReadout
                        title="Advisor next actions"
                        readout={budget.analytics.prescriptive}
                    />
                </div>

                {decisionPoints.length > 0 && (
                    <div className="space-y-2 rounded-md bg-muted/30 p-3">
                        <h4 className="text-sm font-medium">
                            Advisor decision points before approval
                        </h4>
                        <ul className="grid gap-1 text-sm text-muted-foreground">
                            {decisionPoints.map((point) => (
                                <li key={point} className="flex gap-2">
                                    <span className="mt-2 size-1.5 shrink-0 rounded-full bg-foreground/60" />
                                    <span>{point}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <BusinessPlanBudgetFeedbackPanel
                    feedback={budget.assessment_feedback}
                />
            </div>

            <StrategicBudgetAssessmentHistoryTable
                history={budget.assessment_history}
            />
        </section>
    );
}

function AssessmentCriteriaPanel({
    criteria,
}: {
    criteria: StrategicBudgetAssessmentCriterion[];
}) {
    if (criteria.length === 0) {
        return null;
    }

    return (
        <div className="space-y-3 rounded-md border bg-background p-3">
            <div className="space-y-1">
                <h4 className="text-sm font-medium">Assessment criteria</h4>
                <p className="text-sm text-muted-foreground">
                    The BP&amp;B assessment checks the funding-quality plan
                    against DD evidence, forecast assumptions, funding
                    readiness, and advisor/funder reliance.
                </p>
            </div>

            <div className="grid gap-3 lg:grid-cols-3">
                {criteria.map((criterion) => (
                    <article
                        key={criterion.key}
                        className="space-y-3 rounded-md bg-muted/30 p-3"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div className="space-y-1">
                                <h5 className="text-sm font-medium">
                                    {criterion.title}
                                </h5>
                                <div className="text-xs text-muted-foreground">
                                    {criterion.score}/100
                                </div>
                            </div>
                            <Badge
                                variant={criterionStatusVariant(
                                    criterion.status,
                                )}
                            >
                                {criterion.status_label}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {criterion.summary}
                        </p>
                        {criterion.evidence.length > 0 && (
                            <ul className="grid gap-1 text-xs text-muted-foreground">
                                {criterion.evidence.slice(0, 3).map((item) => (
                                    <li key={item} className="flex gap-2">
                                        <span className="mt-1.5 size-1 shrink-0 rounded-full bg-foreground/50" />
                                        <span>{item}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </article>
                ))}
            </div>
        </div>
    );
}

function AssessmentReadout({
    title,
    readout,
}: {
    title: string;
    readout?: StrategicBudgetFrameworkReadout;
}) {
    const findings =
        readout?.findings && readout.findings.length > 0
            ? readout.findings.slice(0, 3)
            : readout?.summary
              ? [readout.summary]
              : ['No assessment detail is available yet.'];

    return (
        <article className="space-y-3 rounded-md bg-muted/30 p-3">
            <div>
                <h4 className="text-sm font-medium">{title}</h4>
                {readout?.summary && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {readout.summary}
                    </p>
                )}
            </div>
            <div className="grid gap-2 text-sm text-muted-foreground">
                {findings.map((finding) => (
                    <div key={finding} className="flex gap-2">
                        <span className="mt-2 size-1.5 shrink-0 rounded-full bg-foreground/60" />
                        <span>{finding}</span>
                    </div>
                ))}
            </div>
        </article>
    );
}

function BusinessPlanBudgetFeedbackPanel({
    feedback,
}: {
    feedback: StrategicBudgetAssessmentFeedback;
}) {
    const feedbackKey = [
        feedback.id,
        feedback.advisor_feedback,
        feedback.proposed_reply,
        feedback.sent_at,
        feedback.saved_at,
    ].join(':');
    const [draft, setDraft] = useState<{
        key: string;
        advisorFeedback: string;
        proposedReply: string;
    } | null>(null);
    const [pending, setPending] = useState(false);
    const [errors, setErrors] = useState<Record<string, string | undefined>>(
        {},
    );
    const advisorFeedback =
        draft?.key === feedbackKey
            ? draft.advisorFeedback
            : feedback.advisor_feedback;
    const proposedReply =
        draft?.key === feedbackKey
            ? draft.proposedReply
            : feedback.proposed_reply;
    const updateDraft = (nextFeedback: string, nextReply: string) => {
        setDraft({
            key: feedbackKey,
            advisorFeedback: nextFeedback,
            proposedReply: nextReply,
        });
    };
    const resetToSuggested = () => {
        updateDraft(feedback.suggested_feedback, feedback.suggested_reply);
        setErrors({});
        toast.success('Suggested BP&B reply loaded into the draft.');
    };
    const saveFeedback = (sendToClient: boolean) => {
        if (!feedback.can_save || pending) {
            return;
        }

        router.patch(
            feedback.action_url,
            {
                advisor_feedback: advisorFeedback,
                proposed_reply: proposedReply,
                send_to_client: sendToClient,
            },
            {
                preserveScroll: true,
                onStart: () => {
                    setPending(true);
                    setErrors({});
                },
                onError: (formErrors) => {
                    setErrors({
                        advisor_feedback:
                            typeof formErrors.advisor_feedback === 'string'
                                ? formErrors.advisor_feedback
                                : undefined,
                        proposed_reply:
                            typeof formErrors.proposed_reply === 'string'
                                ? formErrors.proposed_reply
                                : undefined,
                    });
                },
                onSuccess: () => {
                    setDraft(null);
                    toast.success(
                        sendToClient
                            ? 'BP&B feedback sent to the client.'
                            : 'BP&B feedback draft saved.',
                    );
                },
                onFinish: () => setPending(false),
            },
        );
    };
    const canSubmit =
        feedback.can_save &&
        advisorFeedback.trim().length >= 10 &&
        proposedReply.trim().length >= 10 &&
        !pending;

    return (
        <div
            id="section-strategic-budget-feedback"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <MessageSquarePlus
                            className="size-4"
                            aria-hidden="true"
                        />
                        <h4 className="text-sm font-medium">
                            Suggested client reply
                        </h4>
                        <Badge
                            variant={assessmentVersionStatusVariant(
                                feedback.status,
                            )}
                        >
                            {feedback.status_label}
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Generated from the latest BP&amp;B assessment criteria.
                        The advisor can edit it, save a private draft, or send
                        it as a normal client message.
                    </p>
                </div>
                {feedback.message_url ? (
                    <Button asChild size="sm" variant="outline">
                        <Link href={feedback.message_url}>Open message</Link>
                    </Button>
                ) : null}
            </div>

            {!feedback.can_save ? (
                <div className="rounded-md bg-muted/30 p-3 text-sm text-muted-foreground">
                    Run the BP&amp;B assessment first. The suggested reply will
                    appear here once the assessment version has been created.
                </div>
            ) : (
                <>
                    {feedback.priorities.length > 0 && (
                        <div className="grid gap-3 lg:grid-cols-3">
                            {feedback.priorities.map((priority) => (
                                <article
                                    key={`${priority.rank}-${priority.key}`}
                                    className="space-y-2 rounded-md bg-muted/30 p-3 text-sm"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <h5 className="font-medium">
                                            {priority.rank}. {priority.title}
                                        </h5>
                                        <Badge variant="outline">
                                            {priority.score}/100
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground">
                                        {priority.summary}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {priority.suggested_next_step}
                                    </p>
                                </article>
                            ))}
                        </div>
                    )}

                    <div className="grid gap-4 xl:grid-cols-2">
                        <label className="grid gap-2 text-sm font-medium">
                            Private advisor summary
                            <textarea
                                value={advisorFeedback}
                                onChange={(event) =>
                                    updateDraft(
                                        event.target.value,
                                        proposedReply,
                                    )
                                }
                                rows={10}
                                aria-invalid={Boolean(errors.advisor_feedback)}
                                className="min-h-44 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError message={errors.advisor_feedback} />
                        </label>

                        <label className="grid gap-2 text-sm font-medium">
                            Proposed reply to client
                            <textarea
                                value={proposedReply}
                                onChange={(event) =>
                                    updateDraft(
                                        advisorFeedback,
                                        event.target.value,
                                    )
                                }
                                rows={10}
                                aria-invalid={Boolean(errors.proposed_reply)}
                                className="min-h-44 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError message={errors.proposed_reply} />
                        </label>
                    </div>

                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={pending}
                            onClick={resetToSuggested}
                        >
                            <RotateCcw className="size-4" aria-hidden="true" />
                            Reset to suggested draft
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={!canSubmit}
                            onClick={() => saveFeedback(false)}
                        >
                            <FileCheck2 className="size-4" aria-hidden="true" />
                            {pending ? 'Saving' : 'Save draft'}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            disabled={!canSubmit}
                            onClick={() => saveFeedback(true)}
                        >
                            <Send className="size-4" aria-hidden="true" />
                            {pending ? 'Sending' : 'Send to client'}
                        </Button>
                    </div>
                </>
            )}
        </div>
    );
}

function StrategicBudgetAssessmentHistoryTable({
    history,
}: {
    history: StrategicBudgetAssessmentHistoryRow[];
}) {
    return (
        <div className="space-y-3 rounded-md border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <FileText className="size-4" aria-hidden="true" />
                        <h3 className="text-sm font-medium">
                            BP&amp;B versions
                        </h3>
                        <Badge variant="outline">
                            {history.length}{' '}
                            {history.length === 1 ? 'version' : 'versions'}
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Each row is a submitted Business Plan &amp; Budget
                        snapshot, with the assessment result and feedback state
                        kept for audit and comparison.
                    </p>
                </div>
            </div>

            {history.length === 0 ? (
                <div className="rounded-md bg-muted/30 p-3 text-sm text-muted-foreground">
                    No submitted BP&amp;B versions have been assessed yet.
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[920px] text-left text-sm">
                        <thead className="border-b text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Version
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Submitted
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Assessed
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Readiness
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Top priorities
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Feedback
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {history.map((row) => (
                                <tr key={row.id} className="align-top">
                                    <td className="px-3 py-3 font-medium">
                                        Version {row.version}
                                    </td>
                                    <td className="px-3 py-3">
                                        <Badge
                                            variant={assessmentVersionStatusVariant(
                                                row.status,
                                            )}
                                        >
                                            {row.status_label}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-3 text-muted-foreground">
                                        {formatDate(row.submitted_at)}
                                    </td>
                                    <td className="px-3 py-3 text-muted-foreground">
                                        {formatDate(row.assessed_at)}
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="font-medium">
                                            {scoreLabel(row.readiness_score)}
                                            {row.score_delta !== null && (
                                                <span
                                                    className={cn(
                                                        'ml-2 text-xs',
                                                        row.score_delta >= 0
                                                            ? 'text-emerald-700'
                                                            : 'text-red-700',
                                                    )}
                                                >
                                                    {row.score_delta >= 0
                                                        ? '+'
                                                        : ''}
                                                    {row.score_delta}
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            Plan{' '}
                                            {scoreLabel(
                                                row.business_plan_score,
                                            )}{' '}
                                            · Budget{' '}
                                            {scoreLabel(
                                                row.budget_confidence_score,
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-3 py-3">
                                        {row.priorities.length > 0 ? (
                                            <ul className="grid gap-1 text-xs text-muted-foreground">
                                                {row.priorities.map(
                                                    (priority) => (
                                                        <li
                                                            key={`${row.id}-${priority.rank}-${priority.key}`}
                                                        >
                                                            {priority.title}:{' '}
                                                            {
                                                                priority.status_label
                                                            }
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                No priorities recorded.
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="max-w-sm text-xs text-muted-foreground">
                                            {row.suggested_reply_excerpt ||
                                                'No feedback draft yet.'}
                                        </div>
                                        {row.message_url ? (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                                className="mt-2"
                                            >
                                                <Link href={row.message_url}>
                                                    Open message
                                                </Link>
                                            </Button>
                                        ) : null}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function BudgetScore({ label, value }: { label: string; value: number }) {
    const safeValue = Math.max(0, Math.min(100, value));

    return (
        <div className="rounded-md border p-4">
            <div className="flex items-center justify-between gap-3 text-xs text-muted-foreground">
                <span>{label}</span>
                <span>{safeValue}/100</span>
            </div>
            <div className="mt-3 h-2 rounded-full bg-muted">
                <div
                    className="h-2 rounded-full bg-[var(--fs-admiralty)]"
                    style={{ width: `${safeValue}%` }}
                />
            </div>
        </div>
    );
}

function AdvisorServiceTabList({
    tabs,
    activeTab,
    onChange,
}: {
    tabs: AdvisorServiceTab[];
    activeTab: AdvisorServiceTabKey;
    onChange: (tab: AdvisorServiceTabKey) => void;
}) {
    return (
        <div
            className="inline-flex max-w-full overflow-x-auto rounded-md border bg-muted/30 p-1"
            role="tablist"
            aria-label="Client service workspaces"
        >
            <div className="flex min-w-max gap-1">
                {tabs.map((tab) => (
                    <AdvisorServiceTabButton
                        key={tab.key}
                        tab={tab}
                        active={activeTab === tab.key}
                        onClick={() => onChange(tab.key)}
                    />
                ))}
            </div>
        </div>
    );
}

function AdvisorServiceTabButton({
    tab,
    active,
    onClick,
}: {
    tab: AdvisorServiceTab;
    active: boolean;
    onClick: () => void;
}) {
    const Icon = tab.icon;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    role="tab"
                    aria-selected={active}
                    className={cn(
                        'flex shrink-0 items-center gap-2 rounded-sm px-3 py-2 text-left text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                        active && 'bg-background text-foreground shadow-xs',
                    )}
                    onClick={onClick}
                >
                    <span className="flex shrink-0 items-center gap-2 whitespace-nowrap">
                        <Icon className="size-4 shrink-0" aria-hidden={true} />
                        <span>{tab.label}</span>
                    </span>
                    {tab.status && (
                        <Badge
                            variant={active ? 'secondary' : 'outline'}
                            className="shrink-0"
                        >
                            {tab.status}
                        </Badge>
                    )}
                </button>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {tab.description}
            </TooltipContent>
        </Tooltip>
    );
}

function Metric({
    label,
    value,
    hint,
    children,
}: {
    label: string;
    value?: string;
    hint?: string | null;
    children?: ReactNode;
}) {
    return (
        <div className="rounded-md border p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-2 text-sm font-medium">{children ?? value}</div>
            {hint && (
                <div className="mt-1 text-xs text-muted-foreground">{hint}</div>
            )}
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

function formatDate(value: string | null) {
    if (!value) {
        return '-';
    }

    return formatNzDate(value);
}

function formatLabel(value: string) {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function stringDetail(value: string | number | boolean | null | undefined) {
    return value === null || value === undefined ? null : String(value);
}

function formatCurrency(value: number) {
    return formatNzdCurrency(value);
}

function budgetStatusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (
        [
            'advisor_approved',
            'used_in_proposal',
            'accepted_proposal_snapshot',
        ].includes(status)
    ) {
        return 'secondary';
    }

    if (status === 'locked') {
        return 'destructive';
    }

    return 'outline';
}

function criterionStatusVariant(
    status: StrategicBudgetAssessmentCriterion['status'],
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'met') {
        return 'secondary';
    }

    if (status === 'missing') {
        return 'destructive';
    }

    return 'outline';
}

function assessmentVersionStatusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (['approved', 'feedback_sent'].includes(status)) {
        return 'secondary';
    }

    if (status === 'not_started') {
        return 'destructive';
    }

    return 'outline';
}

function scoreLabel(value: number | null) {
    return value === null ? '-' : `${value}/100`;
}

export {
    AdvisorServiceTabList,
    BusinessPlanBudgetActionPanel,
    DueDiligenceTargetPanel,
    StrategicBudgetPanel,
};

export type {
    AdvisorServiceTab,
    AdvisorServiceTabKey,
    DueDiligenceSummary,
    ProposalBudgetGuard,
    StrategicBudgetSummary,
    StrategicPlanDeploymentGuard,
};
