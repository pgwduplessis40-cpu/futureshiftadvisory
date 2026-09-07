import { useState } from 'react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableStat } from './LearningDisplay';

type DeliveryRollupCard = {
    id: string;
    status: string;
    delivery_rollup: {
        key: string;
        label: string;
        surface: string | null;
    };
    implementations: {
        implemented_at: string | null;
        review_due: string | null;
        review_outcome: string | null;
        rolled_back_at: string | null;
    }[];
};

type DeliveryRollupState =
    | 'in_development'
    | 'implementation_in_progress'
    | 'needs_verification'
    | 'delivered'
    | 'needs_attention';

type DeliveryRollup<T extends DeliveryRollupCard> = {
    key: string;
    label: string;
    surface: string | null;
    cards: T[];
    state: DeliveryRollupState;
    implemented: number;
    reviewed: number;
    reviewDue: number;
};

export function DeliveryRollups<T extends DeliveryRollupCard>({
    cards,
    formatSurface,
    renderRow,
}: {
    cards: T[];
    formatSurface: (value: string) => string;
    renderRow: (card: T) => ReactNode;
}) {
    const rollups = deliveryRollups(cards);

    return (
        <section className="space-y-3">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-sm font-semibold">
                        Approved recommendations in delivery
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Grouped by an explicit delivery key or exact source,
                        action, and surface. Each recommendation keeps its own
                        approval and evidence.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge variant="secondary">{rollups.length} groups</Badge>
                    <Badge variant="outline">
                        {cards.length} recommendations
                    </Badge>
                </div>
            </div>

            {cards.length === 0 ? (
                <p className="rounded-md border px-3 py-8 text-sm text-muted-foreground">
                    No approved learnings are waiting for implementation
                    tracking.
                </p>
            ) : (
                <div className="space-y-3">
                    {rollups.map((rollup) => (
                        <DeliveryRollupSection
                            key={rollup.key}
                            rollup={rollup}
                            formatSurface={formatSurface}
                            renderRow={renderRow}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}

function DeliveryRollupSection<T extends DeliveryRollupCard>({
    rollup,
    formatSurface,
    renderRow,
}: {
    rollup: DeliveryRollup<T>;
    formatSurface: (value: string) => string;
    renderRow: (card: T) => ReactNode;
}) {
    const [expanded, setExpanded] = useState(true);

    return (
        <section className="overflow-hidden rounded-md border bg-background">
            <div className="flex flex-wrap items-start justify-between gap-3 border-b bg-muted/30 px-3 py-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-sm font-semibold">
                            {rollup.label}
                        </h3>
                        <Badge variant={deliveryRollupVariant(rollup.state)}>
                            {deliveryRollupLabel(rollup.state)}
                        </Badge>
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {rollup.surface
                            ? `Surface: ${formatSurface(rollup.surface)}.`
                            : 'No delivery surface was recorded.'}{' '}
                        The group is a view only; it does not change child
                        approvals or delivery state.
                    </p>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => setExpanded((value) => !value)}
                >
                    {expanded ? 'Hide' : 'Show'} {rollup.cards.length}{' '}
                    recommendation{rollup.cards.length === 1 ? '' : 's'}
                </Button>
            </div>
            <dl className="grid gap-2 px-3 py-3 text-sm sm:grid-cols-4">
                <TableStat
                    label="Recommendations"
                    value={String(rollup.cards.length)}
                />
                <TableStat
                    label="Implemented"
                    value={`${rollup.implemented}/${rollup.cards.length}`}
                />
                <TableStat
                    label="Impact reviewed"
                    value={`${rollup.reviewed}/${rollup.cards.length}`}
                />
                <TableStat
                    label="Reviews due"
                    value={String(rollup.reviewDue)}
                />
            </dl>
            {expanded && (
                <div className="overflow-x-auto border-t">
                    <table className="fsa-responsive-table min-w-[72rem] table-fixed md:table-auto">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="w-[28%] px-3 py-2 font-medium">
                                    Recommendation
                                </th>
                                <th className="w-[30%] px-3 py-2 font-medium">
                                    What we learnt
                                </th>
                                <th className="px-3 py-2 font-medium">Scope</th>
                                <th className="px-3 py-2 font-medium">
                                    Approval
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Implementation
                                </th>
                            </tr>
                        </thead>
                        <tbody>{rollup.cards.map(renderRow)}</tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

function deliveryRollups<T extends DeliveryRollupCard>(
    cards: T[],
): DeliveryRollup<T>[] {
    const groups = cards.reduce((carry, card) => {
        const existing = carry.get(card.delivery_rollup.key);

        if (existing) {
            existing.cards.push(card);

            return carry;
        }

        carry.set(card.delivery_rollup.key, {
            key: card.delivery_rollup.key,
            label: card.delivery_rollup.label,
            surface: card.delivery_rollup.surface,
            cards: [card],
        });

        return carry;
    }, new Map<string, Omit<DeliveryRollup<T>, 'state' | 'implemented' | 'reviewed' | 'reviewDue'>>());

    return Array.from(groups.values())
        .map((group) => deliveryRollup(group))
        .sort((left, right) => {
            const stateOrder =
                deliveryRollupOrder(left.state) -
                deliveryRollupOrder(right.state);

            return stateOrder !== 0
                ? stateOrder
                : left.label.localeCompare(right.label);
        });
}

function deliveryRollup<T extends DeliveryRollupCard>(
    group: Omit<
        DeliveryRollup<T>,
        'state' | 'implemented' | 'reviewed' | 'reviewDue'
    >,
): DeliveryRollup<T> {
    const activeImplementations = group.cards.flatMap((card) =>
        card.implementations.filter(
            (implementation) => implementation.rolled_back_at === null,
        ),
    );
    const implemented = group.cards.filter((card) =>
        card.implementations.some(
            (implementation) =>
                implementation.rolled_back_at === null &&
                implementation.implemented_at !== null,
        ),
    ).length;
    const reviewed = group.cards.filter((card) =>
        card.implementations.some(
            (implementation) =>
                implementation.rolled_back_at === null &&
                implementation.review_outcome !== null,
        ),
    ).length;
    const reviewDue = activeImplementations.filter(
        (implementation) =>
            implementation.review_outcome === null &&
            implementation.review_due !== null &&
            new Date(implementation.review_due).getTime() <= Date.now(),
    ).length;
    const hasRollback = group.cards.some(
        (card) => card.status === 'rolled_back',
    );
    const state: DeliveryRollupState = hasRollback
        ? 'needs_attention'
        : reviewDue > 0
          ? 'needs_verification'
          : reviewed === group.cards.length && group.cards.length > 0
            ? 'delivered'
            : implemented > 0
              ? 'implementation_in_progress'
              : 'in_development';

    return {
        ...group,
        state,
        implemented,
        reviewed,
        reviewDue,
    };
}

function deliveryRollupLabel(state: DeliveryRollupState): string {
    return {
        in_development: 'In development',
        implementation_in_progress: 'Implementing',
        needs_verification: 'Needs verification',
        delivered: 'Delivered',
        needs_attention: 'Needs attention',
    }[state];
}

function deliveryRollupVariant(
    state: DeliveryRollupState,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (state === 'needs_attention') {
        return 'destructive';
    }

    if (state === 'needs_verification') {
        return 'outline';
    }

    return state === 'delivered' ? 'default' : 'secondary';
}

function deliveryRollupOrder(state: DeliveryRollupState): number {
    return {
        needs_attention: 0,
        needs_verification: 1,
        in_development: 2,
        implementation_in_progress: 3,
        delivered: 4,
    }[state];
}
