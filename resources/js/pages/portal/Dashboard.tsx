import { Head, Link } from '@inertiajs/react';
import {
    Bell,
    ClipboardList,
    FileText,
    HeartPulse,
    MessageSquare,
    TrendingUp,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { DataQualityBadge } from '@/components/data-quality/DataQualityBadge';
import type { DataQualitySummary } from '@/components/data-quality/DataQualityBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { VerificationBadge } from '@/components/verification/Badge';
import type { VerificationOutcome } from '@/components/verification/Badge';
import { FlagBanner } from '@/components/verification/FlagBanner';

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
    documents: DocumentPayload[];
    scenarios: ScenarioPayload[];
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

type ReportPayload = {
    id: string;
    title: string;
    generated_at: string | null;
};

export default function PortalDashboard({
    client,
    progress,
    onboardingUrl,
    notificationSummary,
    wellbeing,
    documents,
    scenarios,
    reports,
    messagesUrl,
}: Props) {
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
