import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock3,
    FileWarning,
    Play,
    RotateCcw,
    Search,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type ResultStatus = 'passed' | 'warning' | 'failed' | 'skipped';
type RunStatus = 'passed' | 'warning' | 'failed';

type HealthResult = {
    id: string;
    run_id: string;
    run_started_at_label: string | null;
    check_key: string;
    name: string;
    area: string;
    status: ResultStatus;
    method: string;
    url: string | null;
    route_name: string | null;
    expected_statuses: number[];
    actual_status: number | null;
    expected_content_type: string | null;
    actual_content_type: string | null;
    response_time_ms: number | null;
    actor_label: string | null;
    actor_role: string | null;
    workflow_subject_type: string | null;
    workflow_subject_id: string | null;
    workflow_subject_label: string | null;
    expected_behavior: string | null;
    issue_summary: string | null;
    issue_detail: string | null;
    fingerprint: string | null;
    consecutive_failures: number | null;
    failures_last_7_days: number | null;
    failures_last_30_days: number | null;
    first_seen_at_label: string | null;
    last_seen_at_label: string | null;
    exception_class: string | null;
    exception_message: string | null;
    context: unknown;
    created_at_label: string | null;
};

type HealthRun = {
    id: string;
    status: RunStatus;
    environment: string;
    release_version: string | null;
    duration_ms: number | null;
    total_checks: number;
    passed_checks: number;
    warning_checks: number;
    failed_checks: number;
    skipped_checks: number;
    started_at_label: string | null;
    finished_at_label: string | null;
    results: HealthResult[];
};

type PaginatedResults = {
    current_page: number;
    data: HealthResult[];
    from: number | null;
    last_page: number;
    to: number | null;
    total: number;
};

type Filters = {
    q: string;
    status: string;
    area: string;
    date_from: string;
    date_to: string;
};

type Props = {
    summary: {
        latest_status: RunStatus | null;
        latest_started_at_label: string | null;
        total_checks: number;
        passed_checks: number;
        warning_checks: number;
        failed_checks: number;
        skipped_checks: number;
        latest_issue: HealthResult | null;
    };
    latestRun: HealthRun | null;
    recurringIssues: HealthResult[];
    results: PaginatedResults;
    filters: Filters;
    runUrl: string;
};

