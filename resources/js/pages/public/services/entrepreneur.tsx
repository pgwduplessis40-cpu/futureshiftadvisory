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
import { breadcrumbLd, serviceLd } from '@/lib/structured-data';
import type { SharedPageProps } from '@/types';

const VALIDATION_CHECKS = [
    {
        label: 'Demand',
        body: 'Is anyone genuinely willing to pay for this?',
    },
    {
        label: 'Competition',
        body: 'Who is already doing it, and what would make you different?',
    },
    {
        label: 'Feasibility',
        body: 'What would it really take to build and deliver?',
    },
    {
        label: 'The numbers',
        body: 'What has to be true for this to make money?',
    },
];

const STAGES = [
    {
        step: '1',
        title: 'Readiness',
        body: 'An honest look at where you and the idea stand today.',
    },
    {
        step: '2',
        title: 'Idea validation',
        body: 'Pressure-testing the concept against evidence rather than optimism.',
    },
    {
        step: '3',
        title: 'Building',
        body: 'Staged work on the plan, the numbers, and the model, with regular mentoring.',
    },
    {
        step: '4',
        title: 'Assessment',
        body: 'A written view of where the idea has landed.',
    },
    {
        step: '5',
        title: 'Launch, or not',
        body: 'A clear recommendation either way.',
    },
];

const AUDIENCE = [
    'First-time founders turning an idea into something real',
    'Someone with a side project deciding whether to go all in',
    'Early-stage startup teams who need the numbers to stand up',
    'Founders who have been told "great idea!" by everyone they know, and want someone to actually check',
];

