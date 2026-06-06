import type { ComponentType, ReactNode } from 'react';

/**
 * Consistent empty state for lists/sections with no content yet: a centered
 * dashed-border panel with an optional icon, a title, an optional description,
 * and an optional action. Replaces one-off "No items." text blocks.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
}: {
    icon?: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    title: ReactNode;
    description?: ReactNode;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed bg-muted/30 px-6 py-12 text-center">
            {Icon ? (
                <span className="rounded-full bg-background p-3 text-muted-foreground">
                    <Icon className="size-6" aria-hidden={true} />
                </span>
            ) : null}
            <div className="space-y-1">
                <p className="text-sm font-medium">{title}</p>
                {description ? (
                    <p className="mx-auto max-w-sm text-sm text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            {action ? <div className="pt-1">{action}</div> : null}
        </div>
    );
}
