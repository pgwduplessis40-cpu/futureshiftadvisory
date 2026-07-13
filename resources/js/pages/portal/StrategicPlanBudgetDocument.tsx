import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BookOpen, FileDown, FileSpreadsheet, Lightbulb, Printer } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { BudgetCashChart } from '@/components/budget-cash-chart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type ClientPayload = {
    id: string;
    legal_name: string;
    trading_name: string | null;
    engagement_type_label: string;
};

type Goal = {
    title: string;
    measure?: string | null;
};

type PlanSection = {
    key: string;
    title: string;
    prompt: string;
    answer: string;
};

type BudgetRow = {
    label?: string;
    amount?: number | string;
    quantity?: number | string;
    month?: number | string;
    monthly_growth_percent?: number | string;
    gross_profit_percent?: number | string;
    unit_cost?: number | string;
    year?: number | string;
    recurring?: boolean;
    confidence?: 'known' | 'estimate' | 'guess';
};

type FundingScenario = {
    name?: string;
    type?: string;
    amount?: number | string;
    year?: number | string;
    interest_rate_percent?: number | string;
    term_years?: number | string;
    interest_only_months?: number | string;
    investor_equity_percent?: number | string;
    confidence?: 'known' | 'estimate' | 'guess';
};

type AnnualForecast = {
    year: number;
    revenue: number;
    variable_costs: number;
    fixed_costs: number;
    interest: number;
    tax: number;
    loan_principal: number;
    funding_inflow: number;
    launch_costs: number;
    gross_profit: number;
    net_profit_before_tax: number;
    net_profit_after_tax: number;
    net_cash_flow: number;
    ending_cash: number;
};

type Readout = {
    summary: string;
    explanation: string;
    findings: string[];
};

type Action = {
    priority: string;
    action: string;
    reason: string;
};

type Scenario = {
    key: string;
    name: string;
    runway_months: number | null;
    runway_open_ended: boolean;
    break_even_year: number | null;
    cash_flow_positive_year: number | null;
    total_funding: number;
    ending_cash: number;
};

type InsightCharts = {
    annual_revenue_costs: Array<{
        label: string;
        revenue: number;
        costs: number;
        net_cash_flow: number;
    }>;
    margin_percentages: Array<{
        label: string;
        gross_profit_percent: number;
        net_profit_before_tax_percent: number;
        net_profit_after_tax_percent: number;
    }>;
    monthly_cash: Array<{
        month: number;
        month_in_year?: number;
        label: string;
        revenue: number;
        cumulative_cash: number;
    }>;
    scenario_comparison: Scenario[];
    confidence_mix: Array<{ label: string; value: number }>;
};

type BudgetPayload = {
    label: string;
    status_label: string;
    horizon_months: number;
    readiness_score: number;
    progress_score: number;
    business_plan_readiness_score: number;
    confidence: { message?: string | null; overall?: string | null };
    source_financials: { count?: number; system_review?: string | null };
    client_goals: Goal[];
    advisor_goals: Goal[];
    business_plan_sections: PlanSection[];
    assumptions: Record<string, number | string | null>;
    implementation_costs: BudgetRow[];
    monthly_fixed_costs: BudgetRow[];
    revenue_forecast: BudgetRow[];
    funding_sources: BudgetRow[];
    future_costs: BudgetRow[];
    funding_scenarios: FundingScenario[];
    computed: {
        total_launch_costs?: number;
        monthly_fixed_costs?: number;
        total_funding?: number;
        available_after_launch?: number;
        break_even_year?: number | null;
        cash_flow_positive_year?: number | null;
        runway_months?: number | null;
        runway_open_ended?: boolean;
    };
    flags: Array<{ key: string; title: string; message: string; severity: string }>;
    analytics: {
        descriptive: Readout;
        diagnostic: Readout;
        predictive: Readout & {
            annual_forecast: AnnualForecast[];
            monthly_forecast: Array<{
                month: number;
                month_in_year: number;
                revenue: number;
                cumulative_cash: number;
            }>;
            scenarios: Scenario[];
        };
        prescriptive: Readout & {
            actions: Action[];
            advisor_decision_points: string[];
        };
        charts: InsightCharts;
    };
};

type Props = {
    client: ClientPayload;
    budget: BudgetPayload;
    workspaceUrl: string;
    pdfUrl: string;
    preparedAt: string;
};

