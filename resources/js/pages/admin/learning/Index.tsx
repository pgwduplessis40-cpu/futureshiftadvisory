import { Head, router } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    Info,
    ListChecks,
    PauseCircle,
    RefreshCw,
    ShieldCheck,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type Decision = 'approve' | 'approve_modified_date' | 'defer' | 'reject';
type LearningTab = 'actions' | 'information';

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
    impact_reviews: ImpactReviewCard[];
    monitor: LearningMonitor;
    rerun_url: string;
};

type ImpactReviewCard = {
    id: string;
    learning_update_id: string;
    summary: string;
    layer_id: number | null;
    implemented_at: string | null;
    review_due: string | null;
    review_url: string;
    proposed_change: Record<string, unknown> | null;
};

type LearningMonitor = {
    summary: {
        registered_layers: number;
        queued_candidates: number;
        approved_candidates: number;
        recent_runs: number;
    };
    layers: {
        id: number;
        name: string;
        cadence: string;
        window_days: number;
        command: string | null;
        governed_candidates_only: boolean;
        latest_run: LearningLayerRun | null;
    }[];
    recent_runs: LearningLayerRun[];
    queue_by_status: Record<string, number>;
};

type LearningLayerRun = {
    id: string;
    layer_id: number;
    ran_at: string | null;
    candidates_created: number;
    status: string;
    window: Record<string, unknown> | null;
};

const decisionCopy: Record<Decision, string> = {
    approve: 'Approve',
    approve_modified_date: 'Approve with date',
    defer: 'Defer',
    reject: 'Reject',
};

