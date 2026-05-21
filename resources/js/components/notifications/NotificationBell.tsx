import { router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect } from 'react';
import { NotificationPopover } from '@/components/notifications/NotificationPopover';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { NotificationSummary } from './types';

export function NotificationBell() {
    const { notificationSummary } = usePage<{
        notificationSummary?: NotificationSummary | null;
    }>().props;

    useEffect(() => {
        if (!notificationSummary) {
            return;
        }

        const interval = window.setInterval(() => {
            router.reload({
                only: ['notificationSummary'],
            });
        }, 30000);

        return () => window.clearInterval(interval);
    }, [notificationSummary]);

    if (!notificationSummary) {
        return null;
    }

    const unread = notificationSummary.unread;
    const urgent = notificationSummary.urgent;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="relative"
                    aria-label={
                        unread === 0
                            ? 'Notifications'
                            : `${unread} unread notifications`
                    }
                >
                    <Bell className="size-4" aria-hidden="true" />
                    {unread > 0 && (
                        <span className="absolute -top-1 -right-1 flex min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[11px] font-medium text-primary-foreground">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                    {urgent > 0 && (
                        <span className="absolute -right-1 -bottom-1 size-2.5 rounded-full bg-destructive ring-2 ring-background" />
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="p-0">
                <NotificationPopover summary={notificationSummary} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
