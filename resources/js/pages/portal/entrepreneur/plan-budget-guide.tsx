import type { Dispatch, SetStateAction } from 'react';
import { Button } from '@/components/ui/button';
import { formatNzdCurrency } from '@/lib/formatters';
import type {
    BudgetAssumptions,
    BudgetFormState,
    BudgetPayload,
} from './plan-types';

export type PlanBudgetGuidance = {
    sources: { requirement: string; title: string; excerpt: string }[];
    facts: {
        key: string;
        label: string;
        value: number;
        source: string;
        requirement: string;
    }[];
    issues: {
        key: string;
        title: string;
        detail: string;
        action: string;
        requirement: string;
        focus: string;
    }[];
};

export function applyPlanAssumption(
    current: BudgetAssumptions,
    key: string,
    value: number,
): BudgetAssumptions {
    const allowed = [
        'opening_cash_balance',
        'revenue_growth_percent',
        'cost_inflation_percent',
        'target_gross_profit_percent',
    ];

    if (!allowed.includes(key)) {
        return current;
    }

    return {
        ...current,
        [key]: value,
        ...(key === 'opening_cash_balance'
            ? { opening_cash_verified: false }
            : {}),
    };
}

export function PlanBudgetStarter({
    guidance,
    onFormChange,
}: {
    guidance?: PlanBudgetGuidance;
    onFormChange: Dispatch<SetStateAction<BudgetFormState>>;
}) {
    const suggestions = (guidance?.facts ?? []).filter(
        (fact, index, all) =>
            [
                'opening_cash_balance',
                'revenue_growth_percent',
                'cost_inflation_percent',
                'target_gross_profit_percent',
            ].includes(fact.key) &&
            all.findIndex((other) => other.key === fact.key) === index &&
            !all.some(
                (other) => other.key === fact.key && other.value !== fact.value,
            ),
    );

    return (
        <section
            className="space-y-3 rounded-md border bg-muted/20 p-4"
            aria-label="Build your budget from your plan"
        >
            <div>
                <h3 className="font-medium">
                    Build your budget from your plan
                </h3>
                <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                    Use the offers, prices, delivery limits and costs you have
                    described. Confirm the amounts below, then answer the
                    questions beside each part of the budget. Your saved results
                    feed the financial summary and founder brief.
                </p>
            </div>
            {suggestions.length > 0 ? (
                <div className="grid gap-3 md:grid-cols-2">
                    {suggestions.map((fact) => (
                        <div
                            key={fact.key}
                            className="space-y-2 rounded-md border bg-background p-3 text-sm"
                        >
                            <p className="font-medium">
                                {fact.label}:{' '}
                                {fact.key === 'opening_cash_balance'
                                    ? formatNzdCurrency(fact.value)
                                    : fact.value.toLocaleString('en-NZ')}
                            </p>
                            <p className="text-muted-foreground">
                                From your plan: “{fact.source}”
                            </p>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    onFormChange((current) => ({
                                        ...current,
                                        assumptions: applyPlanAssumption(
                                            current.assumptions,
                                            fact.key,
                                            fact.value,
                                        ),
                                    }))
                                }
                            >
                                Use this value
                            </Button>
                        </div>
                    ))}
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Enter any amounts that are not stated clearly in your plan.
                    Suggested cost names are prompts; add only the costs your
                    business needs.
                </p>
            )}
            {(guidance?.issues.length ?? 0) > 0 ? (
                <p className="text-sm text-muted-foreground">
                    {guidance?.issues.length}{' '}
                    {guidance?.issues.length === 1
                        ? 'question appears'
                        : 'questions appear'}{' '}
                    beside the relevant budget inputs below.
                </p>
            ) : null}
        </section>
    );
}

