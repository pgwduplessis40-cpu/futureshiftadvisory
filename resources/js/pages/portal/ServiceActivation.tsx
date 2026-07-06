import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    CreditCard,
    ExternalLink,
} from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Activation = {
    id: string;
    service_type: 'due_diligence' | 'entrepreneur';
    client_label: string;
    status: string;
    status_label: string;
    intake: Record<string, string | number | null>;
    package: null | {
        client_label?: string;
        package_name?: string;
        package_scope?:
            | 'idea_validation'
            | 'plan_budget'
            | 'combo'
            | 'dd_under_300k'
            | 'dd_300k_1m'
            | 'dd_1m_3m'
            | null;
        billing_model?: string;
        fixed_fee?: number | null;
        deposit_percent?: number | null;
        currency?: string;
        scope_description?: string;
        included_stages?: string[];
        client_outcomes?: string[];
        payment_split?: PaymentSplit;
        access?: {
            package_scope_label?: string;
            includes_idea_validation?: boolean;
            includes_plan_budget?: boolean;
        };
    };
    payment_required: boolean;
    payment_status: string;
    payment_status_label: string;
    payment_completed_at: string | null;
    payment_reference: string | null;
    deposit_paid_at: string | null;
    deposit_reference: string | null;
    balance_received_at: string | null;
    balance_reference: string | null;
    full_payment_received: boolean;
    accepted_at: string | null;
    acceptance_text: string | null;
    workspace_ready: boolean;
    workspace_url: string;
    message_thread_url: string | null;
};

type PaymentSplit = {
    deposit_percent: number;
    card_deposit_amount: number | null;
    bank_transfer_amount: number | null;
    requires_bank_transfer: boolean;
};

type Props = {
    activation: Activation;
    urls: {
        dashboard: string;
        paymentComplete: string;
        accept: string;
        ddWorkspace: string;
        ideaWorkspace: string;
    };
};

