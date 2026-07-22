import { Monitor } from 'lucide-react';
import { useCallback, useState } from 'react';
import { AdvisorSupport } from '@/components/screen-share/AdvisorSupport';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type AdvisorScreenShareConfig = {
    connection_url: string;
    connection_heartbeat_url: string;
    request_url: string;
    ice_servers_url: string;
    active_url: string;
    signal_url: string;
    pending_signals_url: string;
    heartbeat_url: string;
    end_url: string;
    heartbeat_seconds: number;
    participants: Array<{ id: string; name: string }>;
};

type Props = {
    config: AdvisorScreenShareConfig | null;
};

export function AdvisorSupportAction({ config }: Props) {
    const [open, setOpen] = useState(false);
    const [expanded, setExpanded] = useState(false);
    const available = config !== null && config.participants.length > 0;
    const handleConnectionChange = useCallback((connected: boolean) => {
        setExpanded(connected);
    }, []);
    const handleSessionEnded = useCallback(() => {
        setExpanded(false);
        setOpen(false);
    }, []);

    function handleOpenChange(nextOpen: boolean): void {
        setOpen(nextOpen);
        if (!nextOpen) {
            setExpanded(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={!available}
                title={
                    available
                        ? 'Open screen support'
                        : 'Screen support is available once the account is active.'
                }
                onClick={() => setOpen(true)}
            >
                <Monitor className="size-4" aria-hidden="true" />
                Screen support
            </Button>
            {config ? (
                <DialogContent
                    className={
                        expanded
                            ? 'h-[92vh] w-[96vw] max-w-none gap-0 overflow-hidden p-0 sm:max-w-none'
                            : 'gap-0 overflow-hidden p-0'
                    }
                >
                    <DialogHeader className="sr-only">
                        <DialogTitle>Screen support</DialogTitle>
                    </DialogHeader>
                    <AdvisorSupport
                        config={config}
                        onConnectionChange={handleConnectionChange}
                        onSessionEnded={handleSessionEnded}
                    />
                </DialogContent>
            ) : null}
        </Dialog>
    );
}
