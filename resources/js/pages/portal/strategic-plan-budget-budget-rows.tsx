import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { BudgetInput } from './strategic-plan-budget-plan-drivers';
import type { PlanFinancialDriverOption } from './strategic-plan-budget-plan-drivers';

type BudgetRow = {
    label?: string;
    amount?: number | string;
    quantity?: number | string;
    plan_financial_driver_key?: string;
    month?: number | string;
    cadence?: 'weekly' | 'fortnightly' | 'monthly' | 'quarterly' | 'annual';
    cadence_confirmed?: boolean;
    growth_percent?: number | string;
    monthly_growth_percent?: number | string;
    growth_cadence?: 'monthly' | 'annual';
    growth_cadence_confirmed?: boolean;
    monthly_capacity_units?: number | string;
    capacity_confirmed?: boolean;
    unit_cost?: number | string;
    gross_profit_percent?: number | string;
    confidence?: 'known' | 'estimate' | 'guess';
};

type BudgetGroupKey =
    | 'implementation_costs'
    | 'monthly_fixed_costs'
    | 'revenue_forecast'
    | 'funding_sources';

export function BudgetRowsEditor({
    highlighted,
    title,
    helper,
    group,
    rows,
    planFinancialDrivers,
    onRowsChange,
    revenue = false,
}: {
    highlighted: boolean;
    title: string;
    helper: string;
    group: BudgetGroupKey;
    rows: BudgetRow[];
    planFinancialDrivers: PlanFinancialDriverOption[];
    onRowsChange: (rows: BudgetRow[]) => void;
    revenue?: boolean;
}) {
    const update = (index: number, patch: Partial<BudgetRow>) => {
        onRowsChange(
            rows.map((row, current) =>
                current === index ? { ...row, ...patch } : row,
            ),
        );
    };
    const compatibleDrivers = planFinancialDrivers.filter(
        (driver) => driver.category === group,
    );

    return (
        <section
            id={`budget-section-${group}`}
            className={cn(
                'scroll-mt-24 space-y-3 rounded-md border bg-muted/20 p-3 transition-[background-color,box-shadow,border-color] duration-300',
                highlighted &&
                    'border-amber-400 bg-amber-50/70 ring-2 ring-amber-400/70',
            )}
            style={budgetTargetHighlightStyle(highlighted)}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-sm font-medium">{title}</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {helper}
                    </p>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        onRowsChange([...rows, blankBudgetRow(revenue)])
                    }
                >
                    <Plus className="size-4" aria-hidden="true" />
                    Add
                </Button>
            </div>
            <div className="space-y-2">
                {rows.map((row, index) => (
                    <div
                        key={`${group}-${index}`}
                        className={cn(
                            'grid gap-2',
                            revenue
                                ? 'lg:grid-cols-[minmax(0,1fr)_repeat(8,minmax(72px,0.45fr))_minmax(160px,0.8fr)_120px]'
                                : 'lg:grid-cols-[minmax(0,1fr)_repeat(4,minmax(80px,0.35fr))_minmax(160px,0.8fr)_120px]',
                        )}
                    >
                        <BudgetInput
                            label="Item"
                            value={row.label ?? ''}
                            onChange={(value) =>
                                update(index, { label: value })
                            }
                        />
                        <BudgetInput
                            label="Amount"
                            type="number"
                            value={row.amount ?? ''}
                            onChange={(value) =>
                                update(index, { amount: value })
                            }
                        />
                        <BudgetInput
                            label="Qty"
                            type="number"
                            value={row.quantity ?? 1}
                            onChange={(value) =>
                                update(index, { quantity: value })
                            }
                        />
                        {revenue ? (
                            <>
                                <BudgetInput
                                    label="Start"
                                    type="number"
                                    value={row.month ?? 1}
                                    onChange={(value) =>
                                        update(index, { month: value })
                                    }
                                />
                                <BudgetInput
                                    label="Growth %"
                                    type="number"
                                    value={
                                        row.growth_percent ??
                                        row.monthly_growth_percent ??
                                        0
                                    }
                                    onChange={(value) =>
                                        update(index, {
                                            growth_percent: value,
                                            monthly_growth_percent: value,
                                        })
                                    }
                                />
                                <GrowthCadenceControl
                                    row={row}
                                    onChange={(patch) => update(index, patch)}
                                />
                                <BudgetInput
                                    label="Monthly capacity"
                                    type="number"
                                    value={row.monthly_capacity_units ?? ''}
                                    onChange={(value) =>
                                        update(index, {
                                            monthly_capacity_units: value,
                                        })
                                    }
                                />
                                <ConfirmationControl
                                    label="Capacity confirmed"
                                    checked={row.capacity_confirmed ?? false}
                                    onChange={(capacity_confirmed) =>
                                        update(index, { capacity_confirmed })
                                    }
                                />
                                <BudgetInput
                                    label="GP %"
                                    type="number"
                                    value={row.gross_profit_percent ?? ''}
                                    onChange={(value) =>
                                        update(index, {
                                            gross_profit_percent: value,
                                        })
                                    }
                                />
                                <BudgetInput
                                    label="Unit cost"
                                    type="number"
                                    value={row.unit_cost ?? ''}
                                    onChange={(value) =>
                                        update(index, { unit_cost: value })
                                    }
                                />
                            </>
                        ) : (
                            <CostCadenceControl
                                row={row}
                                onChange={(patch) => update(index, patch)}
                            />
                        )}
                        <label className="grid gap-1 text-xs">
                            <span>Plan link</span>
                            <select
                                value={row.plan_financial_driver_key ?? ''}
                                onChange={(event) =>
                                    update(index, {
                                        plan_financial_driver_key:
                                            event.target.value,
                                    })
                                }
                                className="h-9 rounded-md border bg-background px-2 text-sm"
                            >
                                <option value="">Select plan commitment</option>
                                {compatibleDrivers.map((driver) => (
                                    <option key={driver.key} value={driver.key}>
                                        {driver.section_title}: {driver.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="grid gap-1 text-xs">
                            <span>Confidence</span>
                            <select
                                value={row.confidence ?? 'estimate'}
                                onChange={(event) =>
                                    update(index, {
                                        confidence: event.target.value as
                                            | 'known'
                                            | 'estimate'
                                            | 'guess',
                                    })
                                }
                                className="h-9 rounded-md border bg-background px-2 text-sm"
                            >
                                <option value="known">Known</option>
                                <option value="estimate">Estimate</option>
                                <option value="guess">Guess</option>
                            </select>
                        </label>
                    </div>
                ))}
            </div>
        </section>
    );
}

function CostCadenceControl({
    row,
    onChange,
}: {
    row: BudgetRow;
    onChange: (patch: Partial<BudgetRow>) => void;
}) {
    return (
        <>
            <label className="grid gap-1 text-xs">
                <span>Cost cadence</span>
                <select
                    value={row.cadence ?? 'monthly'}
                    onChange={(event) =>
                        onChange({
                            cadence: event.target.value as BudgetRow['cadence'],
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
            </label>
            <ConfirmationControl
                label="Cadence confirmed"
                checked={row.cadence_confirmed ?? false}
                onChange={(cadence_confirmed) =>
                    onChange({ cadence_confirmed })
                }
            />
        </>
    );
}

function GrowthCadenceControl({
    row,
    onChange,
}: {
    row: BudgetRow;
    onChange: (patch: Partial<BudgetRow>) => void;
}) {
    return (
        <>
            <label className="grid gap-1 text-xs">
                <span>Growth cadence</span>
                <select
                    value={row.growth_cadence ?? 'monthly'}
                    onChange={(event) =>
                        onChange({
                            growth_cadence: event.target.value as
                                | 'monthly'
                                | 'annual',
                        })
                    }
                    className="h-9 rounded-md border bg-background px-2 text-sm"
                >
                    <option value="monthly">Monthly</option>
                    <option value="annual">Annual</option>
                </select>
            </label>
            <ConfirmationControl
                label="Growth confirmed"
                checked={row.growth_cadence_confirmed ?? false}
                onChange={(growth_cadence_confirmed) =>
                    onChange({ growth_cadence_confirmed })
                }
            />
        </>
    );
}

function ConfirmationControl({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <label className="flex h-9 items-center gap-2 self-end text-xs">
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
            />
            {label}
        </label>
    );
}

export function blankBudgetRow(revenue = false): BudgetRow {
    return revenue
        ? {
              label: '',
              amount: '',
              quantity: 1,
              month: 1,
              growth_percent: 0,
              monthly_growth_percent: 0,
              growth_cadence: 'monthly',
              growth_cadence_confirmed: false,
              monthly_capacity_units: '',
              capacity_confirmed: false,
              gross_profit_percent: '',
              confidence: 'estimate',
          }
        : {
              label: '',
              amount: '',
              quantity: 1,
              cadence: 'monthly',
              cadence_confirmed: false,
              confidence: 'estimate',
          };
}

function budgetTargetHighlightStyle(highlighted: boolean) {
    if (!highlighted) {
        return undefined;
    }

    return {
        backgroundColor: 'rgba(254, 243, 199, 0.82)',
        borderColor: 'rgba(217, 119, 6, 0.9)',
        boxShadow:
            '0 0 0 3px rgba(245, 158, 11, 0.38), 0 12px 28px rgba(146, 64, 14, 0.12)',
    };
}