export default function ServiceActivation({ activation, urls }: Props) {
    const form = useForm({
        confirm_fee_scope: false,
    });
    const paymentForm = useForm({});
    const selectedPackage = activation.package;
    const fullPaymentReceived = activation.full_payment_received;
    const paymentSplit = selectedPackage
        ? packagePaymentSplit(selectedPackage)
        : null;
    const canAccept =
        activation.status === 'package_selected' &&
        selectedPackage !== null &&
        fullPaymentReceived;

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(urls.accept);
    }

    function completePayment() {
        paymentForm.post(urls.paymentComplete, { preserveScroll: true });
    }

    return (
        <>
            <Head title={activation.client_label} />

            <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={urls.dashboard}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Dashboard
                            </Link>
                        </Button>
                        <h1 className="mt-3 text-xl font-semibold">
                            {activation.client_label}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Workspace request and fee/scope acknowledgement.
                        </p>
                    </div>
                    <Badge variant="secondary">{activation.status_label}</Badge>
                </div>

                <section className="rounded-md border bg-background p-4">
                    <h2 className="text-sm font-medium">Request details</h2>
                    <dl className="mt-3 grid gap-3 sm:grid-cols-2">
                        {Object.entries(activation.intake).map(
                            ([key, value]) => (
                                <div key={key}>
                                    <dt className="text-xs text-muted-foreground">
                                        {formatLabel(key)}
                                    </dt>
                                    <dd className="text-sm">
                                        {String(value ?? '-')}
                                    </dd>
                                </div>
                            ),
                        )}
                    </dl>
                </section>

                <section className="rounded-md border bg-background p-4">
                    <h2 className="text-sm font-medium">
                        Package, scope, and fee
                    </h2>
                    {selectedPackage ? (
                        <div className="mt-3 space-y-3">
                            <div>
                                <div className="font-medium">
                                    {selectedPackage.client_label ??
                                        selectedPackage.package_name}
                                </div>
                                {selectedPackage.package_scope ? (
                                    <Badge className="mt-2" variant="outline">
                                        {selectedPackage.access
                                            ?.package_scope_label ??
                                            packageScopeLabel(
                                                selectedPackage.package_scope,
                                            )}
                                    </Badge>
                                ) : null}
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {selectedPackage.scope_description}
                                </p>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-3">
                                <Metric
                                    label="Fee ex GST"
                                    value={
                                        selectedPackage.fixed_fee !== null &&
                                        selectedPackage.fixed_fee !== undefined
                                            ? formatMoney(
                                                  selectedPackage.fixed_fee,
                                                  selectedPackage.currency ??
                                                      'NZD',
                                              )
                                            : 'Proposal'
                                    }
                                />
                                <Metric
                                    label="Billing"
                                    value={formatLabel(
                                        selectedPackage.billing_model ??
                                            'fixed_fee',
                                    )}
                                />
                                <Metric
                                    label="Source"
                                    value="Admin Service Rates"
                                />
                                {paymentSplit &&
                                paymentSplit.card_deposit_amount !== null ? (
                                    <Metric
                                        label="Card deposit"
                                        value={formatMoney(
                                            paymentSplit.card_deposit_amount,
                                            selectedPackage.currency ?? 'NZD',
                                        )}
                                    />
                                ) : null}
                                {paymentSplit &&
                                paymentSplit.bank_transfer_amount !== null &&
                                paymentSplit.bank_transfer_amount > 0 ? (
                                    <Metric
                                        label="Bank transfer balance"
                                        value={formatMoney(
                                            paymentSplit.bank_transfer_amount,
                                            selectedPackage.currency ?? 'NZD',
                                        )}
                                    />
                                ) : null}
                            </div>
                            <div className="grid gap-3 md:grid-cols-2">
                                <PackageList
                                    title="What you get access to"
                                    items={
                                        selectedPackage.included_stages ?? []
                                    }
                                />
                                <PackageList
                                    title="What you will produce"
                                    items={
                                        selectedPackage.client_outcomes ?? []
                                    }
                                />
                            </div>
                        </div>
                    ) : (
                        <p className="mt-3 text-sm text-muted-foreground">
                            Your advisor is reviewing this request and will
                            select the GST-exclusive package/scope/pricing from
                            the active Admin Service Rates table.
                        </p>
                    )}
                </section>

                {selectedPackage && !activation.workspace_ready ? (
                    <section className="rounded-md border border-amber-300 bg-amber-50 p-4 text-amber-950">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div className="flex items-start gap-3">
                                <AlertTriangle
                                    className="mt-0.5 size-5 shrink-0"
                                    aria-hidden="true"
                                />
                                <div>
                                    <h2 className="text-sm font-semibold">
                                        Your action required: payment pending
                                    </h2>
                                    <PaymentGateMessage
                                        activation={activation}
                                        packageCurrency={
                                            selectedPackage.currency ?? 'NZD'
                                        }
                                        paymentSplit={paymentSplit}
                                    />
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        <Badge variant="secondary">
                                            {activation.payment_status_label}
                                        </Badge>
                                        {activation.deposit_reference ? (
                                            <Badge variant="secondary">
                                                Card:{' '}
                                                {activation.deposit_reference}
                                            </Badge>
                                        ) : null}
                                        {activation.balance_reference ? (
                                            <Badge variant="secondary">
                                                Balance:{' '}
                                                {activation.balance_reference}
                                            </Badge>
                                        ) : null}
                                    </div>
                                </div>
                            </div>
                            {fullPaymentReceived ? (
                                <Badge variant="secondary">
                                    Full payment received
                                </Badge>
                            ) : activation.payment_status ===
                              'balance_pending' ? (
                                <Badge variant="outline">
                                    Bank transfer still due
                                </Badge>
                            ) : (
                                <Button
                                    type="button"
                                    onClick={completePayment}
                                    disabled={paymentForm.processing}
                                >
                                    <CreditCard
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {paymentSplit?.requires_bank_transfer
                                        ? 'Pay card deposit now'
                                        : 'Pay package now'}
                                </Button>
                            )}
                        </div>
                    </section>
                ) : null}

                {activation.workspace_ready ? (
                    <section className="rounded-md border bg-background p-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-start gap-3">
                                <CheckCircle2
                                    className="mt-0.5 size-4 text-emerald-600"
                                    aria-hidden="true"
                                />
                                <div>
                                    <h2 className="text-sm font-medium">
                                        Workspace active
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {activation.acceptance_text}
                                    </p>
                                </div>
                            </div>
                            <Button asChild>
                                <Link href={activation.workspace_url}>
                                    Open workspace
                                    <ExternalLink
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </Button>
                        </div>
                    </section>
                ) : (
                    <form
                        onSubmit={submit}
                        className="rounded-md border bg-background p-4"
                    >
                        <h2 className="text-sm font-medium">
                            Fee/scope acknowledgement
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            The standard Terms and Conditions already accepted
                            for portal access continue to apply. Full payment
                            must be received and confirmed first; this checkbox
                            confirms the workspace-specific scope and
                            GST-exclusive fee.
                        </p>
                        {!canAccept ? (
                            <p className="mt-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm font-medium text-amber-950">
                                This acknowledgement is locked because payment
                                is still pending. Complete the required payment
                                step above before the service, workspace,
                                reports, previews, downloads, or exports open.
                            </p>
                        ) : null}
                        <label className="mt-4 flex gap-3 text-sm">
                            <input
                                type="checkbox"
                                disabled={!canAccept}
                                checked={form.data.confirm_fee_scope}
                                onChange={(event) =>
                                    form.setData(
                                        'confirm_fee_scope',
                                        event.target.checked,
                                    )
                                }
                            />
                            <span>
                                I accept the selected package, scope, and
                                GST-exclusive fee for this workspace.
                            </span>
                        </label>
                        <InputError
                            message={form.errors.confirm_fee_scope}
                            className="mt-2"
                        />
                        <InputError
                            message={
                                (
                                    form.errors as Record<
                                        string,
                                        string | undefined
                                    >
                                ).payment
                            }
                            className="mt-2"
                        />
                        <div className="mt-4 flex justify-end">
                            <Button
                                type="submit"
                                disabled={!canAccept || form.processing}
                            >
                                Accept and open workspace
                            </Button>
                        </div>
                    </form>
                )}

                {activation.message_thread_url ? (
                    <div className="flex justify-end">
                        <Button asChild variant="outline">
                            <Link href={activation.message_thread_url}>
                                Open message thread
                            </Link>
                        </Button>
                    </div>
                ) : null}
            </main>
        </>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-sm font-medium">{value}</div>
        </div>
    );
}

