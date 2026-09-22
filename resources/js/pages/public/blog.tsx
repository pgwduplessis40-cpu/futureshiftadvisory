import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import { BackToTop } from '@/components/public/back-to-top';
import {
    GoldRule,
    Section,
    SectionEyebrow,
    SectionLead,
    SectionTitle,
} from '@/components/public/section';
import { Seo } from '@/components/public/seo';
import { breadcrumbLd } from '@/lib/structured-data';
import type { SharedPageProps } from '@/types';

type PostSummary = {
    slug: string;
    title: string;
    description: string;
    date: string;
    date_iso: string;
};

export default function Blog({ posts }: { posts: PostSummary[] }) {
    const base = usePage<SharedPageProps>().props.publicUrl ?? '';

    return (
        <>
            <Seo
                title="Blog - practical advice for New Zealand founders and SMEs"
                description="Honest, evidence-based writing on starting and building a business in New Zealand - idea validation, business plans, funding, and running a good SME."
                jsonLd={breadcrumbLd(base, [
                    { name: 'Home', path: '/' },
                    { name: 'Blog', path: '/blog' },
                ])}
            />

            <Section className="pt-20 pb-16 lg:pt-24">
                <SectionEyebrow>Blog</SectionEyebrow>
                <SectionTitle as="h1" className="mt-4">
                    Notes on building a{' '}
                    <span className="font-accent text-[var(--fs-cognac)] italic">
                        good business.
                    </span>
                </SectionTitle>
                <GoldRule className="mt-6" />
                <SectionLead>
                    Practical, honest writing for New&nbsp;Zealand founders and
                    SME owners - the same evidence-based thinking we bring to
                    the work, shared here for anyone who finds it useful.
                </SectionLead>
            </Section>

            <Section className="pb-20">
                {posts.length === 0 ? (
                    <p className="text-base text-[var(--fs-graphite)]">
                        The first posts are on their way - check back soon.
                    </p>
                ) : (
                    <div className="space-y-6">
                        {posts.map((post) => (
                            <Link
                                key={post.slug}
                                href={`/blog/${post.slug}`}
                                className="group block rounded-xl border border-[var(--fs-sand)] bg-white p-6 shadow-[0_1px_2px_rgba(28,43,69,0.04)] transition hover:shadow-[0_8px_24px_rgba(28,43,69,0.08)] md:p-8"
                            >
                                <time
                                    dateTime={post.date_iso}
                                    className="eyebrow"
                                >
                                    {post.date}
                                </time>
                                <h2 className="font-display mt-3 text-2xl text-[var(--fs-admiralty)] sm:text-3xl">
                                    {post.title}
                                </h2>
                                {post.description ? (
                                    <p className="mt-3 max-w-3xl text-base leading-relaxed text-[var(--fs-graphite)]">
                                        {post.description}
                                    </p>
                                ) : null}
                                <span className="mt-5 inline-flex items-center gap-1 text-sm font-medium text-[var(--fs-admiralty)] transition group-hover:text-[var(--fs-pacific)]">
                                    Read the post{' '}
                                    <ArrowRight className="h-4 w-4" />
                                </span>
                            </Link>
                        ))}
                    </div>
                )}
            </Section>

            <BackToTop />
        </>
    );
}
