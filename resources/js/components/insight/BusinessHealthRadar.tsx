import { InsightHoverCard } from '@/components/insight/InsightHoverCard';
import type { InsightHoverCardRow } from '@/components/insight/InsightHoverCard';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type BusinessHealthRadarAxis = {
    dimension: string;
    label: string;
    score: number | null;
    state: string;
    message: string;
    trend: {
        delta: number;
        direction: 'up' | 'down' | 'flat';
    } | null;
    top_finding: {
        id: string;
        module: string | null;
        lens: string;
        severity: string;
        title: string;
        body: string;
        attributions: Attribution[];
        created_at: string | null;
    } | null;
    contributing_finding_ids: string[];
    module_run_states: Record<string, ModuleRunState>;
    drill_url: string | null;
};

export type BusinessHealthRadarPayload = {
    captured_at: string | null;
    axes: BusinessHealthRadarAxis[];
};

export type ModuleRunState = {
    state: string;
    scoring_run_id: string | null;
    scoring_completed_at: string | null;
    latest_run_id: string | null;
    latest_run_status: string | null;
    latest_run_at: string | null;
    stale: boolean;
};

type Attribution = {
    claim?: string;
    source_reference?: string;
    [key: string]: unknown;
};

type Point = {
    x: number;
    y: number;
};

const SIZE = 240;
const CENTER = 120;
const RADIUS = 86;

export function BusinessHealthRadar({
    payload,
}: {
    payload: BusinessHealthRadarPayload;
}) {
    const axes = payload.axes;
    const endpoints = axes.map((axis, index) => ({
        axis,
        point: polarPoint(index, 100, axes.length),
        valuePoint:
            typeof axis.score === 'number'
                ? polarPoint(index, axis.score, axes.length)
                : null,
    }));
    const scored = endpoints.filter((entry) => entry.valuePoint !== null);
    const canDrawPolygon = scored.length === axes.length && scored.length >= 3;

    return (
        <div className="grid gap-5 lg:grid-cols-[minmax(0,320px)_1fr]">
            <div className="relative mx-auto aspect-square w-full max-w-80">
                <svg
                    viewBox={`0 0 ${SIZE} ${SIZE}`}
                    className="h-full w-full overflow-visible"
                    role="img"
                    aria-label="Business health radar"
                >
                    {[20, 40, 60, 80, 100].map((level) => (
                        <polygon
                            key={level}
                            points={axes
                                .map((_, index) =>
                                    pointString(
                                        polarPoint(index, level, axes.length),
                                    ),
                                )
                                .join(' ')}
                            className="fill-none stroke-border"
                            strokeWidth={level === 100 ? 1.5 : 1}
                        />
                    ))}
                    {endpoints.map(({ axis, point }) => (
                        <line
                            key={axis.dimension}
                            x1={CENTER}
                            y1={CENTER}
                            x2={point.x}
                            y2={point.y}
                            className="stroke-muted-foreground/40"
                            strokeWidth={1}
                        />
                    ))}
                    {canDrawPolygon && (
                        <polygon
                            points={scored
                                .map((entry) => pointString(entry.valuePoint!))
                                .join(' ')}
                            className="fill-emerald-500/20 stroke-emerald-600"
                            strokeWidth={2}
                        />
                    )}
                    {!canDrawPolygon &&
                        scored.map((entry) => (
                            <line
                                key={`score-${entry.axis.dimension}`}
                                x1={CENTER}
                                y1={CENTER}
                                x2={entry.valuePoint!.x}
                                y2={entry.valuePoint!.y}
                                className="stroke-emerald-600"
                                strokeWidth={2}
                            />
                        ))}
                    <circle
                        cx={CENTER}
                        cy={CENTER}
                        r={2}
                        className="fill-muted-foreground"
                    />
                </svg>

                {endpoints.map(({ axis, point, valuePoint }) => {
                    const triggerPoint = valuePoint ?? point;

                    return (
                        <InsightHoverCard
                            key={axis.dimension}
                            title={axis.label}
                            rows={axisRows(axis)}
                            drillHref={axis.drill_url ?? undefined}
                            drillAriaLabel={`Open ${axis.label} health findings`}
                            footer={axis.message}
                            className="w-80"
                            contentClassName="w-full"
                        >
                            <button
                                type="button"
                                aria-label={`${axis.label} health ${axis.score === null ? stateLabel(axis.state) : `${axis.score} out of 100`}`}
                                className={cn(
                                    'absolute h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 bg-background shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                                    axis.score === null
                                        ? 'border-dashed border-muted-foreground'
                                        : scorePointClass(axis.score),
                                )}
                                style={{
                                    left: `${(triggerPoint.x / SIZE) * 100}%`,
                                    top: `${(triggerPoint.y / SIZE) * 100}%`,
                                }}
                            />
                        </InsightHoverCard>
                    );
                })}
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                {axes.map((axis) => (
                    <div
                        key={axis.dimension}
                        className="rounded-md border p-3 text-sm"
                    >
                        <div className="flex items-center justify-between gap-3">
                            <div className="font-medium">{axis.label}</div>
                            {axis.score === null ? (
                                <Badge variant="outline">
                                    {stateLabel(axis.state)}
                                </Badge>
                            ) : (
                                <Badge variant={scoreBadgeVariant(axis.score)}>
                                    {axis.score}
                                </Badge>
                            )}
                        </div>
                        <p className="mt-2 line-clamp-2 text-xs text-muted-foreground">
                            {axis.score === null
                                ? axis.message
                                : axis.top_finding?.title}
                        </p>
                    </div>
                ))}
            </div>
        </div>
    );
}

