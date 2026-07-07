import { InsightHoverCard } from '@/components/insight/InsightHoverCard';
import type { InsightHoverCardRow } from '@/components/insight/InsightHoverCard';
import { Badge } from '@/components/ui/badge';

export type WaterfallStep = {
    key: string;
    label: string;
    kind: 'absolute' | 'increase' | 'decrease' | 'total';
    value: number;
    start: number;
    end: number;
    recommendation_type?: 'improvement' | 'risk_mitigation';
    is_remainder?: boolean;
    remainder_count?: number | null;
    annual_benefit?: number | null;
    annual_expected_cost?: number | null;
    duration_years?: number | null;
    discount_rate?: number | null;
    discount_method?: string | null;
    pv_calculation_id?: string | null;
    source_finding_id?: string | null;
    drill_url?: string | null;
};

type Props = {
    steps: WaterfallStep[];
};

export function WaterfallChart({ steps }: Props) {
    const max = Math.max(
        1,
        ...steps.map((step) => Math.max(step.start, step.end, step.value)),
    );

    return (
        <div className="space-y-3">
            {steps.map((step) => {
                const left = `${(Math.max(0, step.start) / max) * 100}%`;
                const width = `${(Math.abs(step.end - step.start || step.value) / max) * 100}%`;

                return (
                    <InsightHoverCard
                        key={step.key}
                        title={step.label}
                        rows={waterfallRows(step)}
                        drillHref={step.drill_url ?? undefined}
                        drillAriaLabel={`Open source finding for ${step.label}`}
                        footer={
                            step.is_remainder
                                ? 'Aggregate remainder'
                                : step.pv_calculation_id
                                  ? `PV: ${step.pv_calculation_id}`
                                  : undefined
                        }
                        contentClassName="min-w-72"
                    >
                        <button
                            type="button"
                            className="block w-full space-y-1.5 rounded-md text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            <span className="flex items-center justify-between gap-3 text-xs">
                                <span className="font-medium">
                                    {step.label}
                                </span>
                                <span className="tabular-nums">
                                    {formatCurrency(step.value)}
                                </span>
                            </span>
                            <span className="relative block h-8 overflow-hidden rounded-md bg-muted">
                                <span
                                    className={barClass(step.kind)}
                                    style={{ left, width }}
                                />
                            </span>
                        </button>
                    </InsightHoverCard>
                );
            })}
        </div>
    );
}

export function PvSummaryBadges({
    current,
    target,
    targetRange,
}: {
    current: number;
    target: number;
    targetRange?: {
        low: number;
        high: number;
    };
}) {
    return (
        <div className="flex flex-wrap gap-2">
            <Badge variant="outline">{formatCurrency(current)} current</Badge>
            <Badge variant="secondary">
                {formatCurrency(target)} modelled upside
            </Badge>
            {targetRange ? (
                <Badge variant="outline">
                    {formatCurrency(targetRange.low)} -{' '}
                    {formatCurrency(targetRange.high)} range
                </Badge>
            ) : null}
        </div>
    );
}

function barClass(kind: WaterfallStep['kind']): string {
    const base = 'absolute top-1 h-6 rounded-sm';

    if (kind === 'total') {
        return `${base} bg-slate-900 dark:bg-slate-100`;
    }

    if (kind === 'decrease') {
        return `${base} bg-rose-500`;
    }

    if (kind === 'increase') {
        return `${base} bg-emerald-500`;
    }

    return `${base} bg-sky-500`;
}

function waterfallRows(step: WaterfallStep): InsightHoverCardRow[] {
    const rows: InsightHoverCardRow[] = [
        {
            label: 'Present value',
            value: formatCurrency(step.value),
            description:
                'The value today after spreading the benefit or risk reduction over time and applying the discount rate.',
        },
    ];

    if (step.remainder_count) {
        rows.push({
            label: 'Items',
            value: step.remainder_count,
            description:
                'Smaller PV movements grouped together so the waterfall stays readable.',
        });
    }

    if (typeof step.annual_benefit === 'number') {
        rows.push({
            label: 'Annual benefit',
            value: formatCurrency(step.annual_benefit),
            description:
                'The estimated yearly improvement before discounting. It is converted into present value over the benefit period.',
            tone: 'positive',
        });
    }

    if (typeof step.annual_expected_cost === 'number') {
        rows.push({
            label: 'Annual risk value',
            value: formatCurrency(step.annual_expected_cost),
            description:
                'The expected yearly cost exposure that could be reduced or avoided if the risk is addressed.',
            tone: 'positive',
        });
    }

    if (typeof step.duration_years === 'number') {
        rows.push({
            label: 'Years',
            value: step.duration_years,
            description:
                'The period over which the annual benefit or risk reduction is expected to apply.',
        });
    }

    if (typeof step.discount_rate === 'number') {
        rows.push({
            label: 'Discount rate',
            value: formatPercent(step.discount_rate),
            description:
                "The annual rate used to convert future value into today's value for comparison.",
        });
    }

    if (step.discount_method) {
        rows.push({
            label: 'Method',
            value: formatLabel(step.discount_method),
            description:
                'The source of the PV assumptions, such as advisor configuration or reference-data defaults.',
        });
    }

    return rows;
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'NZD',
        maximumFractionDigits: 0,
    }).format(value);
}

function formatPercent(value: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'percent',
        maximumFractionDigits: 2,
    }).format(value);
}

function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}
