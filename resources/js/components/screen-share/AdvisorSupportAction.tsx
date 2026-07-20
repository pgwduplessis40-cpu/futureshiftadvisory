import { Monitor } from 'lucide-react';
import { useState } from 'react';
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
    request_url: string;
    ice_servers_url: string;
    active_url: string;
    signal_url: string;
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
    const available = config !== null && config.participants.length > 0;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
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
                <DialogContent className="max-w-5xl p-0">
                    <DialogHeader className="sr-only">
                        <DialogTitle>Screen support</DialogTitle>
                    </DialogHeader>
                    <AdvisorSupport config={config} />
                </DialogContent>
            ) : null}
        </Dialog>
    );
}
