import { CircleHelp } from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { useId } from 'react';

import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { usePointerCoarse } from '@/hooks/use-pointer-coarse';
import { cn } from '@/lib/utils';

export type Explanation = {
    title?: ReactNode;
    what: ReactNode;
    action: ReactNode;
    why: ReactNode;
};

export function Explainer({
    explanation,
    label = 'Explain this item',
    className,
    triggerClassName,
    contentClassName,
}: {
    explanation: Explanation;
    label?: string;
    className?: string;
    triggerClassName?: string;
    contentClassName?: string;
}) {
    const contentId = useId();
    const isCoarse = usePointerCoarse();
    const trigger = (
        <button
            type="button"
            className={cn(
                'inline-flex size-7 shrink-0 items-center justify-center rounded-full border bg-background text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                triggerClassName,
            )}
            aria-label={label}
            aria-describedby={contentId}
        >
            <CircleHelp className="size-4" aria-hidden="true" />
        </button>
    );
    const content = (
        <ExplainerContent
            id={contentId}
            explanation={explanation}
            className={contentClassName}
        />
    );

    if (isCoarse) {
        return (
            <Popover>
                <PopoverTrigger asChild>{trigger}</PopoverTrigger>
                <PopoverContent align="end" className={cn('w-80', className)}>
                    {content}
                </PopoverContent>
            </Popover>
        );
    }

    return (
        <HoverCard openDelay={150} closeDelay={100}>
            <HoverCardTrigger asChild>{trigger}</HoverCardTrigger>
            <HoverCardContent align="end" className={cn('w-80', className)}>
                {content}
            </HoverCardContent>
        </HoverCard>
    );
}

export function ExplainedSectionHeader({
    title,
    description,
    explanation,
    icon: Icon,
    actions,
    className,
    titleClassName,
}: {
    title: ReactNode;
    description?: ReactNode;
    explanation: Explanation;
    icon?: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    actions?: ReactNode;
    className?: string;
    titleClassName?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between',
                className,
            )}
        >
            <div className="min-w-0">
                <div className="flex items-center gap-2">
                    {Icon ? (
                        <Icon
                            className="size-4 shrink-0 text-muted-foreground"
                            aria-hidden={true}
                        />
                    ) : null}
                    <h2 className={cn('text-sm font-medium', titleClassName)}>
                        {title}
                    </h2>
                    <Explainer explanation={explanation} />
                </div>
                {description ? (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            {actions ? (
                <div className="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                    {actions}
                </div>
            ) : null}
        </div>
    );
}

export function ExplainedMetricCard({
    label,
    value,
    helper,
    explanation,
    className,
}: {
    label: ReactNode;
    value: ReactNode;
    helper?: ReactNode;
    explanation: Explanation;
    className?: string;
}) {
    return (
        <div className={cn('rounded-md border bg-background p-3', className)}>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 text-xs text-muted-foreground">
                    {label}
                </div>
                <Explainer explanation={explanation} />
            </div>
            <div className="mt-1 text-sm font-medium">{value}</div>
            {helper ? (
                <div className="mt-1 text-xs text-muted-foreground">
                    {helper}
                </div>
            ) : null}
        </div>
    );
}

function ExplainerContent({
    id,
    explanation,
    className,
}: {
    id: string;
    explanation: Explanation;
    className?: string;
}) {
    return (
        <div id={id} className={cn('space-y-3 text-sm', className)}>
            {explanation.title ? (
                <div className="font-medium leading-none">
                    {explanation.title}
                </div>
            ) : null}
            <div className="space-y-3">
                <ExplanationRow
                    label="What this shows"
                    value={explanation.what}
                />
                <ExplanationRow label="What to do" value={explanation.action} />
                <ExplanationRow
                    label="Why it matters"
                    value={explanation.why}
                />
            </div>
        </div>
    );
}

function ExplanationRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="space-y-1">
            <div className="text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                {label}
            </div>
            <div className="text-sm leading-snug text-foreground">{value}</div>
        </div>
    );
}
