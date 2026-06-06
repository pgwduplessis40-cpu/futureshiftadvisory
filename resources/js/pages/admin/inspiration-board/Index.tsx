import { Head, router, useForm } from '@inertiajs/react';
import { HeartHandshake, Pin, PinOff, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type PostType = 'message' | 'quote' | 'image';

type BoardPost = {
    id: string;
    type: PostType;
    title: string | null;
    body: string | null;
    attribution: string | null;
    status: 'draft' | 'published' | 'archived';
    pinned: boolean;
    image_url: string | null;
    image_filename: string | null;
    published_at: string | null;
    created_by: string | null;
    created_at: string | null;
};

type Props = {
    posts: BoardPost[];
    storeUrl: string;
};

const typeLabels: Record<PostType, string> = {
    message: 'Message',
    quote: 'Quote',
    image: 'Image',
};

const statusVariants: Record<
    BoardPost['status'],
    'default' | 'secondary' | 'outline'
> = {
    published: 'default',
    draft: 'secondary',
    archived: 'outline',
};

function action(id: string, verb: string): void {
    router.post(
        `/admin/inspiration-board/${id}/${verb}`,
        {},
        {
            preserveScroll: true,
        },
    );
}

export default function InspirationBoardIndex({ posts, storeUrl }: Props) {
    const form = useForm<{
        type: PostType;
        title: string;
        body: string;
        attribution: string;
        image: File | null;
    }>({
        type: 'quote',
        title: '',
        body: '',
        attribution: '',
        image: null,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(storeUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <>
            <Head title="Inspiration board" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Engagement"
                    icon={HeartHandshake}
                    title="Inspiration board"
                    description="Post motivational messages, quotes, or images for clients and entrepreneurs. Everything is kept in the library to reuse later."
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="lg:col-span-1">
                        <form
                            onSubmit={submit}
                            className="space-y-4 rounded-md border bg-background p-4"
                        >
                            <div className="space-y-2">
                                <Label>Type</Label>
                                <Select
                                    value={form.data.type}
                                    onValueChange={(value) =>
                                        form.setData('type', value as PostType)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="quote">
                                            Quote
                                        </SelectItem>
                                        <SelectItem value="message">
                                            Message
                                        </SelectItem>
                                        <SelectItem value="image">
                                            Image
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="title">Title (optional)</Label>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(event) =>
                                        form.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.title} />
                            </div>

                            {form.data.type === 'image' ? (
                                <div className="space-y-2">
                                    <Label htmlFor="image">Image</Label>
                                    <input
                                        id="image"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp,image/gif"
                                        onChange={(event) =>
                                            form.setData(
                                                'image',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                        className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border file:bg-muted file:px-3 file:py-1.5 file:text-sm"
                                    />
                                    <InputError message={form.errors.image} />
                                    <Label
                                        htmlFor="body"
                                        className="pt-2 text-xs text-muted-foreground"
                                    >
                                        Caption (optional)
                                    </Label>
                                    <textarea
                                        id="body"
                                        value={form.data.body}
                                        rows={2}
                                        onChange={(event) =>
                                            form.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                        className="min-h-16 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    />
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <Label htmlFor="body">
                                        {form.data.type === 'quote'
                                            ? 'Quote'
                                            : 'Message'}
                                    </Label>
                                    <textarea
                                        id="body"
                                        value={form.data.body}
                                        rows={5}
                                        onChange={(event) =>
                                            form.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                        className="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        required
                                    />
                                    <InputError message={form.errors.body} />
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="attribution">
                                    Attribution (optional)
                                </Label>
                                <Input
                                    id="attribution"
                                    placeholder="e.g. Maya Angelou"
                                    value={form.data.attribution}
                                    onChange={(event) =>
                                        form.setData(
                                            'attribution',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.attribution} />
                            </div>

                            <p className="text-xs text-muted-foreground">
                                Saved to the library as a draft. Publish it to
                                show it on portals.
                            </p>
                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    <Send
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Add to library
                                </Button>
                            </div>
                        </form>
                    </section>

                    <section className="space-y-3 lg:col-span-2">
                        {posts.length === 0 ? (
                            <EmptyState
                                icon={HeartHandshake}
                                title="No posts yet"
                                description="Add your first message, quote, or image — it stays in the library to reuse later."
                            />
                        ) : (
                            posts.map((post) => (
                                <SectionCard
                                    key={post.id}
                                    className="space-y-3"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge variant="outline">
                                            {typeLabels[post.type]}
                                        </Badge>
                                        <Badge
                                            variant={
                                                statusVariants[post.status]
                                            }
                                        >
                                            {post.status}
                                        </Badge>
                                        {post.pinned && (
                                            <Badge variant="secondary">
                                                <Pin
                                                    className="mr-1 size-3"
                                                    aria-hidden="true"
                                                />
                                                Pinned
                                            </Badge>
                                        )}
                                        <span className="ml-auto text-xs text-muted-foreground">
                                            {post.created_by ?? 'admin'}
                                        </span>
                                    </div>

                                    {post.title && (
                                        <p className="text-sm font-semibold">
                                            {post.title}
                                        </p>
                                    )}
                                    {post.type === 'image' && post.image_url ? (
                                        <img
                                            src={post.image_url}
                                            alt={post.title ?? 'Inspiration'}
                                            className="max-h-48 rounded-md object-cover"
                                        />
                                    ) : null}
                                    {post.body && (
                                        <p className="text-sm whitespace-pre-line text-muted-foreground">
                                            {post.body}
                                        </p>
                                    )}
                                    {post.attribution && (
                                        <p className="text-xs text-muted-foreground">
                                            — {post.attribution}
                                        </p>
                                    )}

                                    <div className="flex flex-wrap gap-2 pt-1">
                                        {post.status !== 'published' && (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    action(post.id, 'publish')
                                                }
                                            >
                                                Publish
                                            </Button>
                                        )}
                                        {post.status === 'published' &&
                                            (post.pinned ? (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        action(post.id, 'unpin')
                                                    }
                                                >
                                                    <PinOff
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Unpin
                                                </Button>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        action(post.id, 'pin')
                                                    }
                                                >
                                                    <Pin
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Pin
                                                </Button>
                                            ))}
                                        {post.status !== 'archived' && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    action(post.id, 'archive')
                                                }
                                            >
                                                Archive
                                            </Button>
                                        )}
                                    </div>
                                </SectionCard>
                            ))
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}
