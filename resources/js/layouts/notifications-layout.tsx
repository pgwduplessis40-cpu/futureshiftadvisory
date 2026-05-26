import type { ReactNode } from 'react';
import AppLayout from '@/layouts/app-layout';

export default function NotificationsLayout({
    children,
}: {
    children: ReactNode;
}) {
    return (
        <AppLayout
            breadcrumbs={[
                {
                    title: 'Notifications',
                    href: '/notifications',
                },
            ]}
        >
            {children}
        </AppLayout>
    );
}
