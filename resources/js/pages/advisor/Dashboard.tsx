import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    BarChart3,
    CheckCircle2,
    Clock,
    Inbox,
    PlugZap,
    ShieldAlert,
    TrendingUp,
    UsersRound,
} from 'lucide-react';
import {
    PvSummaryBadges,
    WaterfallChart,
} from '@/components/pv/WaterfallChart';
import type { WaterfallStep } from '@/components/pv/WaterfallChart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DocumentVerificationFlagPanel } from '@/components/verification/DocumentVerificationFlagPanel';
import type { DocumentVerificationFlag } from '@/components/verification/DocumentVerificationFlagPanel';
import { dashboard } from '@/routes';

type QualityLevel = 'high' | 'medium' | 'low' | 'insufficient';
type HealthLevel = 'green' | 'amber' | 'red';

type ClientHealth = {
    id: string;
    legal_name: string;
    trading_name: string | null;
    engagement_type_label: string;
    status: string;
    status_label: string;
    data_quality: QualityLevel;
    open_document_flags_count: number;
    last_activity_at: string | null;
    show_url: string;
};

type ClientsHealthPayload = {
    summary: {
        total: number;
        high: number;
        medium: number;
        low: number;
        insufficient: number;
        needs_attention: number;
    };
    clients: ClientHealth[];
};

type PendingTermsPayload = {
    latest_version: {
        id: string;
        version: string;
        published_at: string | null;
    } | null;
    total: number;
    items: Array<{
        id: string;
        client_id: string;
        client_name: string | null;
        user_id: number;
        user_name: string | null;
        user_email: string | null;
        role: string;
    }>;
};

type ProspectInboxPayload = {
    total: number;
    triage_enabled: boolean;
    index_url: string;
    items: Array<{
        id: number;
        name: string;
        email: string;
        company: string | null;
        source: string;
        status: string;
        created_at: string | null;
    }>;
};

type IntegrationHealthPayload = {
    summary: {
        total: number;
        green: number;
        amber: number;
        red: number;
    };
    index_url: string | null;
    services: Array<{
        id: string;
        service: string;
        health: HealthLevel;
        success_rate: number;
        p95_latency_ms: number | null;
        window_end: string | null;
    }>;
};

type EconomicIndicatorsPayload = {
    summary: {
        indicators: number;
        exchange_rates: number;
        change_alerts: number;
        latest_fetched_at: string | null;
    };
    indicators: Array<{
        id: string;
        indicator: string;
        label: string;
        value: number;
        unit: string;
        period_date: string | null;
        source: string;
        source_badge: string;
        degraded: boolean;
        fetched_at: string | null;
    }>;
    exchange_rates: Array<{
        id: string;
        base_currency: string;
        quote_currency: string;
        rate: number;
        rate_date: string | null;
        source: string;
        source_badge: string;
        degraded: boolean;
        fetched_at: string | null;
    }>;
    alerts: Array<{
        id: string;
        summary: string;
        created_at: string | null;
    }>;
};

type RedFlagsPayload = {
    summary: {
        open: number;
        unacknowledged: number;
    };
    items: Array<{
        id: string;
        client_id: string;
        client_name: string | null;
        analysis_finding_id: string | null;
        module: string | null;
        category: string;
        severity: string;
        headline: string;
        detail: string;
        surfaced_at: string | null;
        acknowledged_at: string | null;
        acknowledge_url: string;
        resolve_url: string;
        client_url: string;
    }>;
};

type PvWaterfallPayload = {
    summary: {
        clients: number;
        current_pv: number;
        improvement_pv: number;
        risk_mitigation_pv: number;
        target_pv: number;
    };
    clients: Array<{
        client_id: string;
        client_name: string;
        business_valuation_id: string | null;
        current_pv: number;
        improvement_pv: number;
        risk_mitigation_pv: number;
        target_pv: number;
        waterfall: WaterfallStep[];
    }>;
};

type ScenarioPlanningPayload = {
    summary: {
        scenarios: number;
        clients: number;
    };
    items: Array<{
        id: string;
        client_id: string;
        client_name: string | null;
        name: string;
        kind: string;
        pv_impact: number;
        position: number;
        is_client_visible: boolean;
    }>;
};

