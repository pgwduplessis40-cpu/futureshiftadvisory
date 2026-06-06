import type { ComponentProps, ComponentType, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Standard page/section header used across the app for a consistent heading
 * hierarchy: an optional eyebrow (small icon + context label), a title, an
 * optional description, and an optional actions slot (buttons/badges) that wraps
 * cleanly and drops below the title on small screens. Use instead of one-off
 * `<header>` blocks so spacing and hierarchy stay consistent everywhere.
 */
export function PageHeader({
    title,
    eyebrow,
    description,
    icon: Icon,
    actions,
    className,
    ...props
}: {
    title: ReactNode;
    eyebrow?: ReactNode;
    description?: ReactNode;
    icon?: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    actions?: ReactNode;
} & Omit<ComponentProps<'div'>, 'title'>) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between',
                className,
            )}
            {...props}
        >
            <div className="min-w-0 space-y-1">
                {eyebrow || Icon ? (
                    <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        {Icon ? (
                            <Icon
                                className="size-4 shrink-0"
                                aria-hidden={true}
                            />
                        ) : null}
                        {eyebrow ? <span>{eyebrow}</span> : null}
                    </div>
                ) : null}
                <h1 className="text-xl font-semibold tracking-tight">
                    {title}
                </h1>
                {description ? (
                    <p className="max-w-2xl text-sm text-muted-foreground">
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
