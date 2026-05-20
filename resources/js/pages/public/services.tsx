import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Check } from 'lucide-react';

import {
    GoldRule,
    Section,
    SectionEyebrow,
    SectionLead,
    SectionTitle,
} from '@/components/public/section';

type EngagementType = {
    slug: string;
    title: string;
    tagline: string;
    summary: string;
    audience: string;
    deliverables: string[];
    accent: string;
};

const accentBar: Record<string, string> = {
    pacific: 'bg-[var(--fs-pacific)]',
    admiralty: 'bg-[var(--fs-admiralty)]',
    'deep-cove': 'bg-[var(--fs-deep-cove)]',
    cognac: 'bg-[var(--fs-cognac)]',
};

export default function Services({
    engagementTypes,
}: {
    engagementTypes: EngagementType[];
}) {
    return (
        <>
            <Head title="Services — Future Shift Advisory">
                <meta
                    name="description"
                    content="Standard Advisory, Due Diligence, Post-acquisition Advisory, and the Entrepreneur Module. Four ways to engage Future Shift Advisory."
                />
            </Head>

            <Section className="pt-20 pb-16 lg:pt-24">
                <SectionEyebrow>Services</SectionEyebrow>
                <SectionTitle as="h1" className="mt-4">
                    Four ways to{' '}
                    <span className="font-accent italic text-[var(--fs-cognac)]">work with us.</span>
                </SectionTitle>
                <GoldRule className="mt-6" />
                <SectionLead>
                    Each engagement type is its own commitment, with its own deliverables and its own
                    cadence. They share one thing: every finding is evidenced, and nothing is asserted
                    without a reason.
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
                <div className="space-y-16">
                    {engagementTypes.map((e, idx) => (
                        <article
                            key={e.slug}
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
                                        <span className="eyebrow">
                                            0{idx + 1} · Engagement type
                                        </span>
                                    </div>
                                    <h2 className="font-display mt-4 text-3xl text-[var(--fs-admiralty)] sm:text-4xl">
                                        {e.title}
                                    </h2>
                                    <p className="font-accent mt-2 text-xl italic text-[var(--fs-cognac)]">
                                        {e.tagline}
                                    </p>
                                    <p className="mt-6 text-base leading-relaxed text-[var(--fs-graphite)]">
                                        {e.summary}
                                    </p>

                                    <div className="mt-6 rounded-md bg-[var(--fs-linen)] px-4 py-3 text-sm text-[var(--fs-admiralty)]">
                                        <span className="font-semibold">Who it's for:</span>{' '}
                                        <span className="text-[var(--fs-graphite)]">{e.audience}</span>
                                    </div>
                                </div>

                                <div className="md:col-span-5">
                                    <div className="rounded-lg border border-[var(--fs-sand)] bg-[var(--fs-parchment)] p-6">
                                        <div className="eyebrow">What you receive</div>
                                        <ul className="mt-4 space-y-3">
                                            {e.deliverables.map((d) => (
                                                <li key={d} className="flex gap-3 text-sm text-[var(--fs-admiralty)]">
                                                    <Check className="mt-0.5 h-4 w-4 shrink-0 text-[var(--fs-pacific)]" />
                                                    <span>{d}</span>
                                                </li>
                                            ))}
                                        </ul>
                                        <Link
                                            href={`/contact?interest=${e.slug}`}
                                            className="mt-6 inline-flex items-center gap-2 text-sm font-medium text-[var(--fs-admiralty)] hover:text-[var(--fs-pacific)]"
                                        >
                                            Enquire about {e.title.toLowerCase()}{' '}
                                            <ArrowRight className="h-4 w-4" />
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
            </Section>

            <div className="bg-[var(--fs-admiralty)] py-16 text-[var(--fs-parchment)]">
                <Section>
                    <div className="grid items-center gap-8 md:grid-cols-12">
                        <div className="md:col-span-8">
                            <h2 className="font-display text-2xl sm:text-3xl">
                                Not sure which one fits?
                            </h2>
                            <p className="font-accent mt-3 max-w-xl text-lg text-[#E0D8CC] italic">
                                Start with a discovery call. We will listen, ask, and tell you honestly
                                which engagement makes sense — or that another provider would serve you better.
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
