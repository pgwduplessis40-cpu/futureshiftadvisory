import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { Dispatch, SetStateAction } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import {
    BudgetConfidenceSelect,
    BudgetEvidenceInputs,
    BudgetInput,
} from './budget-row-inputs';
import {
    blankBudgetRow,
    budgetRowDescription,
    numberFromInput,
    quickAddBudgetRow,
    updateBudgetRow,
} from './budget-row-state';
import { BudgetRowsScrollRegion } from './budget-rows-scroll-region';
import {
    FixedCostCadenceControl,
    fixedCostQuantityWarning,
} from './fixed-cost-cadence';
import type { BudgetFormState, BudgetGroupKey, BudgetRow } from './plan-types';

export function BudgetRowsEditor({
    title,
    helper,
    group,
    rows,
    onFormChange,
    quickAdds = [],
    revenue = false,
    timed = false,
    fixedCost = false,
    focusTarget,
}: {
    title: string;
    helper?: string;
    group: BudgetGroupKey;
    rows: BudgetRow[];
    onFormChange: Dispatch<SetStateAction<BudgetFormState>>;
    quickAdds?: BudgetRow[];
    revenue?: boolean;
    timed?: boolean;
    fixedCost?: boolean;
    focusTarget?: string;
}) {
    const itemInputs = useRef<Array<HTMLInputElement | null>>([]);
    const pendingFocusIndex = useRef<number | null>(null);
    const [quickAddNotice, setQuickAddNotice] = useState<string | null>(null);
    const coBrowseTarget = focusTarget
        ? `entrepreneur.plan.${focusTarget}`
        : undefined;

    useEffect(() => {
        if (pendingFocusIndex.current === null) {
            return;
        }

        itemInputs.current[pendingFocusIndex.current]?.focus();
        pendingFocusIndex.current = null;
    }, [rows]);

    const appendBlankRow = () => {
        onFormChange((current) => {
            pendingFocusIndex.current = current[group].length;

            return {
                ...current,
                [group]: [
                    ...current[group],
                    blankBudgetRow(revenue, timed, fixedCost),
                ],
            };
        });
    };

    const addOrOpenSuggestedRow = (suggestedRow: BudgetRow) => {
        const result = quickAddBudgetRow(
            rows,
            suggestedRow,
            revenue,
            timed,
            fixedCost,
        );
        setQuickAddNotice(
            result.added
                ? `${suggestedRow.label} was added. Complete the highlighted row below.`
                : `${suggestedRow.label} is already in the list. Complete the highlighted row below.`,
        );
        onFormChange((current) => {
            const currentResult = quickAddBudgetRow(
                current[group],
                suggestedRow,
                revenue,
                timed,
                fixedCost,
            );
            pendingFocusIndex.current = currentResult.rowIndex;

            return { ...current, [group]: currentResult.rows };
        });
    };

    return (
        <section
            className="scroll-mt-28 space-y-2 rounded-md border bg-muted/20 p-3"
            data-founder-action-target={focusTarget}
            data-co-browse-target={fixedCost ? undefined : coBrowseTarget}
        >
            <div className="flex items-center justify-between gap-3">
                <div className="text-sm font-medium">{title}</div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={appendBlankRow}
                    data-founder-action-target={
                        focusTarget ? `${focusTarget}-add-row` : undefined
                    }
                    data-co-browse-target={
                        fixedCost ? coBrowseTarget : undefined
                    }
                    aria-label={
                        fixedCost ? 'Add another cost' : `Add another ${title}`
                    }
                >
                    <Plus className="size-4" aria-hidden="true" />
                    {fixedCost ? 'Add another cost' : 'Add another'}
                </Button>
            </div>
            {helper ? (
                <p className="text-sm text-muted-foreground">{helper}</p>
            ) : null}
            {quickAdds.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                    {quickAdds.slice(0, 6).map((row) => (
                        <Tooltip key={row.label}>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className="max-w-full text-left whitespace-normal"
                                    title={budgetRowDescription(row)}
                                    aria-label={`Add or open ${row.label}`}
                                    onClick={() => addOrOpenSuggestedRow(row)}
                                >
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {row.label}
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="top" className="max-w-xs">
                                {budgetRowDescription(row)}
                            </TooltipContent>
                        </Tooltip>
                    ))}
                </div>
            ) : null}
            {quickAddNotice ? (
                <p className="text-sm text-muted-foreground" role="status">
                    {quickAddNotice}
                </p>
            ) : null}

            <BudgetRowsScrollRegion title={title}>
                {rows.map((row, index) => (
                    <div
                        key={index}
                        className={cn(
                            'grid gap-3',
                            !revenue &&
                                !timed &&
                                !fixedCost &&
                                'xl:grid-cols-[minmax(13rem,1.35fr)_minmax(7rem,0.75fr)_minmax(5rem,0.55fr)_minmax(8rem,0.8fr)_auto]',
                            timed &&
                                !revenue &&
                                '2xl:grid-cols-[minmax(13rem,1.35fr)_minmax(7rem,0.75fr)_minmax(5rem,0.55fr)_minmax(5rem,0.55fr)_minmax(8rem,0.8fr)_auto]',
                            revenue &&
                                '2xl:grid-cols-[minmax(12rem,1.2fr)_repeat(8,minmax(5rem,0.55fr))_minmax(8rem,0.8fr)_auto]',
                            fixedCost &&
                                '2xl:grid-cols-[minmax(12rem,1.2fr)_repeat(4,minmax(6rem,0.6fr))_minmax(8rem,0.8fr)_auto]',
                        )}
                    >
                        <BudgetInput
                            label="Item"
                            inputRef={(input) => {
                                itemInputs.current[index] = input;
                            }}
                            value={row.label}
                            onChange={(value) =>
                                updateBudgetRow(onFormChange, group, index, {
                                    label: value,
                                })
                            }
                        />
                        <BudgetInput
                            label={
                                fixedCost ? 'Rate / billing period' : 'Amount'
                            }
                            type="number"
                            value={row.amount}
                            onChange={(value) =>
                                updateBudgetRow(onFormChange, group, index, {
                                    amount: value,
                                })
                            }
                        />
                        <BudgetInput
                            label={fixedCost ? 'Units' : 'Qty'}
                            type="number"
                            value={row.quantity ?? 1}
                            onChange={(value) =>
                                updateBudgetRow(onFormChange, group, index, {
                                    quantity: value,
                                })
                            }
                        />
                        {timed && !revenue ? (
                            <BudgetInput
                                label="Month"
                                type="number"
                                min={1}
                                value={row.month ?? 1}
                                onChange={(value) =>
                                    updateBudgetRow(
                                        onFormChange,
                                        group,
                                        index,
                                        { month: value },
                                    )
                                }
                            />
                        ) : null}
                        {fixedCost ? (
                            <>
                                <FixedCostCadenceControl
                                    cadence={row.cadence}
                                    confirmed={Boolean(row.cadence_confirmed)}
                                    onChange={(changes) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            changes,
                                        )
                                    }
                                />
                                <BudgetEvidenceInputs
                                    row={row}
                                    onChange={(changes) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            changes,
                                        )
                                    }
                                />
                            </>
                        ) : null}
                        {revenue ? (
                            <>
                                <BudgetInput
                                    label="Start"
                                    type="number"
                                    min={1}
                                    value={row.month ?? 1}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            { month: value },
                                        )
                                    }
                                />
                                <BudgetInput
                                    label="Growth %"
                                    type="number"
                                    min={-100}
                                    value={
                                        row.growth_percent ??
                                        row.monthly_growth_percent ??
                                        0
                                    }
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            { growth_percent: value },
                                        )
                                    }
                                />
                                <label className="grid gap-1 text-xs">
                                    <span className="text-muted-foreground">
                                        Growth cadence
                                    </span>
                                    <select
                                        value={row.growth_cadence ?? 'monthly'}
                                        onChange={(event) =>
                                            updateBudgetRow(
                                                onFormChange,
                                                group,
                                                index,
                                                {
                                                    growth_cadence: event.target
                                                        .value as BudgetRow['growth_cadence'],
                                                    growth_cadence_confirmed: true,
                                                },
                                            )
                                        }
                                        className="h-9 rounded-md border bg-background px-2 text-sm"
                                    >
                                        <option value="monthly">Monthly</option>
                                        <option value="annual">Annual</option>
                                    </select>
                                </label>
                                <BudgetInput
                                    label="Max units / month"
                                    type="number"
                                    min={0}
                                    value={row.monthly_capacity_units ?? ''}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            {
                                                monthly_capacity_units: value,
                                                capacity_confirmed:
                                                    value !== '',
                                            },
                                        )
                                    }
                                />
                                <BudgetInput
                                    label="Founder units / month"
                                    type="number"
                                    min={0}
                                    value={row.founder_capacity_units ?? ''}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            { founder_capacity_units: value },
                                        )
                                    }
                                />
                                <BudgetInput
                                    label="Unit label"
                                    value={row.unit_label ?? 'units'}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            { unit_label: value },
                                        )
                                    }
                                />
                                <BudgetInput
                                    label="Cost %"
                                    type="number"
                                    value={row.variable_cost_percent ?? 0}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            { variable_cost_percent: value },
                                        )
                                    }
                                />
                                <BudgetInput
                                    label="Unit cost"
                                    type="number"
                                    value={row.unit_cost ?? ''}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            { unit_cost: value },
                                        )
                                    }
                                />
                                <BudgetInput
                                    label="Contractor cost / unit"
                                    type="number"
                                    min={0}
                                    value={row.contractor_unit_cost ?? ''}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            {
                                                contractor_unit_cost: value,
                                                contractor_cost_confirmed:
                                                    value !== '',
                                            },
                                        )
                                    }
                                />
                                <BudgetInput
                                    label="GP %"
                                    type="number"
                                    value={row.gross_profit_percent ?? ''}
                                    onChange={(value) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            {
                                                gross_profit_percent: value,
                                                variable_cost_percent:
                                                    value === ''
                                                        ? row.variable_cost_percent
                                                        : Math.max(
                                                              0,
                                                              100 -
                                                                  numberFromInput(
                                                                      value,
                                                                  ),
                                                          ),
                                            },
                                        )
                                    }
                                />
                                <BudgetEvidenceInputs
                                    row={row}
                                    revenue
                                    onChange={(changes) =>
                                        updateBudgetRow(
                                            onFormChange,
                                            group,
                                            index,
                                            changes,
                                        )
                                    }
                                />
                            </>
                        ) : null}
                        {fixedCost && fixedCostQuantityWarning(row) ? (
                            <p className="text-xs leading-snug text-destructive">
                                {fixedCostQuantityWarning(row)}
                            </p>
                        ) : null}
                        <BudgetConfidenceSelect
                            value={row.confidence ?? 'estimate'}
                            onChange={(value) =>
                                updateBudgetRow(onFormChange, group, index, {
                                    confidence: value,
                                })
                            }
                        />
                        <div className="flex items-end">
                            <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                title="Remove row"
                                onClick={() =>
                                    onFormChange((current) => ({
                                        ...current,
                                        [group]: current[group].filter(
                                            (_row, rowIndex) =>
                                                rowIndex !== index,
                                        ),
                                    }))
                                }
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                            </Button>
                        </div>
                    </div>
                ))}
            </BudgetRowsScrollRegion>
        </section>
    );
}
