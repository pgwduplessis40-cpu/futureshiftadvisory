import { Head, router } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    PauseCircle,
    ShieldCheck,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Decision = 'approve' | 'approve_modified_date' | 'defer' | 'reject';

type LearningUpdateCard = {
    id: string;
    layer_id: number;
    source: Record<string, unknown> | null;
    summary: string;
    proposed_change: Record<string, unknown> | null;
    impact_scope: Record<string, unknown> | null;
    clients_affected: number;
    magnitude: string;
    confidence: number | null;
    evidence: Record<string, unknown> | null;
    status: string;
    effective_date: string | null;
    pre_implementation_notice_at: string | null;
    review_due_at: string | null;
    implementations: {
        id: string;
        implemented_at: string | null;
        review_due: string | null;
        rolled_back_at: string | null;
    }[];
    latest_decision: {
        decision: string;
        reason: string | null;
        decided_at: string | null;
    } | null;
};

type Props = {
    cards: LearningUpdateCard[];
    decisions: Decision[];
};

const decisionCopy: Record<Decision, string> = {
    approve: 'Approve',
    approve_modified_date: 'Approve with date',
    defer: 'Defer',
    reject: 'Reject',
};

export default function LearningUpdatesIndex({ cards, decisions }: Props) {
    return (
        <>
            <Head title="Learning update queue" />

            <div className="space-y-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <ShieldCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            Human approval
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            Learning update queue
                        </h1>
                    </div>
                    <Badge variant="secondary">{cards.length} queued</Badge>
                </header>

                {cards.length === 0 ? (
                    <p className="rounded-md border px-3 py-8 text-sm text-muted-foreground">
                        No governed learning updates are waiting for review.
                    </p>
                ) : (
                    <div className="grid gap-4">
                        {cards.map((card) => (
                            <UpdateCard
                                key={card.id}
                                card={card}
                                decisions={decisions}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function UpdateCard({
    card,
    decisions,
}: {
    card: LearningUpdateCard;
    decisions: Decision[];
}) {
    const [effectiveDate, setEffectiveDate] = useState(
        card.effective_date?.slice(0, 16) ?? '',
    );
    const [reason, setReason] = useState('');
    const [rollbackReason, setRollbackReason] = useState('');

    function submit(decision: Decision) {
        router.patch(`/admin/learning-updates/${card.id}/decision`, {
            decision,
            effective_date:
                decision === 'approve_modified_date' && effectiveDate
                    ? effectiveDate
                    : null,
            reason: reason || null,
        });
    }

    function rollback(implementationId: string) {
        router.patch(
            `/admin/learning-update-implementations/${implementationId}/rollback`,
            {
                reason: rollbackReason || 'Admin rollback requested',
            },
        );
    }

    return (
        <article className="rounded-md border bg-background p-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0 space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">Layer {card.layer_id}</Badge>
                        <Badge variant={statusVariant(card.status)}>
                            {card.status}
                        </Badge>
                        <Badge variant="secondary">{card.magnitude}</Badge>
                    </div>
                    <h2 className="text-base font-semibold">{card.summary}</h2>
                    <dl className="grid gap-2 text-sm sm:grid-cols-3">
                        <Metric
                            label="Clients"
                            value={String(card.clients_affected)}
                        />
                        <Metric
                            label="Confidence"
                            value={
                                card.confidence === null
                                    ? 'n/a'
                                    : `${Math.round(card.confidence * 100)}%`
                            }
                        />
                        <Metric
                            label="Review due"
                            value={formatDate(card.review_due_at)}
                        />
                    </dl>
                </div>
                <div className="grid gap-2 text-sm sm:min-w-72">
                    <label className="grid gap-1">
                        <span className="text-xs text-muted-foreground">
                            Effective date
                        </span>
                        <input
                            className="h-9 rounded-md border bg-background px-3"
                            type="datetime-local"
                            value={effectiveDate}
                            onChange={(event) =>
                                setEffectiveDate(event.target.value)
                            }
                        />
                    </label>
                    <label className="grid gap-1">
                        <span className="text-xs text-muted-foreground">
                            Decision note
                        </span>
                        <textarea
                            className="min-h-20 rounded-md border bg-background px-3 py-2"
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                        />
                    </label>
                </div>
            </div>

            <div className="mt-4 grid gap-3 lg:grid-cols-3">
                <JsonPanel title="Source" value={card.source} />
                <JsonPanel
                    title="Proposed change"
                    value={card.proposed_change}
                />
                <JsonPanel title="Evidence" value={card.evidence} />
            </div>

            {card.implementations.length > 0 && (
                <section className="mt-4 space-y-2 rounded-md border p-3">
                    <h3 className="text-xs font-medium text-muted-foreground">
                        Implementations
                    </h3>
                    <div className="grid gap-2">
                        {card.implementations.map((implementation) => (
                            <div
                                key={implementation.id}
                                className="flex flex-col gap-2 rounded-md border px-3 py-2 text-sm sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <div className="font-medium">
                                        {formatDate(
                                            implementation.implemented_at,
                                        )}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        Review{' '}
                                        {formatDate(implementation.review_due)}
                                    </div>
                                </div>
                                {implementation.rolled_back_at ? (
                                    <Badge variant="outline">
                                        Rolled back{' '}
                                        {formatDate(
                                            implementation.rolled_back_at,
                                        )}
                                    </Badge>
                                ) : (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            rollback(implementation.id)
                                        }
                                    >
                                        <XCircle
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Roll back
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>
                    <label className="grid gap-1 text-sm">
                        <span className="text-xs text-muted-foreground">
                            Rollback reason
                        </span>
                        <textarea
                            className="min-h-16 rounded-md border bg-background px-3 py-2"
                            value={rollbackReason}
                            onChange={(event) =>
                                setRollbackReason(event.target.value)
                            }
                        />
                    </label>
                </section>
            )}

            {!['rejected', 'implemented', 'rolled_back'].includes(
                card.status,
            ) && (
                <div className="mt-4 flex flex-wrap gap-2">
                    {decisions.map((decision) => (
                        <Button
                            key={decision}
                            type="button"
                            size="sm"
                            variant={buttonVariant(decision)}
                            onClick={() => submit(decision)}
                        >
                            <DecisionIcon decision={decision} />
                            {decisionCopy[decision]}
                        </Button>
                    ))}
                </div>
            )}
        </article>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border px-3 py-2">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-1 font-medium">{value}</dd>
        </div>
    );
}

function JsonPanel({
    title,
    value,
}: {
    title: string;
    value: Record<string, unknown> | null;
}) {
    return (
        <section className="rounded-md border p-3">
            <h3 className="text-xs font-medium text-muted-foreground">
                {title}
            </h3>
            <pre className="mt-2 max-h-36 overflow-auto text-xs whitespace-pre-wrap">
                {JSON.stringify(value ?? {}, null, 2)}
            </pre>
        </section>
    );
}

function DecisionIcon({ decision }: { decision: Decision }) {
    if (decision === 'reject') {
        return <XCircle className="size-4" aria-hidden="true" />;
    }

    if (decision === 'defer') {
        return <PauseCircle className="size-4" aria-hidden="true" />;
    }

    if (decision === 'approve_modified_date') {
        return <CalendarClock className="size-4" aria-hidden="true" />;
    }

    return <CheckCircle2 className="size-4" aria-hidden="true" />;
}

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (status === 'approved') {
        return 'default';
    }

    if (status === 'rejected' || status === 'rolled_back') {
        return 'destructive';
    }

    if (status === 'deferred') {
        return 'outline';
    }

    return 'secondary';
}

function buttonVariant(
    decision: Decision,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (decision === 'reject') {
        return 'destructive';
    }

    if (decision === 'defer' || decision === 'approve_modified_date') {
        return 'outline';
    }

    return 'default';
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not scheduled';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

LearningUpdatesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Learning updates',
            href: '/admin/learning-updates',
        },
    ],
};
