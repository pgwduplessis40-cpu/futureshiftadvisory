import { MonitorUp } from 'lucide-react';
import { Button } from '@/components/ui/button';

export type PresenceStatus =
    'connecting' | 'ready' | 'reconnecting' | 'unavailable';

export function ClientSupportPresenceStatus({
    status,
    onRetry,
}: {
    status: PresenceStatus;
    onRetry: () => void;
}) {
    return (
        <div
            className="fixed right-4 bottom-4 z-40 flex max-w-sm items-center gap-2 rounded-md border bg-background px-3 py-2 text-xs shadow-md"
            role="status"
            aria-live="polite"
        >
            <MonitorUp
                className="size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <span>{presenceStatusCopy(status)}</span>
            {status !== 'ready' ? (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={onRetry}
                >
                    Retry
                </Button>
            ) : null}
        </div>
    );
}

function presenceStatusCopy(status: PresenceStatus): string {
    if (status === 'ready') {
        return 'Screen support is ready for your advisor.';
    }

    if (status === 'connecting') {
        return 'Connecting screen support…';
    }

    if (status === 'reconnecting') {
        return 'Reconnecting screen support…';
    }

    return 'Screen support is unavailable right now.';
}
