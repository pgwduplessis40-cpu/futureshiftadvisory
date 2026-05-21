import { AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { VerificationOutcome } from './Badge';
import { VerificationBadge } from './Badge';

type Props = {
    outcome: VerificationOutcome;
    claim: string;
    explanation?: string | null;
    documentName?: string | null;
    clientName?: string | null;
};

export function DiscrepancyDialog({
    outcome,
    claim,
    explanation,
    documentName,
    clientName,
}: Props) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <AlertTriangle className="size-4" aria-hidden="true" />
                    Review
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Document verification flag</DialogTitle>
                    <DialogDescription>
                        Review this before relying on the attached claim.
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-4 text-sm">
                    <div className="flex flex-wrap items-center gap-2">
                        <VerificationBadge outcome={outcome} />
                        {clientName && (
                            <span className="text-muted-foreground">
                                {clientName}
                            </span>
                        )}
                    </div>
                    <dl className="grid gap-3">
                        <Detail label="Document" value={documentName} />
                        <Detail label="Claim" value={claim} />
                        <Detail label="Explanation" value={explanation} />
                    </dl>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function Detail({ label, value }: { label: string; value?: string | null }) {
    return (
        <div className="grid gap-1">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd>{value || '-'}</dd>
        </div>
    );
}
