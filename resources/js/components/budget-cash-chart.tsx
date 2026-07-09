import { Explainer, type Explanation } from '@/components/explainer';

export type BudgetChartPoint = {
    month: number;
    month_in_year?: number;
    revenue: number;
    cumulative_cash: number;
};

type BudgetCashChartProps = {
    series: BudgetChartPoint[];
    breakEvenMonth?: number | null;
    runwayMonths?: number | null;
    runwayOpenEnded?: boolean;
    title?: string;
    description?: string;
    explanation?: Explanation;
};

export function BudgetCashChart({
    series,
    breakEvenMonth,
    runwayMonths,
    runwayOpenEnded = false,
    title = 'Revenue and cumulative cash',
    description = 'Monthly revenue uses the right axis; cumulative cash uses the left axis.',
    explanation,
}: BudgetCashChartProps) {
    const points = series
        .filter(
            (point) =>
                Number.isFinite(point.month) &&
                Number.isFinite(point.revenue) &&
                Number.isFinite(point.cumulative_cash),
        )
        .slice(0, 60);

    if (points.length === 0) {
        return null;
    }

    const width = 720;
    const height = 260;
    const top = 22;
    const right = 72;
    const bottom = 42;
    const left = 68;
    const plotWidth = width - left - right;
    const plotHeight = height - top - bottom;
    const cashValues = points.map((point) => point.cumulative_cash);
    const cashMin = Math.min(0, ...cashValues);
    const cashMax = Math.max(0, ...cashValues);
    const cashRange = cashMax === cashMin ? 1 : cashMax - cashMin;
    const revenueMax = Math.max(1, ...points.map((point) => point.revenue));
    const xForIndex = (index: number) =>
        points.length === 1
            ? left + plotWidth / 2
            : left + (index / (points.length - 1)) * plotWidth;
    const cashY = (value: number) =>
        top + ((cashMax - value) / cashRange) * plotHeight;
    const revenueY = (value: number) =>
        top + (1 - value / revenueMax) * plotHeight;
    const cashPoints = points
        .map(
            (point, index) =>
                `${xForIndex(index)},${cashY(point.cumulative_cash)}`,
        )
        .join(' ');
    const revenuePoints = points
        .map((point, index) => `${xForIndex(index)},${revenueY(point.revenue)}`)
        .join(' ');
    const zeroY = cashY(0);
    const xTicks = tickIndexes(points.length);
    const cashTicks = valueTicks(cashMin, cashMax);
    const revenueTicks = valueTicks(0, revenueMax);
    const markers = [
        markerForMonth(points, breakEvenMonth, 'Break-even'),
        markerForRunway(points, runwayMonths, runwayOpenEnded),
    ].filter((marker): marker is ChartMarker => marker !== null);

    return (
        <section className="rounded-md border bg-background p-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <h3 className="text-sm font-medium">{title}</h3>
                        {explanation ? (
                            <Explainer explanation={explanation} />
                        ) : null}
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                    <LegendDot color="var(--chart-1)" label="Cumulative cash" />
                    <LegendDot color="var(--chart-4)" label="Revenue" />
                </div>
            </div>

            <svg
                role="img"
                aria-label={`${title}: cumulative cash and revenue over ${points.length} months`}
                viewBox={`0 0 ${width} ${height}`}
                className="mt-3 h-72 w-full overflow-visible text-foreground"
            >
                <line
                    x1={left}
                    x2={left + plotWidth}
                    y1={zeroY}
                    y2={zeroY}
                    stroke="currentColor"
                    strokeOpacity="0.28"
                    strokeDasharray="4 4"
                />
                {cashTicks.map((value) => (
                    <g key={`cash-${value}`}>
                        <line
                            x1={left}
                            x2={left + plotWidth}
                            y1={cashY(value)}
                            y2={cashY(value)}
                            stroke="currentColor"
                            strokeOpacity="0.08"
                        />
                        <text
                            x={left - 10}
                            y={cashY(value) + 4}
                            textAnchor="end"
                            className="fill-muted-foreground text-[11px]"
                        >
                            {moneyShort(value)}
                        </text>
                    </g>
                ))}
                {revenueTicks.map((value) => (
                    <text
                        key={`revenue-${value}`}
                        x={left + plotWidth + 10}
                        y={revenueY(value) + 4}
                        className="fill-muted-foreground text-[11px]"
                    >
                        {moneyShort(value)}
                    </text>
                ))}
                {xTicks.map((index) => (
                    <g key={`x-${index}`}>
                        <line
                            x1={xForIndex(index)}
                            x2={xForIndex(index)}
                            y1={top}
                            y2={top + plotHeight}
                            stroke="currentColor"
                            strokeOpacity="0.06"
                        />
                        <text
                            x={xForIndex(index)}
                            y={height - 14}
                            textAnchor="middle"
                            className="fill-muted-foreground text-[11px]"
                        >
                            M{points[index]?.month ?? index + 1}
                        </text>
                    </g>
                ))}
                <polyline
                    points={cashPoints}
                    fill="none"
                    stroke="var(--chart-1)"
                    strokeWidth="3"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
                <polyline
                    points={revenuePoints}
                    fill="none"
                    stroke="var(--chart-4)"
                    strokeWidth="3"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
                {markers.map((marker, markerIndex) => {
                    const markerX = xForIndex(marker.index);

                    return (
                        <g key={marker.label}>
                            <line
                                x1={markerX}
                                x2={markerX}
                                y1={top}
                                y2={top + plotHeight}
                                stroke="currentColor"
                                strokeOpacity="0.36"
                                strokeDasharray="3 5"
                            />
                            <text
                                x={markerX}
                                y={top + 13 + markerIndex * 15}
                                textAnchor="middle"
                                className="fill-foreground text-[11px] font-medium"
                            >
                                {marker.label}
                            </text>
                        </g>
                    );
                })}
                <text
                    x={left}
                    y={height - 1}
                    className="fill-muted-foreground text-[11px]"
                >
                    Cash axis
                </text>
                <text
                    x={left + plotWidth}
                    y={height - 1}
                    textAnchor="end"
                    className="fill-muted-foreground text-[11px]"
                >
                    Revenue axis
                </text>
            </svg>
        </section>
    );
}

