import { Link } from '@inertiajs/react';
import { FileText, LockKeyhole, Upload } from 'lucide-react';
import FileDropzone from '@/components/file-dropzone';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';

type LockedFinancialsPanelProps = {
    file: File | null;
    uploadKey: number;
    uploading: boolean;
    uploadError: string | null;
    onboardingUrl: string;
    onFileChange: (file: File | null) => void;
    onUpload: () => void;
};

export function LockedFinancialsPanel({
    file,
    uploadKey,
    uploading,
    uploadError,
    onboardingUrl,
    onFileChange,
    onUpload,
}: LockedFinancialsPanelProps) {
    return (
        <section className="grid gap-4 rounded-md border bg-background p-4 lg:grid-cols-[1fr_420px]">
            <div className="space-y-3">
                <div className="flex items-center gap-2 text-sm font-medium">
                    <LockKeyhole className="size-4" aria-hidden="true" />
                    Budget locked until financials are uploaded
                </div>
                <p className="text-sm text-muted-foreground">
                    Upload a P&amp;L or management accounts file. The system
                    will unlock a preliminary budget shell and request extra
                    files if the financial base is incomplete.
                </p>
                <Button asChild variant="outline">
                    <Link href={onboardingUrl}>
                        <FileText className="size-4" aria-hidden="true" />
                        Open onboarding documents
                    </Link>
                </Button>
            </div>
            <div className="space-y-3 rounded-md border bg-muted/20 p-3">
                <FileDropzone
                    key={uploadKey}
                    id="strategic_budget_financial_upload"
                    files={file ? [file] : []}
                    label="Upload P&L or management accounts"
                    disabled={uploading}
                    onFilesChange={(files) => onFileChange(files[0] ?? null)}
                />
                <InputError message={uploadError ?? undefined} />
                {uploading ? (
                    <span
                        className="text-xs text-muted-foreground"
                        role="status"
                    >
                        Uploading financials…
                    </span>
                ) : uploadError && file ? (
                    <Button type="button" onClick={onUpload}>
                        <Upload className="size-4" aria-hidden="true" />
                        Retry upload
                    </Button>
                ) : null}
            </div>
        </section>
    );
}