type FunnelAnalyticsPayload = {
    summary: {
        events: number;
        abandoned: number;
        completed: number;
        worst_drop_off_rate: number;
    };
    steps: Array<{
        flow: string;
        step: string;
        entered: number;
        completed: number;
        abandoned: number;
        drop_off_rate: number;
    }>;
};

type Props = {
    clientsHealth: ClientsHealthPayload;
    redFlags: RedFlagsPayload;
    documentVerificationFlags: DocumentVerificationFlag[];
    pendingTermsReacceptance: PendingTermsPayload;
    prospectInbox: ProspectInboxPayload;
    integrationHealth: IntegrationHealthPayload;
    economicIndicators: EconomicIndicatorsPayload;
    pvWaterfall: PvWaterfallPayload;
    scenarioPlanning: ScenarioPlanningPayload;
    funnelAnalytics: FunnelAnalyticsPayload;
};

export default function AdvisorDashboard({
    clientsHealth,
    redFlags,
    documentVerificationFlags,
    pendingTermsReacceptance,
    prospectInbox,
    integrationHealth,
    economicIndicators,
    pvWaterfall,
    scenarioPlanning,
    funnelAnalytics,
}: Props) {
    return (
        <>
            <Head title="Advisor dashboard" />

            <div className="space-y-6 p-4">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Activity className="size-4" aria-hidden="true" />
                            Live workspace
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            Advisor dashboard
                        </h1>
                    </div>

                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <Metric
                            label="Clients"
                            value={clientsHealth.summary.total}
                        />
                        <Metric
                            label="Data flags"
                            value={clientsHealth.summary.needs_attention}
                        />
                        <Metric
                            label="Red flags"
                            value={redFlags.summary.open}
                        />
                        <Metric
                            label="Documents"
                            value={documentVerificationFlags.length}
                        />
                        <Metric
                            label="Terms"
                            value={pendingTermsReacceptance.total}
                        />
                    </div>
                </header>

                <div className="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(360px,1fr)]">
                    <MyClientsHealth payload={clientsHealth} />

                    <div className="space-y-4">
                        <RedFlagPanel payload={redFlags} />
                        <DocumentVerificationFlagPanel
                            flags={documentVerificationFlags}
                        />
                        <PendingTermsReacceptance
                            payload={pendingTermsReacceptance}
                        />
                    </div>
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <PvWaterfallPanel payload={pvWaterfall} />
                    <ScenarioPlanning payload={scenarioPlanning} />
                    <ProspectInbox payload={prospectInbox} />
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <EconomicIndicators payload={economicIndicators} />
                    <IntegrationHealth payload={integrationHealth} />
                    <FunnelAnalytics payload={funnelAnalytics} />
                </div>

                <UpcomingPanels />
            </div>
        </>
    );
}

