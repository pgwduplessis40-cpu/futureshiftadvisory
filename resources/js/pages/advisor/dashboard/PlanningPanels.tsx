import { Link, router } from '@inertiajs/react';
import { BarChart3, CheckCircle2, ShieldAlert, TrendingUp } from 'lucide-react';
import { InsightHoverCard } from '@/components/insight/InsightHoverCard';
import {
    PvSummaryBadges,
    WaterfallChart,
} from '@/components/pv/WaterfallChart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ClientNameLink } from './ClientHealthPanels';
import {
    formatCurrency,
    formatDate,
    formatLabel,
    formatPercent,
} from './formatters';
import type {
    FunnelAnalyticsPayload,
    PvWaterfallPayload,
    RedFlagsPayload,
    ScenarioPlanningPayload,
} from './types';

export function FunnelAnalytics({
    payload,
}: {
    payload: FunnelAnalyticsPayload;
}) {
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
                        <InsightHoverCard
                            key={`${step.flow}-${step.step}`}
                            title={`${formatLabel(step.flow)} / ${formatLabel(step.step)}`}
                            rows={[
                                {
                                    label: 'Entered',
                                    value: String(step.entered),
                                },
                                {
                                    label: 'Dropped',
                                    value: String(step.dropped_count),
                                    tone:
                                        step.dropped_count > 0
                                            ? 'negative'
                                            : 'default',
                                },
                                {
                                    label: 'Last dropped',
                                    value: formatDate(step.last_dropped_at),
                                    tone: step.last_dropped_at
                                        ? 'default'
                                        : 'muted',
                                },
                                {
                                    label: 'Returned',
                                    value: String(step.returned_count),
                                    tone:
                                        step.returned_count > 0
                                            ? 'positive'
                                            : 'default',
                                },
                            ]}
                        >
                            <div
                                tabIndex={0}
                                className="grid gap-2 p-3 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none sm:grid-cols-[1fr_auto]"
                            >
                                <div className="min-w-0">
                                    <div className="text-sm font-medium">
                                        {formatLabel(step.flow)} /{' '}
                                        {formatLabel(step.step)}
                                    </div>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        {step.completed} of {step.entered}{' '}
                                        completed
                                    </div>
                                    {step.dropped_clients.length > 0 && (
                                        <details className="mt-2 text-xs">
                                            <summary className="cursor-pointer font-medium text-primary outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                                Open
                                            </summary>
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                {step.dropped_clients.map(
                                                    (client) => (
                                                        <Link
                                                            key={client.id}
                                                            href={
                                                                client.show_url
                                                            }
                                                            className="rounded-md border px-2 py-1 text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                                        >
                                                            {client.name}
                                                        </Link>
                                                    ),
                                                )}
                                            </div>
                                        </details>
                                    )}
                                </div>
                                <div className="text-sm font-medium sm:text-right">
                                    {formatPercent(step.drop_off_rate)} drop-off
                                </div>
                            </div>
                        </InsightHoverCard>
                    ))}
                </div>
            )}
        </section>
    );
}

