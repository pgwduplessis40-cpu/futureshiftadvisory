import {
    Ban,
    CheckCircle2,
    PauseCircle,
    RotateCcw,
    ShieldAlert,
} from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    formatCurrency as formatLocalizedCurrency,
    formatNumber,
    formatNzdCurrency,
    formatNzDate,
    formatNzMonth,
    formatPercentage,
} from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type {
    StandardAdvisorySummary,
    WellbeingPoint,
} from './client-detail-types';
export function Metric({
    label,
    value,
    hint,
    children,
}: {
    label: string;
    value?: string;
    hint?: string | null;
    children?: ReactNode;
}) {
    return (
        <div className="rounded-md border p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-2 text-sm font-medium">{children ?? value}</div>
            {hint && (
                <div className="mt-1 text-xs text-muted-foreground">{hint}</div>
            )}
        </div>
    );
}

export function Detail({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="grid grid-cols-[120px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value || '-'}</dd>
        </div>
    );
}

export function WellbeingTrend({ points }: { points: WellbeingPoint[] }) {
    if (points.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No wellbeing check-ins yet.
            </p>
        );
    }

    return (
        <div className="space-y-3">
            {points.map((point) => (
                <article key={point.id} className="grid gap-2 text-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="font-medium">
                            {formatMonth(point.period_start)}
                        </div>
                        <div className="text-muted-foreground">
                            {point.submitted_by ?? 'Client'}
                        </div>
                    </div>
                    <ScoreBar
                        label="Business confidence"
                        value={point.business_confidence}
                    />
                    <ScoreBar
                        label="Personal coping"
                        value={point.personal_coping}
                    />
                    {point.notes && (
                        <p className="rounded-md bg-muted px-3 py-2 text-muted-foreground">
                            {point.notes}
                        </p>
                    )}
                </article>
            ))}
        </div>
    );
}

export function ScoreBar({ label, value }: { label: string; value: number }) {
    const width = `${Math.max(0, Math.min(100, (value / 5) * 100))}%`;

    return (
        <div className="grid gap-1">
            <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span>{label}</span>
                <span>{value}/5</span>
            </div>
            <div className="h-2 rounded-full bg-muted">
                <div
                    className="h-2 rounded-full bg-[var(--fs-admiralty)]"
                    style={{ width }}
                />
            </div>
        </div>
    );
}

export function formatMonth(value: string | null) {
    if (!value) {
        return 'Current period';
    }

    return formatNzMonth(value);
}

export function formatDate(value: string | null) {
    if (!value) {
        return '-';
    }

    return formatNzDate(value);
}

export function formatLabel(value: string) {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

export function WebsiteAuditSignal({
    label,
    complete,
}: {
    label: string;
    complete: boolean;
}) {
    const Icon = complete ? CheckCircle2 : ShieldAlert;

    return (
        <div className="flex items-center gap-2 rounded-md border bg-background px-3 py-2 text-xs">
            <Icon
                className={
                    complete
                        ? 'size-4 text-emerald-700'
                        : 'size-4 text-muted-foreground'
                }
                aria-hidden="true"
            />
            <span className="font-medium">{label}</span>
            <span className="ml-auto text-muted-foreground">
                {complete ? 'Ready' : 'Needed'}
            </span>
        </div>
    );
}

export function AnalysisReadinessIndicator({
    readiness,
}: {
    readiness: StandardAdvisorySummary['analysis_readiness'];
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span className="inline-flex h-8 items-center gap-2 rounded-md border px-2 text-sm">
                    <span
                        className="grid gap-0.5 rounded-full bg-muted p-1"
                        aria-hidden="true"
                    >
                        <span
                            className={cn(
                                'size-2 rounded-full bg-red-500',
                                readiness.level === 'red'
                                    ? 'opacity-100 ring-2 ring-red-300'
                                    : 'opacity-25',
                            )}
                        />
                        <span
                            className={cn(
                                'size-2 rounded-full bg-amber-400',
                                readiness.level === 'amber'
                                    ? 'opacity-100 ring-2 ring-amber-200'
                                    : 'opacity-25',
                            )}
                        />
                        <span
                            className={cn(
                                'size-2 rounded-full bg-emerald-500',
                                readiness.level === 'green'
                                    ? 'opacity-100 ring-2 ring-emerald-200'
                                    : 'opacity-25',
                            )}
                        />
                    </span>
                    <span className="sr-only lg:not-sr-only">
                        Analysis readiness: {readiness.label}
                    </span>
                </span>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {readiness.description}
            </TooltipContent>
        </Tooltip>
    );
}

export function analysisRunButtonClass(
    level: StandardAdvisorySummary['analysis_readiness']['level'],
): string {
    return {
        red: 'border-red-700 bg-red-600 text-white hover:bg-red-700 disabled:border-red-700 disabled:bg-red-600 disabled:text-white disabled:opacity-70',
        amber: 'border-amber-600 bg-amber-500 text-white hover:bg-amber-600 disabled:border-amber-600 disabled:bg-amber-500 disabled:text-white disabled:opacity-70',
        green: 'border-emerald-700 bg-emerald-600 text-white hover:bg-emerald-700 disabled:border-emerald-700 disabled:bg-emerald-600 disabled:text-white disabled:opacity-70',
    }[level];
}

export function standardAdvisoryStatusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'client_report_released') {
        return 'secondary';
    }

    if (status === 'verification_blocked') {
        return 'destructive';
    }

    return 'outline';
}

