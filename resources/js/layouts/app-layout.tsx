import { usePage } from '@inertiajs/react';

import { AiUnavailableNotice } from '@/components/ai-unavailable-notice';
import type { AiNotice } from '@/components/ai-unavailable-notice';
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
    const { aiNotice } = usePage<{ aiNotice?: AiNotice | null }>().props;

    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs} brandHeader={brandHeader}>
            <AiUnavailableNotice notice={aiNotice} />
            {children}
        </AppLayoutTemplate>
    );
}
