import { Quote, Sparkles } from 'lucide-react';

export type InspirationPost = {
    id: string;
    type: 'message' | 'quote' | 'image';
    title: string | null;
    body: string | null;
    attribution: string | null;
    image_url: string | null;
    published_at: string | null;
};

export function InspirationCard({
    post,
    compact = false,
}: {
    post: InspirationPost;
    compact?: boolean;
}) {
    return (
        <section
            aria-label="Inspiration"
            className="overflow-hidden rounded-md border border-[var(--fs-linen)] bg-[var(--fs-linen)]/50"
        >
            {post.type === 'image' && post.image_url ? (
                <figure className="m-0">
                    <img
                        src={post.image_url}
                        alt={post.title ?? 'Inspiration'}
                        loading="lazy"
                        className="max-h-96 w-full object-cover"
                    />
                    {(post.body || post.attribution || post.title) && (
                        <figcaption className="space-y-1 p-4">
                            {post.title && (
                                <p className="text-sm font-semibold">
                                    {post.title}
                                </p>
                            )}
                            {post.body && (
                                <p className="text-sm text-muted-foreground">
                                    {post.body}
                                </p>
                            )}
                            {post.attribution && (
                                <p className="text-xs text-muted-foreground">
                                    — {post.attribution}
                                </p>
                            )}
                        </figcaption>
                    )}
                </figure>
            ) : (
                <div className={compact ? 'p-4' : 'p-5'}>
                    <div className="flex items-start gap-3">
                        <div className="rounded-md bg-background/70 p-2 text-[var(--fs-admiralty)]">
                            {post.type === 'quote' ? (
                                <Quote className="size-5" aria-hidden="true" />
                            ) : (
                                <Sparkles
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            )}
                        </div>
                        <div className="space-y-2">
                            {post.title && (
                                <p className="text-sm font-semibold">
                                    {post.title}
                                </p>
                            )}
                            {post.type === 'quote' ? (
                                <blockquote className="text-sm leading-relaxed text-foreground italic">
                                    {post.body}
                                </blockquote>
                            ) : (
                                <p className="text-sm leading-relaxed whitespace-pre-line text-foreground">
                                    {post.body}
                                </p>
                            )}
                            {post.attribution && (
                                <p className="text-xs text-muted-foreground">
                                    — {post.attribution}
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </section>
    );
}
