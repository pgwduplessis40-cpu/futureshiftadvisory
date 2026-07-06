import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, BriefcaseBusiness, CheckCircle2, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Activation = {
    id: string;
    client_id: string;
    client_name: string | null;
    client_label: string;
    service_type: 'due_diligence' | 'entrepreneur';
    status: string;
    status_label: string;
    intake: Record<string, string | number | null>;
    package: PackagePayload | null;
    payment_status: string;
    payment_status_label: string;
    payment_completed_at: string | null;
    deposit_paid_at: string | null;
    deposit_reference: string | null;
    balance_received_at: string | null;
    balance_reference: string | null;
    accepted_at: string | null;
    workspace: {
        dd_engagement_id: string | null;
        entrepreneur_profile_id: string | null;
    };
};

type PackagePayload = {
    id: string;
    service_type: string;
    package_scope?:
        | 'idea_validation'
        | 'plan_budget'
        | 'combo'
        | 'dd_under_300k'
        | 'dd_300k_1m'
        | 'dd_1m_3m'
        | null;
    package_name: string;
    client_label: string;
    billing_model: string;
    fixed_fee: number | null;
    deposit_percent?: number | null;
    hourly_rate: number | null;
    retainer_amount: number | null;
    purchase_price_min: number | null;
    purchase_price_max: number | null;
    currency: string;
    scope_description: string;
    included_stages?: string[];
    client_outcomes?: string[];
    payment_split?: PaymentSplit;
    is_active: boolean;
};

type PaymentSplit = {
    deposit_percent: number;
    card_deposit_amount: number | null;
    bank_transfer_amount: number | null;
    requires_bank_transfer: boolean;
};

type Props = {
    activation: Activation;
    packages: PackagePayload[];
    urls: {
        index: string;
        package: string;
        balanceReceived: string;
        client: string;
    };
};

