import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, FileCheck2, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    batchImportPreview,
    batchImportStore,
    index as blogIndex,
} from '@/routes/admin/blog';

type ImportedPost = {
    filename: string;
    title: string;
    slug: string;
    description: string;
    body: string;
    publish_at: string | null;
};

function importedSchedules(imports: ImportedPost[]): string[] {
    return imports.map((post) => post.publish_at ?? '');
}

export default function BlogBatchImport({
    imports,
}: {
    imports: ImportedPost[];
}) {
    const uploadForm = useForm<{ files: File[] }>({ files: [] });
    const scheduleForm = useForm<{ scheduled_at: string[] }>({
        scheduled_at: importedSchedules(imports),
    });
    const { clearErrors: clearScheduleErrors, setData: setScheduleData } =
        scheduleForm;

    useEffect(() => {
        setScheduleData('scheduled_at', importedSchedules(imports));
        clearScheduleErrors();
    }, [clearScheduleErrors, imports, setScheduleData]);

    function reviewFiles(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        uploadForm.post(batchImportPreview().url, { forceFormData: true });
    }

    function setSchedule(index: number, value: string) {
        scheduleForm.setData(
            'scheduled_at',
            scheduleForm.data.scheduled_at.map((current, currentIndex) =>
                currentIndex === index ? value : current,
            ),
        );
    }

    function createDrafts() {
        scheduleForm.post(batchImportStore().url);
    }

    return (
        <>
            <Head title="Import blog posts" />

            <div className="mx-auto max-w-5xl space-y-6">
                <div className="space-y-3">
                    <Button asChild variant="outline" size="sm">
                        <Link href={blogIndex()}>
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Back to all blog posts
                        </Link>
                    </Button>
                    <div>
                        <p className="eyebrow">Administration</p>
                        <h1 className="font-display mt-2 text-3xl text-[var(--fs-admiralty)]">
                            Import multiple blog posts
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                            Review Markdown imports before creating drafts. You
                            can also schedule each approved snapshot to publish
                            automatically in Pacific/Auckland time.
                        </p>
                    </div>
                </div>

                <form
                    onSubmit={reviewFiles}
                    className="rounded-md border bg-background p-5"
                >
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="min-w-64 flex-1">
                            <Label htmlFor="markdown-files">
                                Markdown files
                            </Label>
                            <Input
                                id="markdown-files"
                                type="file"
                                multiple
                                accept=".md,text/markdown,text/plain"
                                className="mt-2"
                                onChange={(event) =>
                                    uploadForm.setData(
                                        'files',
                                        Array.from(event.target.files ?? []),
                                    )
                                }
                            />
                            <InputError message={uploadForm.errors.files} />
                            <p className="mt-1 text-xs text-muted-foreground">
                                Choose up to 20 files, each up to 512 KB. Files
                                are read only to prepare the review queue and
                                are not stored.
                            </p>
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={
                                uploadForm.data.files.length === 0 ||
                                uploadForm.processing
                            }
                        >
                            <Upload className="size-4" aria-hidden="true" />
                            Review selected files
                        </Button>
                    </div>
                    {uploadForm.data.files.map((file, index) => (
                        <InputError
                            key={`${file.name}-${index}`}
                            message={uploadForm.errors[`files.${index}`]}
                        />
                    ))}
                </form>

                {imports.length > 0 ? (
                    <section className="overflow-hidden rounded-md border bg-background">
                        <div className="border-b bg-muted/40 px-5 py-4">
                            <h2 className="font-medium text-[var(--fs-admiralty)]">
                                Review {imports.length} imported{' '}
                                {imports.length === 1 ? 'post' : 'posts'}
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                A scheduled post publishes this exact reviewed
                                title, description, and body. A Markdown
                                publish_at value pre-fills its schedule. Clear a
                                date to create a normal draft instead. Use
                                publish_at: 2026-10-05T08:00 for an Auckland
                                publication time.
                            </p>
                        </div>
                        <div className="divide-y">
                            {imports.map((post, index) => (
                                <article
                                    key={`${post.filename}-${post.slug}`}
                                    className="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_18rem]"
                                >
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            {post.filename}
                                        </p>
                                        <h3 className="mt-1 font-medium">
                                            {post.title}
                                        </h3>
                                        <p className="mt-1 font-mono text-xs text-muted-foreground">
                                            /blog/{post.slug}
                                        </p>
                                        <p className="mt-3 text-sm text-muted-foreground">
                                            {post.description}
                                        </p>
                                    </div>
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={`scheduled-at-${index}`}
                                        >
                                            Publish date and time (NZ)
                                        </Label>
                                        <Input
                                            id={`scheduled-at-${index}`}
                                            type="datetime-local"
                                            value={
                                                scheduleForm.data.scheduled_at[
                                                    index
                                                ] ?? ''
                                            }
                                            onChange={(event) =>
                                                setSchedule(
                                                    index,
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                scheduleForm.errors[
                                                    `scheduled_at.${index}`
                                                ]
                                            }
                                        />
                                    </div>
                                </article>
                            ))}
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t bg-muted/20 px-5 py-4">
                            <p className="text-sm text-muted-foreground">
                                You can still edit a draft afterwards. Use
                                “Update scheduled version” to deliberately
                                replace a scheduled snapshot.
                            </p>
                            <Button
                                type="button"
                                disabled={scheduleForm.processing}
                                onClick={createDrafts}
                            >
                                <FileCheck2
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Create {imports.length}{' '}
                                {imports.length === 1 ? 'draft' : 'drafts'}
                            </Button>
                        </div>
                    </section>
                ) : (
                    <section className="rounded-md border border-dashed bg-muted/20 p-6 text-sm text-muted-foreground">
                        Choose one or more Markdown files to start a review
                        queue.
                    </section>
                )}
            </div>
        </>
    );
}