function FunnelAnalytics({ payload }: { payload: FunnelAnalyticsPayload }) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <BarChart3 className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Funnel analytics</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {payload.summary.events} events
                    </Badge>
                    <Badge
                        variant={
                            payload.summary.abandoned > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.abandoned} abandoned
                    </Badge>
                </div>
            </div>

            {payload.steps.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No funnel events captured yet.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.steps.slice(0, 6).map((step) => (
                        <div
                            key={`${step.flow}-${step.step}`}
                            className="grid gap-2 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <div className="text-sm font-medium">
                                    {formatLabel(step.flow)} /{' '}
                                    {formatLabel(step.step)}
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {step.completed} of {step.entered} completed
                                </div>
                            </div>
                            <div className="text-sm font-medium sm:text-right">
                                {formatPercent(step.drop_off_rate)} drop-off
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

function ScenarioPlanning({ payload }: { payload: ScenarioPlanningPayload }) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <TrendingUp className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Scenario planning</h2>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge variant="secondary">
                        {payload.summary.scenarios} scenarios
                    </Badge>
                    <Badge variant="outline">
                        {payload.summary.clients} clients
                    </Badge>
                </div>
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No scenarios prepared yet.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.slice(0, 5).map((scenario) => (
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
                                    {scenario.is_client_visible && (
                                        <Badge variant="secondary">
                                            Client
                                        </Badge>
                                    )}
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {scenario.client_name ?? 'Client'}
                                </div>
                            </div>
                            <div className="text-sm font-medium sm:text-right">
                                {formatCurrency(scenario.pv_impact)}
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function PvWaterfallPanel({ payload }: { payload: PvWaterfallPayload }) {
    const firstClient = payload.clients[0] ?? null;

    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <TrendingUp className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">PV waterfall</h2>
                </div>
                <PvSummaryBadges
                    current={payload.summary.current_pv}
                    target={payload.summary.target_pv}
                />
            </div>

            {firstClient === null ? (
                <p className="text-sm text-muted-foreground">
                    No PV baseline has been calculated yet.
                </p>
            ) : (
                <div className="space-y-4">
                    <div>
                        <div className="text-sm font-medium">
                            {firstClient.client_name}
                        </div>
                        <div className="mt-1 text-xs text-muted-foreground">
                            {formatCurrency(firstClient.improvement_pv)}{' '}
                            improvements +{' '}
                            {formatCurrency(firstClient.risk_mitigation_pv)}{' '}
                            risk mitigation
                        </div>
                    </div>
                    <WaterfallChart steps={firstClient.waterfall} />
                </div>
            )}
        </section>
    );
}

function RedFlagPanel({ payload }: { payload: RedFlagsPayload }) {
    const patch = (url: string) => {
        router.patch(url, {}, { preserveScroll: true });
    };

    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <ShieldAlert className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">AI red flags</h2>
                </div>
                <div className="flex flex-wrap justify-end gap-2">
                    <Badge
                        variant={
                            payload.summary.unacknowledged > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.unacknowledged} new
                    </Badge>
                    <Badge variant="secondary">
                        {payload.summary.open} open
                    </Badge>
                </div>
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No open red flags.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((flag) => (
                        <article key={flag.id} className="space-y-3 p-3">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="destructive">
                                            {formatLabel(flag.severity)}
                                        </Badge>
                                        <Badge variant="outline">
                                            {formatLabel(flag.category)}
                                        </Badge>
                                        {flag.module && (
                                            <Badge variant="secondary">
                                                {formatLabel(flag.module)}
                                            </Badge>
                                        )}
                                    </div>
                                    <h3 className="text-sm font-medium">
                                        {flag.headline}
                                    </h3>
                                    <div className="text-xs text-muted-foreground">
                                        {flag.client_name ?? 'Client'} -{' '}
                                        {formatDate(flag.surfaced_at)}
                                    </div>
                                </div>
                                <Button asChild size="sm" variant="outline">
                                    <Link href={flag.client_url}>Open</Link>
                                </Button>
                            </div>

                            <p className="line-clamp-3 text-sm text-muted-foreground">
                                {flag.detail}
                            </p>

                            <div className="flex flex-wrap gap-2">
                                {flag.acknowledged_at === null && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            patch(flag.acknowledge_url)
                                        }
                                    >
                                        <CheckCircle2
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Acknowledge
                                    </Button>
                                )}
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => patch(flag.resolve_url)}
                                >
                                    <CheckCircle2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Resolve
                                </Button>
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-md border bg-background px-4 py-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-lg font-semibold">{value}</div>
        </div>
    );
}

function MyClientsHealth({ payload }: { payload: ClientsHealthPayload }) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <UsersRound className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">My clients health</h2>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge variant="secondary">
                        {payload.summary.high} high
                    </Badge>
                    <Badge variant="outline">
                        {payload.summary.medium} medium
                    </Badge>
                    <Badge variant="destructive">
                        {payload.summary.needs_attention} attention
                    </Badge>
                </div>
            </div>

            {payload.clients.length === 0 ? (
                <p className="rounded-md border px-3 py-8 text-sm text-muted-foreground">
                    No assigned clients.
                </p>
            ) : (
                <div className="overflow-hidden rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Client
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Quality
                                </th>
                                <th className="px-3 py-2 font-medium">Flags</th>
                                <th className="px-3 py-2 font-medium">
                                    Activity
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Open
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {payload.clients.map((client) => (
                                <tr key={client.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <div className="font-medium">
                                            {client.legal_name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {client.trading_name ??
                                                client.engagement_type_label}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge
                                            variant={qualityVariant(
                                                client.data_quality,
                                            )}
                                        >
                                            {qualityLabel(client.data_quality)}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge
                                            variant={
                                                client.open_document_flags_count >
                                                0
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                        >
                                            {client.open_document_flags_count}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {formatDate(client.last_activity_at)}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link href={client.show_url}>
                                                Open
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

function PendingTermsReacceptance({
    payload,
}: {
    payload: PendingTermsPayload;
}) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <ShieldAlert className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Pending terms re-acceptance
                    </h2>
                </div>
                <Badge variant={payload.total > 0 ? 'destructive' : 'outline'}>
                    {payload.total}
                </Badge>
            </div>

            {payload.latest_version && (
                <p className="text-xs text-muted-foreground">
                    Current version {payload.latest_version.version}
                </p>
            )}

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No client contacts are waiting on terms.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <div key={item.id} className="space-y-1 p-3">
                            <div className="text-sm font-medium">
                                {item.user_name ?? item.user_email}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {item.client_name ?? 'Client'} -{' '}
                                {item.user_email}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

function ProspectInbox({ payload }: { payload: ProspectInboxPayload }) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Inbox className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Prospect inbox</h2>
                </div>
                <div className="flex items-center gap-2">
                    <Badge variant="secondary">{payload.total} total</Badge>
                    <Button asChild size="sm" variant="outline">
                        <Link href={payload.index_url}>Open</Link>
                    </Button>
                </div>
            </div>

            {!payload.triage_enabled && (
                <div className="flex items-center gap-2 rounded-md border px-3 py-2 text-xs text-muted-foreground">
                    <Clock className="size-3.5" aria-hidden="true" />
                    Triage pending website intake
                </div>
            )}

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No prospect leads captured.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((lead) => (
                        <div key={lead.id} className="space-y-1 p-3">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <div className="text-sm font-medium">
                                        {lead.name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {lead.company ?? lead.email}
                                    </div>
                                </div>
                                <div className="flex flex-wrap justify-end gap-2">
                                    <Badge variant="outline">
                                        {lead.source}
                                    </Badge>
                                    <Badge variant="secondary">
                                        {lead.status}
                                    </Badge>
                                </div>
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {formatDate(lead.created_at)}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

function EconomicIndicators({
    payload,
}: {
    payload: EconomicIndicatorsPayload;
}) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <TrendingUp className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Economic indicators</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant={
                            payload.summary.change_alerts > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.change_alerts} changes
                    </Badge>
                    <Badge variant="secondary">
                        {payload.summary.indicators} indicators
                    </Badge>
                </div>
            </div>

            {payload.indicators.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No economic indicators refreshed.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.indicators.map((indicator) => (
                        <div
                            key={indicator.id}
                            className="grid gap-2 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <div className="text-sm font-medium">
                                    {indicator.label}
                                </div>
                                <div className="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                    <span>
                                        {formatDateOnly(indicator.period_date)}
                                    </span>
                                    <Badge
                                        variant={
                                            indicator.degraded
                                                ? 'outline'
                                                : 'secondary'
                                        }
                                    >
                                        {formatLabel(indicator.source_badge)}
                                    </Badge>
                                </div>
                            </div>
                            <div className="text-sm font-medium sm:text-right">
                                {formatIndicatorValue(
                                    indicator.value,
                                    indicator.unit,
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {payload.exchange_rates.length > 0 && (
                <div className="grid gap-2 sm:grid-cols-2">
                    {payload.exchange_rates.map((rate) => (
                        <div key={rate.id} className="rounded-md border p-3">
                            <div className="text-xs text-muted-foreground">
                                {rate.base_currency}/{rate.quote_currency}
                            </div>
                            <div className="mt-1 text-sm font-medium">
                                {rate.rate.toFixed(4)}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {payload.alerts.length > 0 && (
                <div className="space-y-2">
                    {payload.alerts.map((alert) => (
                        <div
                            key={alert.id}
                            className="flex gap-2 rounded-md border px-3 py-2 text-xs text-muted-foreground"
                        >
                            <AlertTriangle
                                className="mt-0.5 size-3.5 text-destructive"
                                aria-hidden="true"
                            />
                            <span>{alert.summary}</span>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

function IntegrationHealth({ payload }: { payload: IntegrationHealthPayload }) {
    const dashboardUrl = payload.index_url;

    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <PlugZap className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Integration health</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {payload.summary.green} green
                    </Badge>
                    <Badge variant="outline">
                        {payload.summary.amber} amber
                    </Badge>
                    <Badge variant="destructive">
                        {payload.summary.red} red
                    </Badge>
                    {dashboardUrl && (
                        <Button asChild size="sm" variant="outline">
                            <Link href={dashboardUrl}>Open</Link>
                        </Button>
                    )}
                </div>
            </div>

            {payload.services.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No integration samples yet.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.services.map((service) => (
                        <div
                            key={service.id}
                            className="grid gap-2 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <HealthIcon health={service.health} />
                                    <span className="text-sm font-medium">
                                        {service.service}
                                    </span>
                                    <Badge
                                        variant={healthVariant(service.health)}
                                    >
                                        {service.health}
                                    </Badge>
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {formatDate(service.window_end)}
                                </div>
                            </div>
                            <div className="text-sm text-muted-foreground sm:text-right">
                                <div>
                                    {formatPercent(service.success_rate)}{' '}
                                    success
                                </div>
                                <div>
                                    p95{' '}
                                    {service.p95_latency_ms === null
                                        ? 'n/a'
                                        : `${service.p95_latency_ms}ms`}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

function UpcomingPanels() {
    const panels = [
        'Proposals',
        'Payments',
        'Broker referrals',
        'Coach referrals',
        'Learning queue',
    ];

    return (
        <section className="space-y-3 rounded-md border bg-background p-4">
            <div className="flex items-center gap-2">
                <AlertTriangle className="size-4" aria-hidden="true" />
                <h2 className="text-sm font-medium">Upcoming panels</h2>
            </div>
            <div className="flex flex-wrap gap-2">
                {panels.map((panel) => (
                    <Badge key={panel} variant="outline">
                        {panel}
                    </Badge>
                ))}
            </div>
        </section>
    );
}

function HealthIcon({ health }: { health: HealthLevel }) {
    if (health === 'green') {
        return (
            <CheckCircle2
                className="size-4 text-emerald-600"
                aria-hidden="true"
            />
        );
    }

    return (
        <AlertTriangle
            className={
                health === 'amber'
                    ? 'size-4 text-amber-600'
                    : 'size-4 text-destructive'
            }
            aria-hidden="true"
        />
    );
}

function qualityLabel(level: QualityLevel): string {
    return level.replace('_', ' ');
}

function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function qualityVariant(
    level: QualityLevel,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (level === 'high') {
        return 'default';
    }

    if (level === 'medium') {
        return 'secondary';
    }

    if (level === 'low') {
        return 'outline';
    }

    return 'destructive';
}

function healthVariant(
    health: HealthLevel,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (health === 'green') {
        return 'secondary';
    }

    if (health === 'amber') {
        return 'outline';
    }

    return 'destructive';
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'No activity';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatDateOnly(value: string | null): string {
    if (!value) {
        return 'No period';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
}

function formatPercent(value: number): string {
    return `${Math.round(value * 1000) / 10}%`;
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'NZD',
        maximumFractionDigits: 0,
    }).format(value);
}

function formatIndicatorValue(value: number, unit: string): string {
    if (unit === 'percent') {
        return `${value.toFixed(1)}%`;
    }

    if (unit === 'nzd_per_hour') {
        return `$${value.toFixed(2)}/hr`;
    }

    return value.toLocaleString();
}

AdvisorDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
