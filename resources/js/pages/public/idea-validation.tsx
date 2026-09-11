import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Check } from 'lucide-react';

import { BackToTop } from '@/components/public/back-to-top';
import {
    GoldRule,
    Section,
    SectionEyebrow,
    SectionTitle,
} from '@/components/public/section';
import { Seo } from '@/components/public/seo';
import type { SharedPageProps } from '@/types';
import { breadcrumbLd, serviceLd } from '@/lib/structured-data';

type Offer =
    | { available: false }
    | {
          available: true;
          price: number;
          priceFormatted: string;
          currency: string;
      };

type Props = {
    offer: Offer;
    checkoutUrl: string;
};

const WHAT_YOU_GET = [
    'A straight answer on whether the idea holds up - demand, competition, feasibility, and what has to be true for the numbers to work',
    'The reasoning behind it, not just a verdict - you see what we looked at and why we reached it',
    'What would have to change for a "not yet" to become a "yes"',
    'A written validation you can keep, share, or build on',
];

const HOW_IT_WORKS = [
    {
        step: '1',
        title: 'Sign up and pay',
        body: 'A few details, a secure card payment, and your workspace opens straight away.',
    },
    {
        step: '2',
        title: 'Tell us about the idea',
        body: 'Guided questions in the portal. No formal business plan needed. Bring it in whatever shape it is in.',
    },
    {
        step: '3',
        title: 'We review it and come back to you',
        body: 'A real advisor, not an automated score.',
    },
];

