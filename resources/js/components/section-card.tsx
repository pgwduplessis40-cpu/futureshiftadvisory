import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * Standard content card/panel: rounded corners, a subtle border, light surface,
 * and consistent internal padding (a touch more on larger screens). Use to wrap
 * related sections — forms, tables, lists, action panels — so nothing floats
 * directly on the page without a container.
 */
export function SectionCard({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn('rounded-md border bg-background p-4', className)}
            {...props}
        />
    );
}
