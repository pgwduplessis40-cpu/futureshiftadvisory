import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { cloneElement, useId } from 'react';
import type { ReactElement, ReactNode } from 'react';

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

export type InsightHoverCardRow = {
    label: ReactNode;
    value: ReactNode;
    description?: ReactNode;
    tone?: 'default' | 'muted' | 'positive' | 'negative';
};

type InsightHoverCardProps = {
    title: ReactNode;
    rows?: InsightHoverCardRow[];
    drillHref?: InertiaLinkProps['href'];
    drillLabel?: string;
    drillAriaLabel?: string;
    footer?: ReactNode;
    children: ReactElement;
    className?: string;
    contentClassName?: string;
};

export function InsightHoverCard({
    title,
    rows = [],
    drillHref,
    drillLabel = 'Open',
    drillAriaLabel,
    footer,
    children,
    className,
    contentClassName,
}: InsightHoverCardProps) {
    const isCoarse = usePointerCoarse();
    const contentId = useId();
    const trigger = cloneElement(
        children as ReactElement<Record<string, unknown>>,
        {
            'aria-describedby': contentId,
        },
    );
    const content = (
        <InsightHoverCardContent
            id={contentId}
            title={title}
            rows={rows}
            drillHref={drillHref}
            drillLabel={drillLabel}
            drillAriaLabel={drillAriaLabel}
            footer={footer}
            className={contentClassName}
        />
    );

    if (isCoarse) {
        return (
            <Popover>
                <PopoverTrigger asChild>{trigger}</PopoverTrigger>
                <PopoverContent align="start" className={className}>
                    {content}
                </PopoverContent>
            </Popover>
        );
    }

    return (
        <HoverCard openDelay={150} closeDelay={100}>
            <HoverCardTrigger asChild>{trigger}</HoverCardTrigger>
            <HoverCardContent align="start" className={className}>
                {content}
            </HoverCardContent>
        </HoverCard>
    );
}

type InsightHoverCardContentProps = {
    id: string;
    title: ReactNode;
    rows: InsightHoverCardRow[];
    drillHref?: InertiaLinkProps['href'];
    drillLabel: string;
    drillAriaLabel?: string;
    footer?: ReactNode;
    className?: string;
};

function InsightHoverCardContent({
    id,
    title,
    rows,
    drillHref,
    drillLabel,
    drillAriaLabel,
    footer,
    className,
}: InsightHoverCardContentProps) {
    return (
        <div id={id} className={cn('space-y-3 text-sm', className)}>
            <div className="leading-none font-medium">{title}</div>
            {rows.length > 0 && (
                <dl className="space-y-2">
                    {rows.map((row, index) => (
                        <div
                            key={index}
                            className="grid grid-cols-[minmax(0,1fr)_auto] gap-3"
                        >
                            <dt className="min-w-0 text-muted-foreground">
                                {row.label}
                                {row.description && (
                                    <div className="mt-1 text-[11px] leading-snug text-muted-foreground/80">
                                        {row.description}
                                    </div>
                                )}
                            </dt>
                            <dd
                                className={cn(
                                    'min-w-0 text-right font-medium',
                                    rowToneClass(row.tone),
                                )}
                            >
                                {row.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            )}
            {(footer || drillHref) && (
                <div className="flex items-center justify-between gap-3 border-t pt-3">
                    {footer ? (
                        <div className="min-w-0 text-xs text-muted-foreground">
                            {footer}
                        </div>
                    ) : (
                        <span />
                    )}
                    {drillHref && (
                        <Link
                            href={drillHref}
                            className="inline-flex items-center gap-1 text-xs font-medium whitespace-nowrap text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            aria-label={drillAriaLabel}
                        >
                            {drillLabel}
                            <ChevronRight
                                className="size-3"
                                aria-hidden="true"
                            />
                        </Link>
                    )}
                </div>
            )}
        </div>
    );
}

function rowToneClass(tone: InsightHoverCardRow['tone']) {
    if (tone === 'positive') {
        return 'text-emerald-700 dark:text-emerald-300';
    }

    if (tone === 'negative') {
        return 'text-destructive';
    }

    if (tone === 'muted') {
        return 'text-muted-foreground';
    }

    return 'text-foreground';
}