function axisRows(axis: BusinessHealthRadarAxis): InsightHoverCardRow[] {
    const rows: InsightHoverCardRow[] = [];

    if (axis.score === null) {
        rows.push({
            label: 'State',
            value: stateLabel(axis.state),
            tone: 'muted',
        });

        return rows;
    }

    rows.push({
        label: 'Score',
        value: `${axis.score}/100`,
        tone:
            axis.score >= 75
                ? 'positive'
                : axis.score < 50
                  ? 'negative'
                  : 'default',
    });

    if (axis.top_finding) {
        rows.push({
            label: 'Top finding',
            value: (
                <span className="block max-w-36 truncate">
                    {axis.top_finding.title}
                </span>
            ),
            tone: severityTone(axis.top_finding.severity),
        });
    }

    rows.push({
        label: 'Trend',
        value: trendLabel(axis.trend),
        tone:
            axis.trend?.direction === 'up'
                ? 'positive'
                : axis.trend?.direction === 'down'
                  ? 'negative'
                  : 'muted',
    });

    rows.push({
        label: 'Findings',
        value: axis.contributing_finding_ids.length,
    });

    return rows;
}

function polarPoint(index: number, score: number, count: number): Point {
    const angle = -Math.PI / 2 + (index * 2 * Math.PI) / Math.max(1, count);
    const radius = (Math.max(0, Math.min(100, score)) / 100) * RADIUS;

    return {
        x: CENTER + Math.cos(angle) * radius,
        y: CENTER + Math.sin(angle) * radius,
    };
}

function pointString(point: Point): string {
    return `${point.x.toFixed(2)},${point.y.toFixed(2)}`;
}

function stateLabel(state: string): string {
    return state
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function trendLabel(trend: BusinessHealthRadarAxis['trend']): string {
    if (trend === null) {
        return 'No prior batch';
    }

    if (trend.delta === 0) {
        return 'Flat';
    }

    return `${trend.delta > 0 ? '+' : ''}${trend.delta}`;
}

function severityTone(severity: string): InsightHoverCardRow['tone'] {
    return ['critical', 'high'].includes(severity) ? 'negative' : 'default';
}

function scoreBadgeVariant(
    score: number,
): 'default' | 'secondary' | 'destructive' {
    if (score < 50) {
        return 'destructive';
    }

    if (score < 75) {
        return 'secondary';
    }

    return 'default';
}

function scorePointClass(score: number): string {
    if (score < 50) {
        return 'border-rose-600';
    }

    if (score < 75) {
        return 'border-amber-500';
    }

    return 'border-emerald-600';
}
