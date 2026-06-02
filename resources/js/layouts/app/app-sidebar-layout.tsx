import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { NotificationBell } from '@/components/notifications/NotificationBell';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
    brandHeader = true,
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-clip">
                <AppSidebarHeader
                    breadcrumbs={breadcrumbs}
                    brandHeader={brandHeader}
                    actions={<NotificationBell brandHeader={brandHeader} />}
                />
                {children}
            </AppContent>
        </AppShell>
    );
}
