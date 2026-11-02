import { formatNzdCurrency } from '@/lib/formatters';
import { BudgetAuditSection } from './budget-audit-section';
import { FixedCostCalculationAudit } from './fixed-cost-calculation-audit';
import type { EntrepreneurDetail } from './types';

type Budget = NonNullable<EntrepreneurDetail['latest_plan']>['budget'];
type ScheduledCost = Budget['scheduled_cost_trace'][number];

export function BudgetCostAudit({ budget }: { budget: Budget }) {
    const costs = budget.scheduled_cost_trace ?? [];

    return (
        <>
            <ScheduledCosts
                title="One-off costs"
                rows={costs.filter((row) => row.schedule === 'one_off')}
            />
            <ScheduledCosts
                title="Future costs and renewals"
                rows={costs.filter((row) => row.schedule === 'future')}
            />
            <FixedCostCalculationAudit
                trace={budget.fixed_cost_trace ?? []}
                repairUrl={budget.fixed_cost_cadence_repair_url ?? null}
                repairAllUrl={budget.fixed_cost_cadence_repair_all_url ?? null}
            />
        </>
    );
}

function ScheduledCosts({
    title,
    rows,
}: {
    title: string;
    rows: ScheduledCost[];
}) {
    return (
        <BudgetAuditSection
            title={title}
            count={rows.length}
            defaultOpen={title === 'One-off costs'}
        >
            <div className="border-b p-4">
                <p className="text-sm text-muted-foreground">
                    Current saved Budget entries. Amounts are shown per payment,
                    separately from monthly running costs. Reassess after Budget
                    changes to update the assessment findings.
                </p>
            </div>
            {rows.length === 0 ? (
                <p className="p-4 text-sm text-muted-foreground">
                    No saved entries.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">
                            {title} in the saved Budget
                        </caption>
                        <thead className="bg-muted/30 text-xs text-muted-foreground">
                            <tr>
                                {[
                                    'Cost item',
                                    'Amount',
                                    'Qty',
                                    'Total per payment',
                                    'When paid',
                                    'Confidence',
                                ].map((label) => (
                                    <th
                                        key={label}
                                        scope="col"
                                        className="px-3 py-2 font-medium"
                                    >
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr
                                    key={`${row.schedule}-${index}`}
                                    className="border-t"
                                >
                                    <th
                                        scope="row"
                                        className="px-3 py-3 font-medium"
                                    >
                                        {row.label || 'Unlabelled cost'}
                                    </th>
                                    <td className="px-3 py-3 whitespace-nowrap">
                                        {money(row.amount)}
                                    </td>
                                    <td className="px-3 py-3">
                                        {row.quantity}
                                    </td>
                                    <td className="px-3 py-3 whitespace-nowrap">
                                        {money(row.total)}
                                    </td>
                                    <td className="px-3 py-3">{timing(row)}</td>
                                    <td className="px-3 py-3 capitalize">
                                        {row.confidence}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </BudgetAuditSection>
    );
}

function money(value: number) {
    return formatNzdCurrency(value, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function timing(row: ScheduledCost) {
    if (row.schedule === 'one_off') {
        return `Forecast month ${row.month} · once`;
    }

    const repeat = row.recurring
        ? 'every year'
        : row.repeat_every_years
          ? `every ${row.repeat_every_years} years`
          : 'once';

    return `Forecast year ${row.year} · ${repeat}`;
}
