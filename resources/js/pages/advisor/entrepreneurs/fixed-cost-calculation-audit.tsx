import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatNzdCurrency } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { EntrepreneurDetail } from './types';

type FixedCostTrace = NonNullable<
    EntrepreneurDetail['latest_plan']
>['budget']['fixed_cost_trace'];

type Props = {
    trace: FixedCostTrace;
    repairUrl: string | null;
};

export function FixedCostCalculationAudit({ trace, repairUrl }: Props) {
    const [repairIndex, setRepairIndex] = useState<number | null>(null);
    const [repairError, setRepairError] = useState<string | null>(null);
    const duplicateCount = trace.filter(
        (row) => row.duplicate_cadence_quantity,
    ).length;

    if (trace.length === 0) {
        return null;
    }

    const repairDuplicateCadenceQuantity = (rowIndex: number) => {
        if (!repairUrl || repairIndex !== null) {
            return;
        }

        router.patch(
            repairUrl,
            { row_index: rowIndex },
            {
                preserveScroll: true,
                onStart: () => {
                    setRepairIndex(rowIndex);
                    setRepairError(null);
                },
                onError: (errors) => {
                    const error = errors.row_index;
                    setRepairError(
                        typeof error === 'string'
                            ? error
                            : 'The stored fixed-cost row could not be corrected. Refresh the audit and try again.',
                    );
                },
                onFinish: () => setRepairIndex(null),
            },
        );
    };

    return (
        <section className="rounded-md border">
            <div className="flex flex-wrap items-start justify-between gap-3 border-b p-4">
                <div>
                    <h3 className="text-sm font-medium">
                        Fixed-cost calculation audit
                    </h3>
                    <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                        These are the live stored rate, number of parallel
                        units, billing cadence, and monthly equivalent used by
                        the funding calculation. A cadence-period count in Qty
                        would convert the same period twice.
                    </p>
                </div>
                <Badge
                    variant={duplicateCount > 0 ? 'destructive' : 'secondary'}
                >
                    {duplicateCount} duplicate cadence count
                    {duplicateCount === 1 ? '' : 's'}
                </Badge>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[860px] text-sm">
                    <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="px-3 py-2 font-medium">Cost item</th>
                            <th className="px-3 py-2 font-medium">
                                Stored rate
                            </th>
                            <th className="px-3 py-2 font-medium">Qty</th>
                            <th className="px-3 py-2 font-medium">Cadence</th>
                            <th className="px-3 py-2 font-medium">
                                Monthly equivalent
                            </th>
                            <th className="px-3 py-2 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {trace.map((row) => (
                            <tr
                                key={`${row.index}-${row.label}`}
                                className={cn(
                                    row.duplicate_cadence_quantity &&
                                        'bg-red-50/60',
                                )}
                            >
                                <td className="px-3 py-3 font-medium">
                                    {row.label}
                                    {row.duplicate_cadence_quantity ? (
                                        <span className="mt-1 block text-xs font-normal text-destructive">
                                            Qty matches the number of{' '}
                                            {row.cadence} billing periods in a
                                            year.
                                        </span>
                                    ) : null}
                                </td>
                                <td className="px-3 py-3 tabular-nums">
                                    {formatNzdCurrency(row.rate)}
                                </td>
                                <td className="px-3 py-3 tabular-nums">
                                    {row.quantity}
                                </td>
                                <td className="px-3 py-3 capitalize">
                                    {row.cadence}
                                    {!row.cadence_confirmed ? (
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            Unconfirmed
                                        </span>
                                    ) : null}
                                </td>
                                <td className="px-3 py-3 font-medium tabular-nums">
                                    {formatNzdCurrency(row.monthly_equivalent)}
                                </td>
                                <td className="px-3 py-3">
                                    {row.duplicate_cadence_quantity &&
                                    repairUrl ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            disabled={repairIndex !== null}
                                            onClick={() =>
                                                repairDuplicateCadenceQuantity(
                                                    row.index,
                                                )
                                            }
                                        >
                                            {repairIndex === row.index
                                                ? 'Correcting and queueing assessment'
                                                : 'Set Qty to 1 and reassess'}
                                        </Button>
                                    ) : (
                                        <span className="text-xs text-muted-foreground">
                                            No cadence repair required
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {repairError ? (
                <p className="border-t px-4 py-3 text-sm text-destructive">
                    {repairError}
                </p>
            ) : null}
        </section>
    );
}
