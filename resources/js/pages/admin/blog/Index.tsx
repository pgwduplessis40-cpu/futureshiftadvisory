import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarClock,
    Eye,
    FilePlus,
    Pencil,
    Send,
    Trash2,
    Undo2,
    Upload,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    batchImport,
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
    status: 'draft' | 'published' | 'scheduled';
    published_at: string | null;
    published_revision_at: string | null;
    scheduled_at: string | null;
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

function formatDateTime(value: string | null): string {
    if (!value) {
        return 'Not scheduled';
    }

    return new Intl.DateTimeFormat('en-NZ', {
        dateStyle: 'medium',
        timeStyle: 'short',
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

    function unpublishPost(post: BlogPostSummary) {
        if (
            !window.confirm(
                `Unpublish “${post.title || post.slug}”? It will no longer be visible on the public blog.`,
            )
        ) {
            return;
        }

        router.post(unpublish(post.id).url);
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
                        <Link href={batchImport()}>
                            <Button variant="outline" type="button">
                                <Upload className="size-4" aria-hidden="true" />
                                Import multiple .md files
                            </Button>
                        </Link>
                        <Link href={create()}>
                            <Button variant="outline" type="button">
                                <Upload className="size-4" aria-hidden="true" />
                                Import one .md file
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
                                        Publication
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
                                                    : post.status ===
                                                        'scheduled'
                                                      ? 'Scheduled'
                                                      : 'Draft'}
                                            </Badge>
                                            {post.has_pending_changes ? (
                                                <p className="mt-1 text-xs text-amber-700">
                                                    {post.status === 'scheduled'
                                                        ? 'Working changes are not scheduled'
                                                        : 'Pending changes'}
                                                </p>
                                            ) : null}
                                        </td>
                                        <td
                                            className="px-4 py-3 text-sm"
                                            data-label="Published"
                                        >
                                            {post.status === 'scheduled' ? (
                                                <span>
                                                    Scheduled for{' '}
                                                    {formatDateTime(
                                                        post.scheduled_at,
                                                    )}
                                                </span>
                                            ) : (
                                                formatDate(post.published_at)
                                            )}
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
                                            <div className="flex flex-wrap justify-end gap-2">
                                                <ActionTooltip
                                                    label={`Edit the working copy of ${post.title || post.slug}`}
                                                >
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-label={`Edit ${post.title || post.slug}`}
                                                    >
                                                        <Link
                                                            href={edit(post.id)}
                                                        >
                                                            <Pencil
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                </ActionTooltip>
                                                <ActionTooltip
                                                    label={`Preview the working copy of ${post.title || post.slug}`}
                                                >
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-label={`Preview ${post.title || post.slug}`}
                                                    >
                                                        <Link
                                                            href={preview(
                                                                post.id,
                                                            )}
                                                        >
                                                            <Eye
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                            Preview
                                                        </Link>
                                                    </Button>
                                                </ActionTooltip>
                                                {post.status === 'published' ? (
                                                    <>
                                                        <ActionTooltip
                                                            label={`Open the live post ${post.title || post.slug}`}
                                                        >
                                                            <Button
                                                                asChild
                                                                variant="ghost"
                                                                size="sm"
                                                            >
                                                                <Link
                                                                    href={`/blog/${post.slug}`}
                                                                    aria-label={`View live ${post.title || post.slug}`}
                                                                >
                                                                    <Eye
                                                                        className="size-4"
                                                                        aria-hidden="true"
                                                                    />
                                                                    View live
                                                                </Link>
                                                            </Button>
                                                        </ActionTooltip>
                                                        <ActionTooltip
                                                            label={`Remove ${post.title || post.slug} from the public blog`}
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                aria-label={`Unpublish ${post.title || post.slug}`}
                                                                onClick={() =>
                                                                    unpublishPost(
                                                                        post,
                                                                    )
                                                                }
                                                            >
                                                                <Undo2
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                                Unpublish
                                                            </Button>
                                                        </ActionTooltip>
                                                    </>
                                                ) : post.status ===
                                                  'scheduled' ? (
                                                    <ActionTooltip
                                                        label={`Manage the automatic publication time for ${post.title || post.slug}`}
                                                    >
                                                        <Button
                                                            asChild
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            <Link
                                                                href={edit(
                                                                    post.id,
                                                                )}
                                                                aria-label={`Manage schedule for ${post.title || post.slug}`}
                                                            >
                                                                <CalendarClock
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                                Manage schedule
                                                            </Link>
                                                        </Button>
                                                    </ActionTooltip>
                                                ) : (
                                                    <>
                                                        <ActionTooltip
                                                            label={`Choose when ${post.title || post.slug} should automatically publish`}
                                                        >
                                                            <Button
                                                                asChild
                                                                variant="ghost"
                                                                size="sm"
                                                            >
                                                                <Link
                                                                    href={edit(
                                                                        post.id,
                                                                    )}
                                                                    aria-label={`Schedule ${post.title || post.slug}`}
                                                                >
                                                                    <CalendarClock
                                                                        className="size-4"
                                                                        aria-hidden="true"
                                                                    />
                                                                    Schedule
                                                                </Link>
                                                            </Button>
                                                        </ActionTooltip>
                                                        <ActionTooltip
                                                            label={`Publish ${post.title || post.slug} immediately`}
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                aria-label={`Publish ${post.title || post.slug}`}
                                                                onClick={() =>
                                                                    router.post(
                                                                        publish(
                                                                            post.id,
                                                                        ).url,
                                                                    )
                                                                }
                                                            >
                                                                <Send
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                                Publish now
                                                            </Button>
                                                        </ActionTooltip>
                                                    </>
                                                )}
                                                <ActionTooltip
                                                    label={`Permanently delete ${post.title || post.slug}`}
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-label={`Delete ${post.title || post.slug}`}
                                                        onClick={() =>
                                                            destroy(post)
                                                        }
                                                    >
                                                        <Trash2
                                                            className="size-4 text-destructive"
                                                            aria-hidden="true"
                                                        />
                                                        Delete
                                                    </Button>
                                                </ActionTooltip>
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

function ActionTooltip({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>{children}</TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
