import { BackToTopButton } from '@/components/back-to-top-button';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    brandHeader = true,
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    brandHeader?: boolean;
    children: React.ReactNode;
}) {
    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs} brandHeader={brandHeader}>
            {/* Shared page container: one consistent, responsive padding scale so
                page content never sits flush against the sidebar/top/edges. Pages
                provide their own vertical rhythm (space-y-*) inside this. */}
            <div className="fsa-app-surface flex-1 px-4 py-6 sm:px-6 lg:px-8">
                {children}
            </div>
            <BackToTopButton />
        </AppLayoutTemplate>
    );
}
