import { Head } from '@inertiajs/react';
import { ThreadedMessaging } from '@/components/messages/ThreadedMessaging';
import type {
    MessagingClient,
    SelectedThread,
    ThreadSummary,
} from '@/components/messages/ThreadedMessaging';
import { index } from '@/routes/portal/messages';

type Props = {
    client: MessagingClient;
    threads: ThreadSummary[];
    selectedThread: SelectedThread | null;
    createUrl: string;
    indexUrl: string;
    backHref?: string;
    backLabel?: string;
};

export default function PortalMessages({
    client,
    threads,
    selectedThread,
    createUrl,
    indexUrl,
    backHref = '/portal',
    backLabel = 'Dashboard',
}: Props) {
    return (
        <>
            <Head title="Messages" />
            <div className="px-4 py-6">
                <ThreadedMessaging
                    client={client}
                    threads={threads}
                    selectedThread={selectedThread}
                    createUrl={createUrl}
                    indexUrl={indexUrl}
                    backHref={backHref}
                    backLabel={backLabel}
                />
            </div>
        </>
    );
}

PortalMessages.layout = {
    breadcrumbs: [
        {
            title: 'Messages',
            href: index(),
        },
    ],
};
