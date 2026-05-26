import { Upload, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { DragEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    files: File[];
    onFilesChange: (files: File[]) => void;
    accept?: string;
    className?: string;
    description?: string;
    disabled?: boolean;
    label?: string;
    multiple?: boolean;
};

export default function FileDropzone({
    id,
    files,
    onFilesChange,
    accept,
    className,
    description,
    disabled = false,
    label = 'Upload file',
    multiple = false,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);

    useEffect(() => {
        if (files.length === 0 && inputRef.current) {
            inputRef.current.value = '';
        }
    }, [files.length]);

    const selectFiles = (fileList: FileList | null) => {
        if (disabled || !fileList) {
            return;
        }

        const incoming = Array.from(fileList);

        if (incoming.length === 0) {
            return;
        }

        if (!multiple) {
            onFilesChange(incoming.slice(0, 1));

            return;
        }

        onFilesChange(uniqueFiles([...files, ...incoming]));
    };

    const onDragOver = (event: DragEvent<HTMLDivElement>) => {
        if (disabled) {
            return;
        }

        event.preventDefault();
        setDragging(true);
    };

    const onDragLeave = (event: DragEvent<HTMLDivElement>) => {
        if (event.currentTarget.contains(event.relatedTarget as Node | null)) {
            return;
        }

        setDragging(false);
    };

    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        if (disabled) {
            return;
        }

        event.preventDefault();
        setDragging(false);
        selectFiles(event.dataTransfer.files);
    };

    return (
        <div className="grid gap-2">
            <div
                onDragOver={onDragOver}
                onDragLeave={onDragLeave}
                onDrop={onDrop}
                className={cn(
                    'rounded-md border border-dashed bg-background transition-colors',
                    dragging
                        ? 'border-primary bg-muted/60'
                        : 'border-input hover:bg-muted/40',
                    disabled && 'cursor-not-allowed opacity-60',
                    className,
                )}
            >
                <input
                    ref={inputRef}
                    id={id}
                    type="file"
                    accept={accept}
                    multiple={multiple}
                    disabled={disabled}
                    className="sr-only"
                    onChange={(event) => selectFiles(event.target.files)}
                />
                <label
                    htmlFor={id}
                    className={cn(
                        'flex cursor-pointer flex-col items-center justify-center gap-2 px-4 py-5 text-center text-sm',
                        disabled && 'pointer-events-none',
                    )}
                >
                    <Upload
                        className="size-5 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span className="font-medium">{label}</span>
                    <span className="text-xs text-muted-foreground">
                        {description ??
                            (multiple
                                ? 'Drag files here or browse'
                                : 'Drag a file here or browse')}
                    </span>
                </label>
            </div>

            {files.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                    {files.map((file) => (
                        <Badge
                            key={`${file.name}-${file.size}-${file.lastModified}`}
                            variant="secondary"
                            className="gap-2"
                        >
                            {file.name}
                            <button
                                type="button"
                                className="rounded-xs outline-none focus-visible:ring-[2px] focus-visible:ring-ring"
                                aria-label={`Remove ${file.name}`}
                                onClick={() =>
                                    onFilesChange(
                                        files.filter(
                                            (selected) =>
                                                selected.name !== file.name ||
                                                selected.size !== file.size ||
                                                selected.lastModified !==
                                                    file.lastModified,
                                        ),
                                    )
                                }
                            >
                                <X className="size-3" aria-hidden="true" />
                            </button>
                        </Badge>
                    ))}
                </div>
            ) : null}
        </div>
    );
}

function uniqueFiles(files: File[]): File[] {
    const seen = new Set<string>();

    return files.filter((file) => {
        const key = `${file.name}-${file.size}-${file.lastModified}`;

        if (seen.has(key)) {
            return false;
        }

        seen.add(key);

        return true;
    });
}
