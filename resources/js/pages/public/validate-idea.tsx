import { Link } from '@inertiajs/react';
import { ArrowRight, Check } from 'lucide-react';
import { BackToTop } from '@/components/public/back-to-top';
import {
    GoldRule,
    Section,
    SectionEyebrow,
    SectionTitle,
} from '@/components/public/section';
import { Seo } from '@/components/public/seo';

type Offer = {
    available: boolean;
    label: string;
    amount_ex_gst: number | null;
    currency: string | null;
};

export default function ValidateIdea({ offer }: { offer: Offer }) {
    const price =
        offer.available && offer.amount_ex_gst !== null
            ? `$${offer.amount_ex_gst.toLocaleString('en-NZ', { maximumFractionDigits: 2 })} + GST`
            : null;

    return (
        <>
            <Seo
                title="Validate your business idea"
                description="Submit your idea for advisor-reviewed validation with Future Shift Advisory."
            />
            <Section className="py-20 lg:py-24">
                <SectionEyebrow>Idea Validation</SectionEyebrow>
                <SectionTitle as="h1" className="mt-4">
                    Find out what your idea needs before you build it.
                </SectionTitle>
                <GoldRule className="mt-6" />
                <p className="mt-6 max-w-2xl text-lg leading-relaxed text-[var(--fs-graphite)]">
                    Create your account, verify your email, protect it with an
                    authenticator app, then pay securely by card. Your answers
                    go to an advisor for a practical review within 24 hours.
                </p>
                {price ? (
                    <p className="mt-5 text-lg font-semibold text-[var(--fs-admiralty)]">
                        Validate your idea — {price}.
                    </p>
                ) : null}
                <div className="mt-10 grid max-w-3xl gap-4 sm:grid-cols-2">
                    {[
                        'Create an account and verify your email',
                        'Set up authenticator-app MFA and recovery codes',
                        'Accept FSA terms and pay securely via Stripe',
                        'Complete the validation questions for advisor review',
                    ].map((step) => (
                        <div
                            key={step}
                            className="flex gap-3 rounded-lg border border-[var(--fs-sand)] bg-white p-5"
                        >
                            <Check className="mt-0.5 h-4 w-4 shrink-0 text-[var(--fs-pacific)]" />
                            <span className="text-sm text-[var(--fs-graphite)]">
                                {step}
                            </span>
                        </div>
                    ))}
                </div>
                <div className="mt-10 rounded-lg border border-[var(--fs-sand)] bg-[var(--fs-linen)] p-6">
                    <h2 className="font-display text-xl text-[var(--fs-admiralty)]">
                        Secure checkout is being connected
                    </h2>
                    <p className="mt-3 max-w-xl text-sm leading-relaxed text-[var(--fs-graphite)]">
                        The page now shows the live Service Rates price and the
                        exact account-to-validation journey. Card checkout is
                        intentionally not simulated: it will only open once the
                        Stripe purchase record and receipt flow are connected.
                    </p>
                    <Link
                        href="/services/entrepreneur"
                        className="mt-5 inline-flex items-center gap-2 text-sm font-medium text-[var(--fs-admiralty)] hover:text-[var(--fs-pacific)]"
                    >
                        Back to founder services{' '}
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>
            </Section>
            <BackToTop />
        </>
    );
}
