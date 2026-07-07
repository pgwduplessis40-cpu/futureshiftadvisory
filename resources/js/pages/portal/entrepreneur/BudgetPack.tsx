import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, FileText, Scale } from 'lucide-react';
import type { ReactNode } from 'react';
import { BudgetCashChart } from '@/components/budget-cash-chart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

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
    summary: {
        break_even_year?: number | null;
        first_profitable_year?: number | null;
        cash_flow_positive_year?: number | null;
    };
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
    assumptions: {
        label: string;
        value: string;
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

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric
                        label="Break-even"
                        value={formatYear(summary.break_even_year)}
                        helper="Net profit before tax is zero or positive."
                    />
                    <Metric
                        label="Profit year"
                        value={formatYear(summary.first_profitable_year)}
                        helper="Net profit after tax is positive."
                    />
                    <Metric
                        label="Cash positive"
                        value={formatYear(summary.cash_flow_positive_year)}
                        helper="Cumulative cash becomes zero or positive."
                    />
                    <Metric
                        label="After launch"
                        value={formatCurrency(summary.available_after_launch)}
                        helper="Funding left after one-off setup costs."
                    />
                </section>

                <BudgetCashChart
                    series={monthlySeries}
                    breakEvenMonth={summary.break_even_month}
                    runwayMonths={summary.runway_months}
                    runwayOpenEnded={summary.runway_open_ended}
                    title="Budget pack cash curve"
                    description="The cumulative cash line is the bank and investor view; revenue is scaled separately so the sales curve remains readable."
                />

                <section className="space-y-3 rounded-md border bg-background p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-medium">
                                Annual totals
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                One-page view of the full forecast. Values are
                                GST exclusive by default.
                            </p>
                        </div>
                        <Badge variant="outline">
                            {formatLabel(pack.status ?? 'draft')}
                        </Badge>
                    </div>
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
                        <div className="flex items-center gap-2 text-sm font-medium">
                            <Scale className="size-4" aria-hidden="true" />
                            Assumptions
                        </div>
                        <div className="divide-y rounded-md border text-sm">
                            {pack.assumptions.map((row) => (
                                <div
                                    key={row.label}
                                    className="grid grid-cols-[minmax(0,1fr)_120px] gap-3 p-3"
                                >
                                    <span className="text-muted-foreground">
                                        {row.label}
                                    </span>
                                    <span className="text-right font-medium">
                                        {row.value}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="space-y-3 rounded-md border bg-background p-4">
                        <h2 className="text-sm font-medium">
                            Scenario summary
                        </h2>
                        <div className="divide-y rounded-md border text-sm">
                            {pack.scenarios.map((scenario) => (
                                <div
                                    key={scenario.key ?? scenario.name}
                                    className="grid gap-2 p-3 sm:grid-cols-4"
                                >
                                    <div className="font-medium">
                                        {scenario.name}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {formatLabel(scenario.type)}
                                    </div>
                                    <div>
                                        Break-even:{' '}
                                        {formatYear(
                                            scenario.summary.break_even_year,
                                        )}
                                    </div>
                                    <div>
                                        Cash positive:{' '}
                                        {formatYear(
                                            scenario.summary
                                                .cash_flow_positive_year,
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>

                <section className="space-y-4">
                    <h2 className="text-sm font-medium">
                        Monthly detail by year
                    </h2>
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
}: {
    label: string;
    value: string;
    helper: string;
}) {
    return (
        <div className="rounded-md border bg-background p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-sm font-medium">{value}</div>
            <div className="mt-1 text-xs text-muted-foreground">{helper}</div>
        </div>
    );
}

function MonthlyTable({ year, rows }: { year: number; rows: MonthlyRow[] }) {
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
                            <tr key={row.month} className="border-b">
                                <Td>Month {row.month_in_year}</Td>
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
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'NZD',
        maximumFractionDigits: 0,
    }).format(value ?? 0);
}

function formatPercent(value: number | null | undefined): string {
    return value === null || value === undefined ? '-' : `${value.toFixed(1)}%`;
}

function formatYear(value: number | null | undefined): string {
    return value ? `Year ${value}` : 'Not reached';
}

function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

BudgetPack.layout = {
    breadcrumbs: [
        {
            title: 'Budget pack',
            href: '/portal/entrepreneur/plan/budget-pack',
        },
    ],
};
