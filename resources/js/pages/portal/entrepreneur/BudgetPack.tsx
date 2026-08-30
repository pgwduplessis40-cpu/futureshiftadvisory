import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, FileText, Scale } from 'lucide-react';
import type { ReactNode } from 'react';
import { BudgetCashChart } from '@/components/budget-cash-chart';
import {
    ExplainedMetricCard,
    ExplainedSectionHeader,
} from '@/components/explainer';
import type { Explanation } from '@/components/explainer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatNzdCurrency } from '@/lib/formatters';

type AnnualRow = {
    year: number;
    revenue: number;
    variable_costs: number;
    gross_profit: number;
    gross_profit_percent: number | null;
    fixed_costs: number;
    interest: number;
    tax: number;
    net_profit_before_tax: number;
    net_profit_before_tax_percent: number | null;
    net_profit_after_tax: number;
    net_profit_after_tax_percent: number | null;
    net_cash_flow: number;
    ending_cash: number;
};

type MonthlyRow = {
    month: number;
    month_in_year: number;
    revenue: number;
    variable_costs: number;
    gross_profit: number;
    fixed_costs: number;
    tax: number;
    net_profit_after_tax: number;
    net_cash_flow: number;
    cumulative_cash: number;
};

type ScenarioRow = {
    key: string | null;
    name: string;
    type: string;
    sensitivity_label?: string;
    lowest_cash_month?: number | null;
    lowest_cash?: number | null;
    additional_funding_needed?: number;
    implication?: string;
    summary: {
        break_even_year?: number | null;
        first_profitable_year?: number | null;
        cash_flow_positive_year?: number | null;
    };
};

type FundingDecision = {
    input_status_label: string;
    readiness_label: string;
    readiness_tone: 'good' | 'medium' | 'high' | string;
    headline: string;
    lowest_cash_month?: number | null;
    lowest_cash?: number | null;
    additional_funding_needed: number;
    required_additional_funding: number;
    available_funding: number;
    recommended_funding_target: number;
    funding_gap_or_surplus: number;
    operating_cover_months: number;
    funding_position: 'self_funded' | 'external_funding' | 'undecided' | string;
    funding_position_label: string;
    funding_position_aligned: boolean;
    risk_reasons: string[];
};

type UseOfFundsRow = {
    label: string;
    amount: number;
    display_amount?: string;
    note: string;
};

type FixedCostRow = {
    label: string;
    monthly_amount: number;
    start_month_label: string;
    confidence: string;
};

type PackPayload = {
    available: boolean;
    profile_name: string;
    plan_title: string;
    status?: string;
    forecast_years?: number;
    generated_at?: string;
    gst_exclusive?: boolean;
    tax_configured?: boolean;
    warnings: string[];
    summary: {
        break_even_month?: number | null;
        break_even_year?: number | null;
        first_profitable_year?: number | null;
        cash_flow_positive_year?: number | null;
        runway_months?: number | null;
        runway_open_ended?: boolean;
        available_after_launch?: number;
    };
    funding_decision: FundingDecision | null;
    use_of_funds: UseOfFundsRow[];
    fixed_costs: FixedCostRow[];
    cash_story: string[];
    assumptions: {
        label: string;
        value: string;
        basis?: string;
        review_note?: string;
        provided?: boolean;
    }[];
    explanations: Record<string, string>;
    annual_totals: AnnualRow[];
    monthly_by_year: {
        year: number;
        rows: MonthlyRow[];
    }[];
    scenarios: ScenarioRow[];
    active_flags: {
        key: string;
        title: string;
        message: string;
    }[];
};

type Props = {
    pack: PackPayload;
    urls: {
        plan: string;
        pdf: string;
    };
};

