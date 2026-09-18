import type { BudgetRow } from './plan-types';

type FixedCostCadenceControlProps = {
    cadence: BudgetRow['cadence'] | undefined;
    confirmed: boolean;
    onChange: (
        changes: Partial<Pick<BudgetRow, 'cadence' | 'cadence_confirmed'>>,
    ) => void;
};

export function FixedCostCadenceControl({
    cadence,
    confirmed,
    onChange,
}: FixedCostCadenceControlProps) {
    return (
        <label className="grid gap-1 text-xs">
            <span className="text-muted-foreground">Billing cadence</span>
            <select
                value={cadence ?? 'monthly'}
                onChange={(event) =>
                    onChange({
                        cadence: event.target.value as BudgetRow['cadence'],
                        cadence_confirmed: false,
                    })
                }
                className="h-9 rounded-md border bg-background px-2 text-sm"
            >
                <option value="weekly">Weekly</option>
                <option value="fortnightly">Fortnightly</option>
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
                <option value="annual">Annual</option>
            </select>
            <span className="flex items-center gap-2 text-[11px] leading-snug text-muted-foreground">
                <input
                    type="checkbox"
                    name="cadence_confirmed"
                    aria-label="Confirm billing cadence"
                    checked={confirmed}
                    onChange={(event) =>
                        onChange({ cadence_confirmed: event.target.checked })
                    }
                />
                Cadence checked
            </span>
        </label>
    );
}

export function fixedCostQuantityWarning(row: BudgetRow): string | null {
    const paymentsPerYear = {
        weekly: 52,
        fortnightly: 26,
        monthly: 12,
        quarterly: 4,
    } as const;
    const cadence = row.cadence ?? 'monthly';
    const expectedQuantity =
        cadence === 'annual' ? undefined : paymentsPerYear[cadence];
    const quantity = Number(row.quantity ?? 1);

    if (
        expectedQuantity === undefined ||
        !Number.isFinite(quantity) ||
        Math.abs(quantity - expectedQuantity) >= 0.005
    ) {
        return null;
    }

    return `Units ${quantity} looks like the number of ${cadence} payments in a year. Units means parallel subscriptions, people, or licences; use 1 for one billed item.`;
}
