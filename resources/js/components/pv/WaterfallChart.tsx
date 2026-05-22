import { Badge } from '@/components/ui/badge';

export type WaterfallStep = {
    key: string;
    label: string;
    kind: 'absolute' | 'increase' | 'decrease' | 'total';
    value: number;
    start: number;
    end: number;
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
                    <div key={step.key} className="space-y-1.5">
                        <div className="flex items-center justify-between gap-3 text-xs">
                            <span className="font-medium">{step.label}</span>
                            <span className="tabular-nums">
                                {formatCurrency(step.value)}
                            </span>
                        </div>
                        <div className="relative h-8 overflow-hidden rounded-md bg-muted">
                            <div
                                className={barClass(step.kind)}
                                style={{ left, width }}
                            />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export function PvSummaryBadges({
    current,
    target,
}: {
    current: number;
    target: number;
}) {
    return (
        <div className="flex flex-wrap gap-2">
            <Badge variant="outline">{formatCurrency(current)} current</Badge>
            <Badge variant="secondary">{formatCurrency(target)} target</Badge>
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

function formatCurrency(value: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'NZD',
        maximumFractionDigits: 0,
    }).format(value);
}
