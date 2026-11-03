import { useId } from 'react';
import type { BudgetRow } from './plan-types';

export function BudgetMonthlySchedule({
    row,
    revenue,
    onChange,
}: {
    row: BudgetRow;
    revenue: boolean;
    onChange: (patch: Partial<BudgetRow>) => void;
}) {
    const id = useId();

    return (
        <details className="col-span-full rounded-md border bg-background p-3 text-sm">
            <summary className="cursor-pointer font-medium">
                {revenue
                    ? 'Set monthly sales volumes'
                    : 'Set staged pay or cost amounts'}{' '}
                (optional)
            </summary>
            <p className="my-3 max-w-3xl text-muted-foreground">
                {revenue
                    ? 'Enter the units you expect to sell in each month, including zero. These volumes replace automatic growth for those months and remain subject to the delivery limit. Year 2 uses the selected December run rate or Year 1 average.'
                    : 'Enter the payment rate for each month using the frequency above—for example, weekly owner pay. Year 2 starts from the December rate, then applies cost inflation.'}{' '}
                Leave a month blank to use the main row value
                {revenue ? ' and growth' : ''}.
            </p>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                {Array.from({ length: 12 }, (_, index) => (
                    <label
                        key={index}
                        htmlFor={`${id}-${index}`}
                        className="grid gap-1 text-xs"
                    >
                        Month {index + 1}{' '}
                        {revenue ? `(${row.unit_label || 'units'})` : '(rate)'}
                        <input
                            id={`${id}-${index}`}
                            type="number"
                            min="0"
                            step="0.01"
                            className="h-9 min-w-0 rounded-md border bg-background px-2 text-sm"
                            value={row.monthly_schedule?.[index] ?? ''}
                            placeholder="Use row value"
                            onChange={(event) => {
                                const values = Array.from(
                                    { length: 12 },
                                    (_, month) =>
                                        row.monthly_schedule?.[month] ?? null,
                                );
                                values[index] =
                                    event.target.value === ''
                                        ? null
                                        : Number(event.target.value);
                                onChange({ monthly_schedule: values });
                            }}
                        />
                    </label>
                ))}
            </div>
        </details>
    );
}
