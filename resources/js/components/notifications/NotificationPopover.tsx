import { Link, router } from '@inertiajs/react';
import { AlertTriangle, CheckCheck, ExternalLink } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { NotificationSummary } from './types';

type Props = {
    summary: NotificationSummary;
};

export function NotificationPopover({ summary }: Props) {
    const markAllRead = () => {
        router.patch(
            summary.mark_all_read_url,
            {},
            {
                only: ['notificationSummary'],
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    return (
        <div className="w-[340px] max-w-[calc(100vw-2rem)]">
            <div className="flex items-center justify-between gap-3 border-b px-3 py-2">
                <div>
                    <div className="text-sm font-medium">Notifications</div>
                    <div className="text-xs text-muted-foreground">
                        {summary.unread} unread
                    </div>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={summary.unread === 0}
                    onClick={markAllRead}
                >
                    <CheckCheck className="size-4" aria-hidden="true" />
                    Read all
                </Button>
            </div>

            <div className="max-h-[360px] overflow-y-auto p-1">
                {summary.latest.length === 0 ? (
                    <div className="px-3 py-8 text-center text-sm text-muted-foreground">
                        No notifications.
                    </div>
                ) : (
                    summary.latest.map((notification) => (
                        <NotificationPreview
                            key={notification.id}
                            notification={notification}
                        />
                    ))
                )}
            </div>

            <div className="border-t p-2">
                <Button asChild variant="outline" className="w-full">
                    <Link href={summary.index_url}>
                        View all
                        <ExternalLink className="size-4" aria-hidden="true" />
                    </Link>
                </Button>
            </div>
        </div>
    );
}

function NotificationPreview({
    notification,
}: {
    notification: NotificationSummary['latest'][number];
}) {
    const content = (
        <div
            className={cn(
                'grid gap-1 rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-accent',
                notification.read_at === null && 'bg-muted/60',
                notification.urgency === 'urgent' &&
                    'border-l-2 border-destructive',
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <div className="min-w-0 truncate font-medium">
                    {notification.title}
                </div>
                {notification.urgency === 'urgent' && (
                    <Badge variant="destructive">
                        <AlertTriangle className="size-3" aria-hidden="true" />
                        Urgent
                    </Badge>
                )}
            </div>
            {notification.message && (
                <div className="line-clamp-2 text-xs text-muted-foreground">
                    {notification.message}
                </div>
            )}
            <div className="text-xs text-muted-foreground">
                {formatRelative(notification.created_at)}
            </div>
        </div>
    );

    if (notification.url) {
        return <Link href={notification.url}>{content}</Link>;
    }

    return content;
}

function formatRelative(value: string | null) {
    if (!value) {
        return '';
    }

    const timestamp = new Date(value).getTime();
    const seconds = Math.max(0, Math.round((Date.now() - timestamp) / 1000));

    if (seconds < 60) {
        return 'Just now';
    }

    const minutes = Math.round(seconds / 60);

    if (minutes < 60) {
        return `${minutes}m ago`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 24) {
        return `${hours}h ago`;
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
    }).format(new Date(value));
}
