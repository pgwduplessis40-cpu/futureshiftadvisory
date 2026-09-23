import { Head, Link, router } from '@inertiajs/react';
import {
    Eye,
    FilePlus,
    Pencil,
    Send,
    Trash2,
    Undo2,
    Upload,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    create,
    destroy as destroyPost,
    edit,
    preview,
    publish,
    unpublish,
} from '@/routes/admin/blog';

type BlogPostSummary = {
    id: string;
    title: string;
    slug: string;
    status: 'draft' | 'published';
    published_at: string | null;
    published_revision_at: string | null;
    updated_at: string;
    has_pending_changes: boolean;
};

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not published';
    }

    return new Intl.DateTimeFormat('en-NZ', {
        dateStyle: 'medium',
        timeZone: 'Pacific/Auckland',
    }).format(new Date(value));
}

export default function BlogIndex({ posts }: { posts: BlogPostSummary[] }) {
    function destroy(post: BlogPostSummary) {
        if (
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
            <Head title="Blog" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="eyebrow">Administration</p>
                        <h1 className="font-display mt-2 text-3xl text-[var(--fs-admiralty)]">
                            Blog
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                            Draft, review, and publish site posts without a
                            deployment.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href={create()}>
                            <Button variant="outline" type="button">
                                <Upload className="size-4" aria-hidden="true" />
                                Import .md
                            </Button>
                        </Link>
                        <Link href={create()}>
                            <Button type="button">
                                <FilePlus
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                New post
                            </Button>
                        </Link>
                    </div>
                </div>

                {posts.length === 0 ? (
                    <section className="rounded-md border bg-background p-6 text-sm text-muted-foreground">
                        No posts yet. Create a draft or import a Markdown file
                        to begin.
                    </section>
                ) : (
                    <section className="overflow-hidden rounded-md border bg-background">
                        <table className="fsa-responsive-table w-full">
                            <thead className="bg-muted/60 text-left text-sm">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Post
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Published
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Last saved
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {posts.map((post) => (
                                    <tr
                                        key={post.id}
                                        className="border-t align-top"
                                    >
                                        <td
                                            className="px-4 py-3"
                                            data-label="Post"
                                        >
                                            <p className="font-medium">
                                                {post.title || 'Untitled draft'}
                                            </p>
                                            <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                /blog/{post.slug || '…'}
                                            </p>
                                        </td>
                                        <td
                                            className="px-4 py-3"
                                            data-label="Status"
                                        >
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
                                            {post.has_pending_changes ? (
                                                <p className="mt-1 text-xs text-amber-700">
                                                    Pending changes
                                                </p>
                                            ) : null}
                                        </td>
                                        <td
                                            className="px-4 py-3 text-sm"
                                            data-label="Published"
                                        >
                                            {formatDate(post.published_at)}
                                        </td>
                                        <td
                                            className="px-4 py-3 text-sm"
                                            data-label="Last saved"
                                        >
                                            {formatDate(post.updated_at)}
                                        </td>
                                        <td
                                            className="px-4 py-3"
                                            data-label="Actions"
                                        >
                                            <div className="flex justify-end gap-1">
                                                <Link href={edit(post.id)}>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Edit ${post.title || post.slug}`}
                                                    >
                                                        <Pencil
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Button>
                                                </Link>
                                                <Link href={preview(post.id)}>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Preview ${post.title || post.slug}`}
                                                    >
                                                        <Eye
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Button>
                                                </Link>
                                                {post.status === 'published' ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Unpublish ${post.title || post.slug}`}
                                                        onClick={() =>
                                                            router.post(
                                                                unpublish(
                                                                    post.id,
                                                                ).url,
                                                            )
                                                        }
                                                    >
                                                        <Undo2
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Button>
                                                ) : (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Publish ${post.title || post.slug}`}
                                                        onClick={() =>
                                                            router.post(
                                                                publish(post.id)
                                                                    .url,
                                                            )
                                                        }
                                                    >
                                                        <Send
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Button>
                                                )}
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Delete ${post.title || post.slug}`}
                                                    onClick={() =>
                                                        destroy(post)
                                                    }
                                                >
                                                    <Trash2
                                                        className="size-4 text-destructive"
                                                        aria-hidden="true"
                                                    />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                )}
            </div>
        </>
    );
}
