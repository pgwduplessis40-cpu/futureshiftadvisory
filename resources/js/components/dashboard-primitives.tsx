import { Link } from '@inertiajs/react';
import { ArrowDownRight, ArrowRight, ArrowUpRight, Minus } from 'lucide-react';
import { useId   } from 'react';
import type {ComponentType, ReactNode} from 'react';
import { cn } from '@/lib/utils';

type Trend = 'up' | 'down' | 'flat';

export function StatCard({
    title,
    value,
    footnote,
    trend = 'flat',
    inverted = false,
    icon: Icon,
    href,
    className,
}: {
    title: ReactNode;
    value: ReactNode;
    footnote?: ReactNode;
    trend?: Trend;
    inverted?: boolean;
    icon?: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    href?: string;
    className?: string;
}) {
    const trendIcon = {
        up: ArrowUpRight,
        down: ArrowDownRight,
        flat: Minus,
    }[trend];
    const TrendIcon = trendIcon;
    const CornerIcon = Icon ?? ArrowRight;
    const content = (
        <>
            <div className="flex items-start justify-between gap-4">
                <div className="min-w-0 space-y-2">
                    <p
                        className={cn(
                            'text-xs font-semibold tracking-[0.12em] uppercase',
                            inverted
                                ? 'text-primary-foreground/70'
                                : 'text-muted-foreground',
                        )}
                    >
                        {title}
                    </p>
                    <div className="text-4xl leading-none font-bold tracking-tight">
                        {value}
                    </div>
                </div>
                <span
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-full',
                        inverted
                            ? 'bg-[var(--gold)] text-primary'
                            : 'bg-secondary text-primary',
                    )}
                    aria-hidden="true"
                >
                    <CornerIcon className="size-4" aria-hidden={true} />
                </span>
            </div>
            {footnote ? (
                <p
                    className={cn(
                        'mt-5 flex items-center gap-2 text-xs',
                        inverted
                            ? 'text-primary-foreground/75'
                            : 'text-muted-foreground',
                    )}
                >
                    <TrendIcon
                        className="size-3.5 shrink-0"
                        aria-hidden={true}
                    />
                    <span>{footnote}</span>
                </p>
            ) : null}
        </>
    );
    const classes = cn(
        'rounded-[1.25rem] border border-border/80 p-5 shadow-card transition-shadow hover:shadow-card-hover',
        inverted
            ? 'bg-primary text-primary-foreground'
            : 'bg-card text-card-foreground',
        className,
    );

    if (href) {
        return (
            <Link href={href} className={classes}>
                {content}
            </Link>
        );
    }

    return <div className={classes}>{content}</div>;
}

export function CountBadge({
    value,
    className,
}: {
    value: number | string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex min-w-6 items-center justify-center rounded-full bg-secondary px-2 py-0.5 text-xs font-semibold text-secondary-foreground',
                className,
            )}
        >
            {value}
        </span>
    );
}

export function MiniBarChart({
    values,
    labels,
    emphasisIndex,
    className,
}: {
    values: number[];
    labels?: string[];
    emphasisIndex?: number;
    className?: string;
}) {
    const max = Math.max(...values, 1);

    return (
        <div className={cn('flex h-44 items-end gap-2', className)}>
            {values.map((value, index) => (
                <div
                    key={`${labels?.[index] ?? index}-${value}`}
                    className="flex min-w-0 flex-1 flex-col items-center gap-2"
                >
                    <div className="flex h-36 w-full items-end rounded-full bg-secondary/70 p-1">
                        <div
                            className={cn(
                                'w-full rounded-t-full bg-[var(--chart-1)]',
                                emphasisIndex === index &&
                                    'bg-[var(--chart-4)]',
                            )}
                            style={{
                                height: `${Math.max(8, (value / max) * 100)}%`,
                            }}
                        />
                    </div>
                    {labels?.[index] ? (
                        <span className="truncate text-[11px] text-muted-foreground">
                            {labels[index]}
                        </span>
                    ) : null}
                </div>
            ))}
        </div>
    );
}

export function RadialProgress({
    value,
    label,
    className,
}: {
    value: number;
    label?: ReactNode;
    className?: string;
}) {
    const patternId = useId().replaceAll(':', '');
    const safeValue = Math.max(0, Math.min(100, value));
    const radius = 46;
    const circumference = 2 * Math.PI * radius;
    const dashOffset = circumference - (safeValue / 100) * circumference;

    return (
        <div
            className={cn(
                'relative flex size-36 items-center justify-center',
                className,
            )}
        >
            <svg viewBox="0 0 120 120" className="size-full -rotate-90">
                <defs>
                    <pattern
                        id={patternId}
                        width="6"
                        height="6"
                        patternUnits="userSpaceOnUse"
                        patternTransform="rotate(35)"
                    >
                        <path
                            d="M 0 0 L 0 6"
                            stroke="var(--fs-champagne)"
                            strokeWidth="2"
                        />
                    </pattern>
                </defs>
                <circle
                    cx="60"
                    cy="60"
                    r={radius}
                    fill="none"
                    stroke={`url(#${patternId})`}
                    strokeWidth="12"
                />
                <circle
                    cx="60"
                    cy="60"
                    r={radius}
                    fill="none"
                    stroke="var(--chart-4)"
                    strokeLinecap="round"
                    strokeWidth="12"
                    strokeDasharray={circumference}
                    strokeDashoffset={dashOffset}
                />
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
                <span className="text-3xl font-bold text-foreground">
                    {safeValue}%
                </span>
                {label ? (
                    <span className="mt-1 text-xs text-muted-foreground">
                        {label}
                    </span>
                ) : null}
            </div>
        </div>
    );
}
