import { Link, router } from '@inertiajs/react';
import {
    Banknote,
    ListChecks,
    ShieldAlert,
    UsersRound,
} from 'lucide-react';
import type React from 'react';
import { InsightHoverCard } from '@/components/insight/InsightHoverCard';
import type { InsightHoverCardRow } from '@/components/insight/InsightHoverCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import {
    engagementLabel,
    formatCurrency,
    formatDate,
    formatLabel,
    formatRunway,
    healthVariant,
} from './formatters';
import type {
    CashFlowStatus,
    CashFlowStatusPayload,
    ClientsHealthPayload,
    EngagementScore,
    StrategicPlanDeploymentsPayload,
    PendingTermsPayload,
} from './types';

export function ClientNameLink({
    name,
    href,
    className,
}: {
    name: React.ReactNode;
    href?: string | null;
    className?: string;
}) {
    const content = name ?? 'Client';

    if (!href) {
        return <span className={cn('font-medium', className)}>{content}</span>;
    }

    return (
        <Link
            href={href}
            className={cn(
                'inline-block max-w-full font-medium text-foreground hover:underline focus-visible:underline focus-visible:outline-none',
                className,
            )}
        >
            {content}
        </Link>
    );
}

export function PortfolioMetric({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    return (
        <div className="rounded-md border px-3 py-2">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-sm font-medium">{value}</div>
        </div>
    );
}

export function Metric({
    label,
    value,
    explanation,
    href,
}: {
    label: string;
    value: number | string;
    explanation: string;
    href: string;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <a
                    href={href}
                    className="rounded-md border bg-card px-4 py-3 shadow-card transition-colors outline-none hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 dark:hover:bg-card/90"
                >
                    <div className="text-xs text-muted-foreground">{label}</div>
                    <div className="mt-1 text-lg font-semibold">{value}</div>
                </a>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {explanation}
            </TooltipContent>
        </Tooltip>
    );
}

export function MyClientsHealth({
    payload,
}: {
    payload: ClientsHealthPayload;
}) {
    return (
        <section
            id="advisor-clients-health"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <UsersRound className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">My clients health</h2>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge variant="secondary">
                        {payload.summary.high} green
                    </Badge>
                    <Badge variant="outline">
                        {payload.summary.medium} amber
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
                <div className="max-h-[460px] overflow-y-auto rounded-md border">
                    <table className="fsa-responsive-table">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Client
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Engagement
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Cash flow
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
                                    <td
                                        className="px-3 py-2"
                                        data-label="Client"
                                    >
                                        <Link
                                            href={client.show_url}
                                            className="font-medium hover:underline focus-visible:underline focus-visible:outline-none"
                                        >
                                            {client.legal_name}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {client.trading_name ??
                                                client.engagement_type_label}
                                        </div>
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Engagement"
                                    >
                                        <EngagementBadge
                                            engagement={client.engagement}
                                        />
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Cash flow"
                                    >
                                        <CashFlowBadge
                                            cashFlow={client.cash_flow}
                                        />
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Flags"
                                    >
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
                                    <td
                                        className="px-3 py-2 text-muted-foreground"
                                        data-label="Activity"
                                    >
                                        {formatDate(client.last_activity_at)}
                                    </td>
                                    <td
                                        className="px-3 py-2 text-left md:text-right"
                                        data-label="Open"
                                    >
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

export function EngagementBadge({
    engagement,
}: {
    engagement: EngagementScore;
}) {
    const rows: InsightHoverCardRow[] =
        engagement.scoring_mode === 'entrepreneur_validation'
            ? [
                  {
                      label: 'Idea validation',
                      value: `${engagement.scores.idea_validation_pct ?? 0}%`,
                      tone:
                          engagement.display.idea_validation_status ===
                          'awaiting_resubmission'
                              ? 'default'
                              : engagement.display.idea_validation_status ===
                                      'refresh_failed' ||
                                  engagement.display.idea_validation_status ===
                                      'recalled'
                                ? 'negative'
                                : 'default',
                  },
                  {
                      label: 'Status',
                      value: formatLabel(
                          engagement.display.idea_validation_status ??
                              'submitted',
                      ),
                  },
                  {
                      label: 'Activity',
                      value:
                          engagement.display.last_activity_days == null
                              ? 'Never'
                              : `${engagement.display.last_activity_days} days`,
                  },
                  {
                      label: 'Milestones',
                      value: `${engagement.scores.milestones_on_track_pct}% (${engagement.display.overdue_count} overdue / ${engagement.display.blocked_count} blocked)`,
                      tone:
                          engagement.display.overdue_count > 0 ||
                          engagement.display.blocked_count > 0
                              ? 'negative'
                              : 'default',
                  },
              ]
            : engagement.scoring_mode === 'entrepreneur_plan'
              ? [
                    {
                        label: 'Business plan',
                        value: `${engagement.scores.plan_progress_pct ?? 0}%`,
                    },
                    {
                        label: 'Activity',
                        value:
                            engagement.display.last_activity_days == null
                                ? 'Never'
                                : `${engagement.display.last_activity_days} days`,
                    },
                    {
                        label: 'Milestones',
                        value: `${engagement.scores.milestones_on_track_pct}% (${engagement.display.overdue_count} overdue / ${engagement.display.blocked_count} blocked)`,
                        tone:
                            engagement.display.overdue_count > 0 ||
                            engagement.display.blocked_count > 0
                                ? 'negative'
                                : 'default',
                    },
                ]
              : [
                    {
                        label: 'Questionnaire',
                        value: `${engagement.scores.questionnaire_pct}%`,
                    },
                    {
                        label: 'Documents',
                        value: `${engagement.scores.documents_pct}%`,
                    },
                    {
                        label: 'Milestones',
                        value: `${engagement.scores.milestones_on_track_pct}% (${engagement.display.overdue_count} overdue / ${engagement.display.blocked_count} blocked)`,
                        tone:
                            engagement.display.overdue_count > 0 ||
                            engagement.display.blocked_count > 0
                                ? 'negative'
                                : 'default',
                    },
                    {
                        label: 'Last comms',
                        value:
                            engagement.display.last_comms_days === null
                                ? 'Never'
                                : `${engagement.display.last_comms_days} days`,
                    },
                ];

    return (
        <InsightHoverCard
            title={`${engagement.score}% engagement`}
            rows={rows}
            drillHref={engagement.drill_url}
            drillAriaLabel={`Open ${engagementLabel(engagement.level)} engagement section`}
        >
            <button
                type="button"
                className="rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
            >
                <Badge variant={healthVariant(engagement.level)}>
                    {engagementLabel(engagement.level)} / {engagement.score}
                </Badge>
            </button>
        </InsightHoverCard>
    );
}

export function CashFlowBadge({ cashFlow }: { cashFlow: CashFlowStatus }) {
    return (
        <InsightHoverCard
            title={`Cash flow: ${cashFlow.status_label}`}
            rows={[
                {
                    label: 'Status',
                    value: cashFlow.status_label,
                    tone:
                        cashFlow.status === 'negative'
                            ? 'negative'
                            : cashFlow.status === 'positive'
                              ? 'positive'
                              : cashFlow.status === 'unknown'
                                ? 'muted'
                                : 'default',
                },
                {
                    label: 'Reason',
                    value: cashFlow.reason,
                    tone:
                        cashFlow.status === 'negative'
                            ? 'negative'
                            : cashFlow.status === 'unknown'
                              ? 'muted'
                              : 'default',
                },
                {
                    label: 'Latest OCF',
                    value:
                        cashFlow.latest_operating_cash_flow === null
                            ? 'Not available'
                            : formatCurrency(
                                  cashFlow.latest_operating_cash_flow,
                              ),
                    tone:
                        cashFlow.latest_operating_cash_flow !== null &&
                        cashFlow.latest_operating_cash_flow < 0
                            ? 'negative'
                            : 'default',
                },
                {
                    label: 'Runway',
                    value: formatRunway(cashFlow),
                },
                {
                    label: 'Source',
                    value: cashFlow.source,
                },
            ]}
            drillHref={cashFlow.detail_url}
            drillAriaLabel={`Open cash flow context for ${cashFlow.client_name}`}
        >
            <button
                type="button"
                className="rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
            >
                <Badge variant={cashFlowBadgeVariant(cashFlow)}>
                    {cashFlow.status_label}
                </Badge>
            </button>
        </InsightHoverCard>
    );
}

function cashFlowBadgeVariant(
    cashFlow: CashFlowStatus,
): React.ComponentProps<typeof Badge>['variant'] {
    if (cashFlow.status === 'negative') {
        return 'destructive';
    }

    if (cashFlow.status === 'watch') {
        return 'secondary';
    }

    return 'outline';
}

export function PendingTermsReacceptance({
    payload,
}: {
    payload: PendingTermsPayload;
}) {
    return (
        <section
            id="advisor-terms"
            className="space-y-4 rounded-md border bg-background p-4"
        >
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
                                <ClientNameLink
                                    name={item.client_name}
                                    href={item.client_url}
                                    className="text-xs text-muted-foreground"
                                />{' '}
                                - {item.user_email}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

export function CashFlowRiskPanel({
    payload,
}: {
    payload: CashFlowStatusPayload;
}) {
    return (
        <section
            id="advisor-cash-flow-risks"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Banknote className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Cash flow risks</h2>
                </div>
                <div className="flex flex-wrap justify-end gap-2">
                    <Badge
                        variant={
                            payload.summary.negative > 0
                                ? 'destructive'
                                : 'outline'
                        }
                    >
                        {payload.summary.negative} negative
                    </Badge>
                    <Badge
                        variant={
                            payload.summary.watch > 0 ? 'secondary' : 'outline'
                        }
                    >
                        {payload.summary.watch} watch
                    </Badge>
                </div>
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No negative or watch cash-flow positions detected.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article
                            key={item.client_id}
                            className="grid gap-3 p-3 sm:grid-cols-[1fr_auto]"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant={cashFlowBadgeVariant(item)}>
                                        {item.status_label}
                                    </Badge>
                                    <ClientNameLink
                                        name={item.client_name}
                                        href={item.client_url}
                                        className="text-sm"
                                    />
                                </div>
                                <div className="mt-2 grid gap-2 text-xs text-muted-foreground sm:grid-cols-2">
                                    <div>
                                        <span className="font-medium text-foreground">
                                            Reason:{' '}
                                        </span>
                                        {item.reason}
                                    </div>
                                    <div>
                                        <span className="font-medium text-foreground">
                                            Source:{' '}
                                        </span>
                                        {item.source}
                                    </div>
                                    <div>
                                        <span className="font-medium text-foreground">
                                            Operating cash flow:{' '}
                                        </span>
                                        {item.latest_operating_cash_flow ===
                                        null
                                            ? 'Not available'
                                            : formatCurrency(
                                                  item.latest_operating_cash_flow,
                                              )}
                                    </div>
                                    <div>
                                        <span className="font-medium text-foreground">
                                            Runway:{' '}
                                        </span>
                                        {formatRunway(item)}
                                    </div>
                                </div>
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <Link href={item.detail_url}>Open client</Link>
                            </Button>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

export function StrategicPlanDeploymentPanel({
    payload,
}: {
    payload: StrategicPlanDeploymentsPayload;
}) {
    const generatePlan = (url: string) => {
        router.post(url, {}, { preserveScroll: false });
    };

    return (
        <section
            id="advisor-strategic-plan-deployments"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <ListChecks className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Strategic plan actions
                    </h2>
                </div>
                <div className="flex flex-wrap justify-end gap-2">
                    <Badge
                        variant={
                            payload.summary.ready_to_generate > 0
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {payload.summary.ready_to_generate} generate
                    </Badge>
                    <Badge
                        variant={
                            payload.summary.ready_to_deploy > 0
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {payload.summary.ready_to_deploy} deploy
                    </Badge>
                </div>
            </div>

            {payload.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No strategic plans are waiting for generation or deployment.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {payload.items.map((item) => (
                        <article
                            key={`${item.type}-${item.id}`}
                            className="flex flex-wrap items-center justify-between gap-3 p-3"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="outline">
                                        {item.type === 'generate'
                                            ? 'Generate'
                                            : 'Draft'}
                                    </Badge>
                                    {item.type === 'deploy' && (
                                        <Badge variant="secondary">
                                            {item.milestones_count} milestones
                                        </Badge>
                                    )}
                                    {item.proposal_version !== null && (
                                        <Badge variant="outline">
                                            Proposal v{item.proposal_version}
                                        </Badge>
                                    )}
                                    {item.budget_status_label && (
                                        <Badge variant="secondary">
                                            {item.budget_status_label}
                                        </Badge>
                                    )}
                                </div>
                                <ClientNameLink
                                    name={item.client_name}
                                    href={item.client_url}
                                    className="mt-2 text-sm"
                                />
                                <div className="text-xs text-muted-foreground">
                                    {item.type === 'generate'
                                        ? `Accepted ${formatDate(item.accepted_at)}`
                                        : `Generated ${formatDate(item.generated_at)}`}
                                </div>
                                {item.proposal_brief ? (
                                    <p className="mt-1 max-w-2xl text-sm leading-5 text-muted-foreground">
                                        {item.proposal_brief}
                                    </p>
                                ) : null}
                            </div>
                            {item.type === 'generate' && item.action_url ? (
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        generatePlan(item.action_url!)
                                    }
                                >
                                    {item.action_label}
                                </Button>
                            ) : (
                                <Button asChild size="sm" variant="outline">
                                    <Link href={item.detail_url}>
                                        {item.action_label}
                                    </Link>
                                </Button>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}
