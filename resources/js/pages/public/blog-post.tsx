import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';

import { BackToTop } from '@/components/public/back-to-top';
import { GoldRule, Section } from '@/components/public/section';
import { Seo } from '@/components/public/seo';
import { blogPostingLd, breadcrumbLd } from '@/lib/structured-data';
import type { SharedPageProps } from '@/types';

type Post = {
    slug: string;
    title: string;
    description: string;
    date: string;
    date_iso: string;
    date_modified_iso: string;
    html: string;
};

type RelatedPost = {
    slug: string;
    title: string;
    description: string;
    date: string;
    date_iso: string;
};

type Preview = {
    backUrl: string;
};

export default function BlogPost({
    post,
    related = [],
    preview,
}: {
    post: Post;
    related?: RelatedPost[];
    preview?: Preview;
}) {
    const base = usePage<SharedPageProps>().props.publicUrl ?? '';
    const path = `/blog/${post.slug}`;

    return (
        <>
            <Seo
                title={post.title}
                description={post.description}
                type="article"
                jsonLd={[
                    blogPostingLd(base, {
                        title: post.title,
                        description: post.description,
                        path,
                        dateIso: post.date_iso,
                        dateModifiedIso: post.date_modified_iso,
                    }),
                    breadcrumbLd(base, [
                        { name: 'Home', path: '/' },
                        { name: 'Blog', path: '/blog' },
                        { name: post.title, path },
                    ]),
                ]}
            />

            <Section className="pt-20 pb-16 lg:pt-24">
                {preview ? (
                    <aside className="mb-8 flex flex-wrap items-center justify-between gap-4 rounded-md border border-[var(--fs-sand)] bg-[var(--fs-linen)] p-4">
                        <div>
                            <p className="text-sm font-semibold text-[var(--fs-admiralty)]">
                                Administrator preview
                            </p>
                            <p className="mt-1 text-sm text-[var(--fs-graphite)]">
                                This view includes draft changes and is not a
                                public page.
                            </p>
                        </div>
                        <Link
                            href={preview.backUrl}
                            className="inline-flex items-center gap-2 rounded-md bg-[var(--fs-admiralty)] px-4 py-2 text-sm font-medium text-[var(--fs-parchment)] transition-colors hover:bg-[var(--fs-commodore)]"
                        >
                            <ArrowLeft className="h-4 w-4" /> Back to Blog admin
                        </Link>
                    </aside>
                ) : (
                    <Link
                        href="/blog"
                        className="inline-flex items-center gap-2 text-sm font-medium text-[var(--fs-admiralty)] hover:text-[var(--fs-pacific)]"
                    >
                        <ArrowLeft className="h-4 w-4" /> All posts
                    </Link>
                )}

                <time
                    dateTime={post.date_iso}
                    className={`eyebrow block ${preview ? '' : 'mt-8'}`}
                >
                    {post.date}
                </time>
                <h1 className="font-display mt-3 max-w-3xl text-4xl leading-tight text-[var(--fs-admiralty)] sm:text-5xl">
                    {post.title}
                </h1>
                <GoldRule className="mt-6" />

                <article
                    className="blog-content mt-10 max-w-3xl"
                    dangerouslySetInnerHTML={{ __html: post.html }}
                />

                {/* ── CLOSING CTA ─────────────────────────────── */}
                <div className="mt-14 max-w-3xl rounded-xl border border-[var(--fs-sand)] bg-[var(--fs-linen)] p-6 md:p-8">
                    <h2 className="font-display text-2xl text-[var(--fs-admiralty)]">
                        Thinking about your own idea?
                    </h2>
                    <p className="mt-3 text-base leading-relaxed text-[var(--fs-graphite)]">
                        Idea validation is the cheapest step to get right first
                        - an honest, evidence-based read on whether it holds up,
                        before you spend real money.
                    </p>
                    <div className="mt-6 flex flex-wrap items-center gap-4">
                        <Link
                            href="/services/entrepreneur"
                            className="inline-flex items-center gap-2 rounded-md bg-[var(--fs-admiralty)] px-5 py-3 text-sm font-medium text-[var(--fs-parchment)] shadow-sm transition-colors hover:bg-[var(--fs-commodore)]"
                        >
                            How idea validation works{' '}
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                        <Link
                            href="/contact"
                            className="inline-flex items-center gap-2 text-sm font-medium text-[var(--fs-admiralty)] hover:text-[var(--fs-pacific)]"
                        >
                            Or book a discovery call{' '}
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>
                </div>

                {/* ── KEEP READING ────────────────────────────── */}
                {!preview && related.length > 0 ? (
                    <div className="mt-16 max-w-3xl border-t border-[var(--fs-sand)] pt-10">
                        <h2 className="font-display text-2xl text-[var(--fs-admiralty)]">
                            Keep reading
                        </h2>
                        <div className="mt-6 space-y-5">
                            {related.map((item) => (
                                <Link
                                    key={item.slug}
                                    href={`/blog/${item.slug}`}
                                    className="group block rounded-xl border border-[var(--fs-sand)] bg-white p-5 shadow-[0_1px_2px_rgba(28,43,69,0.04)] transition hover:shadow-[0_8px_24px_rgba(28,43,69,0.08)]"
                                >
                                    <time
                                        dateTime={item.date_iso}
                                        className="eyebrow"
                                    >
                                        {item.date}
                                    </time>
                                    <h3 className="font-display mt-2 text-xl text-[var(--fs-admiralty)]">
                                        {item.title}
                                    </h3>
                                    {item.description ? (
                                        <p className="mt-2 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                            {item.description}
                                        </p>
                                    ) : null}
                                    <span className="mt-4 inline-flex items-center gap-1 text-sm font-medium text-[var(--fs-admiralty)] transition group-hover:text-[var(--fs-pacific)]">
                                        Read the post{' '}
                                        <ArrowRight className="h-4 w-4" />
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </div>
                ) : null}
            </Section>

            <BackToTop />
        </>
    );
}
