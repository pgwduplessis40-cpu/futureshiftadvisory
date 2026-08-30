import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Clock,
    DatabaseZap,
    HeartHandshake,
    Inbox,
    Lightbulb,
    PlugZap,
    Sparkles,
    TrendingUp,
    UsersRound,
} from 'lucide-react';
import type React from 'react';
import { InsightHoverCard } from '@/components/insight/InsightHoverCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PortfolioMetric } from './ClientHealthPanels';
import {
    HealthIcon,
    formatDate,
    formatDateOnly,
    formatIndicatorValue,
    formatLabel,
    formatPercent,
    formatSignedPercent,
    healthVariant,
} from './formatters';
import type {
    EconomicExposure,
    EconomicIndicatorItem,
    EconomicIndicatorsPayload,
    EntrepreneurReviewsPayload,
    ExchangeRateItem,
    IntegrationHealthPayload,
    LearningQueuePayload,
    PanelApprovalQueue,
    PanelOperationsPayload,
    PanelReferralQueue,
    ProspectInboxPayload,
    ReferenceDataTasksPayload,
    TrendDirection,
} from './types';

export function EntrepreneurReviewPanel({
    payload,
}: {
    payload: EntrepreneurReviewsPayload;
}) {
    return (
        <section
            id="advisor-entrepreneur-reviews"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Lightbulb className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Entrepreneur reviews
                    </h2>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge
                        variant={
                            payload.summary.idea_validations > 0
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {payload.summary.idea_validations} idea
                    </Badge>
                    <Badge
                        variant={
                            payload.summary.business_plans > 0
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {payload.summary.business_plans} plan
                    </Badge>
                </div>
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No entrepreneur idea or business plan reviews are waiting.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article
                            key={`${item.type}:${item.id}`}
                            className="flex flex-wrap items-center justify-between gap-3 p-3"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="outline">
                                        {item.label}
                                    </Badge>
                                    <Badge variant="secondary">
                                        {item.status}
                                    </Badge>
                                </div>
                                {item.detail_url ? (
                                    <Link
                                        href={item.detail_url}
                                        className="mt-2 block text-sm font-medium hover:underline focus-visible:underline focus-visible:outline-none"
                                    >
                                        {item.entrepreneur_name}
                                    </Link>
                                ) : (
                                    <div className="mt-2 text-sm font-medium">
                                        {item.entrepreneur_name}
                                    </div>
                                )}
                                <div className="text-xs text-muted-foreground">
                                    {item.entrepreneur_email ?? 'No email'} -{' '}
                                    {formatDate(item.submitted_at)}
                                </div>
                            </div>
                            {item.detail_url ? (
                                <Button asChild size="sm" variant="outline">
                                    <Link href={item.detail_url}>
                                        {item.action_label}
                                    </Link>
                                </Button>
                            ) : null}
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

export function ProspectInbox({ payload }: { payload: ProspectInboxPayload }) {
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

export function EconomicIndicators({
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
                        <InsightHoverCard
                            key={indicator.id}
                            title={indicator.label}
                            rows={economicIndicatorRows(indicator)}
                            drillHref={
                                indicator.exposure.drill_url ?? undefined
                            }
                            drillAriaLabel={`Open clients exposed to ${indicator.label}`}
                            footer={exposureFooter(indicator.exposure)}
                        >
                            <button
                                type="button"
                                className="grid w-full gap-2 p-3 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none sm:grid-cols-[1fr_auto]"
                            >
                                <span className="min-w-0">
                                    <span className="block text-sm font-medium">
                                        {indicator.label}
                                    </span>
                                    <span className="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                        <span>
                                            {formatDateOnly(
                                                indicator.period_date,
                                            )}
                                        </span>
                                        <Badge
                                            variant={
                                                indicator.degraded
                                                    ? 'outline'
                                                    : 'secondary'
                                            }
                                        >
                                            {formatLabel(
                                                indicator.source_badge,
                                            )}
                                        </Badge>
                                        <ExposureBadge
                                            exposure={indicator.exposure}
                                        />
                                    </span>
                                </span>
                                <span className="text-sm font-medium sm:text-right">
                                    {formatIndicatorValue(
                                        indicator.value,
                                        indicator.unit,
                                    )}
                                </span>
                            </button>
                        </InsightHoverCard>
                    ))}
                </div>
            )}

            {payload.exchange_rates.length > 0 && (
                <div className="grid gap-2 sm:grid-cols-2">
                    {payload.exchange_rates.map((rate) => (
                        <InsightHoverCard
                            key={rate.id}
                            title={`${rate.base_currency}/${rate.quote_currency}`}
                            rows={exchangeRateRows(rate)}
                            footer={exposureFooter(rate.exposure)}
                        >
                            <button
                                type="button"
                                className="w-full rounded-md border p-3 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            >
                                <span className="text-xs text-muted-foreground">
                                    {rate.base_currency}/{rate.quote_currency}
                                </span>
                                <span className="mt-1 block text-sm font-medium">
                                    {rate.rate.toFixed(4)}
                                </span>
                                <span className="mt-2 block">
                                    <ExposureBadge exposure={rate.exposure} />
                                </span>
                            </button>
                        </InsightHoverCard>
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

function ExposureBadge({ exposure }: { exposure: EconomicExposure }) {
    if (!exposure.supported) {
        return <Badge variant="outline">Exposure unavailable</Badge>;
    }

    return (
        <Badge variant="secondary">{exposure.exposed_count ?? 0} exposed</Badge>
    );
}

function economicIndicatorRows(indicator: EconomicIndicatorItem): Array<{
    label: string;
    value: string;
    tone?: 'default' | 'muted' | 'positive' | 'negative';
}> {
    return [
        {
            label: 'Current',
            value: formatIndicatorValue(indicator.value, indicator.unit),
        },
        {
            label: 'Previous',
            value:
                indicator.previous_value === null
                    ? 'No prior reading'
                    : formatIndicatorValue(
                          indicator.previous_value,
                          indicator.unit,
                      ),
            tone: indicator.previous_value === null ? 'muted' : 'default',
        },
        {
            label: 'Change',
            value: formatTrend(indicator.change_pct, indicator.direction),
            tone: trendTone(indicator.direction),
        },
        {
            label: 'Exposure',
            value: exposureSummary(indicator.exposure),
            tone: indicator.exposure.supported ? 'default' : 'muted',
        },
    ];
}

function exchangeRateRows(rate: ExchangeRateItem): Array<{
    label: string;
    value: string;
    tone?: 'default' | 'muted' | 'positive' | 'negative';
}> {
    return [
        {
            label: 'Current',
            value: rate.rate.toFixed(4),
        },
        {
            label: 'Previous',
            value:
                rate.previous_rate === null
                    ? 'No prior reading'
                    : rate.previous_rate.toFixed(4),
            tone: rate.previous_rate === null ? 'muted' : 'default',
        },
        {
            label: 'Change',
            value: formatTrend(rate.change_pct, rate.direction),
            tone: trendTone(rate.direction),
        },
        {
            label: 'Exposure',
            value: exposureSummary(rate.exposure),
            tone: 'muted',
        },
    ];
}

function exposureSummary(exposure: EconomicExposure): string {
    if (!exposure.supported) {
        return exposure.reason === 'classification_not_captured'
            ? 'Classification not captured'
            : 'Unavailable';
    }

    const exposed = exposure.exposed_count ?? 0;
    const unknown = exposure.unknown_count ?? 0;

    return unknown > 0
        ? `${exposed} exposed / ${unknown} unknown`
        : `${exposed} exposed`;
}

function exposureFooter(exposure: EconomicExposure): string | undefined {
    if (exposure.supported) {
        return exposure.unknown_count && exposure.unknown_count > 0
            ? 'Some clients lack enough financial data'
            : undefined;
    }

    return exposure.reason === 'classification_not_captured'
        ? 'Classification not captured'
        : 'Exposure unavailable';
}

export function ReferenceDataTasksPanel({
    payload,
}: {
    payload: ReferenceDataTasksPayload;
}) {
    const needsRefresh =
        payload.summary.missing +
        payload.summary.overdue +
        payload.summary.due_soon;

    return (
        <section
            id="advisor-reference-data-tasks"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <DatabaseZap className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Reference data</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={needsRefresh > 0 ? 'outline' : 'secondary'}>
                        {needsRefresh} due
                    </Badge>
                    {payload.index_url && (
                        <Button asChild size="sm" variant="outline">
                            <Link href={payload.index_url}>Open</Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="grid gap-2 sm:grid-cols-3">
                <PortfolioMetric
                    label="Missing"
                    value={payload.summary.missing.toString()}
                />
                <PortfolioMetric
                    label="Overdue"
                    value={payload.summary.overdue.toString()}
                />
                <PortfolioMetric
                    label="Due soon"
                    value={payload.summary.due_soon.toString()}
                />
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Implemented reference data is current.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article
                            key={item.key}
                            className="grid gap-3 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="line-clamp-1 text-sm font-medium">
                                        {item.label}
                                    </span>
                                    <Badge
                                        variant={
                                            item.status === 'missing' ||
                                            item.status === 'overdue'
                                                ? 'destructive'
                                                : 'outline'
                                        }
                                    >
                                        {formatLabel(item.status)}
                                    </Badge>
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {item.last_as_at
                                        ? `Last ${formatDateOnly(item.last_as_at)}`
                                        : 'No implemented value'}{' '}
                                    - Due {formatDateOnly(item.due_at)}
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {formatLabel(item.dataset)}
                                    {item.indicator
                                        ? ` - ${formatLabel(item.indicator)}`
                                        : ''}
                                    {item.source ? ` - ${item.source}` : ''}
                                </div>
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <Link href={item.action_url}>Record</Link>
                            </Button>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function formatTrend(
    changePct: number | null,
    direction: TrendDirection,
): string {
    if (direction === 'none' || changePct === null) {
        return 'No prior reading';
    }

    return `${formatSignedPercent(changePct)} ${directionLabel(direction)}`;
}

function directionLabel(direction: TrendDirection): string {
    if (direction === 'up') {
        return 'up';
    }

    if (direction === 'down') {
        return 'down';
    }

    if (direction === 'flat') {
        return 'flat';
    }

    return 'no prior';
}

function trendTone(
    direction: TrendDirection,
): 'default' | 'muted' | 'positive' | 'negative' {
    if (direction === 'none' || direction === 'flat') {
        return 'muted';
    }

    return direction === 'up' ? 'positive' : 'negative';
}

export function IntegrationHealth({
    payload,
}: {
    payload: IntegrationHealthPayload;
}) {
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

export function PanelOperations({
    payload,
}: {
    payload: PanelOperationsPayload;
}) {
    return (
        <div
            id="advisor-panel-operations"
            className="grid gap-4 xl:grid-cols-2 2xl:grid-cols-4"
        >
            <PanelApprovalQueuePanel payload={payload.approvals} />
            <PanelReferralQueuePanel
                id="advisor-broker-referrals"
                title="Broker referrals"
                description="Broker panel hand-offs and cover-placement progress."
                icon={<Inbox className="size-4" aria-hidden="true" />}
                payload={payload.broker}
                empty="No broker referrals in the current scope."
            />
            <PanelReferralQueuePanel
                id="advisor-coach-referrals"
                title="Coach referrals"
                description="Founder and client coaching hand-offs."
                icon={<HeartHandshake className="size-4" aria-hidden="true" />}
                payload={payload.coach}
                empty="No coach referrals in the current scope."
            />
            <LearningQueuePanel payload={payload.learning} />
        </div>
    );
}

export function PanelApprovalQueuePanel({
    payload,
}: {
    payload: PanelApprovalQueue;
}) {
    return (
        <section
            id="advisor-panel-approvals"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <UsersRound className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">
                            Partner approvals
                        </h2>
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Broker and coach applications waiting for review.
                    </p>
                </div>
                <Badge
                    variant={payload.summary.total > 0 ? 'default' : 'outline'}
                >
                    {payload.summary.total} pending
                </Badge>
            </div>

            <div className="grid gap-2 sm:grid-cols-3">
                <PortfolioMetric
                    label="Total"
                    value={payload.summary.total.toString()}
                />
                <PortfolioMetric
                    label="Brokers"
                    value={payload.summary.broker.toString()}
                />
                <PortfolioMetric
                    label="Coaches"
                    value={payload.summary.coach.toString()}
                />
            </div>

            {payload.review_url && (
                <Button asChild size="sm" variant="outline">
                    <Link href={payload.review_url}>Open approval queue</Link>
                </Button>
            )}

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No partner applications are waiting for approval.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article
                            key={item.id}
                            className="grid gap-3 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="truncate text-sm font-medium">
                                        {item.business_name}
                                    </span>
                                    <Badge variant="outline">
                                        {item.panel_label}
                                    </Badge>
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {item.contact_name} -{' '}
                                    {formatDate(item.applied_at)}
                                </div>
                                {item.email && (
                                    <div className="mt-1 truncate text-xs text-muted-foreground">
                                        {item.email}
                                    </div>
                                )}
                            </div>
                            {item.review_url && (
                                <Button asChild size="sm" variant="outline">
                                    <Link href={item.review_url}>Review</Link>
                                </Button>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

export function PanelReferralQueuePanel({
    id,
    title,
    description,
    icon,
    payload,
    empty,
}: {
    id: string;
    title: string;
    description: string;
    icon: React.ReactNode;
    payload: PanelReferralQueue;
    empty: string;
}) {
    const activeStages = Object.entries(payload.stage_counts).filter(
        ([, count]) => count > 0,
    );

    return (
        <section
            id={id}
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        {icon}
                        <h2 className="text-sm font-medium">{title}</h2>
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {description}
                    </p>
                </div>
                <Badge
                    variant={payload.summary.active > 0 ? 'default' : 'outline'}
                >
                    {payload.summary.active} active
                </Badge>
            </div>

            <div className="grid gap-2 sm:grid-cols-3">
                <PortfolioMetric
                    label="Total"
                    value={payload.summary.total.toString()}
                />
                <PortfolioMetric
                    label="Active"
                    value={payload.summary.active.toString()}
                />
                <PortfolioMetric
                    label="Closed"
                    value={payload.summary.terminal.toString()}
                />
            </div>

            {activeStages.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {activeStages.map(([stage, count]) => (
                        <Badge key={stage} variant="secondary">
                            {formatLabel(stage)} {count}
                        </Badge>
                    ))}
                </div>
            )}

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">{empty}</p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article
                            key={item.id}
                            className="grid gap-3 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="truncate text-sm font-medium">
                                        {item.subject_name}
                                    </span>
                                    <Badge variant="outline">
                                        {item.stage_label}
                                    </Badge>
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {item.panel_name} ·{' '}
                                    {formatDate(item.sent_at)}
                                </div>
                                {item.reason && (
                                    <p className="mt-2 line-clamp-2 text-sm">
                                        {item.reason}
                                    </p>
                                )}
                            </div>
                            {item.detail_url && (
                                <Button asChild size="sm" variant="outline">
                                    <Link href={item.detail_url}>Open</Link>
                                </Button>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

export function LearningQueuePanel({
    payload,
}: {
    payload: LearningQueuePayload;
}) {
    return (
        <section
            id="advisor-learning-queue"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <Sparkles className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Learning queue</h2>
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Governed updates awaiting review or approval.
                    </p>
                </div>
                {payload.queue_url && (
                    <Button asChild size="sm" variant="outline">
                        <Link href={payload.queue_url}>Open queue</Link>
                    </Button>
                )}
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                <PortfolioMetric
                    label="Awaiting review"
                    value={payload.summary.detected.toString()}
                />
                <PortfolioMetric
                    label="Awaiting approval"
                    value={payload.summary.staged.toString()}
                />
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No learning updates are waiting for review.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article
                            key={item.id}
                            className="grid gap-3 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="line-clamp-1 text-sm font-medium">
                                        {item.summary}
                                    </span>
                                    <Badge variant="outline">
                                        {formatLabel(item.status)}
                                    </Badge>
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {item.source_type
                                        ? formatLabel(item.source_type)
                                        : 'Learning update'}{' '}
                                    · {formatPercent(item.confidence)}{' '}
                                    confidence · {item.clients_affected} clients
                                </div>
                            </div>
                            {item.detail_url && (
                                <Button asChild size="sm" variant="outline">
                                    <Link href={item.detail_url}>Review</Link>
                                </Button>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}
