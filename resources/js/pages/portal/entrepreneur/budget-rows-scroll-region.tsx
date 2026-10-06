import type { ReactNode } from 'react';

type BudgetRowsScrollRegionProps = {
    title: string;
    children: ReactNode;
};

export function BudgetRowsScrollRegion({
    title,
    children,
}: BudgetRowsScrollRegionProps) {
    return (
        <>
            <p className="hidden text-xs text-muted-foreground md:block">
                If these inputs exceed the available width, scroll sideways to
                reach every field.
            </p>
            <div
                className="space-y-2 overflow-x-auto overscroll-x-contain pb-3"
                role="region"
                tabIndex={0}
                aria-label={`${title} inputs. Scroll horizontally to reach every field.`}
            >
                {children}
            </div>
        </>
    );
}