export default function ServiceActivationShow({
    activation,
    packages,
    urls,
}: Props) {
    const form = useForm({
        service_rate_package_id: activation.package?.id ?? '',
    });
    const balanceForm = useForm({});

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(urls.package);
    }

    function confirmBalanceReceived() {
        balanceForm.post(urls.balanceReceived, { preserveScroll: true });
    }

    return (
        <>
            <Head title={activation.client_label} />

            <main className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={urls.index}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Requests
                            </Link>
                        </Button>
                        <div className="mt-3 flex items-center gap-2">
                            <BriefcaseBusiness
                                className="size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <h1 className="text-xl font-semibold">
                                {activation.client_label}
                            </h1>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {activation.client_name ?? 'Client'} requested a
                            cross-service workspace.
                        </p>
                    </div>
                    <Badge variant="secondary">{activation.status_label}</Badge>
                </div>

                <section className="rounded-md border bg-background p-4">
                    <h2 className="text-sm font-medium">Client intake</h2>
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

                {activation.package ? (
                    <section className="rounded-md border bg-background p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 className="text-sm font-medium">
                                    Payment gate
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Workspace access and reports stay locked
                                    until full payment is received.
                                </p>
                                <PaymentSplitSummary
                                    servicePackage={activation.package}
                                />
                                <div className="mt-3 flex flex-wrap gap-2">
                                    <Badge variant="outline">
                                        {activation.payment_status_label}
                                    </Badge>
                                    {activation.deposit_reference ? (
                                        <Badge variant="secondary">
                                            Card: {activation.deposit_reference}
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
                            {activation.payment_status === 'balance_pending' ? (
                                <Button
                                    type="button"
                                    onClick={confirmBalanceReceived}
                                    disabled={balanceForm.processing}
                                >
                                    <CheckCircle2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Confirm balance received
                                </Button>
                            ) : null}
                        </div>
                    </section>
                ) : null}

                <form
                    onSubmit={submit}
                    className="grid gap-4 rounded-md border bg-background p-4"
                >
                    <div>
                        <h2 className="text-sm font-medium">Select package</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            The client can accept the workspace only after you
                            select one active package from Admin Service Rates.
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="service_rate_package_id">
                            Active package
                        </Label>
                        <select
                            id="service_rate_package_id"
                            value={form.data.service_rate_package_id}
                            onChange={(event) =>
                                form.setData(
                                    'service_rate_package_id',
                                    event.target.value,
                                )
                            }
                            className="h-10 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Choose a package</option>
                            {packages.map((servicePackage) => (
                                <option
                                    key={servicePackage.id}
                                    value={servicePackage.id}
                                >
                                    {servicePackage.client_label} /{' '}
                                    {packageScopeLabel(
                                        servicePackage.package_scope ?? null,
                                    )}{' '}
                                    / {packageFee(servicePackage)}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={form.errors.service_rate_package_id}
                        />
                    </div>

                    {packages.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No active package exists for this service. Add one
                            under Admin / Service Rates before approving the
                            request.
                        </p>
                    ) : null}

                    <div className="grid gap-3 lg:grid-cols-2">
                        {packages.map((servicePackage) => (
                            <div
                                key={servicePackage.id}
                                className="rounded-md border p-3"
                            >
                                <div className="font-medium">
                                    {servicePackage.client_label}
                                </div>
                                {servicePackage.package_scope ? (
                                    <Badge className="mt-2" variant="outline">
                                        {packageScopeLabel(
                                            servicePackage.package_scope ??
                                                null,
                                        )}
                                    </Badge>
                                ) : null}
                                <div className="mt-1 text-sm text-muted-foreground">
                                    {packageFee(servicePackage)}
                                </div>
                                <PaymentSplitSummary
                                    servicePackage={servicePackage}
                                />
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {servicePackage.scope_description}
                                </p>
                                {servicePackage.included_stages?.length ? (
                                    <ul className="mt-3 space-y-1 text-xs text-muted-foreground">
                                        {servicePackage.included_stages.map(
                                            (stage) => (
                                                <li key={stage}>{stage}</li>
                                            ),
                                        )}
                                    </ul>
                                ) : null}
                            </div>
                        ))}
                    </div>

                    <div className="flex justify-between gap-3">
                        <Button asChild variant="outline">
                            <Link href={urls.client}>Open client</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                form.data.service_rate_package_id === ''
                            }
                        >
                            <Save className="size-4" aria-hidden="true" />
                            Save package
                        </Button>
                    </div>
                </form>
            </main>
        </>
    );
}

function packageFee(servicePackage: PackagePayload) {
    if (servicePackage.billing_model === 'fixed_fee') {
        return servicePackage.fixed_fee !== null
            ? `${formatMoney(servicePackage.fixed_fee, servicePackage.currency)} ex GST`
            : 'Fixed fee not set';
    }

    if (servicePackage.billing_model === 'hourly_retainer') {
        const hourly =
            servicePackage.hourly_rate !== null
                ? `${formatMoney(servicePackage.hourly_rate, servicePackage.currency)} ex GST`
                : 'Hourly not set';
        const retainer =
            servicePackage.retainer_amount !== null
                ? formatMoney(
                      servicePackage.retainer_amount,
                      servicePackage.currency,
                  ) + ' ex GST'
                : 'retainer not set';

        return `${hourly} / ${retainer}`;
    }

    return 'Proposal flow';
}

function packageScopeLabel(scope: PackagePayload['package_scope']) {
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

function PaymentSplitSummary({
    servicePackage,
}: {
    servicePackage: PackagePayload;
}) {
    const split = packagePaymentSplit(servicePackage);

    if (!split || split.card_deposit_amount === null) {
        return null;
    }

    return (
        <div className="mt-2 space-y-1 text-xs text-muted-foreground">
            <div>
                Card deposit {formatPercent(split.deposit_percent)}:{' '}
                {formatMoney(
                    split.card_deposit_amount,
                    servicePackage.currency,
                )}
            </div>
            {split.bank_transfer_amount !== null &&
            split.bank_transfer_amount > 0 ? (
                <div>
                    Bank-transfer balance:{' '}
                    {formatMoney(
                        split.bank_transfer_amount,
                        servicePackage.currency,
                    )}
                </div>
            ) : null}
        </div>
    );
}

function packagePaymentSplit(servicePackage: PackagePayload) {
    if (servicePackage.payment_split) {
        return servicePackage.payment_split;
    }

    if (
        servicePackage.billing_model !== 'fixed_fee' ||
        servicePackage.fixed_fee === null
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

function formatPercent(value: number) {
    return `${new Intl.NumberFormat(undefined, {
        maximumFractionDigits: 2,
    }).format(value)}%`;
}

function roundCurrency(value: number) {
    return Math.round(value * 100) / 100;
}

function formatMoney(value: number, currency: string) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatLabel(value: string) {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

ServiceActivationShow.layout = {
    breadcrumbs: [
        {
            title: 'Service activations',
            href: '/advisor/service-activations',
        },
    ],
};
