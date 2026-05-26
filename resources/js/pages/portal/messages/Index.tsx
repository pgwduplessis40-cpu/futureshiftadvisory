import { Head } from '@inertiajs/react';
import { ThreadedMessaging } from '@/components/messages/ThreadedMessaging';
import type {
    MessagingClient,
    SelectedThread,
    ThreadSummary,
} from '@/components/messages/ThreadedMessaging';

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
            <ThreadedMessaging
                client={client}
                threads={threads}
                selectedThread={selectedThread}
                createUrl={createUrl}
                indexUrl={indexUrl}
                backHref={backHref}
                backLabel={backLabel}
            />
        </>
    );
}
