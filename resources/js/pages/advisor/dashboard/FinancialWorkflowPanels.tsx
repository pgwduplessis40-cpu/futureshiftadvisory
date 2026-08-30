import { Link } from '@inertiajs/react';
import {
    Clock,
    CreditCard,
    FileText,
    HeartHandshake,
    HeartPulse,
    PieChart,
    Sparkles,
} from 'lucide-react';
import { InsightHoverCard } from '@/components/insight/InsightHoverCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ClientNameLink, PortfolioMetric } from './ClientHealthPanels';
import {
    formatCurrency,
    formatDate,
    formatDateOnly,
    formatLabel,
    formatMoney,
    formatPercent,
    paymentStatusVariant,
} from './formatters';
import type {
    CoachSignalsPayload,
    NpoFundingPayload,
    NpoPendingConversionsPayload,
    PaymentStatusPayload,
    PracticeHealthPayload,
    ProposalStatusPayload,
    QuestionnaireOptimisationPayload,
    WellbeingAnalyticsPayload,
} from './types';

export function ProposalStatusPanel({
    payload,
}: {
    payload: ProposalStatusPayload;
}) {
    const statusOrder = ['draft', 'released', 'recalled', 'expired', 'renewed'];

    return (
        <section
            id="advisor-proposals"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <FileText className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Proposals</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {payload.summary.total} total
                    </Badge>
                    <Badge
                        variant={
                            payload.summary.expiring_soon > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.expiring_soon} expiring
                    </Badge>
                </div>
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                {statusOrder.map((status) => (
                    <PortfolioMetric
                        key={status}
                        label={formatLabel(status)}
                        value={(payload.statuses[status] ?? 0).toString()}
                    />
                ))}
            </div>

            {payload.expiry_alerts.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No released proposals are expiring soon.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.expiry_alerts.map((proposal) => (
                        <div
                            key={proposal.id}
                            className="grid gap-3 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <ClientNameLink
                                    name={proposal.client_name}
                                    href={proposal.client_url}
                                    className="truncate text-sm"
                                />
                                <div className="mt-1 text-xs text-muted-foreground">
                                    v{proposal.version} -{' '}
                                    {formatDate(proposal.expires_at)}
                                </div>
                                <p className="mt-1 max-w-2xl text-sm leading-5 text-muted-foreground">
                                    {proposal.brief}
                                </p>
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <Link href={proposal.client_url}>Open</Link>
                            </Button>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

export function PaymentStatusPanel({
    payload,
}: {
    payload: PaymentStatusPayload;
}) {
    return (
        <section
            id="advisor-payments"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <CreditCard className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Payment exceptions</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant={
                            payload.summary.failed > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.failed} failed
                    </Badge>
                    <Badge variant="secondary">
                        {payload.summary.retryable} retryable
                    </Badge>
                </div>
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No failed or retrying payments.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((payment) => (
                        <article
                            key={payment.id}
                            className="grid gap-3 p-3 sm:grid-cols-[minmax(0,1fr)_auto]"
                        >
                            <InsightHoverCard
                                title={payment.client_name ?? 'Client payment'}
                                rows={[
                                    {
                                        label: 'Amount',
                                        value: formatMoney(
                                            payment.amount,
                                            payment.currency,
                                        ),
                                    },
                                    {
                                        label: 'Processed',
                                        value: formatDate(payment.processed_at),
                                    },
                                    {
                                        label: 'Failure reason',
                                        value:
                                            payment.failed_reason ??
                                            'Not recorded',
                                        tone: payment.failed_reason
                                            ? 'negative'
                                            : 'muted',
                                    },
                                    {
                                        label: 'Attempt',
                                        value: `#${payment.attempt}`,
                                    },
                                    {
                                        label: 'Next retry',
                                        value: formatDate(
                                            payment.automatic_next_retry_at,
                                        ),
                                        tone: payment.automatic_next_retry_at
                                            ? 'default'
                                            : 'muted',
                                    },
                                ]}
                                drillHref={payment.drill_url}
                                drillAriaLabel={`Open payment record for ${payment.client_name ?? 'client'}`}
                                footer={
                                    payment.manual_retry_available
                                        ? 'Manual retry available'
                                        : 'Retry unavailable'
                                }
                            >
                                <div className="min-w-0 space-y-1 text-left focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2">
                                    <span className="flex flex-wrap items-center gap-2">
                                        <Badge
                                            variant={paymentStatusVariant(
                                                payment.status,
                                            )}
                                        >
                                            {formatLabel(payment.status)}
                                        </Badge>
                                        <span className="text-sm font-medium">
                                            {formatMoney(
                                                payment.amount,
                                                payment.currency,
                                            )}
                                        </span>
                                    </span>
                                    <ClientNameLink
                                        name={payment.client_name}
                                        href={payment.client_url}
                                        className="block truncate text-sm"
                                    />
                                    <span className="block text-xs text-muted-foreground">
                                        Attempt {payment.attempt} -{' '}
                                        {formatDate(payment.processed_at)}
                                    </span>
                                </div>
                            </InsightHoverCard>
                            <Button asChild size="sm" variant="outline">
                                <Link href={payment.drill_url}>Open</Link>
                            </Button>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

export function NpoPendingConversions({
    payload,
}: {
    payload: NpoPendingConversionsPayload;
}) {
    return (
        <section
            id="advisor-npo-conversions"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <Clock className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Pending conversion</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {payload.summary.total} total
                    </Badge>
                    <Badge
                        variant={
                            payload.summary.nudge_due > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.nudge_due} nudge due
                    </Badge>
                </div>
            </div>

            <div className="grid gap-2 sm:grid-cols-3">
                <PortfolioMetric
                    label="Delivered"
                    value={payload.summary.report_delivered.toString()}
                />
                <PortfolioMetric
                    label="Declined"
                    value={payload.summary.declined.toString()}
                />
                <PortfolioMetric
                    label="Due"
                    value={payload.summary.nudge_due.toString()}
                />
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No Governance Review conversions are pending.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article
                            key={item.id}
                            className="grid gap-3 p-3 sm:grid-cols-[minmax(0,1fr)_auto]"
                        >
                            <div className="min-w-0 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge
                                        variant={
                                            item.status === 'declined'
                                                ? 'outline'
                                                : 'secondary'
                                        }
                                    >
                                        {item.status_label ??
                                            formatLabel(item.status ?? '')}
                                    </Badge>
                                    {item.next_nudge_day && (
                                        <Badge variant="destructive">
                                            {item.next_nudge_day}d
                                        </Badge>
                                    )}
                                </div>
                                <ClientNameLink
                                    name={item.client_name}
                                    href={item.client_url}
                                    className="truncate text-sm"
                                />
                                <div className="text-xs text-muted-foreground">
                                    Delivered{' '}
                                    {formatDate(item.report_delivered_at)} ·
                                    re-engage{' '}
                                    {formatDateOnly(item.reengagement_due_at)}
                                </div>
                                {item.decline_reason && (
                                    <div className="line-clamp-2 text-xs text-muted-foreground">
                                        {item.decline_reason}
                                    </div>
                                )}
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <Link href={item.client_url}>Open</Link>
                            </Button>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

export function NpoFundingPanel({ payload }: { payload: NpoFundingPayload }) {
    return (
        <section
            id="advisor-npo-funding"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <HeartHandshake className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">NPO funding</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {payload.summary.active_records} active
                    </Badge>
                    <Badge
                        variant={
                            payload.summary.critical_alerts > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.active_alerts} alerts
                    </Badge>
                </div>
            </div>

            {payload.alerts.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No funder deadlines are currently due.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.alerts.map((alert) => (
                        <article
                            key={alert.id}
                            className="grid gap-3 p-3 sm:grid-cols-[minmax(0,1fr)_auto]"
                        >
                            <div className="min-w-0 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge
                                        variant={
                                            alert.severity === 'critical'
                                                ? 'destructive'
                                                : 'outline'
                                        }
                                    >
                                        {formatLabel(alert.severity)}
                                    </Badge>
                                    <Badge variant="secondary">
                                        {formatLabel(alert.type)}
                                    </Badge>
                                </div>
                                <ClientNameLink
                                    name={alert.client_name}
                                    href={alert.client_url}
                                    className="truncate text-sm"
                                />
                                <div className="text-xs text-muted-foreground">
                                    {alert.funder_name ?? 'Funder'} - due{' '}
                                    {formatDateOnly(alert.due_on)}
                                </div>
                                <div className="line-clamp-2 text-xs text-muted-foreground">
                                    {alert.message}
                                </div>
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <Link href={alert.client_url}>Open</Link>
                            </Button>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

export function PracticeHealth({
    payload,
}: {
    payload: PracticeHealthPayload;
}) {
    const topClients = [...payload.clients]
        .sort((a, b) => b.target_pv - a.target_pv)
        .slice(0, 4);

    return (
        <section
            id="advisor-practice-health"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <PieChart className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Practice health</h2>
                    </div>
                    <p className="mt-1 max-w-2xl text-xs text-muted-foreground">
                        Measures portfolio position: active clients, revenue
                        under management, target PV, released proposals,
                        generated reports, and open red flags.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {payload.summary.active_clients} active
                    </Badge>
                    <Badge
                        variant={
                            payload.phase_two.open_red_flags > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.phase_two.open_red_flags} red flags
                    </Badge>
                </div>
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                <PortfolioMetric
                    label="Modelled upside PV"
                    value={formatCurrency(payload.summary.target_pv)}
                />
                <PortfolioMetric
                    label="Revenue"
                    value={formatCurrency(
                        payload.summary.revenue_under_management,
                    )}
                />
                <PortfolioMetric
                    label="Released proposals"
                    value={payload.phase_two.released_proposals.toString()}
                />
                <PortfolioMetric
                    label="Reports"
                    value={payload.phase_two.generated_reports.toString()}
                />
            </div>

            {topClients.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No active client PV portfolio yet.
                </p>
            ) : (
                <div className="max-h-[280px] divide-y overflow-y-auto rounded-md border">
                    {topClients.map((client) => (
                        <div
                            key={client.client_id}
                            className="grid gap-2 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <ClientNameLink
                                    name={client.client_name}
                                    href={client.client_url}
                                    className="truncate text-sm"
                                />
                                <div className="mt-1 text-xs text-muted-foreground">
                                    Revenue{' '}
                                    {formatCurrency(
                                        client.revenue_under_management,
                                    )}
                                </div>
                            </div>
                            <div className="text-sm font-medium sm:text-right">
                                {formatCurrency(client.target_pv)}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

export function QuestionnaireOptimisation({
    payload,
}: {
    payload: QuestionnaireOptimisationPayload;
}) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <Sparkles className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Questionnaire optimisation
                    </h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant={
                            payload.summary.detected_candidates > 0
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {payload.summary.detected_candidates} candidates
                    </Badge>
                    <Badge variant="outline">
                        {payload.summary.latest_candidates_created} latest
                    </Badge>
                </div>
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No governed candidates queued.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article key={item.id} className="space-y-2 p-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">
                                    {formatLabel(item.magnitude)}
                                </Badge>
                                <span className="text-xs text-muted-foreground">
                                    {formatPercent(item.confidence)} confidence
                                </span>
                            </div>
                            <h3 className="line-clamp-2 text-sm font-medium">
                                {item.questionnaire_title ??
                                    'Questionnaire candidate'}
                            </h3>
                            <p className="line-clamp-3 text-sm text-muted-foreground">
                                {item.summary}
                            </p>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

export function WellbeingAnalytics({
    payload,
}: {
    payload: WellbeingAnalyticsPayload;
}) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4 xl:col-span-2">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <HeartPulse className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Wellbeing trends</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {payload.summary.checkins} check-ins
                    </Badge>
                    <Badge
                        variant={
                            payload.summary.active_low_coping_signals > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.active_low_coping_signals} signals
                    </Badge>
                </div>
            </div>

            <div className="grid gap-2 sm:grid-cols-4">
                <PortfolioMetric
                    label="Confidence"
                    value={`${payload.summary.average_business_confidence}/5`}
                />
                <PortfolioMetric
                    label="Coping"
                    value={`${payload.summary.average_personal_coping}/5`}
                />
                <PortfolioMetric
                    label="Low coping"
                    value={payload.summary.low_personal_coping_checkins.toString()}
                />
                <PortfolioMetric
                    label="This month"
                    value={formatPercent(
                        payload.summary.current_period_completion_rate,
                    )}
                />
            </div>

            {payload.monthly.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No wellbeing check-ins captured yet.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.monthly.slice(-6).map((month) => (
                        <div
                            key={month.period_start}
                            className="grid gap-2 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <div className="text-sm font-medium">
                                    {formatDateOnly(month.period_start)}
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {month.checkins} check-ins -{' '}
                                    {month.low_personal_coping_checkins} low
                                    coping
                                </div>
                            </div>
                            <div className="text-sm text-muted-foreground sm:text-right">
                                <div>
                                    Confidence{' '}
                                    {month.average_business_confidence}/5
                                </div>
                                <div>
                                    Coping {month.average_personal_coping}/5
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

export function CoachSignals({ payload }: { payload: CoachSignalsPayload }) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <HeartHandshake className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Coach signals</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">
                        {payload.summary.total} suggested
                    </Badge>
                    <Badge variant="outline">
                        {payload.summary.auto_referrals} auto
                    </Badge>
                </div>
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No coach signal suggestions surfaced.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.slice(0, 5).map((item) => (
                        <div key={item.id} className="space-y-2 p-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">
                                    {formatLabel(item.suggested_specialisation)}
                                </Badge>
                                <ClientNameLink
                                    name={item.client_name}
                                    href={item.client_url}
                                    className="text-sm"
                                />
                            </div>
                            <p className="text-xs leading-5 text-muted-foreground">
                                {item.rationale}
                            </p>
                            <div className="text-xs text-muted-foreground">
                                {formatLabel(item.signal_type ?? 'signal')} -{' '}
                                {item.threshold_ref}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}
