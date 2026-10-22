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
        title: 'Idea validation',
        body: 'An honest look at where you and the idea stand, pressure-tested against evidence rather than optimism. An advisor reviews your answers within 24 hours.',
    },
    {
        step: '2',
        title: 'Business plan and budget',
        body: 'If the idea holds up, your validation answers become the starting point. Staged work on the plan, the numbers, and the model, with regular mentoring.',
    },
    {
        step: '3',
        title: 'Assessment',
        body: 'A written view of where the plan has landed.',
    },
    {
        step: '4',
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

type IdeaValidationOffer = {
    available: boolean;
    label: string;
    amount_ex_gst: number | null;
    currency: string | null;
};

export default function EntrepreneurService({
    ideaValidationOffer,
}: {
    ideaValidationOffer: IdeaValidationOffer;
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

    return (
        <>
            <Seo
                title="Idea validation and business plans for New Zealand founders"
                description="Honest idea validation, a business plan with real numbers, and funding readiness for New Zealand founders. We tell you if the idea does not stack up."
                jsonLd={[
                    serviceLd(base, {
                        name: 'Idea validation and business plans',
                        description:
                            'Idea validation, business planning, and funding readiness for New Zealand founders - from first idea through to launch.',
                        path: '/services/entrepreneur',
                        offer:
                            ideaValidationOffer.available &&
                            ideaValidationOffer.amount_ex_gst !== null &&
                            ideaValidationOffer.currency
                                ? {
                                      amount: ideaValidationOffer.amount_ex_gst,
                                      currency: ideaValidationOffer.currency,
                                  }
                                : undefined,
                    }),
                    breadcrumbLd(base, [
                        { name: 'Home', path: '/' },
                        { name: 'Services', path: '/services' },
                        {
                            name: 'Idea validation',
                            path: '/services/entrepreneur',
                        },
                    ]),
                ]}
            />

            {/* ── HERO ─────────────────────────────────────────── */}
            <Section className="pt-20 pb-16 lg:pt-24">
                <SectionEyebrow>Idea validation</SectionEyebrow>
                <SectionTitle as="h1" className="mt-4">
                    How idea validation{' '}
                    <span className="font-accent text-[var(--fs-cognac)] italic">
                        works.
                    </span>
                </SectionTitle>
                <GoldRule className="mt-6" />
                <p className="mt-6 max-w-2xl text-lg leading-relaxed text-[var(--fs-graphite)]">
                    Idea validation is where every founder&rsquo;s work with us
                    starts. It tests your idea against the evidence - not
                    optimism, and not friends&rsquo; encouragement. If the idea
                    holds up, your answers become the foundation of your
                    business plan and budget, and we help you get ready to
                    launch.
                </p>
                <p className="mt-4 max-w-2xl text-lg leading-relaxed text-[var(--fs-admiralty)]">
                    And you do not have to quit your job to find out. Idea
                    validation and your business plan and budget are self-paced
                    and done online - work through them in your own time,
                    alongside the job you already have, and make the leap only
                    once the evidence says it is worth it.
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
                    <Link
                        href="/validate-idea/purchase"
                        className="inline-flex items-center gap-2 rounded-md bg-[var(--fs-admiralty)] px-5 py-3 text-sm font-medium text-[var(--fs-parchment)] shadow-sm transition-colors hover:bg-[var(--fs-commodore)]"
                    >
                        {validationPrice
                            ? `Validate your idea — ${validationPrice}`
                            : 'Validate my idea'}{' '}
                        <ArrowRight className="h-4 w-4" />
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
                    <p className="mt-5 text-sm font-semibold text-[var(--fs-admiralty)]">
                        {validationPrice
                            ? `A fixed fee of ${validationPrice}, agreed before you pay.`
                            : 'A single fixed fee, agreed before you pay.'}
                    </p>
                    <Link
                        href="/validate-idea/purchase"
                        className="mt-6 inline-flex items-center gap-2 rounded-md bg-[var(--fs-admiralty)] px-5 py-3 text-sm font-medium text-[var(--fs-parchment)] shadow-sm transition-colors hover:bg-[var(--fs-commodore)]"
                    >
                        {validationPrice
                            ? `Validate your idea — ${validationPrice}`
                            : 'Validate my idea'}
                        <ArrowRight className="h-4 w-4" />
                    </Link>

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
                    And you do not start from a blank page. Your idea validation
                    answers carry straight into the plan, so nothing you have
                    already worked out is wasted.
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
                    The business plan and budget is quoted as a fixed fee,
                    agreed before you start - and worth weighing against the
                    cost of finding out the hard way, with borrowed money.
                </p>
            </Section>

            {/* ── FOUNDER STORY ───────────────────────────────── */}
            <div
                id="founder-story"
                className="scroll-mt-24 bg-[var(--fs-linen)] py-16"
            >
                <Section>
                    <figure className="max-w-3xl">
                        <SectionEyebrow>A founder’s story</SectionEyebrow>
                        <h2 className="font-display mt-3 text-2xl text-[var(--fs-admiralty)] sm:text-3xl">
                            From idea validation to business plan
                        </h2>
                        <blockquote className="mt-6 space-y-4 text-base leading-relaxed text-[var(--fs-graphite)]">
                            <p>
                                I found the process of validating my business
                                idea genuinely exciting, particularly as I’ve
                                spent the past few years pivoting how I serve my
                                clients in this next season of business. The
                                validation process came at just the right time.
                                I was ready to bring all the pieces together,
                                articulate what I’d been developing, and explore
                                whether it had real potential.
                            </p>
                            <p>
                                After three iterations, refining a couple of
                                areas to ensure they were solid, I successfully
                                graduated from idea validation and moved into
                                developing my business plan. The next stage is
                                implementation and advisory.
                            </p>
                            <p>
                                The validation process helped me feel as though
                                my feet were planted firmly on a rock. It helped
                                me skim off the dross, clarify my thinking, and
                                strengthen the core of my idea. When I
                                interviewed a selection of people from my target
                                market who weren’t already in my circle, their
                                responses helped me recognise that I was onto
                                something with the way my services had taken
                                shape.
                            </p>
                            <p>
                                With this newfound confidence, I was able to
                                move forward into creating my business plan with
                                greater clarity and conviction.
                            </p>
                            <p>
                                Working alongside Pieter has been incredibly
                                helpful. He’s encouraging, takes the time to
                                explain things, and genuinely cares about
                                helping people navigate the process. In the
                                interest of transparency, Pieter and I have also
                                been collaborating throughout this journey, with
                                me providing ongoing feedback from the
                                perspective of a business owner using the
                                software. I’ve been sharing what I would want to
                                understand at each stage and how the experience
                                could be made clearer and more useful. Pieter
                                has expressed how valuable this feedback is,
                                which is wonderful to know. It demonstrates his
                                commitment to developing software and an app
                                that genuinely serve the people using them.
                            </p>
                            <p>
                                I would highly recommend the validation process,
                                especially if you’ve been sitting on an idea for
                                years and need help getting it over the line.
                                For those of us with a naturally high
                                idea-generation capacity, the biggest challenge
                                may be choosing <em>one</em> idea to take all
                                the way through the process! But once you commit
                                to one, the process can help you become much
                                more grounded in understanding whether it’s the
                                right idea to move forward with.
                            </p>
                            <p>
                                <strong className="text-[var(--fs-admiralty)]">
                                    Your idea is worth the investment in
                                    properly exploring and validating it.
                                </strong>{' '}
                                Sometimes, what you need most isn’t another
                                idea. It’s the clarity and confidence to know
                                which one to build.
                            </p>
                        </blockquote>
                        <figcaption className="mt-6 flex items-center gap-4 text-sm text-[var(--fs-graphite)]">
                            <img
                                src="/images/tania-hassounia.png"
                                alt="Illustration of Tania Hassounia"
                                width={72}
                                height={72}
                                loading="lazy"
                                className="h-[72px] w-[72px] shrink-0 rounded-full border border-[var(--fs-sand)] bg-white"
                            />
                            <div>
                                <span className="font-semibold text-[var(--fs-admiralty)]">
                                    Tania Hassounia
                                </span>
                                ,{' '}
                                <a
                                    href="https://drawerfullofgiants.com"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="underline decoration-[var(--fs-sand)] underline-offset-4 hover:text-[var(--fs-admiralty)]"
                                >
                                    Drawer Full of Giants Ltd
                                </a>
                                , Hamilton
                                <span className="mt-2 block text-xs">
                                    Shared with Tania’s permission and lightly
                                    shortened. Tania was an early user who also
                                    gave feedback on our platform.
                                </span>
                            </div>
                        </figcaption>
                    </figure>
                </Section>
            </div>

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
                                href="/validate-idea/purchase"
                                className="inline-flex items-center gap-2 rounded-md bg-[var(--fs-warm-gold)] px-5 py-3 text-sm font-semibold text-[var(--fs-admiralty)] transition hover:bg-[var(--fs-champagne)]"
                            >
                                {validationPrice
                                    ? `Validate your idea — ${validationPrice}`
                                    : 'Validate my idea'}{' '}
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
