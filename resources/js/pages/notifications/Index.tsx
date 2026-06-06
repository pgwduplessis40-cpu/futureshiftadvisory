import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    CheckCheck,
    ExternalLink,
    MailOpen,
} from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import type {
    NotificationItem,
    NotificationSummary,
} from '@/components/notifications/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = {
    notifications: NotificationItem[];
    summary: NotificationSummary;
    markAllReadUrl: string;
};

export default function NotificationsIndex({
    notifications,
    summary,
    markAllReadUrl,
}: Props) {
    const markAllRead = () => {
        router.patch(markAllReadUrl, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Notifications" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">Notifications</h1>
                        <div className="text-sm text-muted-foreground">
                            {summary.unread} unread
                            {summary.urgent > 0
                                ? `, ${summary.urgent} urgent`
                                : ''}
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={summary.unread === 0}
                        onClick={markAllRead}
                    >
                        <CheckCheck className="size-4" aria-hidden="true" />
                        Mark all read
                    </Button>
                </div>

                {notifications.length === 0 ? (
                    <EmptyState
                        icon={CheckCheck}
                        title="No notifications"
                        description="You're all caught up."
                    />
                ) : (
                    <div className="space-y-3">
                        {notifications.map((notification) => (
                            <NotificationRow
                                key={notification.id}
                                notification={notification}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function NotificationRow({ notification }: { notification: NotificationItem }) {
    const unread = notification.read_at === null;
    const urgent = notification.urgency === 'urgent';
    const bypassed = notification.channel_decision.bypassed_preference === true;

    const markRead = () => {
        router.patch(notification.mark_read_url, {}, { preserveScroll: true });
    };

    return (
        <article
            className={cn(
                'grid gap-4 rounded-md border bg-background p-4 md:grid-cols-[minmax(0,1fr)_auto]',
                unread && 'border-primary/30 bg-muted/40',
                urgent && 'border-destructive/40',
            )}
        >
            <div className="min-w-0 space-y-2">
                <div className="flex flex-wrap items-center gap-2">
                    <h2 className="text-sm font-medium">
                        {notification.title}
                    </h2>
                    {unread && <Badge variant="secondary">Unread</Badge>}
                    {urgent && (
                        <Badge variant="destructive">
                            <AlertTriangle
                                className="size-3"
                                aria-hidden="true"
                            />
                            Urgent
                        </Badge>
                    )}
                    {bypassed && (
                        <Badge variant="outline">Preference bypass</Badge>
                    )}
                </div>
                {notification.message && (
                    <p className="text-sm text-muted-foreground">
                        {notification.message}
                    </p>
                )}
                <div className="text-xs text-muted-foreground">
                    {formatDate(notification.created_at)}
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-2 md:justify-end">
                {notification.url && (
                    <Button asChild variant="outline" size="sm">
                        <Link href={notification.url}>
                            <ExternalLink
                                className="size-4"
                                aria-hidden="true"
                            />
                            Open
                        </Link>
                    </Button>
                )}
                {unread ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={markRead}
                    >
                        <Check className="size-4" aria-hidden="true" />
                        Mark read
                    </Button>
                ) : (
                    <div className="inline-flex h-8 items-center gap-2 rounded-md border px-3 text-sm text-muted-foreground">
                        <MailOpen className="size-4" aria-hidden="true" />
                        Read
                    </div>
                )}
            </div>
        </article>
    );
}

function formatDate(value: string | null) {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
