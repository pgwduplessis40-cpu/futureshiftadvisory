import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Monitor } from 'lucide-react';
import {
    GoldRule,
    Section,
    SectionEyebrow,
    SectionTitle,
} from '@/components/public/section';
import { Seo } from '@/components/public/seo';

export default function IdeaValidationEmailVerified() {
    return (
        <>
            <Seo
                title="Email verified"
                description="Your Idea Validation email address has been verified."
            />
            <Head title="Email verified" />
            <Section className="py-20 lg:py-24">
                <div className="mx-auto max-w-2xl">
                    <SectionEyebrow>Idea Validation</SectionEyebrow>
                    <SectionTitle as="h1" className="mt-4">
                        Email verified
                    </SectionTitle>
                    <GoldRule className="mt-6" />

                    <div className="mt-8 rounded-xl border border-[var(--fs-sand)] bg-white p-6 shadow-[0_1px_2px_rgba(28,43,69,0.04)] sm:p-8">
                        <div className="flex gap-4">
                            <CheckCircle2 className="mt-0.5 size-7 shrink-0 text-[var(--fs-pacific)]" />
                            <div>
                                <h2 className="font-display text-2xl text-[var(--fs-admiralty)]">
                                    Your email address is confirmed
                                </h2>
                                <p className="mt-3 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                    Return to the browser where you started Idea
                                    Validation. It will unlock secure payment
                                    automatically. No payment has been taken.
                                </p>
                            </div>
                        </div>

                        <div className="mt-6 flex gap-3 rounded-md bg-[var(--fs-linen)] p-4 text-sm leading-relaxed text-[var(--fs-graphite)]">
                            <Monitor className="mt-0.5 size-5 shrink-0 text-[var(--fs-pacific)]" />
                            <span>
                                For your security, this email link does not sign
                                you in on this device or start a payment.
                            </span>
                        </div>

                        <Link
                            href="/"
                            className="mt-6 inline-flex text-sm font-semibold text-[var(--fs-admiralty)] underline underline-offset-4 hover:text-[var(--fs-pacific)]"
                        >
                            Return to Future Shift Advisory
                        </Link>
                    </div>
                </div>
            </Section>
        </>
    );
}
