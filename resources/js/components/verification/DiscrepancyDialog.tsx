import { router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { update as updateDocumentVerification } from '@/routes/advisor/document-verifications';
import type { VerificationOutcome } from './Badge';
import { VerificationBadge } from './Badge';

type Props = {
    id: string;
    outcome: VerificationOutcome;
    claim: string;
    explanation?: string | null;
    documentName?: string | null;
    clientName?: string | null;
};

export function DiscrepancyDialog({
    id,
    outcome,
    claim,
    explanation,
    documentName,
    clientName,
}: Props) {
    const [open, setOpen] = useState(false);
    const [resolutionNote, setResolutionNote] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>();
    const trimmedNote = resolutionNote.trim();
    const canResolve = trimmedNote.length > 0 && !processing;

    const resolveFlag = () => {
        if (!canResolve) {
            return;
        }

        router.patch(
            updateDocumentVerification.url(id),
            { resolution_note: trimmedNote },
            {
                preserveScroll: true,
                onStart: () => {
                    setProcessing(true);
                    setError(undefined);
                },
                onError: (errors) => {
                    setError(
                        typeof errors.resolution_note === 'string'
                            ? errors.resolution_note
                            : 'The review note could not be saved.',
                    );
                },
                onSuccess: () => {
                    setOpen(false);
                    setResolutionNote('');
                    toast.success('Document verification flag resolved.');
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
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
                    <label className="grid gap-2 font-medium">
                        Advisor review note
                        <Textarea
                            value={resolutionNote}
                            onChange={(event) =>
                                setResolutionNote(event.target.value)
                            }
                            rows={4}
                            maxLength={2000}
                            placeholder="Record what you checked and why this can be accepted, corrected, or followed up."
                            aria-invalid={Boolean(error)}
                        />
                    </label>
                    <InputError message={error} />
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(false)}
                        disabled={processing}
                    >
                        Close
                    </Button>
                    <Button
                        type="button"
                        onClick={resolveFlag}
                        disabled={!canResolve}
                    >
                        <CheckCircle2 className="size-4" aria-hidden="true" />
                        {processing ? 'Saving review' : 'Resolve flag'}
                    </Button>
                </DialogFooter>
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
