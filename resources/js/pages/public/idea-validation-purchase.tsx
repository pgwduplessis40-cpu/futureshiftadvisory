import { Form, Head, Link } from '@inertiajs/react';
import { loadStripe } from '@stripe/stripe-js';
import type {
    Stripe,
    StripeElements,
    StripePaymentElement,
} from '@stripe/stripe-js';
import { CreditCard, Loader2, Mail, ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import {
    GoldRule,
    Section,
    SectionEyebrow,
    SectionTitle,
} from '@/components/public/section';
import { Seo } from '@/components/public/seo';
import { Button } from '@/components/ui/button';

type State = 'register' | 'verify_email' | 'checkout' | 'existing_session';

type Purchase = {
    id: string;
    status: string;
    email_verified_at: string | null;
    amount_ex_gst: number | null;
    gst_amount: number | null;
    amount_including_gst: number | null;
    currency: string | null;
};

type Terms = {
    id: string;
    version: string;
    title: string;
    url: string;
};

type StripeIntentPayload = {
    publishable_key: string;
    client_secret: string;
    payment_intent_id: string;
    fixture: boolean;
    amount_ex_gst: number | null;
    gst_amount: number | null;
    amount_including_gst: number | null;
    currency: string | null;
};

export default function IdeaValidationPurchase({
    state,
    purchase,
    terms,
}: {
    state: State;
    purchase: Purchase | null;
    terms: Terms | null;
}) {
    return (
        <>
            <Seo
                title="Purchase Idea Validation"
                description="Create your Future Shift Advisory account, verify your email, and purchase Idea Validation securely."
            />
            <Head title="Purchase Idea Validation" />
            <Section className="py-20 lg:py-24">
                <div className="mx-auto max-w-2xl">
                    <SectionEyebrow>Idea Validation</SectionEyebrow>
                    <SectionTitle as="h1" className="mt-4">
                        {state === 'register'
                            ? 'Start your Idea Validation'
                            : state === 'verify_email'
                              ? 'Verify your email'
                              : state === 'existing_session'
                                ? 'Use a separate browser session'
                                : 'Secure payment'}
                    </SectionTitle>
                    <GoldRule className="mt-6" />

                    <ol className="mt-8 grid grid-cols-3 gap-2 text-center text-xs font-medium text-[var(--fs-graphite)] sm:text-sm">
                        {['Account', 'Verify email', 'Payment'].map(
                            (label, index) => {
                                const active =
                                    (state === 'register' && index === 0) ||
                                    (state === 'verify_email' && index === 1) ||
                                    (state === 'checkout' && index === 2);

                                return (
                                    <li
                                        key={label}
                                        className={`rounded-md border px-2 py-2 ${active ? 'border-[var(--fs-admiralty)] bg-[var(--fs-admiralty)] text-white' : 'border-[var(--fs-sand)] bg-white'}`}
                                    >
                                        {index + 1}. {label}
                                    </li>
                                );
                            },
                        )}
                    </ol>

                    <div className="mt-6 rounded-xl border border-[var(--fs-sand)] bg-white p-6 shadow-[0_1px_2px_rgba(28,43,69,0.04)] sm:p-8">
                        {state === 'register' ? (
                            <AccountForm terms={terms} />
                        ) : null}
                        {state === 'existing_session' ? (
                            <ExistingSession />
                        ) : null}
                        {state === 'verify_email' ? <VerifyEmail /> : null}
                        {state === 'checkout' && purchase ? (
                            <Checkout purchase={purchase} />
                        ) : null}
                    </div>

                    <p className="mt-5 text-center text-xs leading-relaxed text-[var(--fs-graphite)]">
                        Payment details are collected by Stripe. Future Shift
                        Advisory does not store card details.
                    </p>
                </div>
            </Section>
        </>
    );
}

function ExistingSession() {
    return (
        <div className="space-y-5">
            <div className="flex gap-4">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-[var(--fs-linen)] text-[var(--fs-admiralty)]">
                    <ShieldCheck className="size-5" />
                </div>
                <div>
                    <h2 className="font-display text-2xl text-[var(--fs-admiralty)]">
                        A portal account is already signed in
                    </h2>
                    <p className="mt-2 text-sm leading-relaxed text-[var(--fs-graphite)]">
                        To protect the account already open in this browser, a
                        new Idea Validation account cannot be created here.
                    </p>
                </div>
            </div>
            <div className="rounded-md border border-[var(--fs-sand)] bg-[var(--fs-linen)] p-4 text-sm leading-relaxed text-[var(--fs-graphite)]">
                Sign out of the current portal account, then return to this
                page, or open the checkout in a private browser window. No new
                account, payment, or email-verification action will be created
                or completed in this session.
            </div>
            <Link
                href="/dashboard"
                className="inline-flex text-sm font-semibold text-[var(--fs-admiralty)] underline"
            >
                Return to the signed-in portal
            </Link>
        </div>
    );
}

function AccountForm({ terms }: { terms: Terms | null }) {
    return (
        <Form
            action="/validate-idea/purchase"
            method="post"
            className="space-y-5"
        >
            {({ processing, errors }) => (
                <>
                    <div>
                        <h2 className="font-display text-2xl text-[var(--fs-admiralty)]">
                            Create your account
                        </h2>
                        <p className="mt-2 text-sm leading-relaxed text-[var(--fs-graphite)]">
                            We will verify this email before opening payment.
                        </p>
                    </div>

                    {!terms ? (
                        <div className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                            Checkout is temporarily unavailable while the
                            current Terms and Privacy Policy is being published.
                        </div>
                    ) : (
                        <>
                            <input
                                type="hidden"
                                name="terms_version_id"
                                value={terms.id}
                            />
                            <Field label="Name" error={errors.name}>
                                <input
                                    name="name"
                                    required
                                    autoComplete="name"
                                    className={inputClass}
                                />
                            </Field>
                            <Field label="Email address" error={errors.email}>
                                <input
                                    name="email"
                                    type="email"
                                    required
                                    autoComplete="email"
                                    className={inputClass}
                                />
                            </Field>
                            <Field label="Password" error={errors.password}>
                                <input
                                    name="password"
                                    type="password"
                                    required
                                    autoComplete="new-password"
                                    className={inputClass}
                                />
                            </Field>
                            <Field
                                label="Confirm password"
                                error={errors.password_confirmation}
                            >
                                <input
                                    name="password_confirmation"
                                    type="password"
                                    required
                                    autoComplete="new-password"
                                    className={inputClass}
                                />
                            </Field>
                            <label
                                htmlFor="idea-validation-terms-accepted"
                                className="flex items-start gap-3 rounded-md border border-[var(--fs-sand)] bg-[var(--fs-linen)] p-4 text-sm leading-relaxed text-[var(--fs-graphite)]"
                            >
                                <input
                                    id="idea-validation-terms-accepted"
                                    name="terms_accepted"
                                    type="checkbox"
                                    value="1"
                                    required
                                    className="mt-1 size-4 rounded border-[var(--fs-sand)]"
                                />
                                <span>
                                    I have read and agree to the{' '}
                                    <Link
                                        href={terms.url}
                                        target="_blank"
                                        className="font-semibold text-[var(--fs-admiralty)] underline"
                                    >
                                        {terms.title} (version {terms.version})
                                    </Link>
                                    .
                                </span>
                            </label>
                            <InputError message={errors.terms_accepted} />
                            <InputError message={errors.terms_version_id} />
                            <InputError message={errors.checkout} />
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full bg-[var(--fs-admiralty)] hover:bg-[var(--fs-pacific)]"
                            >
                                {processing ? (
                                    <Loader2 className="size-4 animate-spin" />
                                ) : (
                                    <Mail className="size-4" />
                                )}
                                Create account and verify email
                            </Button>
                        </>
                    )}

                    <p className="text-center text-sm text-[var(--fs-graphite)]">
                        Already have an account?{' '}
                        <Link
                            href="/login"
                            className="font-semibold text-[var(--fs-admiralty)] underline"
                        >
                            Log in
                        </Link>
                    </p>
                </>
            )}
        </Form>
    );
}

function VerifyEmail() {
    return (
        <div className="space-y-5">
            <div className="flex gap-4">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-[var(--fs-linen)] text-[var(--fs-admiralty)]">
                    <Mail className="size-5" />
                </div>
                <div>
                    <h2 className="font-display text-2xl text-[var(--fs-admiralty)]">
                        Check your inbox
                    </h2>
                    <p className="mt-2 text-sm leading-relaxed text-[var(--fs-graphite)]">
                        We have sent a verification link. Open it to continue to
                        secure payment. The link expires after 60 minutes.
                    </p>
                </div>
            </div>
            <Form
                method="post"
                action="/validate-idea/purchase/resend-verification"
            >
                {({ processing }) => (
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={processing}
                    >
                        {processing ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <Mail className="size-4" />
                        )}
                        Resend verification email
                    </Button>
                )}
            </Form>
        </div>
    );
}

