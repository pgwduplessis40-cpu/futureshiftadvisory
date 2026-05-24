import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    Brain,
    CalendarClock,
    CheckCircle2,
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
    ShieldAlert,
    Star,
    Target,
    TrendingUp,
    Undo2,
    Unplug,
    Upload,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { DataQualityBadge } from '@/components/data-quality/DataQualityBadge';
import type { DataQualitySummary } from '@/components/data-quality/DataQualityBadge';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useDrillFocus } from '@/hooks/use-drill-focus';
import type { ClientSummary } from './types';

type ClientDetail = ClientSummary & {
    data_quality_summary: DataQualitySummary;
    wellbeing_trend: WellbeingPoint[] | null;
    offboarding: OffboardingSummary | null;
    status_options: StatusOption[];
    lifecycle_update_url: string;
    knowledge_assessment_store_url: string;
    latest_knowledge_assessment: KnowledgeAssessmentSummary | null;
    goal_store_url: string;
    goals: GoalDashboard;
    proposal_store_url: string;
    proposal_expiry_days: number;
    fee_calculations: FeeCalculationSummary[];
    proposals: ProposalSummary[];
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
    created_at: string | null;
    analysis_findings: AnalysisFindingFeedback[];
    due_diligence: DueDiligenceSummary | null;
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
};

type MeetingSummary = {
    id: string;
    title: string;
    scheduled_at: string | null;
    location: string | null;
    link: string | null;
    attendees: string[];
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

export default function ClientsShow({ client, conflictDeclaration }: Props) {
    useDrillFocus();

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

    return (
        <>
            <Head title={client.legal_name} />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {client.legal_name}
                        </h1>
                        <div className="text-sm text-muted-foreground">
                            {client.engagement_type_label}
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

                <div
                    id="section-overview"
                    className="grid gap-4 md:grid-cols-3"
                >
                    <Metric label="NZBN" value={client.nzbn ?? '-'} />
                    <Metric label="Lifecycle">
                        <Badge variant={statusVariant(client.status)}>
                            {client.status_label}
                        </Badge>
                    </Metric>
                    <Metric label="Data quality">
                        <div id="section-questionnaire">
                            <div id="section-documents">
                                <DataQualityBadge
                                    summary={client.data_quality_summary}
                                />
                            </div>
                        </div>
                    </Metric>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="space-y-4 rounded-md border p-4">
                        <h2 className="text-sm font-medium">Registry</h2>
                        <dl className="grid gap-3 text-sm">
                            <Detail label="Entity" value={client.entity_type} />
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
                            {Object.entries(client.registry_sources).map(
                                ([service, badge]) => (
                                    <Badge key={service} variant="secondary">
                                        {service}: {badge}
                                    </Badge>
                                ),
                            )}
                        </div>
                    </section>

                    <section className="space-y-4 rounded-md border p-4">
                        <div className="flex items-center gap-2">
                            <h2 className="text-sm font-medium">Engagement</h2>
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
                                    conflictDeclaration ? 'declared' : 'missing'
                                }
                            />
                            <Detail
                                label="Offboarding"
                                value={
                                    client.offboarding
                                        ? formatDate(
                                              client.offboarding.triggered_at,
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
                    </section>
                </div>

                {client.due_diligence && (
                    <DueDiligenceTargetPanel payload={client.due_diligence} />
                )}

                <KnowledgeAssessmentPanel client={client} />

                <GoalsPanel client={client} />

                <AccountingConnectionsPanel client={client} />

                <ProposalsPanel client={client} />

                <ReportsPanel client={client} />

                <MeetingsBriefingsPanel client={client} />

                <section className="space-y-4 rounded-md border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <RotateCcw className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">Lifecycle</h2>
                            <Badge variant={statusVariant(client.status)}>
                                {client.status_label}
                            </Badge>
                        </div>
                        <div className="text-xs text-muted-foreground">
                            Portal access is revoked while suspended.
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="lifecycle_reason">Reason</Label>
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
                        <InputError message={lifecycleForm.errors.reason} />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {lifecycleActions(client.status).map((action) => {
                            const Icon = lifecycleIcon(action.status);

                            return (
                                <Button
                                    key={action.status}
                                    type="button"
                                    variant={
                                        action.status === 'suspended'
                                            ? 'destructive'
                                            : 'outline'
                                    }
                                    disabled={lifecycleForm.processing}
                                    onClick={() =>
                                        submitLifecycle(action.status)
                                    }
                                >
                                    <Icon
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {action.label}
                                </Button>
                            );
                        })}
                    </div>
                    <InputError message={lifecycleForm.errors.status} />
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
                        <Badge variant="outline">
                            {client.analysis_findings.length}
                        </Badge>
                    </div>

                    {client.analysis_findings.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No analysis findings yet.
                        </p>
                    ) : (
                        <div className="space-y-4">
                            {client.analysis_findings.map((finding) => (
                                <FindingFeedbackCard
                                    key={finding.id}
                                    finding={finding}
                                />
                            ))}
                        </div>
                    )}
                </section>

                {client.wellbeing_trend && (
                    <section className="space-y-4 rounded-md border p-4">
                        <div className="flex items-center gap-2">
                            <HeartPulse className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">Wellbeing</h2>
                        </div>
                        <WellbeingTrend points={client.wellbeing_trend} />
                    </section>
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
                <Label htmlFor={`proof_file_${milestone.id}`}>Proof</Label>
                <input
                    id={`proof_file_${milestone.id}`}
                    type="file"
                    onChange={(event) =>
                        form.setData(
                            'proof',
                            event.target.files?.item(0) ?? null,
                        )
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
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

function DueDiligenceTargetPanel({
    payload,
}: {
    payload: DueDiligenceSummary;
}) {
    return (
        <section className="space-y-4 rounded-md border p-4">
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
        <section className="space-y-4 rounded-md border p-4">
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
        <section className="space-y-4 rounded-md border p-4">
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

function ReportsPanel({ client }: { client: ClientDetail }) {
    const generate = (
        type: 'client' | 'advisor' | 'stakeholder' | 'trajectory',
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
        <section className="space-y-4 rounded-md border p-4">
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
                                        Mark reviewed
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
        <section className="space-y-4 rounded-md border p-4">
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
        <section className="space-y-4 rounded-md border p-4">
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
