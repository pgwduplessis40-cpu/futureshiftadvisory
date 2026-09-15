import { loadStripe } from '@stripe/stripe-js';
import type {
    Stripe,
    StripeElements,
    StripePaymentElement,
} from '@stripe/stripe-js';
import { CreditCard, Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { formatNzdCurrency } from '@/lib/formatters';

type Purchase = {
    status: string;
    amount_ex_gst: number | null;
    gst_amount: number | null;
    amount_including_gst: number | null;
    currency: string | null;
} | null;

type CheckoutUrls = {
    paymentIntent: string;
    confirmPayment: string;
    confirmFixturePayment: string;
};

type Intent = {
    publishable_key: string;
    client_secret: string;
    payment_intent_id: string;
    fixture: boolean;
    amount_ex_gst: number | null;
    gst_amount: number | null;
    amount_including_gst: number | null;
    currency: string | null;
};

export function PlanBudgetCheckout({
    offer,
    purchase,
    urls,
}: {
    offer: { amount_ex_gst: number | null; currency: string | null };
    purchase: Purchase;
    urls: CheckoutUrls;
}) {
    const elementRef = useRef<HTMLDivElement | null>(null);
    const stripeRef = useRef<Stripe | null>(null);
    const elementsRef = useRef<StripeElements | null>(null);
    const paymentElementRef = useRef<StripePaymentElement | null>(null);
    const [intent, setIntent] = useState<Intent | null>(null);
    const [status, setStatus] = useState<
        'idle' | 'loading' | 'ready' | 'confirming'
    >('idle');
    const [error, setError] = useState<string | null>(null);

    useEffect(() => () => paymentElementRef.current?.unmount(), []);

    const startCheckout = async () => {
        setStatus('loading');
        setError(null);

        try {
            const response = await fetch(urls.paymentIntent, {
                method: 'POST',
                headers: jsonHeaders(),
            });
            const payload = (await response.json().catch(() => null)) as
                | Intent
                | ErrorPayload
                | null;

            if (!response.ok || !payload || !('client_secret' in payload)) {
                throw new Error(
                    errorMessage(
                        payload,
                        'Secure payment could not be started.',
                    ),
                );
            }

            setIntent(payload);

            if (payload.fixture) {
                setStatus('ready');

                return;
            }

            const stripe = await loadStripe(payload.publishable_key);

            if (!stripe || !elementRef.current) {
                throw new Error(
                    'Secure checkout could not be displayed in this browser.',
                );
            }

            paymentElementRef.current?.unmount();
            const elements = stripe.elements({
                clientSecret: payload.client_secret,
            });
            const paymentElement = elements.create('payment', {
                layout: 'tabs',
            });
            paymentElement.mount(elementRef.current);
            stripeRef.current = stripe;
            elementsRef.current = elements;
            paymentElementRef.current = paymentElement;
            setStatus('ready');
        } catch (reason) {
            setIntent(null);
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Secure payment could not be started.',
            );
            setStatus('idle');
        }
    };

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
            confirmParams: { return_url: window.location.href },
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

        await confirmWithServer(
            urls.confirmPayment,
            intent.payment_intent_id,
            setError,
            setStatus,
        );
    };

    const confirmFixture = async () => {
        setStatus('confirming');
        setError(null);

        try {
            const response = await fetch(urls.confirmFixturePayment, {
                method: 'POST',
                headers: jsonHeaders(),
            });
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

            window.location.assign(payload.next_url ?? window.location.href);
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Test payment could not be completed.',
            );
            setStatus('ready');
        }
    };

    const net =
        intent?.amount_ex_gst ?? purchase?.amount_ex_gst ?? offer.amount_ex_gst;
    const gst =
        intent?.gst_amount ??
        purchase?.gst_amount ??
        (net === null ? null : Math.round(net * 0.15 * 100) / 100);
    const gross =
        intent?.amount_including_gst ??
        purchase?.amount_including_gst ??
        (net === null ? null : Math.round((net + (gst ?? 0)) * 100) / 100);
    const currency =
        intent?.currency ?? purchase?.currency ?? offer.currency ?? 'NZD';

    return (
        <section className="rounded-md border bg-background p-5">
            <h2 className="font-medium">Purchase Business Plan &amp; Budget</h2>
            <p className="mt-1 text-sm text-muted-foreground">
                Your approved Idea Validation will seed the plan foundation. You
                will confirm all financial assumptions in the Budget Pack.
            </p>

            <div className="mt-4 rounded-md bg-muted/40 p-4 text-sm">
                <div className="flex justify-between gap-4">
                    <span>Business Plan &amp; Budget</span>
                    <span>{money(net, currency)}</span>
                </div>
                <div className="mt-2 flex justify-between gap-4">
                    <span>GST</span>
                    <span>{money(gst, currency)}</span>
                </div>
                <div className="mt-3 flex justify-between gap-4 border-t pt-3 font-semibold">
                    <span>Total</span>
                    <span>{money(gross, currency)}</span>
                </div>
            </div>

            {error ? (
                <p className="mt-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
                    {error}
                </p>
            ) : null}
            <div
                ref={elementRef}
                className={intent && !intent.fixture ? 'mt-5' : 'hidden'}
            />

            {intent === null ? (
                <Button
                    className="mt-5"
                    onClick={() => void startCheckout()}
                    disabled={status === 'loading'}
                >
                    {status === 'loading' ? (
                        <Loader2 className="size-4 animate-spin" />
                    ) : (
                        <CreditCard className="size-4" />
                    )}
                    Continue to secure payment
                </Button>
            ) : intent.fixture ? (
                <Button
                    className="mt-5"
                    onClick={() => void confirmFixture()}
                    disabled={status !== 'ready'}
                >
                    {status === 'confirming' ? (
                        <Loader2 className="size-4 animate-spin" />
                    ) : (
                        <CreditCard className="size-4" />
                    )}
                    Complete test payment
                </Button>
            ) : (
                <form className="mt-5" onSubmit={confirm}>
                    <Button type="submit" disabled={status !== 'ready'}>
                        {status === 'confirming' ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <CreditCard className="size-4" />
                        )}
                        Pay {money(gross, currency)} securely
                    </Button>
                </form>
            )}
        </section>
    );
}

