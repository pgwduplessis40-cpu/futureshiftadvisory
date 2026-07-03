import type { ReactNode } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
    actions,
    brandHeader = true,
}: {
    breadcrumbs?: BreadcrumbItemType[];
    actions?: ReactNode;
    brandHeader?: boolean;
}) {
    return (
        <header
            className={cn(
                'sticky top-0 z-30 flex h-16 shrink-0 items-center justify-between gap-4 border-b border-sidebar-border/70 bg-background/95 px-6 backdrop-blur transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4',
                brandHeader &&
                    'border-b-0 bg-[var(--fs-commodore)] text-white shadow-card',
            )}
        >
            <div className="z-10 flex items-center gap-2">
                <SidebarTrigger className="-ml-1 rounded-full" />
                {!brandHeader && <Breadcrumbs breadcrumbs={breadcrumbs} />}
            </div>
            {brandHeader && (
                <div className="pointer-events-none absolute inset-0 flex items-center justify-center px-16 text-center">
                    <span
                        aria-label="Mentor, Advisor, Partner"
                        className="flex items-center justify-center gap-4 text-sm font-semibold tracking-normal text-[var(--gold)] sm:gap-8 sm:text-base"
                    >
                        <span>Mentor</span>
                        <span aria-hidden="true">{'\u00b7'}</span>
                        <span>Advisor</span>
                        <span aria-hidden="true">{'\u00b7'}</span>
                        <span>Partner</span>
                    </span>
                </div>
            )}
            <div className="z-10">{actions}</div>
        </header>
    );
}