export default function AppHealthIndex({
    summary,
    latestRun,
    recurringIssues,
    results,
    filters,
    runUrl,
}: Props) {
    const [form, setForm] = useState<Filters>(filters);
    const [running, setRunning] = useState(false);

    function submit(event: FormEvent) {
        event.preventDefault();

        router.get('/admin/app-health', compactFilters(form), {
            preserveScroll: true,
            preserveState: true,
        });
    }

    function clearFilters() {
        const empty = {
            q: '',
            status: '',
            area: '',
            date_from: '',
            date_to: '',
        };

        setForm(empty);
        router.get('/admin/app-health', {}, { preserveScroll: true });
    }

    function runChecks() {
        router.post(
            runUrl,
            {},
            {
                preserveScroll: true,
                onStart: () => setRunning(true),
                onFinish: () => setRunning(false),
            },
        );
    }

    return (
        <>
            <Head title="App checks" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Operations"
                    icon={Activity}
                    title="App checks"
                    description="Daily synthetic checks for app routes, document previews, and portal access."
                    actions={
                        <Button
                            type="button"
                            size="sm"
                            disabled={running}
                            onClick={runChecks}
                        >
                            <Play className="size-4" aria-hidden="true" />
                            Run now
                        </Button>
                    }
                />

                <section className="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                    <Metric
                        label="Latest"
                        value={statusLabel(summary.latest_status)}
                        status={summary.latest_status}
                    />
                    <Metric label="Checks" value={summary.total_checks} />
                    <Metric
                        label="Passed"
                        value={summary.passed_checks}
                        status="passed"
                    />
                    <Metric
                        label="Failed"
                        value={summary.failed_checks}
                        status={summary.failed_checks > 0 ? 'failed' : null}
                    />
                    <Metric
                        label="Warnings"
                        value={summary.warning_checks}
                        status={summary.warning_checks > 0 ? 'warning' : null}
                    />
                    <Metric
                        label="Skipped"
                        value={summary.skipped_checks}
                        status={summary.skipped_checks > 0 ? 'skipped' : null}
                    />
                </section>

                {summary.latest_issue ? (
                    <section className="rounded-md border border-destructive/40 bg-destructive/5 p-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <StatusBadge
                                        status={summary.latest_issue.status}
                                    />
                                    <Badge variant="outline">
                                        {summary.latest_issue.area}
                                    </Badge>
                                    {summary.latest_issue.actual_status ? (
                                        <Badge variant="secondary">
                                            HTTP{' '}
                                            {summary.latest_issue.actual_status}
                                        </Badge>
                                    ) : null}
                                </div>
                                <h2 className="text-base font-semibold">
                                    {summary.latest_issue.issue_summary}
                                </h2>
                                <p className="max-w-5xl text-sm text-muted-foreground">
                                    {summary.latest_issue.issue_detail}
                                </p>
                            </div>
                            <RecurrenceBadges result={summary.latest_issue} />
                        </div>
                    </section>
                ) : null}

                {latestRun ? (
                    <section className="space-y-3 rounded-md border bg-background p-4">
                        <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 className="text-sm font-medium">
                                    Latest run
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {latestRun.started_at_label ?? 'Unknown'} /{' '}
                                    {latestRun.environment}
                                    {latestRun.release_version
                                        ? ` / ${latestRun.release_version}`
                                        : ''}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <StatusBadge status={latestRun.status} />
                                {latestRun.duration_ms ? (
                                    <Badge variant="outline">
                                        <Clock3
                                            className="size-3"
                                            aria-hidden="true"
                                        />
                                        {latestRun.duration_ms} ms
                                    </Badge>
                                ) : null}
                            </div>
                        </div>

                        <div className="grid gap-3 lg:grid-cols-2">
                            {latestRun.results.map((result) => (
                                <LatestResult key={result.id} result={result} />
                            ))}
                        </div>
                    </section>
                ) : null}

                {recurringIssues.length > 0 ? (
                    <section className="space-y-3">
                        <div>
                            <h2 className="text-sm font-medium">
                                Recurring issues
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Repeat fingerprints from the last recorded
                                checks.
                            </p>
                        </div>
                        <div className="grid gap-3 lg:grid-cols-2">
                            {recurringIssues.map((issue) => (
                                <IssuePanel key={issue.id} issue={issue} />
                            ))}
                        </div>
                    </section>
                ) : null}

                <form
                    onSubmit={submit}
                    className="grid gap-3 rounded-md border bg-background p-4 lg:grid-cols-[1.5fr_0.7fr_0.9fr_auto_auto]"
                >
                    <Field label="Search" htmlFor="app_health_q">
                        <Input
                            id="app_health_q"
                            value={form.q}
                            onChange={(event) =>
                                setForm({ ...form, q: event.target.value })
                            }
                        />
                    </Field>
                    <Field label="Status" htmlFor="app_health_status">
                        <Input
                            id="app_health_status"
                            value={form.status}
                            placeholder="failed"
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    status: event.target.value,
                                })
                            }
                        />
                    </Field>
                    <Field label="Area" htmlFor="app_health_area">
                        <Input
                            id="app_health_area"
                            value={form.area}
                            placeholder="Documents"
                            onChange={(event) =>
                                setForm({ ...form, area: event.target.value })
                            }
                        />
                    </Field>
                    <Field label="From" htmlFor="app_health_date_from">
                        <Input
                            id="app_health_date_from"
                            type="date"
                            value={form.date_from}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    date_from: event.target.value,
                                })
                            }
                        />
                    </Field>
                    <Field label="To" htmlFor="app_health_date_to">
                        <Input
                            id="app_health_date_to"
                            type="date"
                            value={form.date_to}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    date_to: event.target.value,
                                })
                            }
                        />
                    </Field>
                    <div className="flex gap-2 lg:col-span-5">
                        <Button type="submit">
                            <Search className="size-4" aria-hidden="true" />
                            Search
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={clearFilters}
                        >
                            <RotateCcw className="size-4" aria-hidden="true" />
                            Reset
                        </Button>
                    </div>
                </form>

                <section className="overflow-hidden rounded-md border bg-background">
                    <table className="fsa-responsive-table">
                        <thead className="bg-muted text-left [&_th]:bg-muted">
                            <tr>
                                <th className="px-3 py-2 font-medium">Time</th>
                                <th className="px-3 py-2 font-medium">Check</th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Request
                                </th>
                                <th className="px-3 py-2 font-medium">Issue</th>
                                <th className="px-3 py-2 font-medium">
                                    Recurrence
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {results.data.length > 0 ? (
                                results.data.map((result) => (
                                    <ResultRow
                                        key={result.id}
                                        result={result}
                                    />
                                ))
                            ) : (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-3 py-10 text-center text-sm text-muted-foreground"
                                    >
                                        No app check findings match the current
                                        filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </section>

                <Pagination results={results} filters={filters} />
            </div>
        </>
    );
}