function Checkout({ purchase }: { purchase: Purchase }) {
    const elementRef = useRef<HTMLDivElement>(null);
    const stripeRef = useRef<Stripe | null>(null);
    const elementsRef = useRef<StripeElements | null>(null);
    const paymentElementRef = useRef<StripePaymentElement | null>(null);
    const [intent, setIntent] = useState<StripeIntentPayload | null>(null);
    const [status, setStatus] = useState<
        'loading' | 'ready' | 'error' | 'confirming' | 'pending'
    >('loading');
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        const setup = async () => {
            try {
                setStatus('loading');
                setError(null);

                const response = await fetch(
                    '/validate-idea/purchase/payment-intent',
                    {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                    },
                );
                const payload = (await response.json().catch(() => null)) as
                    | StripeIntentPayload
                    | ErrorPayload
                    | null;

                if (!response.ok || !payload || !('client_secret' in payload)) {
                    throw new Error(
                        errorMessage(
                            payload,
                            'Secure checkout could not be started.',
                        ),
                    );
                }

                if (cancelled) {
                    return;
                }

                setIntent(payload);

                if (payload.fixture) {
                    setStatus('ready');

                    return;
                }

                const stripe = await loadStripe(payload.publishable_key);

                if (!stripe) {
                    throw new Error(
                        'Stripe could not be loaded in this browser.',
                    );
                }

                const elements = stripe.elements({
                    clientSecret: payload.client_secret,
                });
                const paymentElement = elements.create('payment', {
                    layout: 'tabs',
                });

                if (cancelled || !elementRef.current) {
                    paymentElement.unmount();

                    return;
                }

                paymentElement.mount(elementRef.current);
                stripeRef.current = stripe;
                elementsRef.current = elements;
                paymentElementRef.current = paymentElement;
                setStatus('ready');
            } catch (reason) {
                if (!cancelled) {
                    setError(
                        reason instanceof Error
                            ? reason.message
                            : 'Secure checkout could not be started.',
                    );
                    setStatus('error');
                }
            }
        };

        void setup();

        return () => {
            cancelled = true;
            paymentElementRef.current?.unmount();
            paymentElementRef.current = null;
        };
    }, []);

    const confirm = async (event: FormEvent) => {
        event.preventDefault();

        if (!stripeRef.current || !elementsRef.current || !intent) {
            return;
        }

        setStatus('confirming');
        setError(null);

        const submitted = await elementsRef.current.submit();

        if (submitted.error) {
            setError(
                submitted.error.message ??
                    'Check your payment details and try again.',
            );
            setStatus('ready');

            return;
        }

        const confirmed = await stripeRef.current.confirmPayment({
            elements: elementsRef.current,
            confirmParams: {
                return_url: `${window.location.origin}/validate-idea/purchase`,
            },
            redirect: 'if_required',
        });

        if (confirmed.error) {
            setError(
                confirmed.error.message ??
                    'Stripe could not confirm your payment.',
            );
            setStatus('ready');

            return;
        }

        await confirmWithServer(intent.payment_intent_id, setError, setStatus);
    };

    const confirmFixture = async () => {
        setStatus('confirming');
        setError(null);

        try {
            const response = await fetch(
                '/validate-idea/purchase/confirm-fixture-payment',
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                },
            );
            const payload = (await response.json().catch(() => null)) as
                | { paid?: boolean; next_url?: string }
                | ErrorPayload
                | null;

            if (
                !response.ok ||
                !payload ||
                !('paid' in payload) ||
                !payload.paid
            ) {
                throw new Error(
                    errorMessage(
                        payload,
                        'Test payment could not be completed.',
                    ),
                );
            }

            window.location.assign(payload.next_url ?? '/mfa/setup');
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Test payment could not be completed.',
            );
            setStatus('ready');
        }
    };

    const net = intent?.amount_ex_gst ?? purchase.amount_ex_gst;
    const gst = intent?.gst_amount ?? purchase.gst_amount;
    const gross = intent?.amount_including_gst ?? purchase.amount_including_gst;
    const currency = intent?.currency ?? purchase.currency ?? 'NZD';

    return (
        <div className="space-y-6">
            <div>
                <h2 className="font-display text-2xl text-[var(--fs-admiralty)]">
                    Pay securely with Stripe
                </h2>
                <p className="mt-2 text-sm leading-relaxed text-[var(--fs-graphite)]">
                    Your payment activates Idea Validation automatically. An
                    advisor is assigned after payment is confirmed.
                </p>
            </div>

            <div className="rounded-md border border-[var(--fs-sand)] bg-[var(--fs-linen)] p-4 text-sm text-[var(--fs-graphite)]">
                <div className="flex justify-between gap-4">
                    <span>Idea Validation</span>
                    <span className="font-semibold">
                        {net === null
                            ? 'Loading current price…'
                            : money(net, currency)}
                    </span>
                </div>
                <div className="mt-2 flex justify-between gap-4">
                    <span>GST</span>
                    <span>{gst === null ? '—' : money(gst, currency)}</span>
                </div>
                <div className="mt-3 flex justify-between gap-4 border-t border-[var(--fs-sand)] pt-3 font-semibold text-[var(--fs-admiralty)]">
                    <span>Total</span>
                    <span>
                        {gross === null ? 'Loading…' : money(gross, currency)}
                    </span>
                </div>
            </div>

            {error ? (
                <div className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
                    {error}
                </div>
            ) : null}
            {status === 'pending' ? (
                <div className="rounded-md border border-[var(--fs-sand)] bg-[var(--fs-linen)] p-4 text-sm text-[var(--fs-graphite)]">
                    Stripe is processing your payment. We will activate access
                    and email you once it is confirmed.
                </div>
            ) : null}

            {intent?.fixture ? (
                <div className="space-y-4">
                    <div className="rounded-md border border-dashed border-[var(--fs-sand)] p-4 text-sm text-[var(--fs-graphite)]">
                        Test environment: no card details are collected. Use
                        this button to exercise the same server-side payment,
                        receipt, activation, and notification flow.
                    </div>
                    <Button
                        type="button"
                        onClick={() => void confirmFixture()}
                        disabled={status !== 'ready'}
                        className="w-full bg-[var(--fs-admiralty)] hover:bg-[var(--fs-pacific)]"
                    >
                        {status === 'confirming' ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <CreditCard className="size-4" />
                        )}
                        Complete test payment
                    </Button>
                </div>
            ) : (
                <form onSubmit={confirm} className="space-y-5">
                    <div
                        ref={elementRef}
                        className={
                            status === 'loading' ? 'min-h-24 opacity-50' : ''
                        }
                    />
                    {status === 'loading' ? (
                        <p className="text-sm text-[var(--fs-graphite)]">
                            Loading secure Stripe payment fields…
                        </p>
                    ) : null}
                    <Button
                        type="submit"
                        disabled={status !== 'ready'}
                        className="w-full bg-[var(--fs-admiralty)] hover:bg-[var(--fs-pacific)]"
                    >
                        {status === 'confirming' ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <CreditCard className="size-4" />
                        )}
                        Pay {gross === null ? '' : money(gross, currency)}{' '}
                        securely
                    </Button>
                </form>
            )}

            <div className="flex gap-3 rounded-md bg-[var(--fs-linen)] p-4 text-sm leading-relaxed text-[var(--fs-graphite)]">
                <ShieldCheck className="mt-0.5 size-5 shrink-0 text-[var(--fs-pacific)]" />
                <span>
                    After payment, set up an authenticator app and keep the
                    recovery codes somewhere safe before completing your Idea
                    Validation questions.
                </span>
            </div>
        </div>
    );
}

