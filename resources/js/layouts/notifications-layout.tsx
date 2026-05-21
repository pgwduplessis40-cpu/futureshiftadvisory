import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLayout from '@/layouts/app-layout';
import PortalLayout from '@/layouts/PortalLayout';
import type { Auth } from '@/types';

export default function NotificationsLayout({
    children,
}: {
    children: ReactNode;
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const userType = auth.user.user_type;

    if (
        userType === 'client_primary' ||
        userType === 'client_team' ||
        userType === 'entrepreneur'
    ) {
        return <PortalLayout>{children}</PortalLayout>;
    }

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