function Metric({
    label,
    value,
    status,
}: {
    label: string;
    value: ReactNode;
    status?: ResultStatus | RunStatus | null;
}) {
    return (
        <div className="rounded-md border bg-background p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 flex items-center gap-2 text-lg font-semibold">
                {statusIcon(status)}
                {value}
            </div>
        </div>
    );
}

function LatestResult({ result }: { result: HealthResult }) {
    return (
        <div
            className={cn(
                'rounded-md border p-3',
                result.status === 'failed' &&
                    'border-destructive/40 bg-destructive/5',
                result.status === 'warning' &&
                    'border-amber-300 bg-amber-50/60',
                result.status === 'skipped' && 'bg-muted/30',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="space-y-1">
                    <div className="font-medium">{result.name}</div>
                    <div className="text-xs text-muted-foreground">
                        {result.check_key}
                    </div>
                </div>
                <StatusBadge status={result.status} />
            </div>
            {result.issue_summary ? (
                <p className="mt-2 text-sm">{result.issue_summary}</p>
            ) : (
                <p className="mt-2 text-sm text-muted-foreground">
                    Expected behavior confirmed.
                </p>
            )}
        </div>
    );
}

function IssuePanel({ issue }: { issue: HealthResult }) {
    return (
        <div className="space-y-3 rounded-md border bg-background p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <div className="font-medium">{issue.name}</div>
                    <div className="text-xs text-muted-foreground">
                        {issue.area} / {issue.check_key}
                    </div>
                </div>
                <StatusBadge status={issue.status} />
            </div>
            <p className="text-sm">{issue.issue_summary}</p>
            <RecurrenceBadges result={issue} />
        </div>
    );
}

function ResultRow({ result }: { result: HealthResult }) {
    return (
        <tr className="border-t align-top">
            <td className="px-3 py-3" data-label="Time">
                <div className="text-sm whitespace-nowrap">
                    {result.created_at_label ?? result.run_started_at_label}
                </div>
            </td>
            <td className="px-3 py-3" data-label="Check">
                <div className="max-w-72 space-y-1">
                    <div className="font-medium">{result.name}</div>
                    <div className="text-xs break-all text-muted-foreground">
                        {result.check_key}
                    </div>
                    <Badge variant="outline">{result.area}</Badge>
                </div>
            </td>
            <td className="px-3 py-3" data-label="Status">
                <div className="space-y-2">
                    <StatusBadge status={result.status} />
                    {result.actual_status ? (
                        <Badge variant="secondary">
                            HTTP {result.actual_status}
                        </Badge>
                    ) : null}
                </div>
            </td>
            <td className="px-3 py-3" data-label="Request">
                <div className="max-w-80 space-y-1 text-xs">
                    <div className="break-all">
                        {result.method} {result.url ?? 'unresolved'}
                    </div>
                    <div className="text-muted-foreground">
                        Expected {result.expected_statuses.join(', ')}
                        {result.expected_content_type
                            ? ` / ${result.expected_content_type}`
                            : ''}
                    </div>
                    {result.actor_label ? (
                        <div className="text-muted-foreground">
                            {result.actor_label}
                        </div>
                    ) : null}
                    {result.workflow_subject_label ? (
                        <div className="text-muted-foreground">
                            {result.workflow_subject_type}:{' '}
                            {result.workflow_subject_label}
                        </div>
                    ) : null}
                </div>
            </td>
            <td className="px-3 py-3" data-label="Issue">
                <div className="max-w-xl space-y-2 text-sm">
                    <p>
                        {result.issue_summary ??
                            result.expected_behavior ??
                            'Expected behavior confirmed.'}
                    </p>
                    {result.issue_detail ? (
                        <details className="rounded-md border bg-muted/20 p-2 text-xs">
                            <summary className="cursor-pointer font-medium">
                                Details
                            </summary>
                            <p className="mt-2 whitespace-pre-wrap">
                                {result.issue_detail}
                            </p>
                        </details>
                    ) : null}
                </div>
            </td>
            <td className="px-3 py-3" data-label="Recurrence">
                <RecurrenceBadges result={result} />
            </td>
        </tr>
    );
}

function RecurrenceBadges({ result }: { result: HealthResult }) {
    if (!result.fingerprint) {
        return (
            <span className="text-xs text-muted-foreground">
                No recurrence fingerprint
            </span>
        );
    }

    return (
        <div className="flex flex-wrap gap-1">
            <Badge variant="outline">
                {result.consecutive_failures ?? 1} consecutive
            </Badge>
            <Badge variant="outline">
                {result.failures_last_7_days ?? 1} / 7d
            </Badge>
            <Badge variant="outline">
                {result.failures_last_30_days ?? 1} / 30d
            </Badge>
            {result.first_seen_at_label ? (
                <Badge variant="secondary">
                    First {result.first_seen_at_label}
                </Badge>
            ) : null}
        </div>
    );
}

function StatusBadge({ status }: { status: ResultStatus | RunStatus }) {
    return (
        <Badge
            variant={
                status === 'failed'
                    ? 'destructive'
                    : status === 'passed'
                      ? 'secondary'
                      : 'outline'
            }
        >
            {statusIcon(status)}
            {statusLabel(status)}
        </Badge>
    );
}

function statusIcon(status?: ResultStatus | RunStatus | null) {
    if (status === 'failed') {
        return <AlertTriangle className="size-3" aria-hidden="true" />;
    }

    if (status === 'passed') {
        return <CheckCircle2 className="size-3" aria-hidden="true" />;
    }

    if (status === 'warning' || status === 'skipped') {
        return <FileWarning className="size-3" aria-hidden="true" />;
    }

    return null;
}

function statusLabel(status?: ResultStatus | RunStatus | null) {
    if (!status) {
        return 'No runs';
    }

    return status.charAt(0).toUpperCase() + status.slice(1);
}

function Field({
    label,
    htmlFor,
    children,
}: {
    label: string;
    htmlFor: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
        </div>
    );
}

function Pagination({
    results,
    filters,
}: {
    results: PaginatedResults;
    filters: Filters;
}) {
    if (results.last_page <= 1) {
        return null;
    }

    const previousPage =
        results.current_page > 1 ? results.current_page - 1 : null;
    const nextPage =
        results.current_page < results.last_page
            ? results.current_page + 1
            : null;

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-muted-foreground">
                Showing {results.from ?? 0} to {results.to ?? 0} of{' '}
                {results.total}
            </p>
            <div className="flex items-center gap-2">
                <PageButton
                    href={
                        previousPage
                            ? appHealthPageUrl(filters, previousPage)
                            : null
                    }
                    label="Previous page"
                    disabled={!previousPage}
                    icon={<ChevronLeft className="size-4" aria-hidden="true" />}
                />
                <Badge variant="outline">
                    Page {results.current_page} of {results.last_page}
                </Badge>
                <PageButton
                    href={nextPage ? appHealthPageUrl(filters, nextPage) : null}
                    label="Next page"
                    disabled={!nextPage}
                    icon={
                        <ChevronRight className="size-4" aria-hidden="true" />
                    }
                />
            </div>
        </div>
    );
}

function PageButton({
    href,
    label,
    disabled,
    icon,
}: {
    href: string | null;
    label: string;
    disabled: boolean;
    icon: ReactNode;
}) {
    return (
        <Button
            asChild={Boolean(href)}
            size="icon"
            variant="outline"
            disabled={disabled}
            aria-label={label}
            title={label}
        >
            {href ? (
                <Link href={href} aria-label={label}>
                    {icon}
                </Link>
            ) : (
                <span>{icon}</span>
            )}
        </Button>
    );
}

function compactFilters(filters: Filters) {
    return Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value.trim() !== ''),
    );
}

function appHealthPageUrl(filters: Filters, page: number) {
    const query = new URLSearchParams(compactFilters(filters));
    query.set('page', String(page));

    return `/admin/app-health?${query.toString()}`;
}

AppHealthIndex.layout = {
    breadcrumbs: [
        {
            title: 'App checks',
            href: '/admin/app-health',
        },
    ],
};
