import type { ReactNode } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

export default function AdvisorLayout({
    breadcrumbs = [],
    brandHeader = true,
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    brandHeader?: boolean;
    children: ReactNode;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs} brandHeader={brandHeader}>
            {children}
        </AppLayout>
    );
}