type ErrorPayload = { message?: string; errors?: Record<string, string[]> };

function jsonHeaders(): HeadersInit {
    return {
        Accept: 'application/json',
        'X-CSRF-TOKEN':
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content ?? '',
    };
}

async function confirmWithServer(
    url: string,
    paymentIntentId: string,
    setError: (value: string | null) => void,
    setStatus: (value: 'idle' | 'loading' | 'ready' | 'confirming') => void,
) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { ...jsonHeaders(), 'Content-Type': 'application/json' },
            body: JSON.stringify({ payment_intent_id: paymentIntentId }),
        });
        const payload = (await response.json().catch(() => null)) as
            | { paid?: boolean; next_url?: string }
            | ErrorPayload
            | null;

        if (!response.ok || !payload || !('paid' in payload) || !payload.paid) {
            throw new Error(
                errorMessage(
                    payload,
                    'Stripe is still processing your payment. Your access will open once it is confirmed.',
                ),
            );
        }

        window.location.assign(payload.next_url ?? window.location.href);
    } catch (reason) {
        setError(
            reason instanceof Error
                ? reason.message
                : 'Stripe is still processing your payment.',
        );
        setStatus('ready');
    }
}

function errorMessage(
    payload:
        | ErrorPayload
        | Intent
        | { paid?: boolean; next_url?: string }
        | null,
    fallback: string,
): string {
    if (payload && 'errors' in payload && payload.errors) {
        return Object.values(payload.errors).flat().at(0) ?? fallback;
    }

    return payload && 'message' in payload && payload.message
        ? payload.message
        : fallback;
}

function money(amount: number | null, currency: string): string {
    if (amount === null) {
        return '—';
    }

    return currency === 'NZD'
        ? formatNzdCurrency(amount)
        : new Intl.NumberFormat('en-NZ', {
              style: 'currency',
              currency,
          }).format(amount);
}
