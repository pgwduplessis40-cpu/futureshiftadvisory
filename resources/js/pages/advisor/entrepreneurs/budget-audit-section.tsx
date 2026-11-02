import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';

export function BudgetAuditSection({
    title,
    count,
    defaultOpen = false,
    status,
    children,
}: {
    title: string;
    count: number;
    defaultOpen?: boolean;
    status?: ReactNode;
    children: ReactNode;
}) {
    return (
        <details className="group min-w-0 rounded-md border" open={defaultOpen}>
            <summary className="flex cursor-pointer list-none flex-wrap items-center gap-3 rounded-md p-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [&::-webkit-details-marker]:hidden">
                <h3 className="text-sm font-medium">{title}</h3>
                <span className="text-xs text-muted-foreground">
                    {count} {count === 1 ? 'item' : 'items'}
                </span>
                <span className="ml-auto flex items-center gap-3">
                    {status}
                    <ChevronDown
                        className="size-4 transition-transform group-open:rotate-180"
                        aria-hidden="true"
                    />
                </span>
            </summary>
            <div className="border-t">{children}</div>
        </details>
    );
}
