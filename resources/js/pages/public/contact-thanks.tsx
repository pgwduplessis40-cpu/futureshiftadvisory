import { Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2 } from 'lucide-react';

import {
    GoldRule,
    Section,
    SectionEyebrow,
    SectionTitle,
} from '@/components/public/section';
import { Seo } from '@/components/public/seo';

export default function ContactThanks() {
    return (
        <>
            <Seo
                title="Thanks - we’ll be in touch"
                description="Your enquiry has reached Future Shift Advisory."
                noindex
            />

            <Section className="py-24 lg:py-32">
                <div className="mx-auto max-w-2xl text-center">
                    <CheckCircle2 className="mx-auto h-12 w-12 text-[var(--fs-pacific)]" />
                    <SectionEyebrow>
                        <span className="mt-6 inline-block">
                            Enquiry received
                        </span>
                    </SectionEyebrow>
                    <SectionTitle className="mt-4">
                        Thanks -{' '}
                        <span className="font-accent text-[var(--fs-cognac)] italic">
                            we&rsquo;ll be in touch.
                        </span>
                    </SectionTitle>
                    <GoldRule className="mx-auto mt-6" />
                    <p className="mt-6 text-lg leading-relaxed text-[var(--fs-graphite)]">
                        Your message has reached us. We respond personally -
                        usually within a working day. If something is urgent,
                        email{' '}
                        <a
                            href="mailto:hello@futureshiftadvisory.nz"
                            className="text-[var(--fs-admiralty)] underline underline-offset-2 hover:text-[var(--fs-pacific)]"
                        >
                            hello@futureshiftadvisory.nz
                        </a>{' '}
                        directly.
                    </p>

                    <div className="mt-10 flex flex-wrap justify-center gap-4">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-2 rounded-md bg-[var(--fs-admiralty)] px-5 py-3 text-sm font-medium text-[var(--fs-parchment)] transition hover:bg-[var(--fs-commodore)]"
                        >
                            Back to home
                        </Link>
                        <Link
                            href="/services"
                            className="inline-flex items-center gap-2 rounded-md border border-[var(--fs-sand)] bg-white px-5 py-3 text-sm font-medium text-[var(--fs-admiralty)] hover:bg-[var(--fs-linen)]"
                        >
                            Explore services <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            </Section>
        </>
    );
}