export default function LearningUpdatesIndex({
    cards,
    decisions,
    impact_reviews,
    monitor,
    rerun_url,
}: Props) {
    const [activeTab, setActiveTab] = useState<LearningTab>('actions');

    return (
        <>
            <Head title="Learning update queue" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Human approval"
                    icon={ShieldCheck}
                    title="Learning update queue"
                    actions={
                        <>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => router.post(rerun_url)}
                            >
                                <RefreshCw
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Run due layers
                            </Button>
                            <Badge variant="secondary">
                                {cards.length} queued
                            </Badge>
                        </>
                    }
                />

                <LearningTabList
                    activeTab={activeTab}
                    onChange={setActiveTab}
                />

                {activeTab === 'actions' ? (
                    <section className="space-y-4">
                        <div className="grid gap-2 sm:grid-cols-4">
                            <Metric
                                label="Queued"
                                value={String(
                                    monitor.summary.queued_candidates,
                                )}
                                explanation="Governed learning changes waiting for a human approval decision."
                            />
                            <Metric
                                label="Approved"
                                value={String(
                                    monitor.summary.approved_candidates,
                                )}
                                explanation="Learning changes approved for implementation or implementation tracking."
                            />
                            <Metric
                                label="Layers"
                                value={String(
                                    monitor.summary.registered_layers,
                                )}
                                explanation="Configured learning layers that can surface governed update candidates."
                            />
                            <Metric
                                label="Recent runs"
                                value={String(monitor.summary.recent_runs)}
                                explanation="Recent learning monitor executions included in this review window."
                            />
                        </div>

                        {impact_reviews.length > 0 && (
                            <ImpactReviewPanel reviews={impact_reviews} />
                        )}

                        {cards.length === 0 ? (
                            <p className="rounded-md border px-3 py-8 text-sm text-muted-foreground">
                                No governed learning updates are waiting for
                                review.
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
                    </section>
                ) : (
                    <MonitorPanel monitor={monitor} />
                )}
            </div>
        </>
    );
}

function ImpactReviewPanel({ reviews }: { reviews: ImpactReviewCard[] }) {
    return (
        <section className="space-y-3 rounded-md border bg-background p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-sm font-medium">Impact reviews due</h2>
                    <p className="text-xs text-muted-foreground">
                        Confirm the 30-day effect of implemented learning
                        changes before leaving them active.
                    </p>
                </div>
                <Badge variant="secondary">{reviews.length}</Badge>
            </div>
            <div className="grid gap-3">
                {reviews.map((review) => (
                    <ImpactReviewCardItem key={review.id} review={review} />
                ))}
            </div>
        </section>
    );
}

function ImpactReviewCardItem({ review }: { review: ImpactReviewCard }) {
    const [outcome, setOutcome] = useState('');

    return (
        <article className="grid gap-3 rounded-md border p-3 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.7fr)]">
            <div className="space-y-1">
                <div className="flex flex-wrap items-center gap-2">
                    {review.layer_id !== null && (
                        <Badge variant="outline">Layer {review.layer_id}</Badge>
                    )}
                    <Badge variant="secondary">
                        Due {formatDate(review.review_due)}
                    </Badge>
                </div>
                <h3 className="text-sm font-medium">{review.summary}</h3>
                <p className="text-xs text-muted-foreground">
                    Implemented {formatDate(review.implemented_at)}
                </p>
            </div>
            <div className="grid gap-2">
                <textarea
                    className="min-h-20 rounded-md border bg-background px-3 py-2 text-sm"
                    value={outcome}
                    onChange={(event) => setOutcome(event.target.value)}
                    placeholder="Record observed impact, exceptions, or rollback decision."
                />
                <Button
                    type="button"
                    size="sm"
                    onClick={() =>
                        router.patch(review.review_url, {
                            review_outcome:
                                outcome ||
                                'Impact review completed with no exceptions recorded.',
                        })
                    }
                >
                    <CheckCircle2 className="size-4" aria-hidden="true" />
                    Save impact review
                </Button>
            </div>
        </article>
    );
}

function LearningTabList({
    activeTab,
    onChange,
}: {
    activeTab: LearningTab;
    onChange: (tab: LearningTab) => void;
}) {
    const tabs: Array<{
        key: LearningTab;
        label: string;
        description: string;
    }> = [
        {
            key: 'actions',
            label: 'Actions',
            description:
                'Approve, defer, reject, or roll back learning updates.',
        },
        {
            key: 'information',
            label: 'Information',
            description: 'Review monitor layers, cadence, and run history.',
        },
    ];

    return (
        <div
            className="inline-flex w-full max-w-md rounded-md border bg-muted/30 p-1"
            role="tablist"
            aria-label="Learning update sections"
        >
            {tabs.map((tab) => (
                <button
                    key={tab.key}
                    type="button"
                    role="tab"
                    aria-selected={activeTab === tab.key}
                    className={cn(
                        'flex flex-1 items-center justify-center gap-2 rounded-sm px-3 py-2 text-sm font-medium transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                        activeTab === tab.key
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                    onClick={() => onChange(tab.key)}
                    title={tab.description}
                >
                    {tab.key === 'actions' ? (
                        <ListChecks className="size-4" aria-hidden="true" />
                    ) : (
                        <Info className="size-4" aria-hidden="true" />
                    )}
                    {tab.label}
                </button>
            ))}
        </div>
    );
}

function MonitorPanel({ monitor }: { monitor: LearningMonitor }) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="grid gap-2 sm:grid-cols-4">
                <Metric
                    label="Layers"
                    value={String(monitor.summary.registered_layers)}
                    explanation="Configured learning layers that can create governed update candidates."
                />
                <Metric
                    label="Queued"
                    value={String(monitor.summary.queued_candidates)}
                    explanation="Candidates waiting for review in the action queue."
                />
                <Metric
                    label="Approved"
                    value={String(monitor.summary.approved_candidates)}
                    explanation="Candidates approved by a reviewer and awaiting or tracking implementation."
                />
                <Metric
                    label="Runs"
                    value={String(monitor.summary.recent_runs)}
                    explanation="Recent monitor runs included in the history below."
                />
            </div>

            <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(22rem,0.7fr)]">
                <div className="overflow-hidden rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Layer</th>
                                <th className="px-3 py-2 font-medium">
                                    Cadence
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Latest run
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Candidates
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {monitor.layers.slice(0, 12).map((layer) => (
                                <tr key={layer.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <div className="font-medium">
                                            {layer.id}. {layer.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {layer.command ?? 'registry'}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge variant="outline">
                                            {layer.cadence}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2">
                                        {formatDate(
                                            layer.latest_run?.ran_at ?? null,
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        {layer.latest_run?.candidates_created ??
                                            0}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="rounded-md border">
                    <div className="border-b px-3 py-2 text-sm font-medium">
                        Run history
                    </div>
                    <div className="divide-y">
                        {monitor.recent_runs.slice(0, 8).map((run) => (
                            <div
                                key={run.id}
                                className="grid grid-cols-[1fr_auto] gap-3 px-3 py-2 text-sm"
                            >
                                <div>
                                    <div className="font-medium">
                                        Layer {run.layer_id}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {formatDate(run.ran_at)}
                                    </div>
                                </div>
                                <Badge
                                    variant={
                                        run.status === 'completed'
                                            ? 'secondary'
                                            : 'destructive'
                                    }
                                >
                                    {run.status}
                                </Badge>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </section>
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
    const canDecide = !['rejected', 'implemented', 'rolled_back'].includes(
        card.status,
    );

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
                            explanation="Client records affected if this learning update is approved."
                        />
                        <Metric
                            label="Confidence"
                            value={
                                card.confidence === null
                                    ? 'n/a'
                                    : `${Math.round(card.confidence * 100)}%`
                            }
                            explanation="Model confidence in the proposed change. Keep lower-confidence changes under closer review."
                        />
                        <Metric
                            label="Review due"
                            value={formatDate(card.review_due_at)}
                            explanation="Date by which this governed update should receive a human decision."
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
                    {canDecide && (
                        <div className="flex flex-wrap gap-2">
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
        </article>
    );
}

function Metric({
    label,
    value,
    explanation,
}: {
    label: string;
    value: string;
    explanation?: string;
}) {
    const metric = (
        <div className="rounded-md border px-3 py-2">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-1 font-medium">{value}</dd>
        </div>
    );

    if (!explanation) {
        return metric;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>{metric}</TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {explanation}
            </TooltipContent>
        </Tooltip>
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