const assumptionLabels: Array<{ key: string; label: string; format: 'currency' | 'percent' }> = [
    { key: 'opening_cash_balance', label: 'Opening cash', format: 'currency' },
    { key: 'revenue_growth_percent', label: 'Revenue growth', format: 'percent' },
    { key: 'cost_inflation_percent', label: 'Cost inflation', format: 'percent' },
    { key: 'target_gross_profit_percent', label: 'Target gross profit', format: 'percent' },
    { key: 'target_net_profit_before_tax_percent', label: 'Target NPBT', format: 'percent' },
    { key: 'target_net_profit_after_tax_percent', label: 'Target NPAT', format: 'percent' },
];

export default function StrategicPlanBudgetDocument({
    client,
    budget,
    workspaceUrl,
    pdfUrl,
    preparedAt,
}: Props) {
    const businessName = client.trading_name || client.legal_name;
    const planLabel = budget.label === 'Operating Plan & Budget' ? 'Operating plan' : 'Business plan';
    const computed = budget.computed ?? {};
    const cashSeries = budget.analytics.predictive.monthly_forecast.map((row) => ({
        month: row.month,
        month_in_year: row.month_in_year,
        revenue: row.revenue,
        cumulative_cash: row.cumulative_cash,
    }));

    return (
        <>
            <Head title={`${budget.label} document`} />
            <style>{printStyles}</style>

            <main className="mx-auto max-w-6xl px-4 py-6 sm:px-8 sm:py-10">
                <div className="document-actions mb-5 flex flex-wrap items-center justify-between gap-3">
                    <Button asChild variant="outline">
                        <Link href={workspaceUrl}>
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Edit workspace
                        </Link>
                    </Button>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <a href={pdfUrl} target="_blank" rel="noreferrer">
                                <FileDown className="size-4" aria-hidden="true" />
                                Open PDF
                            </a>
                        </Button>
                        <Button type="button" onClick={() => window.print()}>
                            <Printer className="size-4" aria-hidden="true" />
                            Print document
                        </Button>
                    </div>
                </div>

                <article className="presentation-paper bg-white px-6 py-8 text-slate-900 shadow-sm sm:px-10 sm:py-12">
                    <section className="presentation-section border-b-4 border-[#D4A91F] pb-9">
                        <div className="flex flex-wrap items-start justify-between gap-6">
                            <div>
                                <p className="text-xs font-semibold tracking-[0.18em] text-[#263D68] uppercase">
                                    Future Shift Advisory
                                </p>
                                <h1 className="mt-4 max-w-3xl text-3xl font-semibold text-[#1F3458] sm:text-4xl">
                                    {budget.label}
                                </h1>
                                <p className="mt-3 text-lg text-slate-600">{businessName}</p>
                                <p className="mt-1 text-sm text-slate-500">{client.engagement_type_label}</p>
                            </div>
                            <div className="min-w-44 border-l-2 border-[#263D68] pl-4 text-sm text-slate-600">
                                <p className="font-medium text-[#1F3458]">Client document</p>
                                <p className="mt-2">Prepared {formatDate(preparedAt)}</p>
                                <Badge className="mt-3 rounded-sm bg-[#263D68] text-white hover:bg-[#263D68]">
                                    {budget.status_label}
                                </Badge>
                            </div>
                        </div>

                        <p className="mt-8 max-w-4xl text-base leading-7 text-slate-700">
                            A single presentation of the {planLabel.toLowerCase()}, financial budget, and decision-ready insights for {businessName}.
                        </p>

                        <div className="mt-8 grid border-y border-slate-200 sm:grid-cols-4">
                            <DocumentMetric label="Plan readiness" value={`${budget.business_plan_readiness_score}/100`} />
                            <DocumentMetric label="Budget confidence" value={`${budget.readiness_score}/100`} />
                            <DocumentMetric label="Forecast horizon" value={`${budget.horizon_months} months`} />
                            <DocumentMetric label="Financial evidence" value={`${budget.source_financials.count ?? 0} files`} />
                        </div>
                    </section>

                    <section className="presentation-section pt-10">
                        <SectionHeading icon={BookOpen} number="01" title={planLabel} subtitle="The client-owned plan that guides the financial forecast and advisory priorities." />
                        <Goals clientGoals={budget.client_goals} advisorGoals={budget.advisor_goals} />
                        <div className="mt-8 divide-y divide-slate-200 border-y border-slate-200">
                            {budget.business_plan_sections.map((section) => (
                                <article key={section.key} className="grid gap-3 py-6 md:grid-cols-[13rem_minmax(0,1fr)]">
                                    <div>
                                        <h3 className="text-sm font-semibold text-[#1F3458]">{section.title}</h3>
                                        <p className="mt-2 text-xs leading-5 text-slate-500">{section.prompt}</p>
                                    </div>
                                    <p className="text-sm leading-6 whitespace-pre-line text-slate-700">
                                        {section.answer?.trim() || 'This section has not yet been completed.'}
                                    </p>
                                </article>
                            ))}
                        </div>
                    </section>

                    <section className="presentation-section presentation-break pt-12">
                        <SectionHeading icon={FileSpreadsheet} number="02" title="Budget" subtitle="All values are NZD and GST exclusive. Forecast figures reflect the current saved assumptions." />
                        <div className="mt-7 grid border-y border-slate-200 sm:grid-cols-2 lg:grid-cols-3">
                            <DocumentMetric label="Implementation costs" value={formatCurrency(computed.total_launch_costs)} />
                            <DocumentMetric label="Monthly operating costs" value={formatCurrency(computed.monthly_fixed_costs)} />
                            <DocumentMetric label="Funding available" value={formatCurrency(computed.total_funding)} />
                            <DocumentMetric label="Available after setup" value={formatCurrency(computed.available_after_launch)} />
                            <DocumentMetric label="Break-even" value={formatYear(computed.break_even_year)} />
                            <DocumentMetric label="Cash-flow positive" value={formatYear(computed.cash_flow_positive_year)} />
                        </div>

                        <div className="mt-9">
                            <h3 className="text-base font-semibold text-[#1F3458]">Financial assumptions</h3>
                            <div className="mt-4 grid gap-x-8 gap-y-4 border-y border-slate-200 py-5 sm:grid-cols-2 lg:grid-cols-3">
                                {assumptionLabels.map((assumption) => (
                                    <div key={assumption.key} className="flex items-baseline justify-between gap-3 text-sm">
                                        <span className="text-slate-600">{assumption.label}</span>
                                        <span className="font-semibold text-slate-900">
                                            {formatAssumption(budget.assumptions[assumption.key], assumption.format)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="mt-10 space-y-10">
                            <BudgetTable title="Implementation costs" rows={budget.implementation_costs} />
                            <BudgetTable title="Monthly operating costs" rows={budget.monthly_fixed_costs} />
                            <RevenueTable rows={budget.revenue_forecast} />
                            <BudgetTable title="Funding sources" rows={budget.funding_sources} />
                            <FutureCostTable rows={budget.future_costs} />
                            <FundingScenarioTable rows={budget.funding_scenarios} />
                            <AnnualForecastTable rows={budget.analytics.predictive.annual_forecast} />
                        </div>
                    </section>

                    <section className="presentation-section presentation-break pt-12">
                        <SectionHeading icon={Lightbulb} number="03" title="Insights" subtitle="The current readout turns the plan and budget into decisions, risks, forecasts, and next actions." />
                        <div className="mt-8 grid gap-x-8 gap-y-8 md:grid-cols-2">
                            <Readout title="Current position" readout={budget.analytics.descriptive} />
                            <Readout title="Risks and diagnoses" readout={budget.analytics.diagnostic} />
                            <Readout title="Forecast outlook" readout={budget.analytics.predictive} />
                            <Readout title="Recommended actions" readout={budget.analytics.prescriptive} />
                        </div>

                        {cashSeries.length > 0 ? (
                            <div className="mt-10">
                                <BudgetCashChart
                                    series={cashSeries}
                                    breakEvenMonth={null}
                                    runwayMonths={computed.runway_months ?? null}
                                    runwayOpenEnded={computed.runway_open_ended ?? false}
                                    title="Cash and revenue forecast"
                                    description="The cash position and monthly revenue across the current forecast horizon."
                                />
                            </div>
                        ) : null}

                        <div className="mt-10 grid gap-5 lg:grid-cols-2">
                            <MarginChart rows={budget.analytics.charts.margin_percentages} />
                            <AnnualRevenueCostsChart rows={budget.analytics.charts.annual_revenue_costs} />
                            <ScenarioImpactChart rows={budget.analytics.charts.scenario_comparison} />
                            <ConfidenceMixChart rows={budget.analytics.charts.confidence_mix} />
                        </div>

                        <div className="mt-10 grid gap-8 lg:grid-cols-2">
                            <ScenarioTable rows={budget.analytics.predictive.scenarios} />
                            <ActionList actions={budget.analytics.prescriptive.actions} />
                        </div>

                        <div className="mt-10 border-t border-slate-200 pt-7">
                            <h3 className="text-base font-semibold text-[#1F3458]">Advisor decision points</h3>
                            <ul className="mt-4 grid gap-3 text-sm leading-6 text-slate-700 md:grid-cols-2">
                                {budget.analytics.prescriptive.advisor_decision_points.map((point) => (
                                    <li key={point} className="border-l-2 border-[#D4A91F] pl-3">{point}</li>
                                ))}
                            </ul>
                        </div>
                    </section>

                    <footer className="mt-14 border-t border-slate-200 pt-5 text-xs leading-5 text-slate-500">
                        Prepared for {businessName} by Future Shift Advisory. This planning document reflects the saved plan and budget at the preparation time shown above and should be reviewed when assumptions, funding, or financial evidence change.
                    </footer>
                </article>
            </main>
        </>
    );
}

function SectionHeading({
    icon: Icon,
    number,
    title,
    subtitle,
}: {
    icon: LucideIcon;
    number: string;
    title: string;
    subtitle: string;
}) {
    return (
        <div className="flex gap-4">
            <div className="flex size-9 shrink-0 items-center justify-center bg-[#263D68] text-xs font-semibold text-white">{number}</div>
            <div>
                <div className="flex items-center gap-2">
                    <Icon className="size-4 text-[#D4A91F]" aria-hidden="true" />
                    <h2 className="text-2xl font-semibold text-[#1F3458]">{title}</h2>
                </div>
                <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{subtitle}</p>
            </div>
        </div>
    );
}

function DocumentMetric({ label, value }: { label: string; value: string }) {
    return (
        <div className="border-slate-200 px-4 py-4 first:pl-0 sm:border-r last:border-r-0">
            <p className="text-xs font-medium tracking-wide text-slate-500 uppercase">{label}</p>
            <p className="mt-2 text-lg font-semibold text-[#1F3458]">{value}</p>
        </div>
    );
}

function Goals({ clientGoals, advisorGoals }: { clientGoals: Goal[]; advisorGoals: Goal[] }) {
    if (clientGoals.length === 0 && advisorGoals.length === 0) {
        return null;
    }

    return (
        <div className="mt-8 grid gap-8 border-y border-slate-200 py-6 md:grid-cols-2">
            <GoalColumn title="Client goals" goals={clientGoals} />
            <GoalColumn title="Advisor goals" goals={advisorGoals} />
        </div>
    );
}

function GoalColumn({ title, goals }: { title: string; goals: Goal[] }) {
    return (
        <div>
            <h3 className="text-sm font-semibold text-[#1F3458]">{title}</h3>
            {goals.length === 0 ? (
                <p className="mt-3 text-sm text-slate-500">No goals recorded.</p>
            ) : (
                <ul className="mt-3 space-y-3">
                    {goals.map((goal, index) => (
                        <li key={`${goal.title}-${index}`} className="border-l-2 border-[#D4A91F] pl-3 text-sm">
                            <p className="font-medium text-slate-800">{goal.title}</p>
                            {goal.measure ? <p className="mt-1 leading-5 text-slate-600">{goal.measure}</p> : null}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function BudgetTable({ title, rows }: { title: string; rows: BudgetRow[] }) {
    const populated = populatedRows(rows);

    return (
        <TableSection title={title} emptyLabel={`No ${title.toLowerCase()} have been recorded.`} hasRows={populated.length > 0}>
            <table className="presentation-table w-full border-collapse text-left text-sm">
                <thead>
                    <tr className="border-b border-slate-300 text-xs tracking-wide text-slate-500 uppercase">
                        <th className="py-2 pr-3 font-medium">Item</th>
                        <th className="py-2 pr-3 text-right font-medium">Amount</th>
                        <th className="py-2 pr-3 text-right font-medium">Quantity</th>
                        <th className="py-2 text-right font-medium">Confidence</th>
                    </tr>
                </thead>
                <tbody>
                    {populated.map((row, index) => (
                        <tr key={`${title}-${index}`} className="border-b border-slate-200 last:border-b-0">
                            <td className="py-2.5 pr-3 text-slate-800">{row.label || 'Untitled item'}</td>
                            <td className="py-2.5 pr-3 text-right tabular-nums">{formatCurrency(row.amount)}</td>
                            <td className="py-2.5 pr-3 text-right tabular-nums">{formatNumber(row.quantity ?? 1)}</td>
                            <td className="py-2.5 text-right capitalize text-slate-600">{row.confidence ?? 'estimate'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </TableSection>
    );
}

function RevenueTable({ rows }: { rows: BudgetRow[] }) {
    const populated = populatedRows(rows);

    return (
        <TableSection title="Revenue forecast" emptyLabel="No revenue forecast lines have been recorded." hasRows={populated.length > 0}>
            <table className="presentation-table w-full border-collapse text-left text-sm">
                <thead>
                    <tr className="border-b border-slate-300 text-xs tracking-wide text-slate-500 uppercase">
                        <th className="py-2 pr-3 font-medium">Revenue line</th>
                        <th className="py-2 pr-3 text-right font-medium">Amount</th>
                        <th className="py-2 pr-3 text-right font-medium">Start</th>
                        <th className="py-2 pr-3 text-right font-medium">Growth</th>
                        <th className="py-2 text-right font-medium">Gross profit</th>
                    </tr>
                </thead>
                <tbody>
                    {populated.map((row, index) => (
                        <tr key={`revenue-${index}`} className="border-b border-slate-200 last:border-b-0">
                            <td className="py-2.5 pr-3 text-slate-800">{row.label || 'Untitled revenue line'}</td>
                            <td className="py-2.5 pr-3 text-right tabular-nums">{formatCurrency(row.amount)}</td>
                            <td className="py-2.5 pr-3 text-right">Month {formatNumber(row.month ?? 1)}</td>
                            <td className="py-2.5 pr-3 text-right tabular-nums">{formatPercent(row.monthly_growth_percent)}</td>
                            <td className="py-2.5 text-right tabular-nums">{formatPercent(row.gross_profit_percent)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </TableSection>
    );
}

function FutureCostTable({ rows }: { rows: BudgetRow[] }) {
    const populated = populatedRows(rows);

    return (
        <TableSection title="Future costs" emptyLabel="No future costs have been recorded." hasRows={populated.length > 0}>
            <table className="presentation-table w-full border-collapse text-left text-sm">
                <thead>
                    <tr className="border-b border-slate-300 text-xs tracking-wide text-slate-500 uppercase">
                        <th className="py-2 pr-3 font-medium">Item</th>
                        <th className="py-2 pr-3 text-right font-medium">Amount</th>
                        <th className="py-2 pr-3 text-right font-medium">Year</th>
                        <th className="py-2 text-right font-medium">Treatment</th>
                    </tr>
                </thead>
                <tbody>
                    {populated.map((row, index) => (
                        <tr key={`future-cost-${index}`} className="border-b border-slate-200 last:border-b-0">
                            <td className="py-2.5 pr-3 text-slate-800">{row.label || 'Untitled item'}</td>
                            <td className="py-2.5 pr-3 text-right tabular-nums">{formatCurrency(row.amount)}</td>
                            <td className="py-2.5 pr-3 text-right">Year {formatNumber(row.year ?? 2)}</td>
                            <td className="py-2.5 text-right text-slate-600">{row.recurring ? 'Recurring' : 'One-off'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </TableSection>
    );
}

function FundingScenarioTable({ rows }: { rows: FundingScenario[] }) {
    const populated = rows.filter((row) => Boolean(row.name?.trim()) || toNumber(row.amount) !== 0);

    return (
        <TableSection title="Funding scenarios" emptyLabel="No funding scenarios have been recorded." hasRows={populated.length > 0}>
            <table className="presentation-table w-full border-collapse text-left text-sm">
                <thead>
                    <tr className="border-b border-slate-300 text-xs tracking-wide text-slate-500 uppercase">
                        <th className="py-2 pr-3 font-medium">Scenario</th>
                        <th className="py-2 pr-3 text-right font-medium">Funding</th>
                        <th className="py-2 pr-3 text-right font-medium">Year</th>
                        <th className="py-2 text-right font-medium">Terms</th>
                    </tr>
                </thead>
                <tbody>
                    {populated.map((row, index) => (
                        <tr key={`funding-scenario-${index}`} className="border-b border-slate-200 last:border-b-0">
                            <td className="py-2.5 pr-3 text-slate-800">{row.name || 'Untitled scenario'}{row.type ? ` (${formatLabel(row.type)})` : ''}</td>
                            <td className="py-2.5 pr-3 text-right tabular-nums">{formatCurrency(row.amount)}</td>
                            <td className="py-2.5 pr-3 text-right">Year {formatNumber(row.year ?? 1)}</td>
                            <td className="py-2.5 text-right text-slate-600">{fundingTerms(row)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </TableSection>
    );
}

function AnnualForecastTable({ rows }: { rows: AnnualForecast[] }) {
    return (
        <TableSection title="Annual forecast" emptyLabel="No forecast has been generated yet." hasRows={rows.length > 0}>
            <div className="overflow-x-auto">
                <table className="presentation-table w-full min-w-[720px] border-collapse text-left text-sm">
                    <thead>
                        <tr className="border-b border-slate-300 text-xs tracking-wide text-slate-500 uppercase">
                            <th className="py-2 pr-3 font-medium">Year</th>
                            <th className="py-2 pr-3 text-right font-medium">Revenue</th>
                            <th className="py-2 pr-3 text-right font-medium">Gross profit</th>
                            <th className="py-2 pr-3 text-right font-medium">NPAT</th>
                            <th className="py-2 pr-3 text-right font-medium">Net cash flow</th>
                            <th className="py-2 text-right font-medium">Ending cash</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.year} className="border-b border-slate-200 last:border-b-0">
                                <td className="py-2.5 pr-3 font-medium text-slate-800">Year {row.year}</td>
                                <td className="py-2.5 pr-3 text-right tabular-nums">{formatCurrency(row.revenue)}</td>
                                <td className="py-2.5 pr-3 text-right tabular-nums">{formatCurrency(row.gross_profit)}</td>
                                <td className="py-2.5 pr-3 text-right tabular-nums">{formatCurrency(row.net_profit_after_tax)}</td>
                                <td className="py-2.5 pr-3 text-right tabular-nums">{formatCurrency(row.net_cash_flow)}</td>
                                <td className="py-2.5 text-right font-medium tabular-nums">{formatCurrency(row.ending_cash)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </TableSection>
    );
}

function TableSection({ title, emptyLabel, hasRows, children }: { title: string; emptyLabel: string; hasRows: boolean; children: React.ReactNode }) {
    return (
        <section className="presentation-section">
            <h3 className="text-base font-semibold text-[#1F3458]">{title}</h3>
            <div className="mt-3 border-y border-slate-200 py-1">
                {hasRows ? children : <p className="py-4 text-sm text-slate-500">{emptyLabel}</p>}
            </div>
        </section>
    );
}

function Readout({ title, readout }: { title: string; readout: Readout }) {
    return (
        <section className="presentation-section border-t-2 border-[#263D68] pt-4">
            <h3 className="text-base font-semibold text-[#1F3458]">{title}</h3>
            <p className="mt-2 text-sm leading-6 text-slate-700">{readout.summary}</p>
            {readout.findings.length > 0 ? (
                <ul className="mt-4 space-y-2 text-sm leading-6 text-slate-600">
                    {readout.findings.map((finding) => <li key={finding} className="border-l-2 border-slate-300 pl-3">{finding}</li>)}
                </ul>
            ) : null}
        </section>
    );
}

function ScenarioTable({ rows }: { rows: Scenario[] }) {
    return (
        <section className="presentation-section">
            <h3 className="text-base font-semibold text-[#1F3458]">Scenario comparison</h3>
            {rows.length === 0 ? (
                <p className="mt-3 text-sm text-slate-500">No scenario comparisons are available yet.</p>
            ) : (
                <div className="mt-3 border-y border-slate-200 py-1">
                    <table className="presentation-table w-full border-collapse text-left text-sm">
                        <thead>
                            <tr className="border-b border-slate-300 text-xs tracking-wide text-slate-500 uppercase">
                                <th className="py-2 pr-3 font-medium">Scenario</th>
                                <th className="py-2 pr-3 text-right font-medium">Runway</th>
                                <th className="py-2 text-right font-medium">Ending cash</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.key || row.name} className="border-b border-slate-200 last:border-b-0">
                                    <td className="py-2.5 pr-3 text-slate-800">{row.name}</td>
                                    <td className="py-2.5 pr-3 text-right text-slate-600">{row.runway_open_ended ? 'Open ended' : `${row.runway_months ?? 0} months`}</td>
                                    <td className="py-2.5 text-right font-medium tabular-nums">{formatCurrency(row.ending_cash)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

function ActionList({ actions }: { actions: Action[] }) {
    return (
        <section className="presentation-section">
            <h3 className="text-base font-semibold text-[#1F3458]">Priority actions</h3>
            {actions.length === 0 ? (
                <p className="mt-3 text-sm text-slate-500">No actions are currently recommended.</p>
            ) : (
                <ol className="mt-3 space-y-4">
                    {actions.map((action, index) => (
                        <li key={`${action.action}-${index}`} className="grid grid-cols-[1.75rem_minmax(0,1fr)] gap-3 text-sm">
                            <span className="flex size-7 items-center justify-center bg-[#263D68] text-xs font-semibold text-white">{index + 1}</span>
                            <div>
                                <p className="font-medium text-slate-800">{action.action}</p>
                                {action.reason ? <p className="mt-1 leading-5 text-slate-600">{action.reason}</p> : null}
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}

function MarginChart({ rows }: { rows: InsightCharts['margin_percentages'] }) {
    const max = Math.max(1, ...rows.flatMap((row) => [
        Math.abs(row.gross_profit_percent),
        Math.abs(row.net_profit_before_tax_percent),
        Math.abs(row.net_profit_after_tax_percent),
    ]));

    return (
        <InsightChartCard
            title="Profit margin story"
            description="Gross profit, profit before tax, and profit after tax across the forecast."
        >
            {rows.length === 0 ? <EmptyChart /> : rows.map((row) => (
                <div key={row.label} className="mt-4 grid grid-cols-[3.5rem_minmax(0,1fr)] gap-3">
                    <span className="pt-0.5 text-xs font-semibold text-slate-700">{row.label}</span>
                    <div className="space-y-2">
                        <InsightBar label="GP" value={row.gross_profit_percent} max={max} tone="bg-emerald-600" format={formatPercent} />
                        <InsightBar label="NPBT" value={row.net_profit_before_tax_percent} max={max} tone="bg-cyan-700" format={formatPercent} />
                        <InsightBar label="NPAT" value={row.net_profit_after_tax_percent} max={max} tone="bg-slate-700" format={formatPercent} />
                    </div>
                </div>
            ))}
        </InsightChartCard>
    );
}

function AnnualRevenueCostsChart({ rows }: { rows: InsightCharts['annual_revenue_costs'] }) {
    const max = Math.max(1, ...rows.flatMap((row) => [row.revenue, row.costs]));

    return (
        <InsightChartCard
            title="Revenue, costs and net cash"
            description="Revenue and costs by year, with the net cash contribution at right."
        >
            {rows.length === 0 ? <EmptyChart /> : rows.map((row) => (
                <div key={row.label} className="mt-4 grid grid-cols-[3.5rem_minmax(0,1fr)_4.5rem] items-center gap-3">
                    <span className="text-xs font-semibold text-slate-700">{row.label}</span>
                    <div className="space-y-2">
                        <InsightBar label="Revenue" value={row.revenue} max={max} tone="bg-emerald-600" format={formatCompactCurrency} />
                        <InsightBar label="Costs" value={row.costs} max={max} tone="bg-amber-500" format={formatCompactCurrency} />
                    </div>
                    <span className={`text-right text-xs font-semibold ${row.net_cash_flow < 0 ? 'text-red-600' : 'text-emerald-700'}`}>
                        {formatCompactCurrency(row.net_cash_flow)}
                    </span>
                </div>
            ))}
        </InsightChartCard>
    );
}

function ScenarioImpactChart({ rows }: { rows: InsightCharts['scenario_comparison'] }) {
    const max = Math.max(1, ...rows.map((row) => Math.abs(row.ending_cash)));

    return (
        <InsightChartCard
            title="Scenario sensitivity impact"
            description="Ending cash and runway across the base case and downside scenarios."
        >
            {rows.length === 0 ? <EmptyChart /> : rows.map((row) => (
                <div key={row.key || row.name} className="mt-4">
                    <div className="mb-1.5 flex items-center justify-between gap-3 text-xs">
                        <span className="font-semibold text-slate-700">{row.name}</span>
                        <span className="text-slate-500">{row.runway_open_ended ? 'Open runway' : `${row.runway_months ?? 0} months`}</span>
                    </div>
                    <InsightBar label="Ending cash" value={row.ending_cash} max={max} tone={row.ending_cash < 0 ? 'bg-red-500' : 'bg-emerald-600'} format={formatCompactCurrency} />
                </div>
            ))}
        </InsightChartCard>
    );
}

function ConfidenceMixChart({ rows }: { rows: InsightCharts['confidence_mix'] }) {
    const total = rows.reduce((sum, row) => sum + row.value, 0);
    const toneByLabel: Record<string, string> = {
        Known: 'bg-emerald-600',
        Estimate: 'bg-amber-500',
        Guess: 'bg-red-500',
    };

    return (
        <InsightChartCard
            title="Evidence confidence mix"
            description="How much of the plan is supported by known evidence, estimates, or guesses."
        >
            {total === 0 ? <EmptyChart /> : (
                <>
                    <div className="mt-5 flex h-3 overflow-hidden rounded-sm bg-slate-100">
                        {rows.map((row) => (
                            <span
                                key={row.label}
                                className={toneByLabel[row.label] ?? 'bg-slate-300'}
                                style={{ width: `${(row.value / total) * 100}%` }}
                                title={`${row.label}: ${formatPercent((row.value / total) * 100)}`}
                            />
                        ))}
                    </div>
                    <div className="mt-5 space-y-3">
                        {rows.map((row) => (
                            <div key={row.label} className="grid grid-cols-[minmax(0,1fr)_3rem_3.5rem] items-center gap-3 text-xs">
                                <span className="flex items-center gap-2 font-medium text-slate-700">
                                    <span className={`size-2 ${toneByLabel[row.label] ?? 'bg-slate-300'}`} />
                                    {row.label}
                                </span>
                                <span className="text-right text-slate-500">{row.value}</span>
                                <span className="text-right font-semibold text-slate-700">{formatPercent((row.value / total) * 100)}</span>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </InsightChartCard>
    );
}

function InsightChartCard({ title, description, children }: { title: string; description: string; children: React.ReactNode }) {
    return (
        <section className="insight-chart presentation-section border border-slate-200 p-5">
            <h3 className="text-base font-semibold text-[#1F3458]">{title}</h3>
            <p className="mt-1 text-xs leading-5 text-slate-600">{description}</p>
            {children}
        </section>
    );
}

function InsightBar({ label, value, max, tone, format }: { label: string; value: number; max: number; tone: string; format: (value: number) => string }) {
    const width = Math.min(100, Math.max(0, (Math.abs(value) / max) * 100));

    return (
        <div className="grid grid-cols-[3.25rem_minmax(0,1fr)_3.75rem] items-center gap-2 text-[11px]">
            <span className="text-slate-500">{label}</span>
            <span className="block h-1.5 overflow-hidden bg-slate-100">
                <span className={`block h-full ${value < 0 ? 'bg-red-500' : tone}`} style={{ width: `${width}%` }} />
            </span>
            <span className="text-right tabular-nums text-slate-600">{format(value)}</span>
        </div>
    );
}

function EmptyChart() {
    return <p className="mt-4 text-sm text-slate-500">No forecast data is available yet.</p>;
}

function populatedRows(rows: BudgetRow[]): BudgetRow[] {
    return rows.filter((row) => Boolean(row.label?.trim()) || toNumber(row.amount) !== 0);
}

function fundingTerms(row: FundingScenario): string {
    const terms = [
        row.interest_rate_percent === undefined ? null : `${formatNumber(row.interest_rate_percent)}% interest`,
        row.term_years === undefined ? null : `${formatNumber(row.term_years)} years`,
        row.investor_equity_percent === undefined ? null : `${formatNumber(row.investor_equity_percent)}% equity`,
    ].filter((term): term is string => term !== null);

    return terms.join(', ') || 'Not specified';
}

function formatAssumption(value: number | string | null | undefined, format: 'currency' | 'percent'): string {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return format === 'currency' ? formatCurrency(value) : formatPercent(value);
}

function formatCurrency(value: number | string | null | undefined): string {
    return new Intl.NumberFormat('en-NZ', {
        style: 'currency',
        currency: 'NZD',
        maximumFractionDigits: 0,
    }).format(toNumber(value));
}

function formatCompactCurrency(value: number): string {
    return new Intl.NumberFormat('en-NZ', {
        style: 'currency',
        currency: 'NZD',
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
}

function formatPercent(value: number | string | null | undefined): string {
    return `${new Intl.NumberFormat('en-NZ', { maximumFractionDigits: 1 }).format(toNumber(value))}%`;
}

function formatNumber(value: number | string | null | undefined): string {
    return new Intl.NumberFormat('en-NZ', { maximumFractionDigits: 1 }).format(toNumber(value));
}

function formatYear(value: number | null | undefined): string {
    return value ? `Year ${value}` : '-';
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-NZ', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(value));
}

function formatLabel(value: string): string {
    return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function toNumber(value: number | string | null | undefined): number {
    const number = typeof value === 'number' ? value : Number(value ?? 0);

    return Number.isFinite(number) ? number : 0;
}

const printStyles = `
    @page { size: A4; margin: 13mm; }
    @media print {
        body { background: #ffffff !important; }
        .document-actions { display: none !important; }
        .presentation-paper { width: auto !important; max-width: none !important; padding: 0 !important; box-shadow: none !important; }
        .presentation-section { break-inside: avoid-page; }
        .insight-chart { break-inside: avoid-page; }
        .presentation-break { break-before: page; }
        .presentation-table { font-size: 9pt; }
    }
`;