export function formatMetric(value: unknown) {
    if (typeof value !== 'number') {
        return String(value ?? '-');
    }

    if (Math.abs(value) <= 1) {
        return formatPercentage(value);
    }

    return formatNumber(value, { maximumFractionDigits: 2 });
}

export function formatCurrency(value: number) {
    return formatNzdCurrency(value);
}

export function formatPercent(value: number) {
    return formatPercentage(value / 100);
}

export function formatMoney(value: number, currency: string) {
    return formatLocalizedCurrency(value, currency, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export function formatBytes(value: number | null) {
    if (!value) {
        return '-';
    }

    if (value < 1024) {
        return `${value} B`;
    }

    return `${(value / 1024).toFixed(1)} KB`;
}

export function nullableNumber(value: string): number | null {
    if (value.trim() === '') {
        return null;
    }

    return Number(value);
}

export function truncate(value: string, limit: number) {
    if (value.length <= limit) {
        return value;
    }

    return `${value.slice(0, Math.max(0, limit - 1))}...`;
}

export function statusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'suspended') {
        return 'destructive';
    }

    if (status === 'active') {
        return 'secondary';
    }

    return 'outline';
}

export function goalStatusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'active' || status === 'achieved') {
        return 'secondary';
    }

    if (status === 'abandoned') {
        return 'destructive';
    }

    return 'outline';
}

export function milestoneStatusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'completed') {
        return 'secondary';
    }

    if (status === 'blocked') {
        return 'destructive';
    }

    return 'outline';
}

export function proofStatusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'verified') {
        return 'secondary';
    }

    if (status === 'flagged') {
        return 'destructive';
    }

    return 'outline';
}

export function proposalStatusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'expired') {
        return 'destructive';
    }

    if (status === 'released' || status === 'renewed') {
        return 'secondary';
    }

    return 'outline';
}

export function paymentStatusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'failed') {
        return 'destructive';
    }

    if (status === 'retrying' || status === 'succeeded') {
        return 'secondary';
    }

    return 'outline';
}

export function severityVariant(
    severity: string,
): 'secondary' | 'destructive' | 'outline' {
    if (severity === 'critical' || severity === 'high') {
        return 'destructive';
    }

    if (severity === 'medium') {
        return 'secondary';
    }

    return 'outline';
}

export function lifecycleActions(status: string) {
    if (status === 'active') {
        return [
            { status: 'paused', label: 'Pause' },
            { status: 'suspended', label: 'Suspend' },
            { status: 'offboarded', label: 'Mark offboarded' },
        ];
    }

    if (status === 'paused') {
        return [
            { status: 'active', label: 'Restore' },
            { status: 'suspended', label: 'Suspend' },
            { status: 'offboarded', label: 'Mark offboarded' },
        ];
    }

    return [{ status: 'active', label: 'Restore' }];
}

export function lifecycleIcon(status: string) {
    if (status === 'paused') {
        return PauseCircle;
    }

    if (status === 'suspended') {
        return Ban;
    }

    if (status === 'offboarded') {
        return CheckCircle2;
    }

    return RotateCcw;
}
