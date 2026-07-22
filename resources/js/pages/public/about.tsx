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

export default function About() {
    const base = usePage().props.publicUrl ?? '';

    return (
        <>
            <Seo
                title="About us - a Hamilton advisory practice you can trust"
                description="Future Shift Advisory is a Hamilton-based practice for New Zealand SMEs, founders, buyers, and not-for-profits. Honest, evidence-based, and genuinely local."
                jsonLd={breadcrumbLd(base, [
                    { name: 'Home', path: '/' },
                    { name: 'About', path: '/about' },
                ])}
            />

            <Section className="pt-20 pb-16 lg:pt-24">
                <SectionEyebrow>About</SectionEyebrow>
                <SectionTitle as="h1" className="mt-4">
                    Clear thinking,{' '}
                    <span className="font-accent text-[var(--fs-cognac)] italic">
                        and the courage to say it kindly.
                    </span>
                </SectionTitle>
                <GoldRule className="mt-6" />
                <SectionLead>
                    Future Shift Advisory is a Hamilton-based practice working
                    with New&nbsp;Zealand SMEs, founders, people buying a
                    business, and not-for-profits. We are here to give you a
                    clear, honest, well-grounded read on where you stand - and a
                    sensible plan for what comes next.
                </SectionLead>
            </Section>

            {/* ── PRINCIPLES ──────────────────────────────────── */}
            <div className="bg-[var(--fs-linen)] py-20">
                <Section>
                    <SectionEyebrow>What we hold ourselves to</SectionEyebrow>
                    <SectionTitle className="mt-3">
                        How we work with you.
                    </SectionTitle>
                    <p className="mt-5 max-w-2xl text-lg leading-relaxed text-[var(--fs-graphite)]">
                        A few promises that shape every piece of advice we give
                        - whether it is a quick conversation or a full review.
                    </p>

                    <div className="mt-12 grid gap-6 md:grid-cols-2">
                        {[
                            {
                                title: 'Honest, and kind with it',
                                body: 'If something needs attention, you will hear it clearly. We are kind in how we say it - never in whether we say it.',
                            },
                            {
                                title: 'Backed by evidence',
                                body: 'Every recommendation comes with the reasoning and the proof. We would rather show you than ask you to take our word for it.',
                            },
                            {
                                title: 'Genuinely New Zealand',
                                body: 'Our advice fits the local context - New Zealand regulation, tax, and the way business actually works here.',
                            },
                            {
                                title: 'Honest about uncertainty',
                                body: 'When the information is not enough to be sure, we say so - rather than dressing up a guess as a fact.',
                            },
                            {
                                title: 'No grading on a curve',
                                body: 'We do not inflate the score to make you feel good. The picture you get is the real one.',
                            },
                            {
                                title: 'Nothing swept under the rug',
                                body: 'Risks, warnings, and awkward findings get surfaced and talked through - never quietly left out.',
                            },
                        ].map((p) => (
                            <div
                                key={p.title}
                                className="rounded-lg border border-[var(--fs-sand)] bg-white p-6"
                            >
                                <h3 className="font-display text-xl text-[var(--fs-admiralty)]">
                                    {p.title}
                                </h3>
                                <p className="mt-3 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                    {p.body}
                                </p>
                            </div>
                        ))}
                    </div>
                </Section>
            </div>

            {/* ── OWNER ────────────────────────────────────────── */}
            <Section className="py-20">
                <div className="grid gap-12 md:grid-cols-12">
                    <div className="md:col-span-5">
                        {/*
                          Portrait placeholder. To use a real photo, save it as
                          public/images/pieter-du-plessis.jpg and swap this block for:
                          <img
                              src="/images/pieter-du-plessis.jpg"
                              alt="Pieter Du Plessis, Principal Advisor at Future Shift Advisory"
                              loading="lazy"
                              className="h-full w-full rounded-lg object-cover"
                          />
                        */}
                        <div className="aspect-[4/5] w-full overflow-hidden rounded-xl bg-[var(--fs-admiralty)] p-1">
                            <div className="flex h-full w-full items-center justify-center rounded-lg bg-gradient-to-br from-[var(--fs-commodore)] via-[var(--fs-harbour)] to-[var(--fs-pacific)] text-[var(--fs-parchment)]">
                                <div className="text-center">
                                    <div className="font-display text-6xl">
                                        PD
                                    </div>
                                    <div className="mt-3 text-[11px] tracking-[0.2em] text-[var(--fs-warm-gold)] uppercase">
                                        Principal Advisor
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="md:col-span-7">
                        <SectionEyebrow>Principal Advisor</SectionEyebrow>
                        <h2 className="font-display mt-3 text-3xl text-[var(--fs-admiralty)] sm:text-4xl">
                            Pieter Du Plessis
                        </h2>
                        <p className="mt-6 text-base leading-relaxed text-[var(--fs-graphite)]">
                            Future Shift Advisory is led by Pieter Du Plessis
                            from Hamilton, New&nbsp;Zealand. The practice grew
                            out of a simple frustration: too many business
                            owners receive advice that is vague, a little too
                            flattering, or disconnected from the evidence
                            sitting in their own books.
                        </p>
                        <p className="mt-4 text-base leading-relaxed text-[var(--fs-graphite)]">
                            So we built something different - advice with real
                            structure behind it, where every finding is backed
                            by evidence, and where you hear what the numbers
                            actually say rather than what is easiest to tell
                            you. We are as comfortable with the technology
                            behind a business as we are with its numbers - and
                            when a small tool will fix a stubborn process, we
                            can build it.
                        </p>

                        <p className="mt-4 text-base leading-relaxed text-[var(--fs-graphite)]">
                            Pieter brings more than 15 years of helping SMEs
                            grow with clarity, structure, and commercial
                            discipline. He has rebuilt finance functions across
                            a range of industries - technology, manufacturing,
                            construction, services, and medical devices. His
                            real strength is making the complicated feel simple:
                            bringing order where it is missing, and grounding
                            strategic thinking in what actually works on the
                            ground.
                        </p>

                        <p className="mt-4 text-base leading-relaxed text-[var(--fs-graphite)]">
                            Pieter is a member of the{' '}
                            <a
                                href="https://instituteadvisors.com/"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-medium text-[var(--fs-admiralty)] underline underline-offset-2 hover:text-[var(--fs-pacific)]"
                            >
                                Institute of Advisors (IOA)
                            </a>
                            , an international organisation of professional
                            advisors.
                        </p>

                        <blockquote className="font-accent mt-8 border-l-2 border-[var(--fs-warm-gold)] pl-5 text-2xl leading-snug text-[var(--fs-admiralty)] italic">
                            &ldquo;The truth before the comfortable.&rdquo;
                        </blockquote>
                    </div>
                </div>
            </Section>

            {/* ── WHERE / NZ-GROUNDED ─────────────────────────── */}
            <div className="bg-[var(--fs-parchment)] py-16">
                <Section>
                    <div className="grid gap-10 md:grid-cols-3">
                        <div>
                            <div className="eyebrow">Where we are</div>
                            <h3 className="font-display mt-3 text-2xl text-[var(--fs-admiralty)]">
                                Hamilton,
                                <br />
                                working nationwide
                            </h3>
                            <p className="mt-4 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                Based in the Waikato, working with businesses
                                and organisations across New&nbsp;Zealand. We
                                work remotely, and in person where it genuinely
                                adds value.
                            </p>
                        </div>
                        <div>
                            <div className="eyebrow">Built for New Zealand</div>
                            <h3 className="font-display mt-3 text-2xl text-[var(--fs-admiralty)]">
                                Local context, not borrowed playbooks
                            </h3>
                            <p className="mt-4 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                Our analysis and frameworks are tuned to the
                                New&nbsp;Zealand setting - NZBN, the Companies
                                Office, IRD, and local tax and compliance.
                            </p>
                        </div>
                        <div>
                            <div className="eyebrow">Private by default</div>
                            <h3 className="font-display mt-3 text-2xl text-[var(--fs-admiralty)]">
                                Your information stays yours
                            </h3>
                            <p className="mt-4 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                Multi-factor sign-in, encrypted documents, and
                                access limited to the people working with you -
                                with a complete record of who did what.
                            </p>
                        </div>
                    </div>
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
                                If this sounds like the kind of advisor you want
                            </h2>
                            <p className="font-accent mt-3 max-w-xl text-lg text-[#E0D8CC] italic">
                                Start with a 30-minute discovery call.
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
