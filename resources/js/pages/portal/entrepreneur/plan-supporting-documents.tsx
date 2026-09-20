import {
    CheckCircle2,
    FileText,
    RefreshCw,
    Trash2,
    Upload,
} from 'lucide-react';
import FileDropzone from '@/components/file-dropzone';
import { Button } from '@/components/ui/button';
import type { PlanSectionPayload } from './plan-types';

type SupportingDocument = PlanSectionPayload['attached_documents'][number];

type PlanSupportingDocumentsProps = {
    canAttachSupportingDocument: boolean;
    planChangesLocked: boolean;
    supportingKey: number;
    supportingFile: File | null;
    uploadingSupportingDocument: boolean;
    sectionError: string | null;
    pendingSupportingDocument: SupportingDocument | null;
    supportingDocumentNotice: string | null;
    supportingDocuments: SupportingDocument[];
    onFilesChange: (file: File | null) => void;
    onRemove: (document: SupportingDocument) => void;
    onRetryAttachment: () => void;
    onRetryUpload: () => void;
};

export function PlanSupportingDocuments({
    canAttachSupportingDocument,
    planChangesLocked,
    supportingKey,
    supportingFile,
    uploadingSupportingDocument,
    sectionError,
    pendingSupportingDocument,
    supportingDocumentNotice,
    supportingDocuments,
    onFilesChange,
    onRemove,
    onRetryAttachment,
    onRetryUpload,
}: PlanSupportingDocumentsProps) {
    return (
        <>
            {canAttachSupportingDocument ? (
                <FileDropzone
                    key={supportingKey}
                    id="entrepreneur-plan-support"
                    files={supportingFile ? [supportingFile] : []}
                    label="Attach supporting document"
                    disabled={planChangesLocked || uploadingSupportingDocument}
                    onFilesChange={(files) => onFilesChange(files[0] ?? null)}
                />
            ) : null}
            {uploadingSupportingDocument ? (
                <span className="text-xs text-muted-foreground" role="status">
                    Uploading supporting document…
                </span>
            ) : sectionError && pendingSupportingDocument ? (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={onRetryAttachment}
                >
                    <RefreshCw className="size-4" aria-hidden="true" />
                    Retry attaching document
                </Button>
            ) : sectionError && supportingFile ? (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={onRetryUpload}
                >
                    <Upload className="size-4" aria-hidden="true" />
                    Retry upload
                </Button>
            ) : null}
            {supportingDocumentNotice ? (
                <p
                    className="flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-400"
                    role="status"
                >
                    <CheckCircle2
                        className="size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {supportingDocumentNotice}
                </p>
            ) : null}
            {supportingDocuments.length > 0 ? (
                <div
                    className="rounded-md border border-emerald-200 bg-emerald-50/60 p-3 dark:border-emerald-900 dark:bg-emerald-950/20"
                    aria-live="polite"
                >
                    <p className="text-sm font-medium">Attached documents</p>
                    <ul className="mt-2 grid gap-2">
                        {supportingDocuments.map((document) => (
                            <li
                                key={document.id}
                                className="flex min-w-0 items-center gap-2 text-sm"
                            >
                                <FileText
                                    className="size-4 shrink-0 text-emerald-700 dark:text-emerald-400"
                                    aria-hidden="true"
                                />
                                <span className="min-w-0 truncate font-medium">
                                    {document.original_filename}
                                </span>
                                <span className="shrink-0 text-muted-foreground">
                                    Uploaded
                                </span>
                                <div className="ml-auto flex shrink-0 items-center gap-2">
                                    {document.scanner_result === 'clean' ? (
                                        <a
                                            href={document.url}
                                            className="text-primary underline-offset-4 hover:underline"
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            View
                                        </a>
                                    ) : null}
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        className="h-7 px-2 text-destructive hover:text-destructive"
                                        disabled={planChangesLocked}
                                        onClick={() => onRemove(document)}
                                    >
                                        <Trash2
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                        Remove
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </>
    );
}
