import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Candidate = {
    id: string;
    customer_name: string | null;
    customer_email: string | null;
    created_at: string | null;
    currency: string;
    recorded_payment_amount: number;
    current_quote: {
        amount_ex_gst: number | null;
        gst_amount: number | null;
        amount_including_gst: number | null;
    };
    stripe_payment_intent_ref: string | null;
    reconcile_url: string;
};

type Props = {
    candidates: Candidate[];
};

export default function PaymentReconciliationsIndex({ candidates }: Props) {
    return (
        <>
            <Head title="Payment reconciliations" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Administration"
                    icon={ShieldCheck}
                    title="Payment reconciliations"
                    description="Recover only exceptional Stripe-confirmed payments whose stored historical quote no longer matches the recorded payment. This does not create a new charge or alter Stripe."
                />

                <section className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                    <div className="flex gap-3">
                        <AlertTriangle
                            className="mt-0.5 size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <div className="space-y-1">
                            <p className="font-semibold">
                                Controlled recovery only
                            </p>
                            <p>
                                Before access can be activated, the server reads
                                the stored Stripe PaymentIntent and checks its
                                succeeded amount and currency against the
                                internal payment. Enter the original net amount
                                and GST only when the supporting evidence is
                                available.
                            </p>
                        </div>
                    </div>
                </section>

                {candidates.length === 0 ? (
                    <section className="rounded-lg border p-8 text-center">
                        <CheckCircle2
                            className="mx-auto size-8 text-emerald-600"
                            aria-hidden="true"
                        />
                        <h2 className="mt-3 text-lg font-semibold">
                            No payment reconciliations need review
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Purchases appear here only when their stored quote
                            differs from their recorded Stripe payment.
                        </p>
                    </section>
                ) : (
                    <div className="space-y-5">
                        {candidates.map((candidate) => (
                            <ReconciliationCard
                                key={candidate.id}
                                candidate={candidate}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function ReconciliationCard({ candidate }: { candidate: Candidate }) {
    const form = useForm({
        historical_amount_ex_gst: '',
        historical_gst_amount: '',
        reason: '',
        confirmation: false,
    });
    const historicalTotal = money(
        decimal(form.data.historical_amount_ex_gst) +
            decimal(form.data.historical_gst_amount),
        candidate.currency,
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(candidate.reconcile_url, { preserveScroll: true });
    }

    return (
        <section className="space-y-5 rounded-lg border p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-lg font-semibold">
                        {candidate.customer_name ?? 'Idea Validation customer'}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {candidate.customer_email ?? 'No customer email'}
                        {candidate.created_at
                            ? ` · purchased ${formatDate(candidate.created_at)}`
                            : ''}
                    </p>
                </div>
                <Badge variant="outline">Review required</Badge>
            </div>

            <div className="grid gap-3 text-sm sm:grid-cols-2">
                <AmountPanel
                    title="Recorded Stripe payment"
                    amount={money(
                        candidate.recorded_payment_amount,
                        candidate.currency,
                    )}
                    detail="The server will verify this value directly with Stripe before it settles access."
                />
                <AmountPanel
                    title="Current stored quote"
                    amount={
                        candidate.current_quote.amount_including_gst === null
                            ? 'Missing'
                            : money(
                                  candidate.current_quote.amount_including_gst,
                                  candidate.currency,
                              )
                    }
                    detail={`Net ${formatMaybeMoney(candidate.current_quote.amount_ex_gst, candidate.currency)} · GST ${formatMaybeMoney(candidate.current_quote.gst_amount, candidate.currency)}`}
                />
            </div>

            <dl className="grid gap-1 rounded-md bg-muted/50 p-3 text-xs sm:grid-cols-[10rem_1fr]">
                <dt className="font-medium text-muted-foreground">
                    Stored PaymentIntent
                </dt>
                <dd className="font-mono break-all">
                    {candidate.stripe_payment_intent_ref ?? 'Missing'}
                </dd>
            </dl>

            <form onSubmit={submit} className="space-y-4 border-t pt-5">
                <div>
                    <h3 className="font-medium">Restore the original quote</h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        These amounts must add exactly to the recorded payment:
                        {` ${money(candidate.recorded_payment_amount, candidate.currency)}.`}
                    </p>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-1.5">
                        <Label htmlFor={`historical-net-${candidate.id}`}>
                            Original net amount ({candidate.currency})
                        </Label>
                        <Input
                            id={`historical-net-${candidate.id}`}
                            inputMode="decimal"
                            placeholder="100.00"
                            value={form.data.historical_amount_ex_gst}
                            onChange={(event) =>
                                form.setData(
                                    'historical_amount_ex_gst',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError
                            message={form.errors.historical_amount_ex_gst}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor={`historical-gst-${candidate.id}`}>
                            Original GST ({candidate.currency})
                        </Label>
                        <Input
                            id={`historical-gst-${candidate.id}`}
                            inputMode="decimal"
                            placeholder="15.00"
                            value={form.data.historical_gst_amount}
                            onChange={(event) =>
                                form.setData(
                                    'historical_gst_amount',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError
                            message={form.errors.historical_gst_amount}
                        />
                    </div>
                </div>
                <p className="rounded-md bg-muted px-3 py-2 text-sm">
                    Historical total: <strong>{historicalTotal}</strong>
                </p>
                <div className="grid gap-1.5">
                    <Label htmlFor={`reason-${candidate.id}`}>
                        Evidence and reason for reconciliation
                    </Label>
                    <textarea
                        id={`reason-${candidate.id}`}
                        rows={3}
                        maxLength={1000}
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        placeholder="For example: signed Stripe event and original quote confirm the historical $100.00 + GST price."
                    />
                    <InputError message={form.errors.reason} />
                </div>
                <label className="flex items-start gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.confirmation}
                        onChange={(event) =>
                            form.setData('confirmation', event.target.checked)
                        }
                        className="mt-1 size-4"
                    />
                    <span>
                        I have reviewed the Stripe payment evidence and the
                        original quote. This will activate access and create a
                        receipt; it will not charge or refund the customer.
                    </span>
                </label>
                <InputError message={form.errors.confirmation} />
                <InputError message={form.errors.payment} />
                <Button type="submit" disabled={form.processing}>
                    {form.processing
                        ? 'Verifying Stripe payment…'
                        : 'Verify and reconcile payment'}
                </Button>
            </form>
        </section>
    );
}

function AmountPanel({
    title,
    amount,
    detail,
}: {
    title: string;
    amount: string;
    detail: string;
}) {
    return (
        <div className="rounded-md border bg-muted/30 p-3">
            <p className="text-xs font-medium text-muted-foreground">{title}</p>
            <p className="mt-1 text-lg font-semibold">{amount}</p>
            <p className="mt-1 text-xs text-muted-foreground">{detail}</p>
        </div>
    );
}

function decimal(value: string): number {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? parsed : 0;
}

function money(amount: number, currency: string): string {
    return new Intl.NumberFormat('en-NZ', {
        style: 'currency',
        currency,
    }).format(amount);
}

function formatMaybeMoney(amount: number | null, currency: string): string {
    return amount === null ? 'Missing' : money(amount, currency);
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-NZ', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