export default function EntrepreneurService() {
    const base = usePage<SharedPageProps>().props.publicUrl ?? '';

    return (
        <>
            <Seo
                title="Idea validation and business plans for New Zealand founders"
                description="Honest idea validation, a business plan with real numbers, and funding readiness for New Zealand founders. We tell you if the idea does not stack up."
                jsonLd={[
                    serviceLd(base, {
                        name: 'Entrepreneur Module',
                        description:
                            'Idea validation, business planning, and funding readiness for New Zealand founders - from first idea through to launch.',
                        path: '/services/entrepreneur',
                    }),
                    breadcrumbLd(base, [
                        { name: 'Home', path: '/' },
                        { name: 'Services', path: '/services' },
                        {
                            name: 'Entrepreneur Module',
                            path: '/services/entrepreneur',
                        },
                    ]),
                ]}
            />

            {/* ── HERO ─────────────────────────────────────────── */}
            <Section className="pt-20 pb-16 lg:pt-24">
                <SectionEyebrow>Entrepreneur Module</SectionEyebrow>
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
                    whether the evidence does. We help New&nbsp;Zealand founders
                    find that out early: honest idea validation, a business plan
                    with real numbers behind it, and a straight read on whether
                    you are ready to launch.
                </p>
                <div className="mt-10 flex flex-wrap items-center gap-4">
                    <Link
                        href="/contact?interest=entrepreneur_module"
                        className="inline-flex items-center gap-2 rounded-md bg-[var(--fs-admiralty)] px-5 py-3 text-sm font-medium text-[var(--fs-parchment)] shadow-sm transition-colors hover:bg-[var(--fs-commodore)]"
                    >
                        Book a discovery call <ArrowRight className="h-4 w-4" />
                    </Link>
                    <Link
                        href="/faq"
                        className="inline-flex items-center gap-2 text-sm font-medium text-[var(--fs-admiralty)] hover:text-[var(--fs-pacific)]"
                    >
                        Common questions from founders{' '}
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>
            </Section>

            {/* ── IDEA VALIDATION ─────────────────────────────── */}
            <div className="bg-[var(--fs-linen)] py-16">
                <Section>
                    <h2 className="font-display text-2xl text-[var(--fs-admiralty)] sm:text-3xl">
                        Idea validation: does the concept hold up?
                    </h2>
                    <p className="mt-5 max-w-3xl text-base leading-relaxed text-[var(--fs-graphite)]">
                        Before you spend serious money, it is worth knowing what
                        you are spending it on. Idea validation tests the
                        concept against the things that actually decide whether
                        a business survives.
                    </p>
                    <p className="mt-4 max-w-3xl text-base leading-relaxed text-[var(--fs-graphite)]">
                        You will get a straight answer. Sometimes that answer is
                        &ldquo;not in this form, and here is why&rdquo; - a far
                        cheaper thing to hear now than two years and a mortgage
                        later.
                    </p>

                    <div className="mt-8 grid gap-4 sm:grid-cols-2">
                        {VALIDATION_CHECKS.map((check) => (
                            <div
                                key={check.label}
                                className="flex gap-3 rounded-lg border border-[var(--fs-sand)] bg-white p-5"
                            >
                                <Check className="mt-0.5 h-4 w-4 shrink-0 text-[var(--fs-pacific)]" />
                                <div>
                                    <div className="text-sm font-semibold text-[var(--fs-admiralty)]">
                                        {check.label}
                                    </div>
                                    <p className="mt-1 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                        {check.body}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </Section>
            </div>

            {/* ── BUSINESS PLAN ───────────────────────────────── */}
            <Section className="py-16">
                <h2 className="font-display text-2xl text-[var(--fs-admiralty)] sm:text-3xl">
                    The business plan: a working document, not a filing exercise
                </h2>
                <p className="mt-5 max-w-3xl text-base leading-relaxed text-[var(--fs-graphite)]">
                    Most business plans are written once to satisfy somebody
                    else, then never opened again. We build yours the other way
                    round - as the thing you actually run the business from.
                </p>
                <p className="mt-4 max-w-3xl text-base leading-relaxed text-[var(--fs-graphite)]">
                    It covers the plan and the budget together, with the numbers
                    worked through rather than guessed: what you will spend,
                    what you need to earn, when you break even, and what happens
                    if it takes longer than you hope. You can export it as a
                    document to take to a bank, an investor, or a grant funder.
                </p>
                <p className="mt-4 max-w-3xl text-base leading-relaxed text-[var(--fs-graphite)]">
                    Wondering whether you need one at all? Legally no -
                    practically yes, the moment anyone else&rsquo;s money is
                    involved.
                </p>
            </Section>

            {/* ── FUNDING READINESS ───────────────────────────── */}
            <div className="bg-[var(--fs-linen)] py-16">
                <Section>
                    <h2 className="font-display text-2xl text-[var(--fs-admiralty)] sm:text-3xl">
                        Funding readiness: before you approach a bank or an
                        investor
                    </h2>
                    <p className="mt-5 max-w-3xl text-base leading-relaxed text-[var(--fs-graphite)]">
                        Banks, investors, and grant funders all want the same
                        three things: believable numbers, a clear plan, and
                        evidence you have thought about what could go wrong.
                    </p>
                    <p className="mt-4 max-w-3xl text-base leading-relaxed text-[var(--fs-graphite)]">
                        We help you get those in order before you go asking -
                        because you generally get one good first impression per
                        funder, and spending it on a half-ready pitch is an
                        expensive way to learn. If we think you are not ready,
                        we will say so.
                    </p>
                </Section>
            </div>

            {/* ── HOW IT RUNS ─────────────────────────────────── */}
            <Section className="py-16">
                <h2 className="font-display text-2xl text-[var(--fs-admiralty)] sm:text-3xl">
                    How the engagement runs
                </h2>
                <div className="mt-8 space-y-5">
                    {STAGES.map((stage) => (
                        <div key={stage.step} className="flex gap-4">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--fs-admiralty)] text-sm font-semibold text-[var(--fs-parchment)]">
                                {stage.step}
                            </div>
                            <div className="pt-1">
                                <h3 className="text-sm font-semibold text-[var(--fs-admiralty)]">
                                    {stage.title}
                                </h3>
                                <p className="mt-1 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                    {stage.body}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
                <p className="mt-8 max-w-3xl rounded-md bg-[var(--fs-linen)] px-4 py-3 text-sm text-[var(--fs-admiralty)]">
                    Quoted as a fixed fee, agreed before we start - and worth
                    weighing against the cost of finding out the hard way, with
                    borrowed money.
                </p>
            </Section>

            {/* ── WHO IT IS FOR ───────────────────────────────── */}
            <div className="bg-[var(--fs-parchment)] py-16">
                <Section>
                    <h2 className="font-display text-2xl text-[var(--fs-admiralty)] sm:text-3xl">
                        Who this is for
                    </h2>
                    <ul className="mt-6 space-y-3">
                        {AUDIENCE.map((item) => (
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

            {/* ── CLOSING CTA ─────────────────────────────────── */}
            <div
                data-surface="dark"
                className="bg-[var(--fs-admiralty)] py-16 text-[var(--fs-parchment)]"
            >
                <Section>
                    <div className="grid items-center gap-8 md:grid-cols-12">
                        <div className="md:col-span-8">
                            <h2 className="font-display text-2xl sm:text-3xl">
                                Bring the idea, however rough
                            </h2>
                            <p className="font-accent mt-3 max-w-xl text-lg text-[#E0D8CC] italic">
                                The earliest conversations are the cheapest ones
                                to have, because nothing has been built yet and
                                every option is still open.
                            </p>
                        </div>
                        <div className="md:col-span-4 md:text-right">
                            <Link
                                href="/contact?interest=entrepreneur_module"
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
