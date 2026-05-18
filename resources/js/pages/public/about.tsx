import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import {
    GoldRule,
    Section,
    SectionEyebrow,
    SectionLead,
    SectionTitle,
} from '@/components/public/section';

export default function About() {
    return (
        <>
            <Head title="About — Future Shift Advisory">
                <meta
                    name="description"
                    content="Future Shift Advisory is a Hamilton-based, NZ-grounded advisory practice. Evidence-based, honest, built around the AI Integrity Principle."
                />
            </Head>

            <Section className="pt-20 pb-16 lg:pt-24">
                <SectionEyebrow>About</SectionEyebrow>
                <SectionTitle as="h1" className="mt-4">
                    The structure of clarity.{' '}
                    <span className="font-accent italic text-[var(--fs-cognac)]">
                        The soul of straight talk.
                    </span>
                </SectionTitle>
                <GoldRule className="mt-6" />
                <SectionLead>
                    Future Shift Advisory is a Hamilton-based advisory practice working with
                    New&nbsp;Zealand SMEs, acquirers, and founders. We exist to give business owners
                    a clear, honest, evidence-backed read on where they stand — and what to do next.
                </SectionLead>
            </Section>

            {/* ── PRINCIPLES ──────────────────────────────────── */}
            <div className="bg-[var(--fs-linen)] py-20">
                <Section>
                    <SectionEyebrow>What we hold non-negotiable</SectionEyebrow>
                    <SectionTitle className="mt-3">The AI Integrity Principle.</SectionTitle>
                    <p className="mt-5 max-w-2xl text-lg leading-relaxed text-[var(--fs-graphite)]">
                        Every output — analysis, guidance, scoring, recommendation, document review —
                        is held to the same standard.
                    </p>

                    <div className="mt-12 grid gap-6 md:grid-cols-2">
                        {[
                            {
                                title: 'Honest',
                                body: 'Problems and low scores stated clearly. Kindness in delivery, not in content.',
                            },
                            {
                                title: 'Evidence-based',
                                body: 'Every finding cites its source. AI evidences, never asserts.',
                            },
                            {
                                title: 'Accurate',
                                body: 'NZ-specific, industry-specific, current. Frameworks and references that actually apply here.',
                            },
                            {
                                title: 'Free from bias',
                                body: 'A bias-detection layer monitors AI outputs. Uncertainty is disclosed when data is insufficient.',
                            },
                            {
                                title: 'No score inflation',
                                body: 'Systematic upward drift triggers a learning-update flag. We do not grade on a curve.',
                            },
                            {
                                title: 'No suppressed warnings',
                                body: 'Viability alerts, risk flags, compliance gaps, document discrepancies — never hidden.',
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
                        <div className="aspect-[4/5] w-full overflow-hidden rounded-xl bg-[var(--fs-admiralty)] p-1">
                            <div className="flex h-full w-full items-center justify-center rounded-lg bg-gradient-to-br from-[var(--fs-commodore)] via-[var(--fs-harbour)] to-[var(--fs-pacific)] text-[var(--fs-parchment)]">
                                <div className="text-center">
                                    <div className="font-display text-6xl">PD</div>
                                    <div className="mt-3 text-[11px] tracking-[0.2em] uppercase text-[var(--fs-warm-gold)]">
                                        Principal Advisor
                                    </div>
                                </div>
                            </div>
                        </div>
                        {/* TODO: replace with a real portrait once available */}
                    </div>

                    <div className="md:col-span-7">
                        <SectionEyebrow>Principal Advisor</SectionEyebrow>
                        <h2 className="font-display mt-3 text-3xl text-[var(--fs-admiralty)] sm:text-4xl">
                            Pieter Du Plessis
                        </h2>
                        <p className="mt-6 text-base leading-relaxed text-[var(--fs-graphite)]">
                            Future Shift Advisory is led by Pieter Du Plessis from Hamilton,
                            New&nbsp;Zealand. The practice was founded on a simple frustration: that
                            too many SME owners receive advice that is either vague, flattering, or
                            disconnected from the evidence sitting in their own books.
                        </p>
                        <p className="mt-4 text-base leading-relaxed text-[var(--fs-graphite)]">
                            What we built instead is an advisory engagement that runs on structure,
                            cites every finding, and tells you what the evidence actually says — not
                            what is easiest to hear.
                        </p>

                        <blockquote className="font-accent mt-8 border-l-2 border-[var(--fs-warm-gold)] pl-5 text-2xl leading-snug italic text-[var(--fs-admiralty)]">
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
                                Hamilton,<br />working nationwide
                            </h3>
                            <p className="mt-4 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                Based in the Waikato, working with SMEs and founders across
                                New&nbsp;Zealand. Engagements are run remotely with in-person work
                                where it adds value.
                            </p>
                        </div>
                        <div>
                            <div className="eyebrow">How we are NZ-grounded</div>
                            <h3 className="font-display mt-3 text-2xl text-[var(--fs-admiralty)]">
                                NZBN, Companies Office, IRD context
                            </h3>
                            <p className="mt-4 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                Our analysis, frameworks, and references are calibrated to NZ tax,
                                compliance, and entity structures — not borrowed playbooks.
                            </p>
                        </div>
                        <div>
                            <div className="eyebrow">How we treat your data</div>
                            <h3 className="font-display mt-3 text-2xl text-[var(--fs-admiralty)]">
                                MFA, encryption, audit trail
                            </h3>
                            <p className="mt-4 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                Multi-factor authentication on every account. Documents encrypted
                                at rest and scanned before storage. Every action permanently logged.
                            </p>
                        </div>
                    </div>
                </Section>
            </div>

            <div className="bg-[var(--fs-admiralty)] py-16 text-[var(--fs-parchment)]">
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
                                Book a discovery call <ArrowRight className="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </Section>
            </div>
        </>
    );
}
