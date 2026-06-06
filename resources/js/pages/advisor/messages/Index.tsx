import { Head, Link } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    Clock3,
    ExternalLink,
    Inbox,
    MessageSquare,
    UserRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type MessageKind = 'client' | 'entrepreneur';
type FilterKind = 'all' | MessageKind;

type ThreadSummary = {
    id: string;
    kind: MessageKind;
    kind_label: string;
    subject: string;
    context_name: string;
    context_detail: string | null;
    latest_sender_name: string | null;
    latest_excerpt: string | null;
    last_activity_at: string | null;
    messages_count: number;
    unread_count: number;
    url: string;
};

type Props = {
    threads: ThreadSummary[];
    counts: {
        all: number;
        client: number;
        entrepreneur: number;
    };
};

const filterLabels: Record<FilterKind, string> = {
    all: 'All',
    client: 'Clients',
    entrepreneur: 'Entrepreneurs',
};

export default function AdvisorMessagesIndex({ threads, counts }: Props) {
    const [filter, setFilter] = useState<FilterKind>('all');
    const filteredThreads = useMemo(
        () =>
            filter === 'all'
                ? threads
                : threads.filter((thread) => thread.kind === filter),
        [filter, threads],
    );

    return (
        <>
            <Head title="Messages" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Inbox className="size-4" aria-hidden="true" />
                            Advisor inbox
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">Messages</h1>
                    </div>

                    <div className="inline-flex overflow-hidden rounded-md border bg-background p-1">
                        {(
                            [
                                ['all', counts.all],
                                ['client', counts.client],
                                ['entrepreneur', counts.entrepreneur],
                            ] as Array<[FilterKind, number]>
                        ).map(([value, count]) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => setFilter(value)}
                                className={cn(
                                    'inline-flex h-8 items-center gap-2 rounded-sm px-3 text-sm font-medium transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                    filter === value
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                )}
                            >
                                {filterLabels[value]}
                                <span
                                    className={cn(
                                        'rounded-sm px-1.5 py-0.5 text-xs',
                                        filter === value
                                            ? 'bg-primary-foreground/20'
                                            : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {count}
                                </span>
                            </button>
                        ))}
                    </div>
                </div>

                {filteredThreads.length === 0 ? (
                    <EmptyState
                        icon={MessageSquare}
                        title="No messages found"
                        description="Conversations with your clients will appear here."
                    />
                ) : (
                    <div className="overflow-hidden rounded-md border bg-background">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[760px] text-sm">
                                <thead className="bg-muted/60 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            Conversation
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Source
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Latest
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Activity
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Open
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredThreads.map((thread) => (
                                        <ThreadRow
                                            key={thread.id}
                                            thread={thread}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

function ThreadRow({ thread }: { thread: ThreadSummary }) {
    const icon =
        thread.kind === 'client' ? (
            <BriefcaseBusiness className="size-4" aria-hidden="true" />
        ) : (
            <UserRound className="size-4" aria-hidden="true" />
        );

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-3">
                <div className="min-w-0 space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline" className="gap-1">
                            {icon}
                            {thread.kind_label}
                        </Badge>
                        {thread.unread_count > 0 && (
                            <Badge variant="secondary">
                                {thread.unread_count} unread
                            </Badge>
                        )}
                    </div>
                    <div className="font-medium">{thread.subject}</div>
                    <div className="text-xs text-muted-foreground">
                        {thread.messages_count} messages
                    </div>
                </div>
            </td>
            <td className="px-3 py-3">
                <div className="min-w-0">
                    <div className="font-medium">{thread.context_name}</div>
                    {thread.context_detail && (
                        <div className="mt-1 max-w-56 truncate text-xs text-muted-foreground">
                            {thread.context_detail}
                        </div>
                    )}
                </div>
            </td>
            <td className="max-w-[26rem] px-3 py-3">
                <div className="min-w-0">
                    {thread.latest_sender_name && (
                        <div className="text-xs font-medium text-muted-foreground">
                            {thread.latest_sender_name}
                        </div>
                    )}
                    {thread.latest_excerpt ? (
                        <p className="mt-1 line-clamp-2 text-muted-foreground">
                            {thread.latest_excerpt}
                        </p>
                    ) : (
                        <p className="mt-1 text-muted-foreground">
                            No message preview.
                        </p>
                    )}
                </div>
            </td>
            <td className="px-3 py-3 text-right whitespace-nowrap">
                <div className="inline-flex items-center justify-end gap-1 text-xs text-muted-foreground">
                    <Clock3 className="size-3" aria-hidden="true" />
                    {formatDate(thread.last_activity_at)}
                </div>
            </td>
            <td className="px-3 py-3">
                <div className="flex justify-end">
                    <Button asChild variant="outline" size="sm">
                        <Link href={thread.url}>
                            <ExternalLink
                                className="size-4"
                                aria-hidden="true"
                            />
                            Open
                        </Link>
                    </Button>
                </div>
            </td>
        </tr>
    );
}

function formatDate(value: string | null) {
    if (!value) {
        return 'Just now';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

AdvisorMessagesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Messages',
            href: '/advisor/messages',
        },
    ],
};
