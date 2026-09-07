import { AlertTriangle } from 'lucide-react';

export function OcrVerificationNotice() {
    return (
        <div className="flex gap-2 rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-xs text-foreground">
            <AlertTriangle
                className="mt-0.5 size-3.5 shrink-0 text-destructive"
                aria-hidden="true"
            />
            <span>
                The Official Cash Rate is not shown because the available
                reading is stub or degraded data. Refresh the RBNZ source or
                record a verified manual reference value before using OCR-linked
                advice.
            </span>
        </div>
    );
}
