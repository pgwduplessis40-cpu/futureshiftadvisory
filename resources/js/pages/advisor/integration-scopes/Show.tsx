import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Calculator,
    CheckCircle2,
    CircleAlert,
    Eye,
    FileUp,
    FileText,
    LoaderCircle,
    RefreshCw,
    RotateCw,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Scope = {
    id: string;
    client_id: string;
    client_name: string | null;
    status: string;
    delivery_mode: string | null;
    fsa_hosting_enabled: boolean;
    systems: Record<string, unknown>[];
    tasks: Record<string, unknown>[];
    connections: Record<string, unknown>[];
    computed: Record<string, unknown>;
    flags: { code: string; severity: string }[];
    pv_calculation_id: string | null;
    fee_calculations: {
        id: string;
        suggested_low: number;
        suggested_mid: number;
        suggested_high: number;
        roi_ratio: number | null;
        created_at: string | null;
    }[];
    quote_source_extractions: QuoteSourceExtraction[];
};

type QuoteSourceRow = Record<string, unknown> & {
    id: string;
    type: 'system' | 'task' | 'connection';
    review_status: 'pending' | 'confirmed' | 'rejected';
    source: 'document' | 'description';
    source_reference: string;
    claim: string;
};

type QuoteSourceExtraction = {
    id: string;
    status: 'pending' | 'extracted' | 'blocked';
    description: string;
    blocked_reason: string | null;
    rows: QuoteSourceRow[];
    documents: {
        id: string | null;
        filename: string | null;
        verification_outcome: string | null;
        url: string | null;
    }[];
    confirm_url: string;
    reject_url: string;
    retry_url: string;
};

type Props = {
    scope: Scope;
    urls: {
        index: string;
        update: string;
        recalculate: string;
        feeCalculation: string;
        quoteSourceExtraction: string;
        clientProposals: string;
    };
};

