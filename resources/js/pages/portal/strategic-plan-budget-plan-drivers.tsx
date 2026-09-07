import { AlertTriangle, CheckCircle2, Plus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export type PlanFinancialDriverCategory =
    | 'implementation_costs'
    | 'monthly_fixed_costs'
    | 'revenue_forecast'
    | 'funding_sources';

export type PlanFinancialDriver = {
    key: string;
    category: PlanFinancialDriverCategory;
    label: string;
    amount: number | string;
    quantity: number | string;
    month: number | string;
};

export type PlanFinancialDriverOption = PlanFinancialDriver & {
    section_key: string;
    section_title: string;
};

export type PlanBudgetCoherence = {
    status: 'met' | 'review' | 'missing';
    status_label: string;
    score: number;
    summary: string;
    evidence: string[];
    findings: Array<{
        severity: 'missing' | 'review';
        message: string;
        next_action: string;
    }>;
    approval_available: boolean;
    approval_message: string;
    linked_driver_count: number;
    material_row_count: number;
    unresolved_count: number;
};

export function PlanBudgetCoherencePanel({
    coherence,
}: {
    coherence: PlanBudgetCoherence;
}) {
    const aligned = coherence.approval_available;

    return (
        <section className="space-y-3 rounded-md border bg-muted/20 p-3">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        {aligned ? (
                            <CheckCircle2
                                className="size-4 text-emerald-600"
                                aria-hidden="true"
                            />
                        ) : (
                            <AlertTriangle
                                className="size-4 text-amber-600"
                                aria-hidden="true"
                            />
                        )}
                        <h2 className="text-sm font-medium">
                            Plan–budget coherence
                        </h2>
                        <Badge variant={aligned ? 'secondary' : 'destructive'}>
                            {coherence.status_label}
                        </Badge>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {coherence.summary}
                    </p>
                </div>
                <div className="text-sm text-muted-foreground">
                    {coherence.score}/100
                </div>
            </div>
            {!aligned && coherence.findings.length > 0 && (
                <ul className="grid gap-2 text-sm text-muted-foreground">
                    {coherence.findings.slice(0, 3).map((finding) => (
                        <li
                            key={finding.message}
                            className="rounded-md bg-background p-2"
                        >
                            <span>{finding.message}</span>
                            <span className="mt-1 block text-xs">
                                Next: {finding.next_action}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

export function FinancialDriversEditor({
    sectionTitle,
    drivers,
    onChange,
}: {
    sectionTitle: string;
    drivers: PlanFinancialDriver[];
    onChange: (drivers: PlanFinancialDriver[]) => void;
}) {
    const update = (index: number, patch: Partial<PlanFinancialDriver>) => {
        onChange(
            drivers.map((driver, current) =>
                current === index ? { ...driver, ...patch } : driver,
            ),
        );
    };

    return (
        <section className="space-y-3 rounded-md border bg-background p-3">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="text-sm font-medium">
                        Financial commitments in this section
                    </h3>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Add only the revenue, cost, or funding commitments made
                        in {sectionTitle}. Link the matching budget row below.
                    </p>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        onChange([...drivers, blankPlanFinancialDriver()])
                    }
                >
                    <Plus className="size-4" aria-hidden="true" />
                    Add driver
                </Button>
            </div>
            {drivers.length > 0 && (
                <div className="space-y-2">
                    {drivers.map((driver, index) => (
                        <div
                            key={driver.key}
                            className="grid gap-2 rounded-md bg-muted/30 p-2 md:grid-cols-[minmax(0,1fr)_140px_repeat(3,minmax(82px,0.35fr))_auto]"
                        >
                            <BudgetInput
                                label="Commitment"
                                value={driver.label}
                                onChange={(value) =>
                                    update(index, { label: value })
                                }
                            />
                            <label className="grid gap-1 text-xs">
                                <span>Category</span>
                                <select
                                    value={driver.category}
                                    onChange={(event) =>
                                        update(index, {
                                            category: event.target
                                                .value as PlanFinancialDriverCategory,
                                        })
                                    }
                                    className="h-9 rounded-md border bg-background px-2 text-sm"
                                >
                                    <option value="revenue_forecast">
                                        Revenue
                                    </option>
                                    <option value="implementation_costs">
                                        Implementation cost
                                    </option>
                                    <option value="monthly_fixed_costs">
                                        Monthly cost
                                    </option>
                                    <option value="funding_sources">
                                        Funding
                                    </option>
                                </select>
                            </label>
                            <BudgetInput
                                label="Amount"
                                type="number"
                                value={driver.amount}
                                onChange={(value) =>
                                    update(index, { amount: value })
                                }
                            />
                            <BudgetInput
                                label="Qty"
                                type="number"
                                value={driver.quantity}
                                onChange={(value) =>
                                    update(index, { quantity: value })
                                }
                            />
                            <BudgetInput
                                label="Start"
                                type="number"
                                value={driver.month}
                                onChange={(value) =>
                                    update(index, { month: value })
                                }
                            />
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                className="self-end"
                                onClick={() =>
                                    onChange(
                                        drivers.filter(
                                            (_, current) => current !== index,
                                        ),
                                    )
                                }
                            >
                                Remove
                            </Button>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

export function BudgetInput({
    label,
    value,
    type = 'text',
    onChange,
}: {
    label: string;
    value: string | number;
    type?: 'text' | 'number';
    onChange: (value: string) => void;
}) {
    return (
        <label className="grid gap-1 text-xs">
            <span>{label}</span>
            <input
                type={type}
                value={value}
                min={type === 'number' ? 0 : undefined}
                onChange={(event) => onChange(event.target.value)}
                className="h-9 rounded-md border bg-background px-2 text-sm"
            />
        </label>
    );
}

export function blankPlanFinancialDriver(): PlanFinancialDriver {
    return {
        key: `driver_${globalThis.crypto?.randomUUID?.() ?? Date.now()}`,
        category: 'revenue_forecast',
        label: '',
        amount: '',
        quantity: 1,
        month: 1,
    };
}
