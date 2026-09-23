import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, Save, Send, Trash2, Undo2, Upload } from 'lucide-react';
import type { ChangeEvent, FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy as destroyPost,
    importMethod as importMarkdownPost,
    index as blogIndex,
    preview,
    publish,
    store,
    unpublish,
    update,
} from '@/routes/admin/blog';

type EditorPost = {
    id: string;
    title: string;
    slug: string;
    description: string;
    body: string;
    status: 'draft' | 'published';
    published_at: string | null;
    published_revision_at: string | null;
    slug_locked: boolean;
};

type WorkingPost = {
    title: string;
    slug: string;
    description: string;
    body: string;
};

function slugify(title: string): string {
    return title
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}

export default function BlogEdit({
    post,
    import: imported,
}: {
    post: EditorPost | null;
    import: WorkingPost | null;
}) {
    const [slugEdited, setSlugEdited] = useState(
        Boolean(post?.slug || imported?.slug),
    );
    const data = post ??
        imported ?? { title: '', slug: '', description: '', body: '' };
    const form = useForm<WorkingPost>(data);
    const importForm = useForm<{ file: File | null }>({ file: null });
    const isNew = post === null;
    const slugLocked = post?.slug_locked ?? false;

    function submit(event: FormEvent) {
        event.preventDefault();

        if (isNew) {
            form.post(store.url());

            return;
        }

        form.patch(update(post.id).url);
    }

    function setTitle(title: string) {
        form.setData('title', title);

        if (!slugLocked && !slugEdited) {
            form.setData('slug', slugify(title));
        }
    }

    function importMarkdown(event: FormEvent) {
        event.preventDefault();
        importForm.post(importMarkdownPost.url(), { forceFormData: true });
    }

    function selectFile(event: ChangeEvent<HTMLInputElement>) {
        importForm.setData('file', event.target.files?.[0] ?? null);
    }

    function destroy() {
        if (
            !post ||
            !window.confirm(
                `Delete “${post.title || post.slug}”? This cannot be undone.`,
            )
        ) {
            return;
        }

        router.delete(destroyPost(post.id).url);
    }

    return (
        <>
            <Head
                title={
                    isNew
                        ? 'New blog post'
                        : `Edit ${post.title || 'blog post'}`
                }
            />

            <div className="mx-auto max-w-5xl space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Link
                            href={blogIndex()}
                            className="text-sm text-muted-foreground hover:underline"
                        >
                            All blog posts
                        </Link>
                        <h1 className="font-display mt-2 text-3xl text-[var(--fs-admiralty)]">
                            {isNew
                                ? 'New blog post'
                                : post.title || 'Untitled draft'}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Draft edits stay private until you explicitly
                            publish them.
                        </p>
                    </div>
                    {post ? (
                        <Badge
                            variant={
                                post.status === 'published'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {post.status === 'published'
                                ? 'Published'
                                : 'Draft'}
                        </Badge>
                    ) : null}
                </div>

                {isNew ? (
                    <form
                        onSubmit={importMarkdown}
                        className="rounded-md border bg-muted/30 p-4"
                    >
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="min-w-64 flex-1">
                                <Label htmlFor="markdown-file">
                                    Import existing Markdown
                                </Label>
                                <Input
                                    id="markdown-file"
                                    type="file"
                                    accept=".md,text/markdown,text/plain"
                                    className="mt-2"
                                    onChange={selectFile}
                                />
                                <InputError message={importForm.errors.file} />
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Up to 512 KB. The file is read only to
                                    pre-fill this form and is not stored.
                                </p>
                            </div>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={
                                    !importForm.data.file ||
                                    importForm.processing
                                }
                            >
                                <Upload className="size-4" aria-hidden="true" />
                                Import .md
                            </Button>
                        </div>
                    </form>
                ) : null}

                <form
                    onSubmit={submit}
                    className="space-y-5 rounded-md border bg-background p-5"
                >
                    <div className="grid gap-5 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="title">Title</Label>
                            <Input
                                id="title"
                                value={form.data.title}
                                maxLength={200}
                                onChange={(event) =>
                                    setTitle(event.target.value)
                                }
                            />
                            <InputError message={form.errors.title} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="slug">Slug</Label>
                            <Input
                                id="slug"
                                value={form.data.slug}
                                maxLength={200}
                                readOnly={slugLocked}
                                onChange={(event) => {
                                    setSlugEdited(true);
                                    form.setData('slug', event.target.value);
                                }}
                            />
                            <InputError message={form.errors.slug} />
                            <p className="text-xs text-muted-foreground">
                                {slugLocked
                                    ? 'The public URL is frozen after first publication.'
                                    : 'Generated from the title; you can edit it before publication.'}
                            </p>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="description">Description</Label>
                        <textarea
                            id="description"
                            rows={3}
                            value={form.data.description}
                            maxLength={300}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-center justify-between gap-3">
                            <Label htmlFor="body">Body (Markdown)</Label>
                            <span className="text-xs text-muted-foreground">
                                {form.data.body.length.toLocaleString()} /
                                100,000
                            </span>
                        </div>
                        <textarea
                            id="body"
                            rows={24}
                            value={form.data.body}
                            maxLength={100000}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                            className="min-h-96 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <InputError message={form.errors.body} />
                    </div>

                    <div className="flex flex-wrap justify-between gap-3 border-t pt-5">
                        <div className="flex flex-wrap gap-2">
                            {post ? (
                                <Link href={preview(post.id)}>
                                    <Button type="button" variant="outline">
                                        <Eye
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Preview
                                    </Button>
                                </Link>
                            ) : null}
                            {post?.status === 'published' ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.post(unpublish(post.id).url)
                                    }
                                >
                                    <Undo2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Unpublish
                                </Button>
                            ) : null}
                            {post ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={destroy}
                                >
                                    <Trash2
                                        className="size-4 text-destructive"
                                        aria-hidden="true"
                                    />
                                    Delete
                                </Button>
                            ) : null}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={form.processing}
                            >
                                <Save className="size-4" aria-hidden="true" />
                                Save draft
                            </Button>
                            {post ? (
                                <Button
                                    type="button"
                                    disabled={form.processing}
                                    onClick={() =>
                                        router.post(publish(post.id).url)
                                    }
                                >
                                    <Send
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Publish
                                </Button>
                            ) : null}
                        </div>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        A draft may be incomplete. Publishing requires a title,
                        description, body, and valid slug.
                    </p>
                </form>
            </div>
        </>
    );
}
