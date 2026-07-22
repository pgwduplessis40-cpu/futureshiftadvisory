import { Form, usePage } from '@inertiajs/react';
import { Loader2, Mail, MapPin, ShieldCheck } from 'lucide-react';
import { useMemo } from 'react';

import InputError from '@/components/input-error';
import {
    GoldRule,
    Section,
    SectionEyebrow,
    SectionTitle,
} from '@/components/public/section';
import { Seo } from '@/components/public/seo';
import { breadcrumbLd } from '@/lib/structured-data';

type Option = { value: string; label: string };

export default function Contact({
    engagementOptions,
}: {
    engagementOptions: Option[];
}) {
    const page = usePage();
    const url = page.url;
    const base = page.props.publicUrl ?? '';

    // /contact?interest=standard_advisory deep-links from the Services page.
    const initialInterest = useMemo(() => {
        const q = url.split('?')[1] ?? '';
        const params = new URLSearchParams(q);
        const interest = params.get('interest');

        return engagementOptions.some((o) => o.value === interest)
            ? interest!
            : '';
    }, [url, engagementOptions]);

    return (
        <>
            <Seo
                title="Contact us - book a discovery call"
                description="Get in touch with Future Shift Advisory. Tell us a little about your business or organisation and we will reply personally, usually within a working day."
                jsonLd={breadcrumbLd(base, [
                    { name: 'Home', path: '/' },
                    { name: 'Contact', path: '/contact' },
                ])}
            />

            <Section className="pt-20 pb-16 lg:pt-24">
                <SectionEyebrow>Contact</SectionEyebrow>
                <SectionTitle as="h1" className="mt-4">
                    Start with a{' '}
                    <span className="font-accent text-[var(--fs-cognac)] italic">
                        30-minute conversation.
                    </span>
                </SectionTitle>
                <GoldRule className="mt-6" />
                <p className="mt-6 max-w-2xl text-lg leading-relaxed text-[var(--fs-graphite)]">
                    Tell us a bit about your business and what you are trying to
                    figure out. We respond personally - usually within a working
                    day. No automated funnels.
                </p>
            </Section>

            {/* ── WHAT HAPPENS NEXT ───────────────────────────── */}
            <Section className="pb-16">
                <div className="rounded-xl border border-[var(--fs-sand)] bg-[var(--fs-linen)] p-6 md:p-8">
                    <div className="eyebrow">What happens next</div>
                    <div className="mt-6 grid gap-6 md:grid-cols-3">
                        {[
                            {
                                step: '1',
                                title: 'Book a 30-minute call',
                                body: 'No charge, no slides, no pressure. Just a conversation about what is going on in your business.',
                            },
                            {
                                step: '2',
                                title: 'We listen, and we are straight with you',
                                body: 'We ask a few good questions and tell you honestly whether we are the right fit - or if someone else would serve you better.',
                            },
                            {
                                step: '3',
                                title: 'You get a clear scope and fee',
                                body: 'If we go ahead, you will see what we will do, what it costs, and when we start - all before any work begins.',
                            },
                        ].map((s) => (
                            <div key={s.step} className="flex gap-4">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--fs-admiralty)] text-sm font-semibold text-[var(--fs-parchment)]">
                                    {s.step}
                                </div>
                                <div>
                                    <h2 className="text-sm font-semibold text-[var(--fs-admiralty)]">
                                        {s.title}
                                    </h2>
                                    <p className="mt-1.5 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                        {s.body}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </Section>

            <Section className="pb-24">
                <div className="grid gap-10 lg:grid-cols-12">
                    {/* ── FORM ────────────────────────────────── */}
                    <div className="lg:col-span-7">
                        <Form
                            action="/contact"
                            method="post"
                            resetOnSuccess
                            className="rounded-xl border border-[var(--fs-sand)] bg-white p-6 shadow-[0_1px_2px_rgba(28,43,69,0.04)] md:p-8"
                        >
                            {({ processing, errors }) => (
                                <>
                                    {/* Honeypot - visually hidden, not focusable */}
                                    <input
                                        type="text"
                                        name="website"
                                        autoComplete="off"
                                        tabIndex={-1}
                                        aria-hidden="true"
                                        className="absolute -left-[9999px] h-0 w-0 opacity-0"
                                    />

                                    <div className="grid gap-5">
                                        <Field
                                            id="name"
                                            label="Your name"
                                            required
                                            error={errors.name}
                                        >
                                            <input
                                                id="name"
                                                name="name"
                                                type="text"
                                                required
                                                autoComplete="name"
                                                className={inputClass}
                                            />
                                        </Field>

                                        <div className="grid gap-5 sm:grid-cols-2">
                                            <Field
                                                id="email"
                                                label="Email"
                                                required
                                                error={errors.email}
                                            >
                                                <input
                                                    id="email"
                                                    name="email"
                                                    type="email"
                                                    required
                                                    autoComplete="email"
                                                    className={inputClass}
                                                />
                                            </Field>

                                            <Field
                                                id="phone"
                                                label="Phone (optional)"
                                                error={errors.phone}
                                            >
                                                <input
                                                    id="phone"
                                                    name="phone"
                                                    type="tel"
                                                    autoComplete="tel"
                                                    className={inputClass}
                                                />
                                            </Field>
                                        </div>

                                        <Field
                                            id="company"
                                            label="Business / company (optional)"
                                            error={errors.company}
                                        >
                                            <input
                                                id="company"
                                                name="company"
                                                type="text"
                                                autoComplete="organization"
                                                className={inputClass}
                                            />
                                        </Field>

                                        <Field
                                            id="engagement_interest"
                                            label="What's this about?"
                                            error={errors.engagement_interest}
                                        >
                                            <select
                                                id="engagement_interest"
                                                name="engagement_interest"
                                                defaultValue={initialInterest}
                                                className={inputClass}
                                            >
                                                <option value="">
                                                    Select one (optional)
                                                </option>
                                                {engagementOptions.map(
                                                    (opt) => (
                                                        <option
                                                            key={opt.value}
                                                            value={opt.value}
                                                        >
                                                            {opt.label}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </Field>

                                        <Field
                                            id="message"
                                            label="Tell us a bit more"
                                            required
                                            error={errors.message}
                                            hint="A sentence or two on the business and what you are trying to figure out."
                                        >
                                            <textarea
                                                id="message"
                                                name="message"
                                                required
                                                rows={6}
                                                className={[
                                                    inputClass,
                                                    'min-h-[160px] resize-y',
                                                ].join(' ')}
                                            />
                                        </Field>

                                        <div className="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                                            <p className="text-xs text-[var(--fs-graphite)]">
                                                By submitting you agree we can
                                                contact you about your enquiry.
                                            </p>
                                            <button
                                                type="submit"
                                                disabled={processing}
                                                className="inline-flex items-center justify-center gap-2 rounded-md bg-[var(--fs-admiralty)] px-5 py-3 text-sm font-medium text-[var(--fs-parchment)] shadow-sm transition-colors hover:bg-[var(--fs-commodore)] disabled:opacity-60"
                                            >
                                                {processing && (
                                                    <Loader2 className="h-4 w-4 animate-spin" />
                                                )}
                                                Send enquiry
                                            </button>
                                        </div>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>

                    {/* ── ASIDE ───────────────────────────────── */}
                    <aside className="space-y-6 lg:col-span-5">
                        <div className="rounded-xl border border-[var(--fs-sand)] bg-[var(--fs-linen)] p-6">
                            <div className="eyebrow">Get in touch directly</div>
                            <ul className="mt-4 space-y-3 text-sm text-[var(--fs-admiralty)]">
                                <li className="flex items-start gap-3">
                                    <MapPin className="mt-0.5 h-4 w-4 text-[var(--fs-cognac)]" />
                                    <span>Hamilton, New&nbsp;Zealand</span>
                                </li>
                                <li className="flex items-start gap-3">
                                    <Mail className="mt-0.5 h-4 w-4 text-[var(--fs-cognac)]" />
                                    <a
                                        href="mailto:hello@futureshiftadvisory.nz"
                                        className="hover:underline"
                                    >
                                        hello@futureshiftadvisory.nz
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <div className="rounded-xl border border-[var(--fs-sand)] bg-white p-6">
                            <div className="flex items-start gap-3">
                                <ShieldCheck className="mt-0.5 h-5 w-5 text-[var(--fs-pacific)]" />
                                <div>
                                    <h3 className="text-sm font-semibold text-[var(--fs-admiralty)]">
                                        Confidentiality is the baseline
                                    </h3>
                                    <p className="mt-2 text-sm leading-relaxed text-[var(--fs-graphite)]">
                                        Your enquiry is treated as confidential.
                                        We do not share, sell, or use it for
                                        marketing lists. If we are not the right
                                        fit, we say so.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </Section>
        </>
    );
}

const inputClass =
    'block w-full rounded-md border border-[var(--fs-sand)] bg-white px-3 py-2.5 text-sm text-[var(--fs-admiralty)] shadow-[inset_0_1px_0_rgba(28,43,69,0.02)] outline-none transition focus:border-[var(--fs-pacific)] focus:ring-2 focus:ring-[var(--fs-pacific)]/20 disabled:opacity-60';

function Field({
    id,
    label,
    children,
    required,
    error,
    hint,
}: {
    id: string;
    label: string;
    children: React.ReactNode;
    required?: boolean;
    error?: string;
    hint?: string;
}) {
    return (
        <div>
            <label
                htmlFor={id}
                className="mb-1.5 block text-sm font-medium text-[var(--fs-admiralty)]"
            >
                {label}
                {required && (
                    <span className="ml-1 text-[var(--fs-cognac)]">*</span>
                )}
            </label>
            {children}
            {hint && !error && (
                <p className="mt-1.5 text-xs text-[var(--fs-graphite)]">
                    {hint}
                </p>
            )}
            <InputError message={error} className="mt-1.5" />
        </div>
    );
}
