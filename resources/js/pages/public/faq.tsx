import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Plus } from 'lucide-react';

import {
    GoldRule,
    Section,
    SectionEyebrow,
    SectionLead,
    SectionTitle,
} from '@/components/public/section';
import { Seo } from '@/components/public/seo';
import { breadcrumbLd } from '@/lib/structured-data';

type Faq = { group: string; question: string; answer: string };

export default function Faq({ faqs }: { faqs: Faq[] }) {
    const base = usePage().props.publicUrl ?? '';

    const grouped = faqs.reduce<Record<string, Faq[]>>((acc, f) => {
        (acc[f.group] ??= []).push(f);

        return acc;
    }, {});

    const faqJsonLd = {
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: faqs.map((f) => ({
            '@type': 'Question',
            name: f.question,
            acceptedAnswer: { '@type': 'Answer', text: f.answer },
        })),
    };

    return (
        <>
            <Seo
                title="Frequently asked questions"
                description="Honest answers about how we work, what engagements cost, security and confidentiality, support for not-for-profits, and whether we use AI."
                jsonLd={[
                    faqJsonLd,
                    breadcrumbLd(base, [
                        { name: 'Home', path: '/' },
                        { name: 'FAQ', path: '/faq' },
                    ]),
                ]}
            />

            <Section className="pt-20 pb-16 lg:pt-24">
                <SectionEyebrow>FAQ</SectionEyebrow>
                <SectionTitle as="h1" className="mt-4">
                    Honest answers to{' '}
                    <span className="font-accent text-[var(--fs-cognac)] italic">
                        common questions.
                    </span>
                </SectionTitle>
                <GoldRule className="mt-6" />
                <SectionLead>
                    If your question is not here, the contact form is the
                    fastest way to a real answer. We do not gate our reply
                    behind a sales sequence.
                </SectionLead>
            </Section>

            <Section className="pb-20">
                <div className="space-y-12">
                    {Object.entries(grouped).map(([group, items]) => (
                        <div key={group}>
                            <h2 className="font-display text-2xl text-[var(--fs-admiralty)]">
                                {group}
                            </h2>
                            <div className="mt-4 divide-y divide-[var(--fs-sand)] border-y border-[var(--fs-sand)]">
                                {items.map((f) => (
                                    <details
                                        key={f.question}
                                        className="group py-5 [&_summary::-webkit-details-marker]:hidden"
                                    >
                                        <summary className="flex cursor-pointer list-none items-start justify-between gap-6">
                                            <span className="text-base font-medium text-[var(--fs-admiralty)]">
                                                {f.question}
                                            </span>
                                            <Plus className="mt-1 h-5 w-5 shrink-0 text-[var(--fs-cognac)] transition-transform group-open:rotate-45" />
                                        </summary>
                                        <p className="mt-3 max-w-3xl text-sm leading-relaxed text-[var(--fs-graphite)]">
                                            {f.answer}
                                        </p>
                                    </details>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </Section>

            <div className="bg-[var(--fs-linen)] py-16">
                <Section>
                    <div className="grid items-center gap-8 md:grid-cols-12">
                        <div className="md:col-span-8">
                            <h2 className="font-display text-2xl text-[var(--fs-admiralty)] sm:text-3xl">
                                Still have a question?
                            </h2>
                            <p className="mt-3 max-w-xl text-base text-[var(--fs-graphite)]">
                                Ask it directly. We respond personally - usually
                                within a working day.
                            </p>
                        </div>
                        <div className="md:col-span-4 md:text-right">
                            <Link
                                href="/contact"
                                className="inline-flex items-center gap-2 rounded-md bg-[var(--fs-admiralty)] px-5 py-3 text-sm font-medium text-[var(--fs-parchment)] transition hover:bg-[var(--fs-commodore)]"
                            >
                                Ask a question{' '}
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </Section>
            </div>
        </>
    );
}