export default function IntegrationScopeShow({ scope, urls }: Props) {
    const computed = scope.computed;
    const quoteRange = computed.quote_range;
    const quoteScopeDescription =
        typeof quoteRange === 'object' && quoteRange !== null
            ? String((quoteRange as Record<string, unknown>).scope_description ?? '')
            : '';
    const hosting = objectValue(computed.hosting);
    const hostingMonthlyFee = numericValue(hosting?.monthly_fee);
    const hostingAnnualFee = numericValue(hosting?.annual_fee);
    const [showCalculation, setShowCalculation] = useState(false);
    const [description, setDescription] = useState('');
    const [planFiles, setPlanFiles] = useState<File[]>([]);
    const [isPreparingPlan, setIsPreparingPlan] = useState(false);
    const [selectedRows, setSelectedRows] = useState<Record<string, string[]>>(
        {},
    );
    const fileInput = useRef<HTMLInputElement>(null);
    const latestCalculation = scope.fee_calculations[0] ?? null;

    function preparePlan() {
        if (planFiles.length === 0) return;

        const form = new FormData();
        form.append('description', description);
        planFiles.forEach((file) => form.append('documents[]', file));
        router.post(urls.quoteSourceExtraction, form, {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => setIsPreparingPlan(true),
            onFinish: () => setIsPreparingPlan(false),
            onSuccess: () => {
                setDescription('');
                setPlanFiles([]);
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    }

    function toggleRow(extractionId: string, rowId: string) {
        setSelectedRows((current) => {
            const selected = current[extractionId] ?? [];
            return {
                ...current,
                [extractionId]: selected.includes(rowId)
                    ? selected.filter((id) => id !== rowId)
                    : [...selected, rowId],
            };
        });
    }

    return (
        <>
            <Head
                title={`Integration scope: ${scope.client_name ?? 'client'}`}
            />
            <main className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={urls.index}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Scopes
                            </Link>
                        </Button>
                        <h1 className="mt-3 text-xl font-semibold">
                            Systems &amp; Integration Efficiency
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {scope.client_name ?? 'Client'} /{' '}
                            {scope.delivery_mode ?? 'delivery mode to confirm'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() => router.post(urls.recalculate)}
                        >
                            <RefreshCw className="size-4" aria-hidden="true" />
                            Recalculate
                        </Button>
                        {latestCalculation ? (
                            <Button asChild variant="outline">
                                <Link href={urls.clientProposals}>
                                    <FileText
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Open proposal builder
                                </Link>
                            </Button>
                        ) : null}
                        <Button
                            onClick={() => router.post(urls.feeCalculation)}
                        >
                            <Calculator className="size-4" aria-hidden="true" />
                            {latestCalculation
                                ? 'Create revised calculation'
                                : 'Create fee calculation'}
                        </Button>
                    </div>
                </div>

                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Metric
                        label="Annual hours"
                        value={number(computed.annual_hours_wasted)}
                        suffix=" hours"
                    />
                    <Metric
                        label="Annual savings"
                        value={money(computed.annual_savings)}
                    />
                    <Metric
                        label="Complexity"
                        value={String(computed.complexity_band ?? '-')}
                        suffix={
                            computed.complexity_score
                                ? ` / ${computed.complexity_score}`
                                : ''
                        }
                    />
                    <Metric
                        label="Payback"
                        value={number(computed.payback_months)}
                        suffix=" months"
                    />
                </section>

                <section className="space-y-5 rounded-md border bg-background p-5">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 className="text-base font-semibold">
                                External implementation plan
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Add the client&apos;s existing app or integration
                                plan before confirming the scope used for this
                                quote.
                            </p>
                        </div>
                        <Badge variant="outline">
                            {scope.quote_source_extractions.length} plan
                            {scope.quote_source_extractions.length === 1
                                ? ''
                                : 's'}{' '}
                            on record
                        </Badge>
                    </div>

                    <div className="grid gap-4 border-y py-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                        <div className="space-y-3">
                            <textarea
                                value={description}
                                onChange={(event) =>
                                    setDescription(event.target.value)
                                }
                                placeholder="Add any scope notes that need to be read with the uploaded plan."
                                className="min-h-24 w-full rounded-md border bg-background px-3 py-2 text-sm outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                            />
                            <input
                                ref={fileInput}
                                type="file"
                                multiple
                                accept=".pdf,.docx,.xlsx,.csv,.txt"
                                className="sr-only"
                                onChange={(event) =>
                                    setPlanFiles(
                                        Array.from(event.target.files ?? []),
                                    )
                                }
                            />
                            {planFiles.length > 0 ? (
                                <div className="flex flex-wrap gap-2">
                                    {planFiles.map((file) => (
                                        <Badge
                                            key={`${file.name}-${file.lastModified}`}
                                            variant="secondary"
                                        >
                                            {file.name}
                                        </Badge>
                                    ))}
                                </div>
                            ) : null}
                        </div>
                        <div className="flex flex-wrap content-start gap-2 lg:w-52 lg:flex-col">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => fileInput.current?.click()}
                            >
                                <FileUp
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Select plan
                            </Button>
                            <Button
                                type="button"
                                onClick={preparePlan}
                                disabled={
                                    planFiles.length === 0 || isPreparingPlan
                                }
                            >
                                {isPreparingPlan ? (
                                    <LoaderCircle
                                        className="size-4 animate-spin"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <FileText
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                                Prepare scope rows
                            </Button>
                        </div>
                    </div>

                    <div className="space-y-3">
                        {scope.quote_source_extractions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No external implementation plan has been added.
                            </p>
                        ) : (
                            scope.quote_source_extractions.map((extraction) => {
                                const pendingRows = extraction.rows.filter(
                                    (row) => row.review_status === 'pending',
                                );
                                const selected = selectedRows[extraction.id] ?? [];

                                return (
                                    <article
                                        key={extraction.id}
                                        className="rounded-md border"
                                    >
                                        <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h3 className="text-sm font-medium">
                                                        Implementation-plan review
                                                    </h3>
                                                    <StatusBadge
                                                        status={
                                                            extraction.status
                                                        }
                                                    />
                                                </div>
                                                <div className="mt-2 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                                    {extraction.documents.map(
                                                        (document) =>
                                                            document.url ? (
                                                                <a
                                                                    key={
                                                                        document.id
                                                                    }
                                                                    href={
                                                                        document.url
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className="underline underline-offset-2"
                                                                >
                                                                    {document.filename}{' '}
                                                                    /{' '}
                                                                    {document.verification_outcome ??
                                                                        'verification pending'}
                                                                </a>
                                                            ) : null,
                                                    )}
                                                </div>
                                            </div>
                                            {extraction.status === 'blocked' ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            extraction.retry_url,
                                                            {},
                                                            {
                                                                preserveScroll:
                                                                    true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <RotateCw
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Retry verification
                                                </Button>
                                            ) : null}
                                        </div>

                                        {extraction.blocked_reason ? (
                                            <div className="m-4 flex gap-2 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                                                <CircleAlert
                                                    className="mt-0.5 size-4 shrink-0"
                                                    aria-hidden="true"
                                                />
                                                <p>{extraction.blocked_reason}</p>
                                            </div>
                                        ) : null}

                                        {extraction.description ? (
                                            <p className="px-4 pt-4 text-sm text-muted-foreground">
                                                {extraction.description}
                                            </p>
                                        ) : null}

                                        {extraction.rows.length > 0 ? (
                                            <div className="divide-y border-t">
                                                {extraction.rows.map((row) => (
                                                    <label
                                                        key={row.id}
                                                        className="flex cursor-pointer gap-3 p-4 hover:bg-muted/30"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            className="mt-1 size-4 accent-primary"
                                                            checked={selected.includes(
                                                                row.id,
                                                            )}
                                                            disabled={
                                                                row.review_status !==
                                                                'pending'
                                                            }
                                                            onChange={() =>
                                                                toggleRow(
                                                                    extraction.id,
                                                                    row.id,
                                                                )
                                                            }
                                                        />
                                                        <span className="min-w-0 flex-1">
                                                            <span className="flex flex-wrap items-center gap-2">
                                                                <span className="text-sm font-medium">
                                                                    {rowLabel(row)}
                                                                </span>
                                                                <Badge variant="outline">
                                                                    {row.type}
                                                                </Badge>
                                                                <ReviewBadge
                                                                    status={
                                                                        row.review_status
                                                                    }
                                                                />
                                                            </span>
                                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                                {row.claim}
                                                            </span>
                                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                                {row.source_reference}
                                                            </span>
                                                        </span>
                                                    </label>
                                                ))}
                                            </div>
                                        ) : null}

                                        {pendingRows.length > 0 ? (
                                            <div className="flex flex-wrap justify-end gap-2 border-t p-4">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={selected.length === 0}
                                                    onClick={() =>
                                                        router.post(
                                                            extraction.reject_url,
                                                            { row_ids: selected },
                                                            {
                                                                preserveScroll:
                                                                    true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Reject selected
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    disabled={selected.length === 0}
                                                    onClick={() =>
                                                        router.post(
                                                            extraction.confirm_url,
                                                            { row_ids: selected },
                                                            {
                                                                preserveScroll:
                                                                    true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Confirm into quote
                                                </Button>
                                            </div>
                                        ) : null}
                                    </article>
                                );
                            })
                        )}
                    </div>
                </section>

                <section className="space-y-5 rounded-md border bg-background p-5">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 className="text-base font-semibold">Quote</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Internal pricing for the systems and connections
                                in this scope.
                            </p>
                        </div>
                        <Badge
                            variant={
                                latestCalculation ? 'secondary' : 'outline'
                            }
                        >
                            {latestCalculation
                                ? 'Calculation ready'
                                : 'Calculation required'}
                        </Badge>
                    </div>

                    {quoteScopeDescription ? (
                        <div className="rounded-md border bg-muted/30 p-4">
                            <div className="text-xs font-medium text-muted-foreground">
                                Scope included in this band
                            </div>
                            <p className="mt-1 text-sm leading-6">
                                {quoteScopeDescription}
                            </p>
                        </div>
                    ) : null}

                    <div className="flex flex-col gap-3 rounded-md border bg-muted/30 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 className="text-sm font-medium">
                                FSA-hosted application
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Add a recurring managed-hosting charge when FSA
                                will host and operate the application.
                            </p>
                        </div>
                        <label className="flex items-center gap-2 text-sm font-medium">
                            <input
                                type="checkbox"
                                checked={scope.fsa_hosting_enabled}
                                onChange={(event) =>
                                    router.patch(
                                        urls.update,
                                        {
                                            fsa_hosting_enabled:
                                                event.target.checked,
                                        },
                                        { preserveScroll: true },
                                    )
                                }
                            />
                            FSA hosts this application
                        </label>
                    </div>

                    {scope.fsa_hosting_enabled ? (
                        <dl className="grid gap-4 rounded-md border p-4 sm:grid-cols-2">
                            <Detail
                                label="Client hosting charge ex GST"
                                value={`${money(hostingMonthlyFee)} / month`}
                                emphasis
                            />
                            <Detail
                                label="Annual client charge ex GST"
                                value={money(hostingAnnualFee)}
                            />
                        </dl>
                    ) : null}

                    <dl className="grid gap-4 border-y py-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Detail
                            label="Quoted fee ex GST"
                            value={money(computed.quoted_fee)}
                            emphasis
                        />
                        <Detail
                            label="Fee range ex GST"
                            value={range(computed.quote_range)}
                        />
                        <Detail
                            label="PV savings"
                            value={money(computed.pv_savings)}
                        />
                        <Detail
                            label="Payback"
                            value={`${number(computed.payback_months)} months`}
                        />
                    </dl>

                    {latestCalculation ? (
                        <div className="space-y-4">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-start gap-3">
                                    <CheckCircle2
                                        className="mt-0.5 size-5 text-emerald-600"
                                        aria-hidden="true"
                                    />
                                    <div>
                                        <h3 className="text-sm font-medium">
                                            Fee calculation created
                                        </h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {calculationDate(
                                                latestCalculation.created_at,
                                            )}{' '}
                                            -{' '}
                                            {money(
                                                latestCalculation.suggested_mid,
                                            )}{' '}
                                            recommended fee ex GST
                                        </p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            setShowCalculation(
                                                (visible) => !visible,
                                            )
                                        }
                                    >
                                        <Eye
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {showCalculation
                                            ? 'Hide calculation'
                                            : 'View calculation'}
                                    </Button>
                                    <Button asChild size="sm">
                                        <Link href={urls.clientProposals}>
                                            Create draft proposal
                                            <ArrowRight
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    </Button>
                                </div>
                            </div>

                            {showCalculation ? (
                                <dl className="grid gap-3 border-t pt-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <Detail
                                        label="Low estimate"
                                        value={money(
                                            latestCalculation.suggested_low,
                                        )}
                                    />
                                    <Detail
                                        label="Recommended fee"
                                        value={money(
                                            latestCalculation.suggested_mid,
                                        )}
                                        emphasis
                                    />
                                    <Detail
                                        label="High estimate"
                                        value={money(
                                            latestCalculation.suggested_high,
                                        )}
                                    />
                                    <Detail
                                        label="Savings to fee"
                                        value={ratio(
                                            latestCalculation.roi_ratio,
                                        )}
                                    />
                                </dl>
                            ) : null}
                        </div>
                    ) : (
                        <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-muted-foreground">
                                Create an internal fee calculation to prepare
                                this quote for a client proposal.
                            </p>
                            <Button
                                onClick={() => router.post(urls.feeCalculation)}
                            >
                                <Calculator
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Create fee calculation
                            </Button>
                        </div>
                    )}

                    <div className="flex flex-wrap gap-2 border-t pt-4">
                        {scope.flags.length === 0 ? (
                            <Badge variant="secondary">No active flags</Badge>
                        ) : (
                            scope.flags.map((flag) => (
                                <Badge key={flag.code} variant="outline">
                                    {flag.code.replaceAll('_', ' ')} /{' '}
                                    {flag.severity}
                                </Badge>
                            ))
                        )}
                    </div>
                </section>

                <section className="grid gap-4 lg:grid-cols-2">
                    <ListCard
                        title="Systems"
                        rows={scope.systems}
                        fields={[
                            'name',
                            'vendor',
                            'api_quality',
                            'monthly_records',
                        ]}
                    />
                    <ListCard
                        title="Duplicate-entry tasks"
                        rows={scope.tasks}
                        fields={[
                            'description',
                            'minutes_per_occurrence',
                            'occurrences_per',
                            'people_count',
                            'hourly_cost',
                        ]}
                    />
                    <ListCard
                        title="Connections"
                        rows={scope.connections}
                        fields={[
                            'from_system',
                            'to_system',
                            'direction',
                            'transform_complexity',
                        ]}
                    />
                </section>
            </main>
        </>
    );
}

function StatusBadge({ status }: { status: QuoteSourceExtraction['status'] }) {
    return (
        <Badge
            variant={
                status === 'extracted'
                    ? 'secondary'
                    : status === 'blocked'
                      ? 'destructive'
                      : 'outline'
            }
        >
            {status === 'extracted'
                ? 'Ready for review'
                : status === 'blocked'
                  ? 'Blocked'
                  : 'Preparing'}
        </Badge>
    );
}

function ReviewBadge({ status }: { status: QuoteSourceRow['review_status'] }) {
    return (
        <Badge variant={status === 'confirmed' ? 'secondary' : 'outline'}>
            {status}
        </Badge>
    );
}

function rowLabel(row: QuoteSourceRow): string {
    if (row.type === 'system') return String(row.name ?? 'System');
    if (row.type === 'task') return String(row.description ?? 'Duplicate-entry task');

    return `${String(row.from_system ?? 'System')} to ${String(row.to_system ?? 'system')}`;
}

function Metric({
    label,
    value,
    suffix = '',
}: {
    label: string;
    value: string;
    suffix?: string;
}) {
    return (
        <section className="rounded-md border bg-background p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-lg font-semibold">
                {value}
                {suffix}
            </div>
        </section>
    );
}

function ListCard({
    title,
    rows,
    fields,
}: {
    title: string;
    rows: Record<string, unknown>[];
    fields: string[];
}) {
    return (
        <section className="rounded-md border bg-background p-4">
            <h2 className="text-sm font-medium">{title}</h2>
            <div className="mt-3 space-y-3">
                {rows.map((row, index) => (
                    <div
                        key={String(row.id ?? index)}
                        className="border-t pt-3 first:border-t-0 first:pt-0"
                    >
                        {fields.map((field) => (
                            <div key={field} className="flex gap-2 text-sm">
                                <span className="w-32 shrink-0 text-muted-foreground">
                                    {field.replaceAll('_', ' ')}
                                </span>
                                <span className="break-words">
                                    {String(row[field] ?? '-')}
                                </span>
                            </div>
                        ))}
                    </div>
                ))}
            </div>
        </section>
    );
}

function Detail({
    label,
    value,
    emphasis = false,
}: {
    label: string;
    value: string;
    emphasis?: boolean;
}) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                className={
                    emphasis
                        ? 'mt-1 text-base font-semibold'
                        : 'mt-1 text-sm font-medium'
                }
            >
                {value}
            </dd>
        </div>
    );
}
function number(value: unknown): string {
    return typeof value === 'number'
        ? new Intl.NumberFormat('en-NZ', { maximumFractionDigits: 2 }).format(
              value,
          )
        : '-';
}
function objectValue(value: unknown): Record<string, unknown> | null {
    return typeof value === 'object' && value !== null
        ? (value as Record<string, unknown>)
        : null;
}
function numericValue(value: unknown): number | undefined {
    return typeof value === 'number' ? value : undefined;
}
function money(value: unknown): string {
    return typeof value === 'number'
        ? new Intl.NumberFormat('en-NZ', {
              style: 'currency',
              currency: 'NZD',
              maximumFractionDigits: 0,
          }).format(value)
        : '-';
}
function range(value: unknown): string {
    if (!value || typeof value !== 'object') return '-';
    const range = value as { low?: number; high?: number };
    return typeof range.low === 'number' && typeof range.high === 'number'
        ? `${money(range.low)} - ${money(range.high)}`
        : '-';
}
function ratio(value: number | null): string {
    return typeof value === 'number'
        ? `${new Intl.NumberFormat('en-NZ', { maximumFractionDigits: 1 }).format(value)}x`
        : '-';
}
function calculationDate(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('en-NZ', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Created now';
}

IntegrationScopeShow.layout = {
    breadcrumbs: [
        { title: 'Integration scopes', href: '/advisor/integration-scopes' },
    ],
};