export function ScenarioPlanning({
    payload,
}: {
    payload: ScenarioPlanningPayload;
}) {
    return (
        <section
            id="advisor-scenario-planning"
            className="space-y-4 rounded-md border bg-background p-4"
        >
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
                <div className="max-h-[280px] divide-y overflow-y-auto rounded-md border">
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
                                    <ClientNameLink
                                        name={scenario.client_name}
                                        href={scenario.client_url}
                                        className="text-xs"
                                    />
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

export function PvWaterfallPanel({ payload }: { payload: PvWaterfallPayload }) {
    const featuredClient =
        [...payload.clients].sort(
            (a, b) =>
                b.improvement_pv +
                b.risk_mitigation_pv -
                (a.improvement_pv + a.risk_mitigation_pv),
        )[0] ?? null;

    return (
        <section
            id="advisor-pv-waterfall"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <TrendingUp className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">
                            Portfolio PV waterfall
                        </h2>
                    </div>
                    <p className="mt-1 max-w-2xl text-xs text-muted-foreground">
                        Totals include all visible clients with PV data. The
                        chart highlights the client with the largest improvement
                        or risk-mitigation movement. Modelled upside includes a
                        +/-15% planning range and assumes surfaced improvements
                        and risk mitigations are fully captured.
                    </p>
                </div>
                <div className="flex flex-wrap justify-start gap-2 sm:justify-end">
                    <Badge variant="outline">
                        {payload.summary.clients} clients
                    </Badge>
                    <PvSummaryBadges
                        current={payload.summary.current_pv}
                        target={payload.summary.target_pv}
                        targetRange={payload.summary.target_pv_range}
                    />
                </div>
            </div>

            {featuredClient === null ? (
                <p className="text-sm text-muted-foreground">
                    No PV baseline has been calculated yet.
                </p>
            ) : (
                <div className="space-y-4">
                    <div>
                        <div className="text-sm font-medium">
                            Featured client:{' '}
                            <ClientNameLink
                                name={featuredClient.client_name}
                                href={featuredClient.client_url}
                                className="text-sm"
                            />
                        </div>
                        <div className="mt-1 text-xs text-muted-foreground">
                            {formatCurrency(featuredClient.improvement_pv)}{' '}
                            improvements +{' '}
                            {formatCurrency(featuredClient.risk_mitigation_pv)}{' '}
                            risk mitigation
                        </div>
                    </div>
                    <div className="max-h-[500px] overflow-y-auto pr-1">
                        <WaterfallChart steps={featuredClient.waterfall} />
                    </div>
                </div>
            )}
        </section>
    );
}

export function RedFlagPanel({ payload }: { payload: RedFlagsPayload }) {
    const patch = (url: string) => {
        router.patch(url, {}, { preserveScroll: true });
    };

    return (
        <section
            id="advisor-red-flags"
            className="space-y-4 rounded-md border bg-background p-4"
        >
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
                    {payload.items.map((flag) => {
                        const openUrl = flag.finding_url ?? flag.client_url;

                        return (
                            <article key={flag.id} className="space-y-3 p-3">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <InsightHoverCard
                                        title={flag.headline}
                                        rows={[
                                            {
                                                label: 'Risk',
                                                value: flag.detail,
                                                tone: 'negative',
                                            },
                                            {
                                                label: 'Severity',
                                                value: formatLabel(
                                                    flag.severity,
                                                ),
                                                tone: 'negative',
                                            },
                                            {
                                                label: 'Detected',
                                                value: formatDate(
                                                    flag.surfaced_at,
                                                ),
                                            },
                                            {
                                                label: 'Trigger',
                                                value:
                                                    flag.trigger?.summary ??
                                                    'Source unavailable',
                                                tone: flag.trigger
                                                    ? 'default'
                                                    : 'muted',
                                            },
                                        ]}
                                        drillHref={
                                            flag.finding_url ?? undefined
                                        }
                                        drillAriaLabel={`Open finding for ${flag.headline}`}
                                        footer={
                                            flag.trigger
                                                ? `Source: ${flag.trigger.source_reference}`
                                                : undefined
                                        }
                                    >
                                        <div className="block min-w-0 space-y-1 text-left focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2">
                                            <span className="flex flex-wrap items-center gap-2">
                                                <Badge variant="destructive">
                                                    {formatLabel(flag.severity)}
                                                </Badge>
                                                <Badge variant="outline">
                                                    {formatLabel(flag.category)}
                                                </Badge>
                                                {flag.module && (
                                                    <Badge variant="secondary">
                                                        {formatLabel(
                                                            flag.module,
                                                        )}
                                                    </Badge>
                                                )}
                                            </span>
                                            <span className="block text-sm font-medium">
                                                {flag.headline}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                <ClientNameLink
                                                    name={flag.client_name}
                                                    href={flag.client_url}
                                                    className="text-xs text-muted-foreground"
                                                />{' '}
                                                - {formatDate(flag.surfaced_at)}
                                            </span>
                                        </div>
                                    </InsightHoverCard>
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={openUrl}>Open</Link>
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
                        );
                    })}
                </div>
            )}
        </section>
    );
}