function PackageList({ title, items }: { title: string; items: string[] }) {
    if (items.length === 0) {
        return null;
    }

    return (
        <div className="rounded-md border p-3">
            <h3 className="text-xs font-medium text-muted-foreground">
                {title}
            </h3>
            <ul className="mt-2 space-y-1 text-sm">
                {items.map((item) => (
                    <li key={item}>{item}</li>
                ))}
            </ul>
        </div>
    );
}

function PaymentGateMessage({
    activation,
    paymentSplit,
    packageCurrency,
}: {
    activation: Activation;
    paymentSplit: PaymentSplit | null;
    packageCurrency: string;
}) {
    const cardDeposit = paymentSplit?.card_deposit_amount ?? null;
    const bankTransfer = paymentSplit?.bank_transfer_amount ?? null;

    if (activation.payment_status === 'balance_pending') {
        return (
            <p className="mt-1 text-sm">
                Payment is still pending on you. Your card deposit has been
                received, but your service, workspace, reports, previews,
                downloads, and exports remain locked until the remaining
                bank-transfer balance
                {bankTransfer !== null
                    ? ` of ${formatMoney(bankTransfer, packageCurrency)}`
                    : ''}{' '}
                is received and confirmed.
            </p>
        );
    }

    if (paymentSplit?.requires_bank_transfer) {
        return (
            <p className="mt-1 text-sm">
                Everything is pending on payment. Pay the card deposit
                {cardDeposit !== null
                    ? ` of ${formatMoney(cardDeposit, packageCurrency)}`
                    : ''}{' '}
                first. The remaining bank-transfer balance
                {bankTransfer !== null
                    ? ` of ${formatMoney(bankTransfer, packageCurrency)}`
                    : ''}{' '}
                must also be received and confirmed before any service,
                workspace, report, preview, download, or export is available.
            </p>
        );
    }

    return (
        <p className="mt-1 text-sm">
            Everything is pending on payment. Complete the selected package
            payment before this workspace opens. No service, report, preview,
            download, or export is available until full payment is received and
            confirmed.
        </p>
    );
}

function packageScopeLabel(scope: string) {
    if (scope === 'dd_under_300k') {
        return 'Purchase price below $300k';
    }

    if (scope === 'dd_300k_1m') {
        return 'Purchase price $300k-$1m';
    }

    if (scope === 'dd_1m_3m') {
        return 'Purchase price $1m-$3m';
    }

    if (scope === 'idea_validation') {
        return 'Idea Validation';
    }

    if (scope === 'plan_budget') {
        return 'Business Plan + Budget';
    }

    if (scope === 'combo') {
        return 'Idea + Business Plan + Budget';
    }

    return 'Standard workspace';
}

function packagePaymentSplit(
    servicePackage: NonNullable<Activation['package']>,
) {
    if (servicePackage.payment_split) {
        return servicePackage.payment_split;
    }

    if (
        servicePackage.billing_model !== 'fixed_fee' ||
        servicePackage.fixed_fee === null ||
        servicePackage.fixed_fee === undefined
    ) {
        return null;
    }

    const depositPercent = Math.min(
        Math.max(Number(servicePackage.deposit_percent ?? 100), 0),
        100,
    );
    const cardDeposit = roundCurrency(
        servicePackage.fixed_fee * (depositPercent / 100),
    );
    const bankTransfer = roundCurrency(
        Math.max(servicePackage.fixed_fee - cardDeposit, 0),
    );

    return {
        deposit_percent: depositPercent,
        card_deposit_amount: cardDeposit,
        bank_transfer_amount: bankTransfer,
        requires_bank_transfer: bankTransfer > 0,
    };
}

function roundCurrency(value: number) {
    return Math.round(value * 100) / 100;
}

function formatLabel(value: string) {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function formatMoney(value: number, currency: string) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(value);
}
