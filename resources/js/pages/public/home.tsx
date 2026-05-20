import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, ShieldCheck, Sparkles } from 'lucide-react';

import {
    GoldRule,
    Section,
    SectionEyebrow,
    SectionLead,
    SectionTitle,
} from '@/components/public/section';

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
};

export default function Home({
    engagementTypes,
}: {
    engagementTypes: EngagementSummary[];
}) {
    return (
        <>
            <Head title="Future Shift Advisory — evidence-based advisory for NZ SMEs">
                <meta
                    name="description"
                    content="Evidence-based business advisory, due diligence, and entrepreneur support for New Zealand SMEs and founders. Hamilton-based, nationwide."
                />
            </Head>

            {/* ── HERO ─────────────────────────────────────────── */}
            <Section className="pt-20 pb-24 lg:pt-28 lg:pb-32">
                <div className="grid gap-12 lg:grid-cols-12 lg:gap-16">
                    <div className="lg:col-span-7">
                        <SectionEyebrow>Future Shift Advisory</SectionEyebrow>
                        <SectionTitle as="h1" className="mt-4">
                            Your business, shifted{' '}
                            <span className="font-accent italic text-[var(--fs-cognac)]">
                                forward.
                            </span>
                        </SectionTitle>
                        <GoldRule className="mt-6" />
                        <p className="mt-6 max-w-xl text-lg leading-relaxed text-[var(--fs-graphite)]">
                            Evidence-based business advisory for New&nbsp;Zealand SMEs and entrepreneurs.
                            Honest findings. Cited sources. The truth before the comfortable.
                        </p>
                        <div className="mt-10 flex flex-wrap items-center gap-4">
                            <Link
                                href="/contact"
                                className="inline-flex items-center gap-2 rounded-md bg-[var(--fs-admiralty)] px-5 py-3 text-sm font-medium text-[var(--fs-parchment)] shadow-sm transition-colors hover:bg-[var(--fs-commodore)]"
                            >
                                Book a discovery call <ArrowRight className="h-4 w-4" />
                            </Link>
                            <Link
                                href="/services"
                                className="inline-flex items-center gap-2 text-sm font-medium text-[var(--fs-admiralty)] hover:text-[var(--fs-pacific)]"
                            >
                                See how we work <ArrowRight className="h-4 w-4" />
                            </Link>
                        </div>
                    </div>

                    <div className="lg:col-span-5">
                        <div className="rounded-xl border border-[var(--fs-sand)] bg-white p-6 shadow-[0_1px_2px_rgba(28,43,69,0.04)]">
                            <div className="eyebrow">What you can expect</div>
                            <ul className="mt-4 space-y-4">
                                {[
                                    {
                                        icon: CheckCircle2,
                                        title: 'Honest findings',
                                        body: 'Problems and low scores stated clearly. Kindness in delivery, not in content.',
                                    },
                                    {
                                        icon: Sparkles,
                                        title: 'Evidence, not assertion',
                                        body: 'Every finding cites its source. AI evidences, never asserts.',
                                    },
                                    {
                                        icon: ShieldCheck,
                                        title: 'NZ-grounded & confidential',
                                        body: 'MFA on every account, encrypted documents, immutable audit trail.',
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
                    <SectionEyebrow>How we work together</SectionEyebrow>
                    <SectionTitle className="mt-3">Four ways in.</SectionTitle>
                    <SectionLead>
                        Most engagements start with a Standard Advisory diagnostic. Others come in
                        through due diligence on an acquisition, post-acquisition advisory, or the
                        entrepreneur module for founders pre-launch.
                    </SectionLead>

                    <div className="mt-12 grid gap-6 md:grid-cols-2">
                        {engagementTypes.map((e) => (
                            <Link
                                key={e.slug}
                                href={`/services#${e.slug}`}
                                className={[
                                    'group rounded-lg border border-[var(--fs-sand)] border-l-4 bg-white p-6 shadow-[0_1px_2px_rgba(28,43,69,0.03)] transition hover:shadow-[0_8px_24px_rgba(28,43,69,0.08)]',
                                    accentClass[e.accent] ?? 'border-l-[var(--fs-pacific)]',
                                ].join(' ')}
                            >
                                <div className="font-display text-2xl text-[var(--fs-admiralty)]">
                                    {e.title}
                                </div>
                                <div className="font-accent mt-1 text-base italic text-[var(--fs-cognac)]">
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
                            kicker: 'NZ-grounded',
                            title: 'Hamilton, working nationwide',
                            body: 'Our analysis, frameworks, and references are New Zealand-specific — NZBN, Companies Office, IRD, NZ tax and compliance context.',
                        },
                        {
                            kicker: 'Evidence-based',
                            title: 'Every finding cites its source',
                            body: 'No score inflation. No suppressed warnings. Uncertainty is disclosed when the data does not support a confident conclusion.',
                        },
                        {
                            kicker: 'Built for confidentiality',
                            title: 'MFA, encryption, audit trail',
                            body: 'Mandatory MFA on every account. Documents encrypted at rest and scanned before storage. Every action permanently logged.',
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
            <div className="bg-[var(--fs-admiralty)] py-20 text-[var(--fs-parchment)]">
                <Section>
                    <div className="grid items-center gap-10 md:grid-cols-12">
                        <div className="md:col-span-8">
                            <p className="text-[11px] font-semibold tracking-[0.15em] text-[var(--fs-warm-gold)] uppercase">
                                Ready when you are
                            </p>
                            <h2 className="font-display mt-3 text-3xl leading-tight text-[var(--fs-parchment)] sm:text-4xl">
                                Have a 30-minute conversation with us.
                            </h2>
                            <p className="font-accent mt-4 max-w-xl text-lg text-[#E0D8CC] italic">
                                No prepared slides. No upsell. We listen, ask, and tell you honestly
                                whether we are the right fit.
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
