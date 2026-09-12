import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Check, Info } from 'lucide-react';

import { BackToTop } from '@/components/public/back-to-top';
import {
    GoldRule,
    Section,
    SectionEyebrow,
    SectionLead,
    SectionTitle,
} from '@/components/public/section';
import { Seo } from '@/components/public/seo';
import { breadcrumbLd, servicesLd } from '@/lib/structured-data';
import type { SharedPageProps } from '@/types';

type EngagementType = {
    slug: string;
    title: string;
    tagline: string;
    summary: string;
    audience: string;
    deliverables: string[];
    accent: string;
    paths?: { name: string; blurb: string }[];
    note?: string;
    detail_path?: string;
};

// FSA's focus is entrepreneurship; everything else is an additional service.
const FEATURED_SLUG = 'entrepreneur_module';

const accentBar: Record<string, string> = {
    pacific: 'bg-[var(--fs-pacific)]',
    admiralty: 'bg-[var(--fs-admiralty)]',
    'deep-cove': 'bg-[var(--fs-deep-cove)]',
    cognac: 'bg-[var(--fs-cognac)]',
    harbour: 'bg-[var(--fs-harbour)]',
};

function EngagementCard({
    e,
    eyebrow,
}: {
    e: EngagementType;
    eyebrow: string;
}) {
    return (
        <article
            id={e.slug}
            className="scroll-mt-24 rounded-xl border border-[var(--fs-sand)] bg-white p-8 shadow-[0_1px_2px_rgba(28,43,69,0.04)] md:p-10"
        >
            <div className="grid gap-10 md:grid-cols-12">
                <div className="md:col-span-7">
                    <div className="flex items-center gap-3">
                        <span
                            className={[
                                'inline-block h-2 w-10 rounded-full',
                                accentBar[e.accent] ?? 'bg-[var(--fs-pacific)]',
                            ].join(' ')}
                        />
                        <span className="eyebrow">{eyebrow}</span>
                    </div>
                    <h2 className="font-display mt-4 text-3xl text-[var(--fs-admiralty)] sm:text-4xl">
                        {e.detail_path ? (
                            <Link
                                href={e.detail_path}
                                className="transition-colors hover:text-[var(--fs-cognac)] hover:underline"
                            >
                                {e.title}
                            </Link>
                        ) : (
                            e.title
                        )}
                    </h2>
                    <p className="font-accent mt-2 text-xl text-[var(--fs-cognac)] italic">
                        {e.tagline}
                    </p>
                    <p className="mt-6 text-base leading-relaxed text-[var(--fs-graphite)]">
                        {e.summary}
                    </p>

                    <div className="mt-6 rounded-md bg-[var(--fs-linen)] px-4 py-3 text-sm text-[var(--fs-admiralty)]">
                        <span className="font-semibold">Who it’s for:</span>{' '}
                        <span className="text-[var(--fs-graphite)]">
                            {e.audience}
                        </span>
                    </div>
                </div>

                <div className="md:col-span-5">
                    <div className="rounded-lg border border-[var(--fs-sand)] bg-[var(--fs-parchment)] p-6">
                        <div className="eyebrow">What you receive</div>
                        <ul className="mt-4 space-y-3">
                            {e.deliverables.map((d) => (
                                <li
                                    key={d}
                                    className="flex gap-3 text-sm text-[var(--fs-admiralty)]"
                                >
                                    <Check className="mt-0.5 h-4 w-4 shrink-0 text-[var(--fs-pacific)]" />
                                    <span>{d}</span>
                                </li>
                            ))}
                        </ul>
                        <div className="mt-6 flex flex-col gap-2">
                            {e.detail_path && (
                                <Link
                                    href={e.detail_path}
                                    className="inline-flex items-center gap-2 text-sm font-semibold text-[var(--fs-admiralty)] hover:text-[var(--fs-pacific)]"
                                >
                                    Read more about {e.title.toLowerCase()}{' '}
                                    <ArrowRight className="h-4 w-4" />
                                </Link>
                            )}
                            <Link
                                href={`/contact?interest=${e.slug}`}
                                className="inline-flex items-center gap-2 text-sm font-medium text-[var(--fs-admiralty)] hover:text-[var(--fs-pacific)]"
                            >
                                Enquire about this{' '}
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            {/* Sub-paths (e.g. the three NPO routes) */}
            {e.paths && e.paths.length > 0 && (
                <div className="mt-8 border-t border-[var(--fs-sand)] pt-8">
                    <div className="eyebrow">Three ways we can help</div>
                    <div className="mt-4 grid gap-4 md:grid-cols-3">
                        {e.paths.map((p) => (
                            <div
                                key={p.name}
                                className="rounded-lg border border-[var(--fs-sand)] bg-[var(--fs-parchment)] p-5"
                            >
                                <h3 className="font-display text-lg text-[var(--fs-admiralty)]">
                                    {p.name}
                                </h3>
                                <p className="mt-2 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                    {p.blurb}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {e.note && (
                <p className="mt-6 flex items-start gap-2 text-sm leading-relaxed text-[var(--fs-graphite)]">
                    <Info className="mt-0.5 h-4 w-4 shrink-0 text-[var(--fs-cognac)]" />
                    <span>{e.note}</span>
                </p>
            )}
        </article>
    );
}

export default function Services({
    engagementTypes,
}: {
    engagementTypes: EngagementType[];
}) {
    const base = usePage<SharedPageProps>().props.publicUrl ?? '';

    const featured = engagementTypes.filter((e) => e.slug === FEATURED_SLUG);
    const additional = engagementTypes.filter((e) => e.slug !== FEATURED_SLUG);

    return (
        <>
            <Seo
                title="Our services - support for entrepreneurs, SMEs & not-for-profits"
                description="Helping entrepreneurs start and build good businesses is the heart of what we do. Around it: Standard Advisory, Due Diligence, Post-acquisition Advisory, and dedicated support for not-for-profits and social enterprises."
                jsonLd={[
                    servicesLd(base, engagementTypes),
                    breadcrumbLd(base, [
                        { name: 'Home', path: '/' },
                        { name: 'Services', path: '/services' },
                    ]),
                ]}
            />

            <Section className="pt-20 pb-16 lg:pt-24">
                <SectionEyebrow>Services</SectionEyebrow>
                <SectionTitle as="h1" className="mt-4">
                    Ways to{' '}
                    <span className="font-accent text-[var(--fs-cognac)] italic">
                        work with us.
                    </span>
                </SectionTitle>
                <GoldRule className="mt-6" />
                <SectionLead>
                    Helping people start and build good businesses is the heart
                    of what we do - so that is where we begin. Around it sits a
                    set of additional services for established businesses,
                    acquisitions, and not-for-profits. Every one of them shares
                    the same promise: we explain what we find in plain language,
                    and we show you the evidence behind it.
                </SectionLead>

                <nav className="mt-10 flex flex-wrap gap-3">
                    {engagementTypes.map((e) => (
                        <a
                            key={e.slug}
                            href={`#${e.slug}`}
                            className="rounded-full border border-[var(--fs-sand)] bg-white px-4 py-1.5 text-sm text-[var(--fs-admiralty)] transition hover:bg-[var(--fs-linen)]"
                        >
                            {e.title}
                        </a>
                    ))}
                </nav>
            </Section>

            <Section className="pb-20">
                {/* Featured: entrepreneurship is where we focus. */}
                <div className="space-y-16">
                    {featured.map((e) => (
                        <EngagementCard
                            key={e.slug}
                            e={e}
                            eyebrow="Where we focus"
                        />
                    ))}
                </div>

                {/* Additional services for every other stage. */}
                {additional.length > 0 && (
                    <div className="mt-20 border-t border-[var(--fs-sand)] pt-16">
                        <SectionEyebrow>Additional services</SectionEyebrow>
                        <h2 className="font-display mt-3 text-2xl text-[var(--fs-admiralty)] sm:text-3xl">
                            Support for every other stage.
                        </h2>
                        <p className="mt-4 max-w-3xl text-base leading-relaxed text-[var(--fs-graphite)]">
                            Once a business is trading - or when you are buying
                            one, or leading a not-for-profit - these are the
                            ways we help. Same honest, evidence-based approach;
                            a different shape to suit where you are.
                        </p>
                        <div className="mt-12 space-y-16">
                            {additional.map((e, idx) => (
                                <EngagementCard
                                    key={e.slug}
                                    e={e}
                                    eyebrow={`0${idx + 1} · Additional service`}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </Section>

            {/* ── BEYOND THE ADVICE ───────────────────────────── */}
            <div className="bg-[var(--fs-linen)] py-16">
                <Section>
                    <SectionEyebrow>Beyond the advice</SectionEyebrow>
                    <h2 className="font-display mt-3 text-2xl text-[var(--fs-admiralty)] sm:text-3xl">
                        Sometimes the fix is a tool.
                    </h2>
                    <p className="mt-5 max-w-3xl text-base leading-relaxed text-[var(--fs-graphite)]">
                        Every so often, while we are inside your business, we
                        spot a job that a small piece of software could do
                        better than a spreadsheet or a manual routine - an
                        approval that keeps stalling, a report that eats half a
                        day, the same data typed into two systems. When that
                        happens we will say so, show you the numbers, and - if
                        it genuinely pays off - quote and build a custom tool to
                        take the work off your plate. And if what you need turns
                        out to be bigger than a small tool, the approach does
                        not change - we scope it carefully, quote it honestly,
                        and build it in stages you can see working. It is not a
                        separate pitch; it is part of helping your business run
                        better.
                    </p>
                </Section>
            </div>

            <div
                data-surface="dark"
                className="bg-[var(--fs-admiralty)] py-16 text-[var(--fs-parchment)]"
            >
                <Section>
                    <div className="grid items-center gap-8 md:grid-cols-12">
                        <div className="md:col-span-8">
                            <h2 className="font-display text-2xl sm:text-3xl">
                                Not sure which one fits?
                            </h2>
                            <p className="font-accent mt-3 max-w-xl text-lg text-[#E0D8CC] italic">
                                Start with a discovery call. We will listen,
                                ask, and tell you honestly which path makes
                                sense - or if another provider would serve you
                                better.
                            </p>
                        </div>
                        <div className="md:col-span-4 md:text-right">
                            <Link
                                href="/contact"
                                className="inline-flex items-center gap-2 rounded-md bg-[var(--fs-warm-gold)] px-5 py-3 text-sm font-semibold text-[var(--fs-admiralty)] transition hover:bg-[var(--fs-champagne)]"
                            >
                                Book a discovery call{' '}
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </Section>
            </div>

            <BackToTop />
        </>
    );
}
