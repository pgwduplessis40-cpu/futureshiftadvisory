import { Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    HeartHandshake,
    ScrollText,
    ShieldCheck,
} from 'lucide-react';

import { BackToTop } from '@/components/public/back-to-top';
import {
    GoldRule,
    Section,
    SectionEyebrow,
    SectionLead,
    SectionTitle,
} from '@/components/public/section';
import { Seo } from '@/components/public/seo';
import { organizationLd, webSiteLd } from '@/lib/structured-data';

type EngagementSummary = {
    slug: string;
    title: string;
    tagline: string;
    summary: string;
    accent: string;
};

const accentClass: Record<string, string> = {
    pacific: 'border-l-[var(--fs-pacific)]',
    admiralty: 'border-l-[var(--fs-admiralty)]',
    'deep-cove': 'border-l-[var(--fs-deep-cove)]',
    cognac: 'border-l-[var(--fs-cognac)]',
    harbour: 'border-l-[var(--fs-harbour)]',
};

export default function Home({
    engagementTypes,
}: {
    engagementTypes: EngagementSummary[];
}) {
    const base = usePage().props.publicUrl ?? '';

    return (
        <>
            <Seo
                title="Future Shift Advisory - honest, evidence-based advice for NZ businesses"
                description="A Hamilton-based advisory practice for New Zealand SMEs, founders, buyers, and not-for-profits. Clear, kind, evidence-based advice you can act on."
                jsonLd={[organizationLd(base), webSiteLd(base)]}
            />

            {/* ── HERO ─────────────────────────────────────────── */}
            <Section className="pt-20 pb-24 lg:pt-28 lg:pb-32">
                <div className="grid gap-12 lg:grid-cols-12 lg:gap-16">
                    <div className="lg:col-span-7">
                        <SectionEyebrow>Future Shift Advisory</SectionEyebrow>
                        <SectionTitle as="h1" className="mt-4">
                            Your business, shifted{' '}
                            <span className="font-accent text-[var(--fs-cognac)] italic">
                                forward.
                            </span>
                        </SectionTitle>
                        <GoldRule className="mt-6" />
                        <p className="mt-6 max-w-xl text-lg leading-relaxed text-[var(--fs-graphite)]">
                            Clear, honest advice for New&nbsp;Zealand SMEs,
                            founders, and not-for-profits. We tell you what we
                            see - kindly, and with the reasoning behind it - so
                            you can move forward with confidence.
                        </p>
                        <div className="mt-10 flex flex-wrap items-center gap-4">
                            <Link
                                href="/contact"
                                className="inline-flex items-center gap-2 rounded-md bg-[var(--fs-admiralty)] px-5 py-3 text-sm font-medium text-[var(--fs-parchment)] shadow-sm transition-colors hover:bg-[var(--fs-commodore)]"
                            >
                                Book a discovery call{' '}
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                            <Link
                                href="/services"
                                className="inline-flex items-center gap-2 text-sm font-medium text-[var(--fs-admiralty)] hover:text-[var(--fs-pacific)]"
                            >
                                See how we can help{' '}
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                        </div>
                    </div>

                    <div className="lg:col-span-5">
                        <div className="rounded-xl border border-[var(--fs-sand)] bg-white p-6 shadow-[0_1px_2px_rgba(28,43,69,0.04)]">
                            <div className="eyebrow">
                                What it’s like to work with us
                            </div>
                            <ul className="mt-4 space-y-4">
                                {[
                                    {
                                        icon: HeartHandshake,
                                        title: 'Honest, and kind with it',
                                        body: 'We tell you what is really going on - the good and the hard - and we are thoughtful in how we say it.',
                                    },
                                    {
                                        icon: ScrollText,
                                        title: 'Backed by evidence',
                                        body: 'Every recommendation comes with the reasoning and the proof behind it, in plain language.',
                                    },
                                    {
                                        icon: ShieldCheck,
                                        title: 'New Zealand-grounded & private',
                                        body: 'Built for the local context, and confidential by default - your information is only seen by the people working with you.',
                                    },
                                ].map((row) => (
                                    <li key={row.title} className="flex gap-3">
                                        <row.icon className="mt-0.5 h-5 w-5 shrink-0 text-[var(--fs-pacific)]" />
                                        <div>
                                            <div className="text-sm font-semibold text-[var(--fs-admiralty)]">
                                                {row.title}
                                            </div>
                                            <p className="mt-0.5 text-sm text-[var(--fs-graphite)]">
                                                {row.body}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </div>
            </Section>

            {/* ── ENGAGEMENT TYPES ────────────────────────────── */}
            <div className="bg-[var(--fs-linen)] py-20">
                <Section>
                    <SectionEyebrow>How we can help</SectionEyebrow>
                    <SectionTitle className="mt-3">
                        Ways to work together.
                    </SectionTitle>
                    <SectionLead>
                        Most people start with a Standard Advisory review.
                        Others come to us for due diligence on a purchase,
                        support after an acquisition, a hand getting a new
                        venture off the ground, or a health check for their
                        not-for-profit.
                    </SectionLead>

                    <div className="mt-12 grid gap-6 md:grid-cols-2">
                        {engagementTypes.map((e) => (
                            <Link
                                key={e.slug}
                                href={`/services#${e.slug}`}
                                className={[
                                    'group rounded-lg border border-l-4 border-[var(--fs-sand)] bg-white p-6 shadow-[0_1px_2px_rgba(28,43,69,0.03)] transition hover:shadow-[0_8px_24px_rgba(28,43,69,0.08)]',
                                    accentClass[e.accent] ??
                                        'border-l-[var(--fs-pacific)]',
                                ].join(' ')}
                            >
                                <div className="font-display text-2xl text-[var(--fs-admiralty)]">
                                    {e.title}
                                </div>
                                <div className="font-accent mt-1 text-base text-[var(--fs-cognac)] italic">
                                    {e.tagline}
                                </div>
                                <p className="mt-4 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                    {e.summary}
                                </p>
                                <div className="mt-5 inline-flex items-center gap-1 text-sm font-medium text-[var(--fs-admiralty)] transition group-hover:text-[var(--fs-pacific)]">
                                    Read more <ArrowRight className="h-4 w-4" />
                                </div>
                            </Link>
                        ))}
                    </div>
                </Section>
            </div>

            {/* ── TRUST BAND ──────────────────────────────────── */}
            <Section className="py-20">
                <div className="grid gap-10 md:grid-cols-3">
                    {[
                        {
                            kicker: 'New Zealand-grounded',
                            title: 'Hamilton, working nationwide',
                            body: 'Our advice is built for the New Zealand context - local regulation, tax, and the day-to-day realities of doing business here.',
                        },
                        {
                            kicker: 'Honest & evidence-based',
                            title: 'Straight talk, backed up',
                            body: 'We show our working. No inflated scores, no buried warnings - and we say so plainly when something is not yet certain.',
                        },
                        {
                            kicker: 'Private by default',
                            title: 'Your information stays yours',
                            body: 'Multi-factor sign-in, encrypted documents, and access limited to the people working with you. Confidentiality is the baseline.',
                        },
                    ].map((b) => (
                        <div key={b.title}>
                            <div className="eyebrow">{b.kicker}</div>
                            <h3 className="font-display mt-3 text-xl text-[var(--fs-admiralty)]">
                                {b.title}
                            </h3>
                            <p className="mt-3 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                {b.body}
                            </p>
                        </div>
                    ))}
                </div>
            </Section>

            {/* ── CLOSING CTA ─────────────────────────────────── */}
            <div
                data-surface="dark"
                className="bg-[var(--fs-admiralty)] py-20 text-[var(--fs-parchment)]"
            >
                <Section>
                    <div className="grid items-center gap-10 md:grid-cols-12">
                        <div className="md:col-span-8">
                            <p className="text-[11px] font-semibold tracking-[0.15em] text-[var(--fs-warm-gold)] uppercase">
                                Ready when you are
                            </p>
                            <h2 className="font-display mt-3 text-3xl leading-tight text-[var(--fs-parchment)] sm:text-4xl">
                                Let’s have a 30-minute chat.
                            </h2>
                            <p className="font-accent mt-4 max-w-xl text-lg text-[#E0D8CC] italic">
                                No prepared slides, no hard sell. We listen, ask
                                a few good questions, and tell you honestly
                                whether we are the right fit.
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
