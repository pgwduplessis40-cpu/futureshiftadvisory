import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    Bell,
    ClipboardList,
    FileText,
    HeartPulse,
    MessageSquare,
    Target,
    TrendingUp,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { DataQualityBadge } from '@/components/data-quality/DataQualityBadge';
import type { DataQualitySummary } from '@/components/data-quality/DataQualityBadge';
import { BusinessHealthRadar } from '@/components/insight/BusinessHealthRadar';
import type { BusinessHealthRadarPayload } from '@/components/insight/BusinessHealthRadar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
    goals: GoalDashboard;
    documents: DocumentPayload[];
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
    goals,
    documents,
    scenarios,
    proposals,
    reports,
    messagesUrl,
}: Props) {
    useDrillFocus();

    return (
        <>
            <Head title="Client portal" />

            <div className="space-y-6">
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
                    />
                    <StatusPanel
                        icon={Bell}
                        label="Notifications"
                        value={`${notificationSummary.unread} unread`}
                    />
                    <StatusPanel
                        icon={FileText}
                        label="Referral status"
                        value="Not requested"
                    />
                </div>

                <BusinessHealthPanel
                    businessHealth={businessHealth}
                    healthFindings={healthFindings}
                />

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
                    className="space-y-4 rounded-md border bg-background p-4"
                    aria-labelledby="documents-heading"
                >
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <FileText className="size-4" aria-hidden="true" />
                            <h2
                                id="documents-heading"
                                className="text-sm font-medium"
                            >
                                Documents
                            </h2>
                        </div>
                        <Badge variant="outline">{documents.length}</Badge>
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
            </div>
        </>
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
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    label: string;
    value: ReactNode;
}) {
    return (
        <section className="rounded-md border bg-background p-4">
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Icon className="size-4" aria-hidden={true} />
                {label}
            </div>
            <div className="mt-2 text-sm font-medium">{value}</div>
        </section>
    );
}
