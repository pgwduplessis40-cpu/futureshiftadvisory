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
import { organizationLd, webSiteLd } from '@/lib/structured-data';
import type { SharedPageProps } from '@/types';

type EngagementSummary = {
    slug: string;
    title: string;
    tagline: string;
    summary: string;
    accent: string;
    detail_path?: string;
};

type IdeaValidationOffer = {
    available: boolean;
    label: string;
    amount_ex_gst: number | null;
    currency: string | null;
};

type LatestPost = {
    slug: string;
    title: string;
    description: string;
    date: string;
    date_iso: string;
};

// The founder journey: idea validation is the front door, and its answers
// feed the business plan and budget. Everything else is a wrap-around service.
const FOUNDER_JOURNEY = [
    {
        step: '1',
        title: 'Validate your idea',
        body: 'Test it against demand, competition, feasibility, and the numbers. An advisor reviews your answers within 24 hours, and you get a straight answer.',
    },
    {
        step: '2',
        title: 'Build your business plan and budget',
        body: 'If the idea holds up, your validation answers carry straight into the plan, so nothing is wasted. The numbers are worked through, not guessed.',
    },
    {
        step: '3',
        title: 'Launch with real numbers',
        body: 'A plan and budget a bank, investor, or funder will take seriously, and a clear recommendation on whether you are ready.',
    },
];

