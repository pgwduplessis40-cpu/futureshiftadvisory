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
    html: string;
};

export default function BlogPost({ post }: { post: Post }) {
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
                    }),
                    breadcrumbLd(base, [
                        { name: 'Home', path: '/' },
                        { name: 'Blog', path: '/blog' },
                        { name: post.title, path },
                    ]),
                ]}
            />

            <Section className="pt-20 pb-16 lg:pt-24">
                <Link
                    href="/blog"
                    className="inline-flex items-center gap-2 text-sm font-medium text-[var(--fs-admiralty)] hover:text-[var(--fs-pacific)]"
                >
                    <ArrowLeft className="h-4 w-4" /> All posts
                </Link>

                <time dateTime={post.date_iso} className="eyebrow mt-8 block">
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
            </Section>

            <BackToTop />
        </>
    );
}
