import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    Bell,
    CalendarClock,
    ClipboardList,
    CircleDollarSign,
    FileText,
    HeartPulse,
    MessageSquare,
    PieChart,
    Save,
    Target,
    TrendingUp,
    Upload,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import type { ComponentType, ReactNode } from 'react';
import { DataQualityBadge } from '@/components/data-quality/DataQualityBadge';
import type { DataQualitySummary } from '@/components/data-quality/DataQualityBadge';
import FileDropzone from '@/components/file-dropzone';
import InputError from '@/components/input-error';
import { BusinessHealthRadar } from '@/components/insight/BusinessHealthRadar';
import type { BusinessHealthRadarPayload } from '@/components/insight/BusinessHealthRadar';
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
    goals: GoalDashboard;
    documents: DocumentPayload[];
    documentUploadUrl: string;
    npoImpactMetricStoreUrl: string | null;
    scenarios: ScenarioPayload[];
    proposals: ProposalPayload[];
    reports: ReportPayload[];
    messagesUrl: string;
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
    status: string;
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
    signed_at: string | null;
    signoff_url: string;
};

type ReportPayload = {
    id: string;
    title: string;
    generated_at: string | null;
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

export default function PortalDashboard({
    client,
    progress,
    onboardingUrl,
    notificationSummary,
    wellbeing,
    businessHealth,
    healthFindings,
    npoHealth,
    npoPortal,
    goals,
    documents: initialDocuments,
    documentUploadUrl,
    npoImpactMetricStoreUrl,
    scenarios,
    proposals,
    reports,
    messagesUrl,
}: Props) {
    useDrillFocus();
    const [documents, setDocuments] =
        useState<DocumentPayload[]>(initialDocuments);
    const [file, setFile] = useState<File | null>(null);
    const [documentCategory, setDocumentCategory] = useState(
        npoPortal ? 'npo_board_record' : 'client_portal_upload',
    );
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [uploadKey, setUploadKey] = useState(0);

    const uploadDocument = async () => {
        if (!file) {
            return;
        }

        setUploading(true);
        setUploadError(null);

        const formData = new FormData();
        formData.append('file', file);
        formData.append('category', documentCategory);
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

    return (
        <>
            <Head title="Client portal" />

            <main className="flex-1 space-y-6 p-6">
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

                <section
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
                                {progress.completed} of {progress.total} steps
                                complete
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
                            style={{ width: `${progress.percentage}%` }}
                        />
                    </div>
                </section>

                <section
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

                <div className="grid gap-4 md:grid-cols-3">
                    <StatusPanel
                        icon={TrendingUp}
                        label="Data quality"
                        value={
                            <DataQualityBadge
                                summary={client.data_quality_summary}
                            />
                        }
                        explanation="Data quality reflects how complete and usable the evidence in your client workspace is for advisory analysis."
                        href="#section-health"
                        actionLabel="Review"
                    />
                    <StatusPanel
                        icon={Bell}
                        label="Notifications"
                        value={`${notificationSummary.unread} unread`}
                        explanation="Notifications include advisor updates, document checks, terms prompts, and other portal alerts."
                        href="/notifications"
                        actionLabel="Open"
                    />
                    <StatusPanel
                        icon={MessageSquare}
                        label="Messages"
                        value="Advisor thread"
                        explanation="Messages opens your secure conversation history with the advisory team."
                        href={messagesUrl}
                        actionLabel="Open"
                    />
                </div>

                <BusinessHealthPanel
                    businessHealth={businessHealth}
                    healthFindings={healthFindings}
                />

                {npoHealth && (
                    <NpoHealthPanel payload={npoHealth} title="NPO health" />
                )}

                {npoPortal && (
                    <NpoPortalPanel
                        payload={npoPortal}
                        metricStoreUrl={npoImpactMetricStoreUrl}
                        onboardingUrl={onboardingUrl}
                    />
                )}

                <GoalProgressPanel goals={goals} />

                <ProposalSignoffPanel proposals={proposals} />

                <section
                    className="space-y-4 rounded-md border bg-background p-4"
                    aria-labelledby="reports-heading"
                >
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <FileText className="size-4" aria-hidden="true" />
                            <h2
                                id="reports-heading"
                                className="text-sm font-medium"
                            >
                                Reports
                            </h2>
                        </div>
                        <Badge variant="outline">{reports.length}</Badge>
                    </div>

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
                                    <div className="text-sm font-medium">
                                        {report.title}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {formatDate(report.generated_at)}
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </section>

                <section
                    id="section-documents"
                    className="space-y-4 rounded-md border bg-background p-4"
                    aria-labelledby="documents-heading"
                >
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div className="flex items-center gap-2">
                            <FileText className="size-4" aria-hidden="true" />
                            <h2
                                id="documents-heading"
                                className="text-sm font-medium"
                            >
                                Documents
                            </h2>
                            <Badge variant="outline">{documents.length}</Badge>
                        </div>
                        <div className="grid w-full gap-2 lg:max-w-sm">
                            {npoPortal && (
                                <Select
                                    value={documentCategory}
                                    onValueChange={setDocumentCategory}
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
                            )}
                            <FileDropzone
                                key={uploadKey}
                                id="client_dashboard_document"
                                files={file ? [file] : []}
                                label="Upload document"
                                onFilesChange={(files) =>
                                    setFile(files[0] ?? null)
                                }
                            />
                            <InputError message={uploadError ?? undefined} />
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={!file || uploading}
                                onClick={() => void uploadDocument()}
                            >
                                <Upload className="size-4" aria-hidden="true" />
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

                <div className="grid gap-6 lg:grid-cols-2">
                    <section
                        className="space-y-4 rounded-md border bg-background p-4"
                        aria-labelledby="scenarios-heading"
                    >
                        <div className="flex items-center gap-2">
                            <TrendingUp className="size-4" aria-hidden="true" />
                            <h2
                                id="scenarios-heading"
                                className="text-sm font-medium"
                            >
                                Scenarios
                            </h2>
                        </div>
                        <ScenarioList scenarios={scenarios} />
                    </section>

                    <section
                        className="space-y-4 rounded-md border bg-background p-4"
                        aria-labelledby="messages-heading"
                    >
                        <div className="flex items-center gap-2">
                            <MessageSquare
                                className="size-4"
                                aria-hidden="true"
                            />
                            <h2
                                id="messages-heading"
                                className="text-sm font-medium"
                            >
                                Messages
                            </h2>
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <Link href={messagesUrl}>Open messages</Link>
                        </Button>
                    </section>
                </div>
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
                                    {formatCurrency(
                                        payload.milestone_progress
                                            .cost_per_beneficiary
                                            .benchmark_cost_per_beneficiary ??
                                            0,
                                    )}
                                </span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Capacity
                                </span>
                                <span className="font-medium">
                                    {formatNumber(
                                        payload.milestone_progress
                                            .cost_per_beneficiary
                                            .additional_beneficiaries_mid ?? 0,
                                    )}
                                </span>
                            </div>
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
                        Business health
                    </h2>
                </div>
                <Badge variant="outline">
                    {businessHealth.captured_at
                        ? formatDate(businessHealth.captured_at)
                        : 'Pending'}
                </Badge>
            </div>

            <BusinessHealthRadar payload={businessHealth} />

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
                                <div className="text-sm font-medium">
                                    {formatCurrency(goal.pv_target)}
                                </div>
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

function ProposalSignoffPanel({ proposals }: { proposals: ProposalPayload[] }) {
    return (
        <section
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
    const flagged =
        document.verification_state === 'advisory_flag' ||
        document.verification_state === 'accuracy_discrepancy' ||
        document.verification_state === 'verification_error';

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
                <a href={document.url}>View document</a>
            </Button>
        </article>
    );
}

function formatDate(value: string | null) {
    if (!value) {
        return 'this month';
    }

    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
    }).format(new Date(value));
}

function formatOptionalDate(value: string | null) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(undefined, {
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
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    label: string;
    value: ReactNode;
    explanation: string;
    href: string;
    actionLabel: string;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <section className="rounded-md border bg-background p-4">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Icon className="size-4" aria-hidden={true} />
                        {label}
                    </div>
                    <div className="mt-2 text-sm font-medium">{value}</div>
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="mt-3 px-0"
                    >
                        <Link href={href}>{actionLabel}</Link>
                    </Button>
                </section>
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
