import { Head } from '@inertiajs/react';

import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <>
            {/* Auth pages (login, password reset, MFA, invites) carry no search
                value and must stay out of the index. */}
            <Head>
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <AuthLayoutTemplate title={title} description={description}>
                {children}
            </AuthLayoutTemplate>
        </>
    );
}