export default function BudgetPack({ pack, urls }: Props) {
    const summary = pack.summary ?? {};
    const monthlySeries = pack.monthly_by_year.flatMap((year) => year.rows);

    return (
        <>
            <Head title="Budget pack" />

            <div className="space-y-5">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={urls.plan}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Business plan
                            </Link>
                        </Button>
                        <div className="mt-3 flex items-center gap-2">
                            <FileText
                                className="size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <h1 className="text-xl font-semibold">
                                Budget pack
                            </h1>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {pack.profile_name} · {pack.plan_title}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">
                            {pack.forecast_years ?? 3} years
                        </Badge>
                        <Badge
                            variant={
                                pack.tax_configured ? 'secondary' : 'outline'
                            }
                        >
                            {pack.tax_configured
                                ? 'Tax configured'
                                : 'Tax not configured'}
                        </Badge>
                        {pack.funding_decision ? (
                            <Badge variant="outline">
                                Inputs:{' '}
                                {pack.funding_decision.input_status_label}
                            </Badge>
                        ) : null}
                        <Button asChild size="sm">
                            <a href={urls.pdf} target="_blank" rel="noreferrer">
                                <Download
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Download PDF
                            </a>
                        </Button>
                    </div>
                </div>

                {pack.warnings.length > 0 ? (
                    <section className="rounded-md border bg-amber-50 p-3 text-sm text-amber-950">
                        <div className="font-medium">
                            Budget quality warnings
                        </div>
                        <div className="mt-2 space-y-1">
                            {pack.warnings.map((warning) => (
                                <p key={warning}>{warning}</p>
                            ))}
                        </div>
                    </section>
                ) : null}

                {pack.funding_decision ? (
                    <FundingDecisionPanel decision={pack.funding_decision} />
                ) : null}

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric
                        label="Break-even"
                        value={formatYear(summary.break_even_year)}
                        helper="Net profit before tax is zero or positive."
                        explanation={packExplanation(
                            pack,
                            'break_even_year',
                            'Break-even',
                            'Break-even is the first forecast year where net profit before tax is zero or positive.',
                            'Use this to judge whether the plan reaches a sustainable operating point within the forecast horizon.',
                            'Break-even timing affects funding needs, runway, and whether the business can support advisory recommendations.',
                        )}
                    />
                    <Metric
                        label="Profit year"
                        value={formatYear(summary.first_profitable_year)}
                        helper="Net profit after tax is positive."
                        explanation={packExplanation(
                            pack,
                            'first_profitable_year',
                            'Profit year',
                            'Profit year is the first forecast year where net profit after tax is positive.',
                            'Compare this with your launch plan and funding expectations.',
                            'After-tax profit is closer to what the business keeps after estimated tax, so it is a clearer founder outcome.',
                        )}
                    />
                    <Metric
                        label="Cash positive"
                        value={formatYear(summary.cash_flow_positive_year)}
                        helper="Cumulative cash becomes zero or positive."
                        explanation={packExplanation(
                            pack,
                            'cash_flow_positive_year',
                            'Cash positive',
                            'Cash positive is the first year where cumulative cash becomes zero or positive.',
                            'Use this to check when startup losses and funding movements are expected to be recovered.',
                            'A business can show profit before it has recovered enough cash, so this protects the runway story.',
                        )}
                    />
                    <Metric
                        label="After launch"
                        value={formatCurrency(summary.available_after_launch)}
                        helper="Funding left after one-off setup costs."
                        explanation={{
                            title: 'After planned one-off costs',
                            what: 'The estimated cash left after funding and opening cash are reduced by planned one-off costs.',
                            action: 'Check whether this amount is enough to cover early trading losses and timing gaps.',
                            why: 'A positive starting balance does not guarantee runway; it is only the cash position after planned one-off costs.',
                        }}
                    />
                </section>

                <BudgetCashChart
                    series={monthlySeries}
                    breakEvenMonth={summary.break_even_month}
                    runwayMonths={summary.runway_months}
                    runwayOpenEnded={summary.runway_open_ended}
                    title="Budget pack cash curve"
                    description="The cumulative cash line is the bank and investor view; revenue is scaled separately so the sales curve remains readable."
                    explanation={{
                        title: 'Budget pack cash curve',
                        what: pack.explanations.working_capital_timing
                            ? `${pack.explanations.working_capital_timing} The chart plots cumulative cash and revenue over the forecast months.`
                            : 'The chart plots cumulative cash and revenue over the forecast months.',
                        action: 'Look for the break-even and runway markers, then check whether cash stays above zero long enough for the plan to work.',
                        why: 'Cash timing is often the constraint that stops a good idea from becoming a viable business.',
                    }}
                />

                {pack.cash_story.length > 0 ? (
                    <section className="space-y-2 rounded-md border bg-background p-4">
                        <ExplainedSectionHeader
                            title="Cash story"
                            description="Plain-English interpretation of the monthly cash curve."
                            explanation={{
                                title: 'Cash story',
                                what: 'This reads the cash curve as a funding decision rather than only a chart.',
                                action: 'Resolve any negative cash point, missing cash-positive timing, or downside failure before external issue.',
                                why: 'Banks and investors need to understand how much cash is needed, when it is needed, and what changes under stress.',
                            }}
                        />
                        <div className="space-y-2 text-sm text-muted-foreground">
                            {pack.cash_story.map((line) => (
                                <p key={line}>{line}</p>
                            ))}
                        </div>
                    </section>
                ) : null}

                {pack.use_of_funds.length > 0 ? (
                    <UseOfFundsTable rows={pack.use_of_funds} />
                ) : null}

                {pack.fixed_costs.length > 0 ? (
                    <FixedCostsTable rows={pack.fixed_costs} />
                ) : null}

                <section className="space-y-3 rounded-md border bg-background p-4">
                    <ExplainedSectionHeader
                        title="Annual totals"
                        description="One-page view of the full forecast. Values are GST exclusive by default."
                        explanation={{
                            title: 'Annual totals',
                            what: pack.explanations.gst_exclusive
                                ? `${pack.explanations.gst_exclusive} This table summarizes the forecast by year.`
                                : 'This table summarizes the forecast by year and excludes GST by default.',
                            action: 'Use the annual view to compare revenue, margins, profit, tax, and ending cash across forecast years.',
                            why: 'Annual totals make the full plan easier to scan before drilling into monthly cash timing.',
                        }}
                        actions={
                            <Badge variant="outline">
                                {formatLabel(pack.status ?? 'draft')}
                            </Badge>
                        }
                    />
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[980px] border-collapse text-sm">
                            <thead>
                                <tr className="border-b bg-muted/30 text-left">
                                    <Th>Year</Th>
                                    <Th>Revenue</Th>
                                    <Th>Gross profit</Th>
                                    <Th>GP %</Th>
                                    <Th>Fixed costs</Th>
                                    <Th>NPBT</Th>
                                    <Th>NPBT %</Th>
                                    <Th>Tax</Th>
                                    <Th>NPAT</Th>
                                    <Th>Ending cash</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {pack.annual_totals.map((row) => (
                                    <tr key={row.year} className="border-b">
                                        <Td>Year {row.year}</Td>
                                        <Td>{formatCurrency(row.revenue)}</Td>
                                        <Td>
                                            {formatCurrency(row.gross_profit)}
                                        </Td>
                                        <Td>
                                            {formatPercent(
                                                row.gross_profit_percent,
                                            )}
                                        </Td>
                                        <Td>
                                            {formatCurrency(row.fixed_costs)}
                                        </Td>
                                        <Td>
                                            {formatCurrency(
                                                row.net_profit_before_tax,
                                            )}
                                        </Td>
                                        <Td>
                                            {formatPercent(
                                                row.net_profit_before_tax_percent,
                                            )}
                                        </Td>
                                        <Td>{formatCurrency(row.tax)}</Td>
                                        <Td>
                                            {formatCurrency(
                                                row.net_profit_after_tax,
                                            )}
                                        </Td>
                                        <Td>
                                            {formatCurrency(row.ending_cash)}
                                        </Td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="space-y-3 rounded-md border bg-background p-4">
                        <ExplainedSectionHeader
                            icon={Scale}
                            title="Assumption quality"
                            explanation={{
                                title: 'Assumption quality',
                                what: 'The key planning inputs, their basis, and the review needed before relying on them.',
                                action: 'Replace weak assumptions with quotes, evidence, advisor-reviewed estimates, or current reference data.',
                                why: 'A professional pack needs to show why the numbers can be trusted, not only what the numbers are.',
                            }}
                        />
                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full min-w-[760px] border-collapse text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/30 text-left">
                                        <Th>Assumption</Th>
                                        <Th>Value</Th>
                                        <Th>Basis</Th>
                                        <Th>Review note</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {pack.assumptions.map((row) => (
                                        <tr
                                            key={row.label}
                                            className="border-b last:border-b-0"
                                        >
                                            <Td>{row.label}</Td>
                                            <Td>{row.value}</Td>
                                            <Td>{row.basis ?? '-'}</Td>
                                            <Td>
                                                <span className="text-muted-foreground">
                                                    {row.review_note ?? '-'}
                                                </span>
                                            </Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="space-y-3 rounded-md border bg-background p-4">
                        <ExplainedSectionHeader
                            title="Scenario comparison"
                            explanation={packExplanation(
                                pack,
                                'automatic_scenarios',
                                'Scenario comparison',
                                'Scenario comparison reads base case and downside cases against break-even, cash-positive timing, lowest cash point, and funding need.',
                                'Use this to decide whether the plan still works when revenue softens or costs rise.',
                                'Sensitivity scenarios show whether the plan is robust or only works in the base case.',
                            )}
                        />
                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full min-w-[900px] border-collapse text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/30 text-left">
                                        <Th>Scenario</Th>
                                        <Th>Test</Th>
                                        <Th>Break-even</Th>
                                        <Th>Cash positive</Th>
                                        <Th>Lowest cash</Th>
                                        <Th>Cash need</Th>
                                        <Th>Implication</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {pack.scenarios.map((scenario) => (
                                        <tr
                                            key={scenario.key ?? scenario.name}
                                            className="border-b last:border-b-0"
                                        >
                                            <Td>{scenario.name}</Td>
                                            <Td>
                                                {scenario.sensitivity_label ??
                                                    formatLabel(scenario.type)}
                                            </Td>
                                            <Td>
                                                {formatYear(
                                                    scenario.summary
                                                        .break_even_year,
                                                )}
                                            </Td>
                                            <Td>
                                                {formatYear(
                                                    scenario.summary
                                                        .cash_flow_positive_year,
                                                )}
                                            </Td>
                                            <Td>
                                                {formatCashMonth(
                                                    scenario.lowest_cash,
                                                    scenario.lowest_cash_month,
                                                )}
                                            </Td>
                                            <Td>
                                                {formatCurrency(
                                                    scenario.additional_funding_needed,
                                                )}
                                            </Td>
                                            <Td>
                                                <span className="text-muted-foreground">
                                                    {scenario.implication ??
                                                        '-'}
                                                </span>
                                            </Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <section className="space-y-4">
                    <ExplainedSectionHeader
                        title="Monthly detail by year"
                        explanation={{
                            title: 'Monthly detail',
                            what: 'The month-by-month forecast used to build the annual totals and cash curve.',
                            action: 'Review months with negative cash flow, unusual cost spikes, or revenue ramps that look too optimistic.',
                            why: 'Monthly timing shows the cash pressure that annual totals can hide.',
                        }}
                    />
                    {pack.monthly_by_year.map((year) => (
                        <MonthlyTable
                            key={year.year}
                            year={year.year}
                            rows={year.rows}
                        />
                    ))}
                </section>
            </div>
        </>
    );
}

function Metric({
    label,
    value,
    helper,
    explanation,
}: {
    label: string;
    value: string;
    helper: string;
    explanation: Explanation;
}) {
    return (
        <ExplainedMetricCard
            label={label}
            value={value}
            helper={helper}
            explanation={explanation}
        />
    );
}

function FundingDecisionPanel({ decision }: { decision: FundingDecision }) {
    return (
        <section
            className={`space-y-4 rounded-md border p-4 ${decisionToneClass(
                decision.readiness_tone,
            )}`}
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="text-sm font-medium">
                        Funding decision view
                    </div>
                    <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                        {decision.headline}
                    </p>
                </div>
                <Badge
                    variant={
                        decision.readiness_tone === 'good'
                            ? 'secondary'
                            : 'outline'
                    }
                >
                    {decision.readiness_label}
                </Badge>
            </div>

            <dl className="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                <DecisionMetric
                    label="Declared funding position"
                    value={decision.funding_position_label}
                />
                <DecisionMetric
                    label="Lowest cash point"
                    value={formatCashMonth(
                        decision.lowest_cash,
                        decision.lowest_cash_month,
                    )}
                />
                <DecisionMetric
                    label="Required additional funding"
                    value={formatCurrency(decision.required_additional_funding)}
                />
                <DecisionMetric
                    label="Funding available"
                    value={formatCurrency(decision.available_funding)}
                />
                <DecisionMetric
                    label="Recommended funding target"
                    value={formatCurrency(decision.recommended_funding_target)}
                />
            </dl>

            {decision.risk_reasons.length > 0 ? (
                <div className="rounded-md border bg-background/70 p-3 text-sm">
                    <div className="font-medium">Review points</div>
                    <ul className="mt-2 list-disc space-y-1 pl-5 text-muted-foreground">
                        {decision.risk_reasons.map((reason) => (
                            <li key={reason}>{reason}</li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </section>
    );
}

function DecisionMetric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border bg-background p-3">
            <dt className="text-xs font-medium text-muted-foreground">
                {label}
            </dt>
            <dd className="mt-1 font-medium">{value}</dd>
        </div>
    );
}

function UseOfFundsTable({ rows }: { rows: UseOfFundsRow[] }) {
    return (
        <section className="space-y-3 rounded-md border bg-background p-4">
            <ExplainedSectionHeader
                title="Funding build-up"
                description="Funding target, cash already available, and one required-additional-funding figure."
                explanation={{
                    title: 'Funding build-up',
                    what: 'This converts the saved costs and funding sources into a lender-style funding need.',
                    action: 'Check whether planned one-off costs, operating cover, contingency, and available funding tell a defensible funding story.',
                    why: 'A funding pack should explain what money is needed for, not only whether the cash curve survives.',
                }}
            />
            <div className="overflow-x-auto rounded-md border">
                <table className="w-full min-w-[760px] border-collapse text-sm">
                    <thead>
                        <tr className="border-b bg-muted/30 text-left">
                            <Th>Item</Th>
                            <Th>Amount</Th>
                            <Th>Why it matters</Th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr
                                key={row.label}
                                className="border-b last:border-b-0"
                            >
                                <Td>{row.label}</Td>
                                <Td>
                                    {row.display_amount ??
                                        formatCurrency(row.amount)}
                                </Td>
                                <Td>
                                    <span className="text-muted-foreground">
                                        {row.note}
                                    </span>
                                </Td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function FixedCostsTable({ rows }: { rows: FixedCostRow[] }) {
    return (
        <section className="space-y-3 rounded-md border bg-background p-4">
            <ExplainedSectionHeader
                title="Monthly fixed-cost trace"
                description="Itemised costs used to calculate operating cover and the funding target."
                explanation={{
                    title: 'Monthly fixed-cost trace',
                    what: 'Each saved fixed-cost item is shown with its monthly amount, start month, and confidence level.',
                    action: 'Check large items against quotes, contracts, staffing plans, and the revenue ramp.',
                    why: 'A single unexplained overhead can materially change runway and the funding request.',
                }}
            />
            <div className="overflow-x-auto rounded-md border">
                <table className="w-full min-w-[680px] border-collapse text-sm">
                    <thead>
                        <tr className="border-b bg-muted/30 text-left">
                            <Th>Cost item</Th>
                            <Th>Monthly amount</Th>
                            <Th>Starts</Th>
                            <Th>Confidence</Th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr
                                key={`${row.label}-${row.start_month_label}`}
                                className="border-b last:border-b-0"
                            >
                                <Td>{row.label}</Td>
                                <Td>{formatCurrency(row.monthly_amount)}</Td>
                                <Td>{row.start_month_label}</Td>
                                <Td>{formatLabel(row.confidence)}</Td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function MonthlyTable({ year, rows }: { year: number; rows: MonthlyRow[] }) {
    const lowestCash = Math.min(...rows.map((row) => row.cumulative_cash));

    return (
        <section className="rounded-md border bg-background p-4">
            <h3 className="text-sm font-medium">Year {year}</h3>
            <div className="mt-3 overflow-x-auto">
                <table className="w-full min-w-[900px] border-collapse text-sm">
                    <thead>
                        <tr className="border-b bg-muted/30 text-left">
                            <Th>Month</Th>
                            <Th>Revenue</Th>
                            <Th>Variable costs</Th>
                            <Th>Gross profit</Th>
                            <Th>Fixed costs</Th>
                            <Th>Tax</Th>
                            <Th>NPAT</Th>
                            <Th>Cash flow</Th>
                            <Th>Cumulative cash</Th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr
                                key={row.month}
                                className={`border-b ${
                                    row.cumulative_cash === lowestCash
                                        ? 'bg-red-50'
                                        : ''
                                }`}
                            >
                                <Td>
                                    Month {row.month_in_year}
                                    {row.cumulative_cash === lowestCash ? (
                                        <span className="ml-2 text-xs font-medium text-red-700">
                                            Lowest cash
                                        </span>
                                    ) : null}
                                </Td>
                                <Td>{formatCurrency(row.revenue)}</Td>
                                <Td>{formatCurrency(row.variable_costs)}</Td>
                                <Td>{formatCurrency(row.gross_profit)}</Td>
                                <Td>{formatCurrency(row.fixed_costs)}</Td>
                                <Td>{formatCurrency(row.tax)}</Td>
                                <Td>
                                    {formatCurrency(row.net_profit_after_tax)}
                                </Td>
                                <Td>{formatCurrency(row.net_cash_flow)}</Td>
                                <Td>{formatCurrency(row.cumulative_cash)}</Td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function Th({ children }: { children: string }) {
    return <th className="px-3 py-2 font-medium">{children}</th>;
}

function Td({ children }: { children: ReactNode }) {
    return <td className="px-3 py-2">{children}</td>;
}

function formatCurrency(value: number | null | undefined): string {
    return formatNzdCurrency(value);
}

function formatPercent(value: number | null | undefined): string {
    return value === null || value === undefined ? '-' : `${value.toFixed(1)}%`;
}

function formatYear(value: number | null | undefined): string {
    return value ? `Year ${value}` : 'Not reached';
}

function formatCashMonth(
    value: number | null | undefined,
    month: number | null | undefined,
): string {
    if (value === null || value === undefined) {
        return 'Not calculated';
    }

    return `${formatCurrency(value)} in ${month ? `Month ${month}` : 'unknown month'}`;
}

function decisionToneClass(tone: FundingDecision['readiness_tone']): string {
    if (tone === 'good') {
        return 'bg-emerald-50 text-emerald-950';
    }

    if (tone === 'medium') {
        return 'bg-amber-50 text-amber-950';
    }

    return 'bg-red-50 text-red-950';
}

function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function packExplanation(
    pack: PackPayload,
    key: string,
    title: string,
    fallbackWhat: string,
    action: string,
    why: string,
): Explanation {
    return {
        title,
        what: pack.explanations[key] ?? fallbackWhat,
        action,
        why,
    };
}

BudgetPack.layout = {
    breadcrumbs: [
        {
            title: 'Budget pack',
            href: '/portal/entrepreneur/plan/budget-pack',
        },
    ],
};
