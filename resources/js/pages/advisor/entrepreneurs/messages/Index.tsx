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
};

export default function AdvisorEntrepreneurMessages({
    client,
    threads,
    selectedThread,
    createUrl,
    indexUrl,
}: Props) {
    return (
        <>
            <Head title={`${client.legal_name} messages`} />
            <ThreadedMessaging
                client={client}
                threads={threads}
                selectedThread={selectedThread}
                createUrl={createUrl}
                indexUrl={indexUrl}
                backHref={`/advisor/entrepreneurs/${client.id}`}
                backLabel="Entrepreneur"
            />
        </>
    );
}

AdvisorEntrepreneurMessages.layout = {
    breadcrumbs: [
        {
            title: 'Entrepreneurs',
            href: '/advisor/entrepreneurs',
        },
    ],
};