async function confirmWithServer(
    paymentIntentId: string,
    setError: (message: string | null) => void,
    setStatus: (status: 'ready' | 'pending') => void,
) {
    try {
        const response = await fetch(
            '/validate-idea/purchase/confirm-payment',
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ payment_intent_id: paymentIntentId }),
            },
        );
        const payload = (await response.json().catch(() => null)) as
            | { paid?: boolean; next_url?: string }
            | ErrorPayload
            | null;

        if (response.status === 202) {
            setStatus('pending');

            return;
        }

        if (!response.ok || !payload || !('paid' in payload) || !payload.paid) {
            throw new Error(
                errorMessage(payload, 'Stripe payment could not be confirmed.'),
            );
        }

        window.location.assign(payload.next_url ?? '/mfa/setup');
    } catch (reason) {
        setError(
            reason instanceof Error
                ? reason.message
                : 'Stripe payment could not be confirmed.',
        );
        setStatus('ready');
    }
}

type ErrorPayload = { message?: string; errors?: Record<string, string[]> };

function errorMessage(payload: unknown, fallback: string): string {
    if (!payload || typeof payload !== 'object') {
        return fallback;
    }

    const candidate = payload as ErrorPayload;

    if (typeof candidate.message === 'string' && candidate.message !== '') {
        return candidate.message;
    }

    const first = candidate.errors
        ? Object.values(candidate.errors).flat()[0]
        : null;

    return typeof first === 'string' && first !== '' ? first : fallback;
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <label className="grid gap-2 text-sm font-medium text-[var(--fs-admiralty)]">
            {label}
            {children}
            <InputError message={error} />
        </label>
    );
}

function money(amount: number, currency: string): string {
    return new Intl.NumberFormat('en-NZ', {
        style: 'currency',
        currency,
    }).format(amount);
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

const inputClass =
    'w-full rounded-md border border-[var(--fs-sand)] bg-white px-3 py-2.5 text-sm text-[var(--fs-graphite)] outline-none transition focus:border-[var(--fs-pacific)] focus:ring-2 focus:ring-[var(--fs-pacific)]/20';
