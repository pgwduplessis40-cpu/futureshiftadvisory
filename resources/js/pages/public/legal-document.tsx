import { Head, Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { BackToTop } from '@/components/public/back-to-top';
import {
    GoldRule,
    Section,
    SectionEyebrow,
    SectionTitle,
} from '@/components/public/section';
import { Seo } from '@/components/public/seo';

type Clause = {
    id: string;
    clause_number: number;
    title: string;
    body: string;
};

type LegalDocument = {
    published: boolean;
    title: string;
    version: string | null;
    published_at: string | null;
    source_preview_html: string | null;
    clauses: Clause[];
};

type Props = {
    document: LegalDocument;
    documentLabel: string;
    jsonUrl: string;
    returnToIdeaValidation: boolean;
};

export default function LegalDocument({
    document,
    documentLabel,
    jsonUrl,
    returnToIdeaValidation,
}: Props) {
    return (
        <>
            <Seo
                title={documentLabel}
                description={`Future Shift Advisory's current published ${documentLabel.toLowerCase()}.`}
            />
            <Head>
                <link rel="alternate" type="application/json" href={jsonUrl} />
            </Head>
            <Section className="py-20 lg:py-24">
                {returnToIdeaValidation ? (
                    <Link
                        href="/validate-idea/purchase"
                        className="inline-flex items-center text-sm font-semibold text-[var(--fs-admiralty)] underline underline-offset-4 hover:text-[var(--fs-pacific)]"
                    >
                        ← Back to Idea Validation account setup
                    </Link>
                ) : null}
                <SectionEyebrow>Legal</SectionEyebrow>
                <SectionTitle as="h1" className="mt-4">
                    {documentLabel}
                </SectionTitle>
                <GoldRule className="mt-6" />

                {!document.published ? (
                    <div className="mt-8 max-w-3xl rounded-lg border border-[var(--fs-sand)] bg-[var(--fs-linen)] p-6">
                        <div className="flex items-start gap-3">
                            <FileText className="mt-0.5 h-5 w-5 shrink-0 text-[var(--fs-admiralty)]" />
                            <div>
                                <h2 className="font-display text-xl text-[var(--fs-admiralty)]">
                                    Document not yet published
                                </h2>
                                <p className="mt-2 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                    The current {documentLabel} will appear here
                                    once it has been reviewed and published by
                                    Future Shift Advisory.
                                </p>
                            </div>
                        </div>
                    </div>
                ) : (
                    <article className="mt-8 max-w-4xl rounded-lg border border-[var(--fs-sand)] bg-white p-6 shadow-sm sm:p-8">
                        <header className="border-b border-[var(--fs-sand)] pb-5">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <h2 className="font-display text-2xl text-[var(--fs-admiralty)]">
                                        {document.title}
                                    </h2>
                                    {document.version && (
                                        <p className="mt-2 text-sm text-[var(--fs-graphite)]">
                                            Version {document.version}
                                            {document.published_at
                                                ? ` · Published ${new Intl.DateTimeFormat(
                                                      'en-NZ',
                                                      {
                                                          day: 'numeric',
                                                          month: 'long',
                                                          year: 'numeric',
                                                      },
                                                  ).format(
                                                      new Date(
                                                          document.published_at,
                                                      ),
                                                  )}`
                                                : ''}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </header>

                        {document.source_preview_html ? (
                            <div
                                className="fsa-legal-document prose prose-slate mt-7 max-w-none"
                                dangerouslySetInnerHTML={{
                                    __html: document.source_preview_html,
                                }}
                            />
                        ) : (
                            <div className="mt-7 space-y-7">
                                {document.clauses.map((clause) => (
                                    <section key={clause.id}>
                                        <h3 className="font-display text-xl text-[var(--fs-admiralty)]">
                                            {clause.clause_number}.{' '}
                                            {clause.title}
                                        </h3>
                                        <p className="mt-3 whitespace-pre-line text-[var(--fs-graphite)]">
                                            {clause.body}
                                        </p>
                                    </section>
                                ))}
                            </div>
                        )}
                    </article>
                )}
            </Section>
            <BackToTop />
        </>
    );
}