export function PlanBudgetQuestions({
    guidance,
    focus,
    onOpenPlanRequirement,
}: {
    guidance?: PlanBudgetGuidance;
    focus?: string;
    onOpenPlanRequirement?: (key: string) => void;
}) {
    const issues = (guidance?.issues ?? []).filter(
        (issue) => !focus || issue.focus === focus,
    );

    if (issues.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2" aria-label="Questions from your plan">
            {!focus ? (
                <p className="text-sm font-medium">
                    {issues.length}{' '}
                    {issues.length === 1 ? 'difference' : 'differences'} to
                    check in your saved plan and budget
                </p>
            ) : null}
            {(focus ? issues : issues.slice(0, 3)).map((issue) => (
                <div
                    key={issue.key}
                    className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950"
                >
                    <p className="font-medium">{issue.title}</p>
                    <p className="mt-1">{issue.detail}</p>
                    <p className="mt-1">{issue.action}</p>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {!focus ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    const target =
                                        document.querySelector<HTMLElement>(
                                            `[data-founder-action-target="${issue.focus}"]`,
                                        );
                                    target?.scrollIntoView({
                                        behavior: 'smooth',
                                        block: 'start',
                                    });
                                    target
                                        ?.querySelector<HTMLElement>(
                                            'input,select,button',
                                        )
                                        ?.focus({ preventScroll: true });
                                }}
                            >
                                Check budget input
                            </Button>
                        ) : null}
                        {onOpenPlanRequirement ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    onOpenPlanRequirement(issue.requirement)
                                }
                            >
                                Open plan section
                            </Button>
                        ) : null}
                    </div>
                </div>
            ))}
            {!focus && issues.length > 3 ? (
                <p className="text-sm text-muted-foreground">
                    The remaining questions appear beside the relevant budget
                    inputs.
                </p>
            ) : null}
        </div>
    );
}

export function PlanBudgetSource({
    guidance,
    requirement,
    focus,
    onOpenPlanRequirement,
}: {
    guidance?: PlanBudgetGuidance;
    requirement: string;
    focus?: string;
    onOpenPlanRequirement?: (key: string) => void;
}) {
    const source = guidance?.sources.find(
        (entry) => entry.requirement === requirement,
    );
    const facts = (guidance?.facts ?? []).filter((fact) =>
        focus === 'fixed-costs'
            ? fact.key === 'owner_weekly_pay'
            : focus === 'revenue-forecast'
              ? fact.key.startsWith('month_') ||
                ['day_rate', 'monthly_revenue_ceiling'].includes(fact.key)
              : focus === 'funding-sources'
                ? fact.key === 'opening_cash_balance'
                : false,
    );

    return (
        <aside className="rounded-md border-l-4 border-teal-700 bg-muted/20 p-3 text-sm">
            <p className="font-medium">
                {source
                    ? `From your plan: ${source.title}`
                    : 'Start with your plan'}
            </p>
            {facts.length > 0 ? (
                <ul className="mt-2 list-disc space-y-1 pl-5">
                    {facts.map((fact, index) => (
                        <li key={`${fact.key}-${fact.value}-${index}`}>
                            {fact.label}: {fact.source}
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="mt-1 max-w-3xl text-muted-foreground">
                    {source?.excerpt ??
                        'Describe the assumptions in this plan section, then use them in the budget below.'}
                </p>
            )}
            {onOpenPlanRequirement ? (
                <Button
                    type="button"
                    size="sm"
                    variant="link"
                    className="mt-1 h-auto px-0"
                    onClick={() => onOpenPlanRequirement(requirement)}
                >
                    Read or edit this plan section
                </Button>
            ) : null}
        </aside>
    );
}

export function PlanFinancialSummary({ budget }: { budget: BudgetPayload }) {
    const annual = budget.computed.annual_totals ?? [];

    if (annual.length === 0) {
        return null;
    }

    return (
        <section
            className="space-y-3 rounded-md border bg-muted/20 p-4"
            aria-label="Financial summary from your budget"
        >
            <h3 className="font-medium">
                Financial summary from your saved budget
            </h3>
            <p className="max-w-3xl text-sm text-muted-foreground">
                These results update when your budget is saved. Use them to
                explain the financial outlook in your plan; the founder brief
                uses the same forecast.
            </p>
            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th className="p-2">Forecast</th>
                            <th className="p-2">Revenue</th>
                            <th className="p-2">Profit before tax</th>
                        </tr>
                    </thead>
                    <tbody>
                        {annual.map((year) => (
                            <tr key={year.year} className="border-t">
                                <th className="p-2 font-medium">
                                    Year {year.year}
                                </th>
                                <td className="p-2">
                                    {formatNzdCurrency(year.revenue)}
                                </td>
                                <td className="p-2">
                                    {year.net_profit_before_tax_percent == null
                                        ? 'Not available'
                                        : `${year.net_profit_before_tax_percent.toFixed(1)}% of revenue`}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <p className="text-sm">
                Break-even:{' '}
                {budget.computed.break_even_month == null
                    ? 'not reached in the saved forecast'
                    : `Month ${budget.computed.break_even_month}`}
                . Opening cash:{' '}
                {budget.assumptions.opening_cash_balance == null
                    ? 'not recorded'
                    : formatNzdCurrency(
                          Number(budget.assumptions.opening_cash_balance),
                      )}
                .
            </p>
        </section>
    );
}