function LegendDot({ color, label }: { color: string; label: string }) {
    return (
        <span className="inline-flex items-center gap-1">
            <span
                className="size-2 rounded-full"
                style={{ backgroundColor: color }}
            />
            {label}
        </span>
    );
}

type ChartMarker = {
    index: number;
    label: string;
};

function markerForMonth(
    points: BudgetChartPoint[],
    month: number | null | undefined,
    label: string,
): ChartMarker | null {
    if (!Number.isFinite(month) || month === null || month === undefined) {
        return null;
    }

    const index = points.findIndex((point) => point.month >= month);

    if (index < 0) {
        return null;
    }

    return {
        index,
        label: `${label} M${month}`,
    };
}

function markerForRunway(
    points: BudgetChartPoint[],
    runwayMonths: number | null | undefined,
    openEnded: boolean,
): ChartMarker | null {
    if (openEnded) {
        return {
            index: points.length - 1,
            label: `Runway > M${points[points.length - 1]?.month ?? points.length}`,
        };
    }

    return markerForMonth(points, runwayMonths, 'Runway');
}

function tickIndexes(length: number): number[] {
    if (length <= 1) {
        return [0];
    }

    return uniqueSorted([
        0,
        Math.floor((length - 1) * 0.25),
        Math.floor((length - 1) * 0.5),
        Math.floor((length - 1) * 0.75),
        length - 1,
    ]);
}

function valueTicks(min: number, max: number): number[] {
    if (min === max) {
        return [min];
    }

    return uniqueSorted(
        [min, min + (max - min) / 2, max].map((value) => Math.round(value)),
    );
}

function uniqueSorted(values: number[]): number[] {
    return [...new Set(values)].sort((left, right) => left - right);
}

function moneyShort(value: number): string {
    const sign = value < 0 ? '-' : '';
    const absolute = Math.abs(value);

    if (absolute >= 1_000_000) {
        return `${sign}$${(absolute / 1_000_000).toFixed(1)}m`;
    }

    if (absolute >= 1_000) {
        return `${sign}$${Math.round(absolute / 1_000)}k`;
    }

    return `${sign}$${Math.round(absolute)}`;
}