export default function IdeaValidation({ offer, checkoutUrl }: Props) {
    const base = usePage<SharedPageProps>().props.publicUrl ?? '';

    // The single price label, with the no-number fallback the whole page shares.
    const ctaLabel = offer.available
        ? `Validate my idea — ${offer.priceFormatted} + GST`
        : 'Validate my idea';

    const primaryCta = (
        <a
            href={checkoutUrl}
            className="inline-flex items-center gap-2 rounded-md bg-[var(--fs-admiralty)] px-5 py-3 text-sm font-semibold text-[var(--fs-parchment)] shadow-sm transition-colors hover:bg-[var(--fs-commodore)]"
        >
            {ctaLabel} <ArrowRight className="h-4 w-4" />
        </a>
    );

    const jsonLd = [
        serviceLd(base, {
            name: 'Idea Validation',
            description:
                'A fixed-fee, evidence-based read on whether a business idea stands up - demand, competition, feasibility, and the numbers. For New Zealand founders. Start online, no call required.',
            path: '/idea-validation',
            offer: offer.available
                ? { price: offer.price, currency: offer.currency }
                : undefined,
        }),
        breadcrumbLd(base, [
            { name: 'Home', path: '/' },
            { name: 'Idea Validation', path: '/idea-validation' },
        ]),
    ];

    return (
        <>
            <Seo
                title="Idea validation for New Zealand founders"
                description={
                    offer.available
                        ? `Test your business idea before you spend real money. A fixed-fee idea validation from Future Shift Advisory - ${offer.priceFormatted} + GST. Start online, no call needed.`
                        : 'Test your business idea before you spend real money. A fixed-fee idea validation from Future Shift Advisory. Start online, no call needed.'
                }
                jsonLd={jsonLd}
            />

            {/* ── HERO ─────────────────────────────────────────── */}
            <Section className="pt-20 pb-16 lg:pt-24">
                <SectionEyebrow>Idea Validation</SectionEyebrow>
                <SectionTitle as="h1" className="mt-4">
                    Find out if your idea works -{' '}
                    <span className="font-accent text-[var(--fs-cognac)] italic">
                        before you spend real money
                    </span>
                </SectionTitle>
                <GoldRule className="mt-6" />
                <p className="mt-6 max-w-2xl text-lg leading-relaxed text-[var(--fs-graphite)]">
                    Most business ideas fail for reasons that were visible at the
                    start, to someone who knew where to look. Idea validation is
                    that look: an honest, evidence-based read on whether your idea
                    stands up, delivered by a real advisor.
                </p>
                <p className="mt-4 max-w-2xl text-lg leading-relaxed text-[var(--fs-admiralty)]">
                    No call to book. No waiting for an invitation. Start online
                    and we get to work.
                </p>
                <div className="mt-10 flex flex-wrap items-center gap-4">
                    {primaryCta}
                    <Link
                        href="/contact?interest=entrepreneur_module"
                        className="inline-flex items-center gap-2 text-sm font-medium text-[var(--fs-admiralty)] hover:text-[var(--fs-pacific)]"
                    >
                        Rather talk it through first? Book a discovery call
                    </Link>
                </div>
            </Section>

            {/* ── WHAT YOU GET ────────────────────────────────── */}
            <div className="bg-[var(--fs-linen)] py-16">
                <Section>
                    <h2 className="font-display text-2xl text-[var(--fs-admiralty)] sm:text-3xl">
                        What you get
                    </h2>
                    <ul className="mt-6 space-y-3">
                        {WHAT_YOU_GET.map((item) => (
                            <li
                                key={item}
                                className="flex gap-3 text-base text-[var(--fs-graphite)]"
                            >
                                <Check className="mt-1 h-4 w-4 shrink-0 text-[var(--fs-pacific)]" />
                                <span>{item}</span>
                            </li>
                        ))}
                    </ul>
                </Section>
            </div>

            {/* ── HOW IT WORKS ────────────────────────────────── */}
            <Section className="py-16">
                <h2 className="font-display text-2xl text-[var(--fs-admiralty)] sm:text-3xl">
                    How it works
                </h2>
                <div className="mt-8 space-y-5">
                    {HOW_IT_WORKS.map((s) => (
                        <div key={s.step} className="flex gap-4">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--fs-admiralty)] text-sm font-semibold text-[var(--fs-parchment)]">
                                {s.step}
                            </div>
                            <div className="pt-1">
                                <h3 className="text-sm font-semibold text-[var(--fs-admiralty)]">
                                    {s.title}
                                </h3>
                                <p className="mt-1 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                    {s.body}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            </Section>

            {/* ── WHAT IT COSTS ───────────────────────────────── */}
            <div className="bg-[var(--fs-linen)] py-16">
                <Section>
                    <h2 className="font-display text-2xl text-[var(--fs-admiralty)] sm:text-3xl">
                        What it costs
                    </h2>
                    <p className="mt-5 max-w-3xl text-base leading-relaxed text-[var(--fs-graphite)]">
                        {offer.available ? (
                            <>
                                <span className="font-semibold text-[var(--fs-admiralty)]">
                                    {offer.priceFormatted} + GST.
                                </span>{' '}
                                That is the whole fee, agreed before you pay. No
                                hourly billing, no scope creep.
                            </>
                        ) : (
                            <>
                                <span className="font-semibold text-[var(--fs-admiralty)]">
                                    A single fixed fee, agreed before you pay.
                                </span>{' '}
                                No hourly billing, no scope creep.
                            </>
                        )}
                    </p>
                    <p className="mt-4 max-w-3xl text-base leading-relaxed text-[var(--fs-graphite)]">
                        It is worth weighing against what is actually at risk. The
                        expensive part of a business idea is rarely the checking -
                        it is the borrowed money, the savings, and the year or two
                        spent building something the evidence never supported.
                        Validation is a small, known cost paid early to avoid a
                        much larger, unknown one later.
                    </p>
                    <div className="mt-8">{primaryCta}</div>
                </Section>
            </div>

            {/* ── IF NOT FOR YOU ──────────────────────────────── */}
            <Section className="py-16">
                <h2 className="font-display text-2xl text-[var(--fs-admiralty)] sm:text-3xl">
                    If it turns out not to be for you
                </h2>
                <p className="mt-5 max-w-3xl text-base leading-relaxed text-[var(--fs-graphite)]">
                    Sometimes the honest answer is that the idea does not hold up
                    in its current form. You will still get the reasoning, and
                    what would need to change - because knowing why is what makes
                    the next idea better.
                </p>
                <p className="mt-4 max-w-3xl text-base leading-relaxed text-[var(--fs-graphite)]">
                    And if we think you would be better served elsewhere, we will
                    tell you that too.
                </p>
            </Section>

            {/* ── WHO DOES THE WORK ───────────────────────────── */}
            <div className="bg-[var(--fs-parchment)] py-16">
                <Section>
                    <h2 className="font-display text-2xl text-[var(--fs-admiralty)] sm:text-3xl">
                        Who does the work
                    </h2>
                    <p className="mt-5 max-w-3xl text-base leading-relaxed text-[var(--fs-graphite)]">
                        Your validation is done by Pieter Du Plessis, Principal
                        Advisor, a member of the Institute of Advisors (IOA), with
                        more than 15 years helping SMEs grow with clarity,
                        structure, and commercial discipline. Not a template, and
                        not a bot.
                    </p>
                </Section>
            </div>

            {/* ── CLOSING CTA ─────────────────────────────────── */}
            <div
                data-surface="dark"
                className="bg-[var(--fs-admiralty)] py-16 text-[var(--fs-parchment)]"
            >
                <Section>
                    <div className="grid items-center gap-8 md:grid-cols-12">
                        <div className="md:col-span-8">
                            <h2 className="font-display text-2xl sm:text-3xl">
                                Start online, in minutes
                            </h2>
                            <p className="font-accent mt-3 max-w-xl text-lg text-[#E0D8CC] italic">
                                Questions first? Read the common ones or book a
                                call.
                            </p>
                            <div className="mt-4 flex flex-wrap gap-4 text-sm">
                                <Link
                                    href="/faq"
                                    className="text-[var(--fs-warm-gold)] hover:text-white"
                                >
                                    Read the common questions
                                </Link>
                                <Link
                                    href="/contact?interest=entrepreneur_module"
                                    className="text-[var(--fs-warm-gold)] hover:text-white"
                                >
                                    Book a discovery call
                                </Link>
                            </div>
                        </div>
                        <div className="md:col-span-4 md:text-right">
                            <a
                                href={checkoutUrl}
                                className="inline-flex items-center gap-2 rounded-md bg-[var(--fs-warm-gold)] px-5 py-3 text-sm font-semibold text-[var(--fs-admiralty)] transition hover:bg-[var(--fs-champagne)]"
                            >
                                {ctaLabel} <ArrowRight className="h-4 w-4" />
                            </a>
                        </div>
                    </div>
                </Section>
            </div>

            <BackToTop />
        </>
    );
}
