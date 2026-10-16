import type { Dispatch, SetStateAction } from 'react';
import { BudgetInput } from './budget-row-inputs';
import { BudgetTermLabel } from './budget-term-help';
import type { BudgetAssumptions, BudgetFormState } from './plan-types';

export function BudgetAssumptionsEditor({
    assumptions,
    onFormChange,
}: {
    assumptions: BudgetAssumptions;
    onFormChange: Dispatch<SetStateAction<BudgetFormState>>;
}) {
    const fields: {
        key: keyof BudgetAssumptions;
        label: string;
        helper: string;
    }[] = [
        {
            key: 'revenue_growth_percent',
            label: 'Annual revenue growth %',
            helper: 'Applied from year two onward; use Monthly growth % on a revenue row for the first-year ramp.',
        },
        {
            key: 'cost_inflation_percent',
            label: 'Cost/CPI %',
            helper: 'How costs should increase after year one.',
        },
        {
            key: 'target_gross_profit_percent',
            label: 'Target GP %',
            helper: 'Sales left after direct product or delivery costs.',
        },
        {
            key: 'target_net_profit_before_tax_percent',
            label: 'Target NPBT %',
            helper: 'Profit before company tax.',
        },
        {
            key: 'target_net_profit_after_tax_percent',
            label: 'Target NPAT %',
            helper: 'Profit after estimated company tax.',
        },
    ];

    return (
        <div className="space-y-3">
            <label
                className="grid max-w-md scroll-mt-28 gap-1 text-xs"
                data-founder-action-target="forecast-start-month"
                data-co-browse-target="entrepreneur.plan.forecast-start-month"
            >
                <span className="text-muted-foreground">
                    <BudgetTermLabel term="forecast-start-month">
                        Forecast start month
                    </BudgetTermLabel>
                </span>
                <input
                    type="month"
                    value={assumptions.forecast_start_month ?? ''}
                    onChange={(event) =>
                        onFormChange((current) => ({
                            ...current,
                            assumptions: {
                                ...current.assumptions,
                                forecast_start_month: event.target.value,
                                forecast_start_confirmed: false,
                            },
                        }))
                    }
                    className="h-9 rounded-md border bg-background px-2 text-sm"
                />
                <span className="text-[11px] leading-snug text-muted-foreground">
                    This anchors Month 1 to the written milestones and cash
                    forecast.
                </span>
                <span className="flex items-center gap-2 text-[11px] leading-snug text-muted-foreground">
                    <input
                        type="checkbox"
                        name="forecast_start_confirmed"
                        aria-label="Confirm forecast start month"
                        checked={Boolean(assumptions.forecast_start_confirmed)}
                        onChange={(event) =>
                            onFormChange((current) => ({
                                ...current,
                                assumptions: {
                                    ...current.assumptions,
                                    forecast_start_confirmed:
                                        event.target.checked,
                                },
                            }))
                        }
                    />
                    I have checked Month 1 against the written milestones.
                </span>
            </label>
            <div className="grid grid-cols-[repeat(auto-fit,minmax(10rem,1fr))] gap-3">
                {(
                    [
                        ['opening_cash_balance', 'Opening cash', 0],
                        ['debtor_days', 'Debtor days', 0],
                        ['creditor_days', 'Creditor days', 0],
                    ] as const
                ).map(([key, label, min]) => (
                    <label key={key} className="grid min-w-0 gap-1 text-xs">
                        <span className="text-muted-foreground">
                            <BudgetTermLabel
                                term={
                                    key === 'opening_cash_balance'
                                        ? 'opening-cash'
                                        : key === 'debtor_days'
                                          ? 'debtor-days'
                                          : 'creditor-days'
                                }
                            >
                                {label}
                            </BudgetTermLabel>
                        </span>
                        <input
                            type="number"
                            min={min}
                            value={assumptions[key]}
                            onChange={(event) =>
                                onFormChange((current) => ({
                                    ...current,
                                    assumptions: {
                                        ...current.assumptions,
                                        [key]: event.target.value,
                                    },
                                }))
                            }
                            className="h-9 min-w-0 rounded-md border bg-background px-2 text-sm"
                        />
                    </label>
                ))}
            </div>
            <div className="flex flex-wrap gap-4 text-xs text-muted-foreground">
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        name="opening_cash_verified"
                        aria-label="Confirm opening cash against current bank records"
                        checked={Boolean(assumptions.opening_cash_verified)}
                        onChange={(event) =>
                            onFormChange((current) => ({
                                ...current,
                                assumptions: {
                                    ...current.assumptions,
                                    opening_cash_verified: event.target.checked,
                                },
                            }))
                        }
                    />
                    Opening cash checked against current bank records
                </label>
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        name="working_capital_verified"
                        aria-label="Confirm debtor and creditor days against actual terms"
                        checked={Boolean(assumptions.working_capital_verified)}
                        onChange={(event) =>
                            onFormChange((current) => ({
                                ...current,
                                assumptions: {
                                    ...current.assumptions,
                                    working_capital_verified:
                                        event.target.checked,
                                },
                            }))
                        }
                    />
                    Debtor and creditor days checked against actual terms
                </label>
            </div>
            <label className="grid max-w-md gap-1 text-xs">
                <span className="text-muted-foreground">
                    <BudgetTermLabel term="revenue-basis">
                        Revenue after Year 1
                    </BudgetTermLabel>
                </span>
                <select
                    value={assumptions.year_two_revenue_basis}
                    onChange={(event) =>
                        onFormChange((current) => ({
                            ...current,
                            assumptions: {
                                ...current.assumptions,
                                year_two_revenue_basis:
                                    event.target.value === 'year_one_average'
                                        ? 'year_one_average'
                                        : 'exit_run_rate',
                            },
                        }))
                    }
                    className="h-9 rounded-md border bg-background px-2 text-sm"
                >
                    <option value="exit_run_rate">
                        Carry forward the Year 1 exit run-rate
                    </option>
                    <option value="year_one_average">
                        Use the Year 1 average monthly revenue
                    </option>
                </select>
                <span className="text-[11px] leading-snug text-muted-foreground">
                    Carry-forward avoids an artificial Month 12 to Month 13
                    drop. Use the average only for a deliberate seasonal or
                    averaged forecast.
                </span>
            </label>
            <div className="grid gap-3 rounded-md border bg-muted/20 p-3 text-xs md:grid-cols-[minmax(14rem,0.8fr)_minmax(16rem,1.2fr)]">
                <label className="grid gap-1">
                    <span className="text-muted-foreground">
                        <BudgetTermLabel term="funding-position">
                            Funding position
                        </BudgetTermLabel>
                    </span>
                    <select
                        value={assumptions.funding_position ?? 'undecided'}
                        onChange={(event) =>
                            onFormChange((current) => ({
                                ...current,
                                assumptions: {
                                    ...current.assumptions,
                                    funding_position:
                                        event.target.value === 'self_funded'
                                            ? 'self_funded'
                                            : event.target.value ===
                                                'external_funding'
                                              ? 'external_funding'
                                              : 'undecided',
                                    funding_position_confirmed: false,
                                },
                            }))
                        }
                        className="h-9 rounded-md border bg-background px-2 text-sm"
                    >
                        <option value="undecided">Confirm the position</option>
                        <option value="self_funded">Self-funded</option>
                        <option value="external_funding">
                            External funding request
                        </option>
                    </select>
                    <span className="flex items-center gap-2 leading-snug text-muted-foreground">
                        <input
                            type="checkbox"
                            name="funding_position_confirmed"
                            aria-label="Confirm the funding position matches the written plan"
                            checked={Boolean(
                                assumptions.funding_position_confirmed,
                            )}
                            onChange={(event) =>
                                onFormChange((current) => ({
                                    ...current,
                                    assumptions: {
                                        ...current.assumptions,
                                        funding_position_confirmed:
                                            event.target.checked,
                                    },
                                }))
                            }
                        />
                        I confirm this matches the written plan.
                    </span>
                </label>
                <BudgetInput
                    label="Funding purpose (required for an external request)"
                    value={assumptions.funding_request_purpose ?? ''}
                    onChange={(value) =>
                        onFormChange((current) => ({
                            ...current,
                            assumptions: {
                                ...current.assumptions,
                                funding_request_purpose: value,
                            },
                        }))
                    }
                />
            </div>
            <div className="grid grid-cols-[repeat(auto-fit,minmax(10rem,1fr))] gap-3">
                {fields.map((field) => (
                    <label
                        key={field.key}
                        className="grid min-w-0 gap-1 text-xs"
                    >
                        <span className="text-muted-foreground">
                            <BudgetTermLabel
                                term={
                                    field.key === 'revenue_growth_percent'
                                        ? 'growth-rate'
                                        : field.key === 'cost_inflation_percent'
                                          ? 'cost-inflation'
                                          : field.key ===
                                              'target_gross_profit_percent'
                                            ? 'gross-profit'
                                            : 'net-profit'
                                }
                            >
                                {field.label}
                            </BudgetTermLabel>
                        </span>
                        <input
                            type="number"
                            min={
                                field.key === 'revenue_growth_percent' ||
                                field.key === 'cost_inflation_percent'
                                    ? -100
                                    : 0
                            }
                            max={
                                field.key === 'cost_inflation_percent'
                                    ? 100
                                    : 500
                            }
                            value={String(assumptions[field.key] ?? '')}
                            onChange={(event) =>
                                onFormChange((current) => ({
                                    ...current,
                                    assumptions: {
                                        ...current.assumptions,
                                        [field.key]: event.target.value,
                                    },
                                }))
                            }
                            className="h-9 min-w-0 rounded-md border bg-background px-2 text-sm"
                        />
                        <span className="text-[11px] leading-snug text-muted-foreground">
                            {field.helper}
                        </span>
                    </label>
                ))}
            </div>
        </div>
    );
}
