import { FileWarning } from 'lucide-react';
import type { VerificationOutcome } from './Badge';
import { VerificationBadge } from './Badge';
import { DiscrepancyDialog } from './DiscrepancyDialog';

export type DocumentVerificationFlag = {
    id: string;
    outcome: VerificationOutcome;
    claim_text: string;
    explanation?: string | null;
    client_explanation?: string | null;
    client_name?: string | null;
    document_name?: string | null;
    created_at?: string | null;
};

export function DocumentVerificationFlagPanel({
    flags,
}: {
    flags: DocumentVerificationFlag[];
}) {
    return (
        <section
            className="space-y-4 rounded-md border bg-background p-4"
            aria-labelledby="document-verification-flags-heading"
        >
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <FileWarning className="size-4" aria-hidden="true" />
                    <h2
                        id="document-verification-flags-heading"
                        className="text-sm font-medium"
                    >
                        Document verification flags
                    </h2>
                </div>
                <span className="text-xs text-muted-foreground">
                    {flags.length} open
                </span>
            </div>

            {flags.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No outstanding document verification flags.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {flags.map((flag) => (
                        <div
                            key={flag.id}
                            className="flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="min-w-0 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <VerificationBadge outcome={flag.outcome} />
                                    <span className="truncate text-sm font-medium">
                                        {flag.client_name ?? 'Unknown client'}
                                    </span>
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {flag.document_name ?? 'Uploaded document'}
                                </div>
                                <p className="line-clamp-2 text-sm">
                                    {flag.claim_text}
                                </p>
                            </div>
                            <DiscrepancyDialog
                                outcome={flag.outcome}
                                claim={flag.claim_text}
                                explanation={
                                    flag.explanation ??
                                    flag.client_explanation ??
                                    null
                                }
                                documentName={flag.document_name}
                                clientName={flag.client_name}
                            />
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}
