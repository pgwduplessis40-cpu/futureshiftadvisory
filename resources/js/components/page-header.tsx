import type { ComponentProps, ComponentType, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Standard page/section header: an optional brand-tinted icon chip, a title, an
 * optional description, and an optional actions slot (buttons) that wraps cleanly
 * and drops below the title on small screens. Use instead of one-off
 * `<header className="flex items-center gap-2">` blocks so heading hierarchy and
 * spacing stay consistent across the app.
 */
export function PageHeader({
    title,
    description,
    icon: Icon,
    actions,
    className,
    ...props
}: {
    title: ReactNode;
    description?: ReactNode;
    icon?: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    actions?: ReactNode;
} & Omit<ComponentProps<'div'>, 'title'>) {
    return (
        <div
            className={cn(
                'flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between',
                className,
            )}
            {...props}
        >
            <div className="flex items-start gap-3">
                {Icon ? (
                    <span className="mt-0.5 shrink-0 rounded-md bg-[var(--fs-linen)] p-2 text-[var(--fs-admiralty)]">
                        <Icon className="size-5" aria-hidden={true} />
                    </span>
                ) : null}
                <div className="space-y-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    {description ? (
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            {description}
                        </p>
                    ) : null}
                </div>
            </div>
            {actions ? (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            ) : null}
        </div>
    );
}
