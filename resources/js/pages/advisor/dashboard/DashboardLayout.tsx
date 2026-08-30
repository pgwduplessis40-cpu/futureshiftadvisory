import type React from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export function AdvisorPortfolioFallback() {
    return (
        <>
            <SignalSkeletonGrid cards={2} />
            <SignalSkeletonGrid cards={2} />
        </>
    );
}

export function AdvisorSignalsFallback() {
    return (
        <>
            <DashboardSection
                title="Panel operations"
                description="Track partner hand-offs and governed learning work that supports the advisory team."
            >
                <SignalSkeletonGrid cards={3} />
            </DashboardSection>

            <DashboardSection
                title="Specialist workflows"
                description="Monitor NPO conversion, funding, wellbeing, coaching, and prospect signals."
            >
                <SignalSkeletonGrid cards={5} />
            </DashboardSection>

            <DashboardSection
                title="Operating signals"
                description="Use these lower-urgency indicators to spot systemic issues and improvement opportunities."
            >
                <SignalSkeletonGrid cards={5} />
            </DashboardSection>
        </>
    );
}

export function SignalSkeletonGrid({ cards }: { cards: number }) {
    return (
        <div className="grid gap-4 xl:grid-cols-3">
            {Array.from({ length: cards }).map((_, index) => (
                <div
                    key={index}
                    className="min-h-[180px] animate-pulse rounded-md border bg-card p-4"
                >
                    <div className="h-4 w-28 rounded bg-muted" />
                    <div className="mt-4 h-8 w-16 rounded bg-muted" />
                    <div className="mt-5 space-y-2">
                        <div className="h-3 rounded bg-muted" />
                        <div className="h-3 w-4/5 rounded bg-muted" />
                        <div className="h-3 w-2/3 rounded bg-muted" />
                    </div>
                </div>
            ))}
        </div>
    );
}

export function DashboardTabButton({
    active,
    onClick,
    label,
    count,
    controls,
}: {
    active: boolean;
    onClick: () => void;
    label: string;
    count: number;
    controls: string;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            aria-controls={controls}
            onClick={onClick}
            className={cn(
                'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                active
                    ? 'bg-sidebar-primary text-sidebar-primary-foreground shadow-sm'
                    : 'text-muted-foreground hover:bg-card hover:text-foreground',
            )}
        >
            {label}
            <Badge
                variant="outline"
                className={
                    active
                        ? 'border-sidebar-primary-foreground/20 bg-sidebar-primary-foreground/15 text-sidebar-primary-foreground'
                        : undefined
                }
            >
                {count}
            </Badge>
        </button>
    );
}

export function DashboardSection({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: React.ReactNode;
}) {
    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-base font-semibold">{title}</h2>
                <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            <div className="space-y-4">{children}</div>
        </section>
    );
}