export default function Home({
    engagementTypes,
    ideaValidationOffer,
    latestPosts = [],
}: {
    engagementTypes: EngagementSummary[];
    ideaValidationOffer: IdeaValidationOffer;
    latestPosts?: LatestPost[];
}) {
    const base = usePage<SharedPageProps>().props.publicUrl ?? '';
    const validationPrice =
        ideaValidationOffer.available &&
        ideaValidationOffer.amount_ex_gst !== null
            ? `$${ideaValidationOffer.amount_ex_gst.toLocaleString('en-NZ', {
                  minimumFractionDigits: 0,
                  maximumFractionDigits: 2,
              })} + GST`
            : null;
    const otherServices = engagementTypes.filter(
        (e) => e.slug !== 'entrepreneur_module',
    );

    return (
        <>
            <Seo
                title="Idea validation & honest advisory for New Zealand founders"
                description="Is your business idea worth building? Self-paced idea validation for New Zealand founders, reviewed by an advisor within 24 hours - then your business plan and budget."
                jsonLd={[organizationLd(base), webSiteLd(base)]}
            />

            {/* ── HERO ─────────────────────────────────────────── */}
            <Section className="pt-20 pb-20 lg:pt-28">
                <SectionEyebrow>
                    Idea validation for New Zealand founders
                </SectionEyebrow>
                <SectionTitle as="h1" className="mt-4">
                    Is your business idea{' '}
                    <span className="font-accent text-[var(--fs-cognac)] italic">
                        worth building?
                    </span>
                </SectionTitle>
                <GoldRule className="mt-6" />
                <p className="mt-6 max-w-2xl text-lg leading-relaxed text-[var(--fs-graphite)]">
                    Every good business starts as an idea that might not work.
                    The useful question is not whether you believe in it - it is
                    whether the evidence does. Find out early, before you spend
                    serious money.
                </p>
                <p className="mt-4 max-w-2xl text-lg leading-relaxed text-[var(--fs-admiralty)]">
                    And you do not have to quit your job to find out. Idea
                    validation is self-paced and online, and an advisor reviews
                    your answers within 24 hours.
                </p>
                <div className="mt-8 max-w-2xl rounded-lg border border-[var(--fs-sand)] bg-white px-5 py-4">
                    <p className="text-lg text-[var(--fs-admiralty)]">
                        <span className="font-semibold">
                            {validationPrice
                                ? `Idea validation is a fixed fee of ${validationPrice}.`
                                : 'Idea validation is a single fixed fee.'}
                        </span>{' '}
                        Agreed before you start - no hidden costs, and no
                        surprises at checkout.
                    </p>
                </div>
                <div className="mt-10 flex flex-wrap items-center gap-4">
                    <a
                        href="/validate-idea/purchase"
                        className="inline-flex items-center gap-2 rounded-md bg-[var(--fs-admiralty)] px-5 py-3 text-sm font-medium text-[var(--fs-parchment)] shadow-sm transition-colors hover:bg-[var(--fs-commodore)]"
                    >
                        {validationPrice
                            ? `Validate my idea — ${validationPrice}`
                            : 'Validate my idea'}{' '}
                        <ArrowRight className="h-4 w-4" />
                    </a>
                    <Link
                        href="/services/entrepreneur"
                        className="inline-flex items-center gap-2 text-sm font-medium text-[var(--fs-admiralty)] hover:text-[var(--fs-pacific)]"
                    >
                        See how it works <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>
            </Section>

            {/* ── HOW IT WORKS ────────────────────────────────── */}
            <div className="bg-[var(--fs-linen)] py-20">
                <Section>
                    <SectionEyebrow>How it works</SectionEyebrow>
                    <SectionTitle className="mt-3">
                        Start with the idea. Build from there.
                    </SectionTitle>

                    <ol className="mt-12 grid gap-6 md:grid-cols-3">
                        {FOUNDER_JOURNEY.map((stage) => (
                            <li
                                key={stage.step}
                                className="rounded-xl border border-[var(--fs-sand)] bg-white p-6 shadow-[0_1px_2px_rgba(28,43,69,0.04)]"
                            >
                                <div className="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--fs-admiralty)] text-sm font-semibold text-[var(--fs-parchment)]">
                                    {stage.step}
                                </div>
                                <h3 className="font-display mt-4 text-xl text-[var(--fs-admiralty)]">
                                    {stage.title}
                                </h3>
                                <p className="mt-2 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                    {stage.body}
                                </p>
                            </li>
                        ))}
                    </ol>

                    <div className="mt-10 flex flex-wrap items-center gap-4">
                        <a
                            href="/validate-idea/purchase"
                            className="inline-flex items-center gap-2 rounded-md bg-[var(--fs-admiralty)] px-5 py-3 text-sm font-medium text-[var(--fs-parchment)] shadow-sm transition-colors hover:bg-[var(--fs-commodore)]"
                        >
                            Start with step 1 <ArrowRight className="h-4 w-4" />
                        </a>
                        <Link
                            href="/services/entrepreneur"
                            className="inline-flex items-center gap-2 text-sm font-medium text-[var(--fs-admiralty)] hover:text-[var(--fs-pacific)]"
                        >
                            Each step in detail{' '}
                            <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>
                </Section>
            </div>

            {/* ── FOUNDER STORY ───────────────────────────────── */}
            <Section className="py-20">
                <figure className="mx-auto max-w-3xl">
                    <SectionEyebrow>From a founder</SectionEyebrow>
                    <blockquote className="font-display mt-4 text-2xl leading-snug text-[var(--fs-admiralty)] sm:text-3xl">
                        &ldquo;The validation process helped me feel as though
                        my feet were planted firmly on a rock. It helped me skim
                        off the dross, clarify my thinking, and strengthen the
                        core of my idea.&rdquo;
                    </blockquote>
                    <figcaption className="mt-6 flex items-center gap-4 text-sm text-[var(--fs-graphite)]">
                        <img
                            src="/images/tania-hassounia.png"
                            alt="Illustration of Tania Hassounia"
                            width={64}
                            height={64}
                            loading="lazy"
                            className="h-16 w-16 shrink-0 rounded-full border border-[var(--fs-sand)] bg-white"
                        />
                        <div>
                            <span className="font-semibold text-[var(--fs-admiralty)]">
                                Tania Hassounia
                            </span>
                            , Drawer Full of Giants Ltd, Hamilton - completed
                            idea validation, then her business plan and budget.
                            <span className="mt-2 block text-xs">
                                Tania was an early user who also gave feedback
                                on our platform.
                            </span>
                        </div>
                    </figcaption>
                    <Link
                        href="/services/entrepreneur#founder-story"
                        className="mt-6 inline-flex items-center gap-2 text-sm font-medium text-[var(--fs-admiralty)] hover:text-[var(--fs-pacific)]"
                    >
                        Read Tania&rsquo;s story{' '}
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </figure>
            </Section>

            {/* ── TRUST BAND ──────────────────────────────────── */}
            <div className="bg-[var(--fs-linen)] py-20">
                <Section>
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
                                title: 'Your idea stays yours',
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
            </div>

            {/* ── FROM THE BLOG ───────────────────────────────── */}
            {latestPosts.length > 0 ? (
                <Section className="py-20">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <SectionEyebrow>From the blog</SectionEyebrow>
                            <SectionTitle className="mt-3">
                                Practical reading for founders.
                            </SectionTitle>
                        </div>
                        <Link
                            href="/blog"
                            className="inline-flex items-center gap-1 text-sm font-medium text-[var(--fs-admiralty)] hover:text-[var(--fs-pacific)]"
                        >
                            All posts <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>

                    <div className="mt-10 grid gap-6 md:grid-cols-3">
                        {latestPosts.map((post) => (
                            <Link
                                key={post.slug}
                                href={`/blog/${post.slug}`}
                                className="group block rounded-xl border border-[var(--fs-sand)] bg-white p-6 shadow-[0_1px_2px_rgba(28,43,69,0.04)] transition hover:shadow-[0_8px_24px_rgba(28,43,69,0.08)]"
                            >
                                <time
                                    dateTime={post.date_iso}
                                    className="eyebrow"
                                >
                                    {post.date}
                                </time>
                                <h3 className="font-display mt-2 text-xl text-[var(--fs-admiralty)]">
                                    {post.title}
                                </h3>
                                {post.description ? (
                                    <p className="mt-2 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                        {post.description}
                                    </p>
                                ) : null}
                                <span className="mt-4 inline-flex items-center gap-1 text-sm font-medium text-[var(--fs-admiralty)] transition group-hover:text-[var(--fs-pacific)]">
                                    Read the post{' '}
                                    <ArrowRight className="h-4 w-4" />
                                </span>
                            </Link>
                        ))}
                    </div>
                </Section>
            ) : null}

            {/* ── OTHER SERVICES ──────────────────────────────── */}
            {otherServices.length > 0 ? (
                <div className="border-t border-[var(--fs-sand)] py-16">
                    <Section>
                        <h2 className="font-display text-2xl text-[var(--fs-admiralty)]">
                            Already trading, buying a business, or running a
                            not-for-profit?
                        </h2>
                        <SectionLead>
                            The same honest, evidence-based approach, shaped for
                            where you are.
                        </SectionLead>
                        <ul className="mt-8 grid gap-4 sm:grid-cols-2">
                            {otherServices.map((e) => (
                                <li key={e.slug}>
                                    <Link
                                        href={`/services#${e.slug}`}
                                        className="group inline-flex flex-col"
                                    >
                                        <span className="inline-flex items-center gap-1 text-sm font-semibold text-[var(--fs-admiralty)] group-hover:text-[var(--fs-pacific)]">
                                            {e.title}
                                            <ArrowRight className="h-4 w-4" />
                                        </span>
                                        <span className="text-sm text-[var(--fs-graphite)]">
                                            {e.tagline}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Section>
                </div>
            ) : null}

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
                                Got an idea? Find out if it holds up.
                            </h2>
                            <p className="font-accent mt-4 max-w-xl text-lg text-[#E0D8CC] italic">
                                Self-paced and online, with an advisor review
                                within 24 hours. Prefer to talk first? A
                                30-minute call is free - no slides, no hard
                                sell.
                            </p>
                        </div>
                        <div className="flex flex-col items-start gap-3 md:col-span-4 md:items-end">
                            <a
                                href="/validate-idea/purchase"
                                className="inline-flex items-center gap-2 rounded-md bg-[var(--fs-warm-gold)] px-5 py-3 text-sm font-semibold text-[var(--fs-admiralty)] transition hover:bg-[var(--fs-champagne)]"
                            >
                                {validationPrice
                                    ? `Validate my idea — ${validationPrice}`
                                    : 'Validate my idea'}{' '}
                                <ArrowRight className="h-4 w-4" />
                            </a>
                            <Link
                                href="/contact"
                                className="inline-flex items-center gap-2 text-sm font-medium text-[#E0D8CC] hover:text-white"
                            >
                                Or book a discovery call{' '}
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
