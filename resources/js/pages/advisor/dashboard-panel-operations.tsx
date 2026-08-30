import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    HeartHandshake,
    Inbox,
    Sparkles,
    UsersRound,
} from 'lucide-react';
import type { ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type HealthLevel = 'green' | 'amber' | 'red';

type PanelReferralQueue = {
    summary: {
        total: number;
        active: number;
        terminal: number;
    };
    stage_counts: Record<string, number>;
    items: Array<{
        id: string;
        subject_name: string;
        panel_name: string;
        stage: string;
        stage_label: string;
        reason: string | null;
        sent_at: string | null;
        detail_url: string | null;
    }>;
};

type PanelApprovalQueue = {
    summary: {
        total: number;
        broker: number;
        coach: number;
    };
    review_url: string | null;
    items: Array<{
        id: string;
        panel_type: string;
        panel_label: string;
        business_name: string;
        contact_name: string;
        email: string | null;
        status: string;
        status_label: string;
        applied_at: string | null;
        review_url: string | null;
    }>;
};

type LearningQueuePayload = {
    summary: {
        detected: number;
        staged: number;
    };
    queue_url: string | null;
    items: Array<{
        id: string;
        summary: string;
        status: string;
        source_type: string | null;
        confidence: number;
        clients_affected: number;
        created_at: string | null;
        detail_url: string | null;
    }>;
};

export type PanelOperationsPayload = {
    broker: PanelReferralQueue;
    coach: PanelReferralQueue;
    learning: LearningQueuePayload;
    approvals: PanelApprovalQueue;
};

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

export function HealthIcon({ health }: { health: HealthLevel }) {
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

export function healthVariant(
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

function PanelApprovalQueuePanel({ payload }: { payload: PanelApprovalQueue }) {
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

function PanelReferralQueuePanel({
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
    icon: ReactNode;
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

function LearningQueuePanel({ payload }: { payload: LearningQueuePayload }) {
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

function PortfolioMetric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border bg-muted/20 p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-sm font-medium">{value}</div>
        </div>
    );
}

function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'No activity';
    }

    return new Intl.DateTimeFormat('en-NZ', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatPercent(value: number): string {
    return `${Math.round(value * 1000) / 10}%`;
}
