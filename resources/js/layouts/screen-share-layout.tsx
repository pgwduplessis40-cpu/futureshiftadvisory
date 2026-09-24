import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { ClientSupport } from '@/components/screen-share/ClientSupport';
import type { ClientScreenShareConfig } from '@/components/screen-share/ClientSupport';

type SharedScreenShareProps = {
    portalScreenShare?: ClientScreenShareConfig;
};

/**
 * This is deliberately the outermost persistent Inertia layout. A client can
 * move between portal pages (including document pages) while sharing a tab;
 * putting the capture owner inside a page layout would stop the browser track
 * whenever that layout changes.
 */
export default function ScreenShareLayout({ children }: PropsWithChildren) {
    const { portalScreenShare } = usePage<SharedScreenShareProps>().props;

    return (
        <>
            {children}
            <ClientSupport config={portalScreenShare ?? null} />
        </>
    );
}
