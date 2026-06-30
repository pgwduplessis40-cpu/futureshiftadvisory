import { Head, router, useForm } from '@inertiajs/react';
import { BadgeDollarSign, History, Package, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ServiceRate = {
    id: string;
    hourly_rate: number;
    currency: string;
    npo_service_discount_percent: number;
    npo_retainer_discount_percent: number;
    effective_from: string | null;
    notes: string | null;
    created_by_name: string | null;
    created_at: string | null;
};

type ServiceRatePackage = {
    id: string;
    service_type: 'due_diligence' | 'entrepreneur';
    package_name: string;
    client_label: string;
    billing_model: string;
    fixed_fee: number | null;
    hourly_rate: number | null;
    retainer_amount: number | null;
    purchase_price_min: number | null;
    purchase_price_max: number | null;
    currency: string;
    scope_description: string;
    is_active: boolean;
    effective_from: string | null;
    effective_to: string | null;
    created_by_name: string | null;
    created_at: string | null;
    toggle_url: string;
};

type Props = {
    current: ServiceRate | null;
    fallback: {
        hourly_rate: number;
        currency: string;
        npo_service_discount_percent: number;
        npo_retainer_discount_percent: number;
    };
    history: ServiceRate[];
    storeUrl: string;
    packages: ServiceRatePackage[];
    packageStoreUrl: string;
};

export default function ServiceRatesIndex({
    current,
    fallback,
    history,
    storeUrl,
    packages,
    packageStoreUrl,
}: Props) {
    const effectiveRate = current?.hourly_rate ?? fallback.hourly_rate;
    const effectiveCurrency = current?.currency ?? fallback.currency;
    const npoServiceDiscount =
        current?.npo_service_discount_percent ??
        fallback.npo_service_discount_percent;
    const npoRetainerDiscount =
        current?.npo_retainer_discount_percent ??
        fallback.npo_retainer_discount_percent;
    const effectiveNpoServiceRate =
        effectiveRate * (1 - npoServiceDiscount / 100);
    const form = useForm({
        hourly_rate: effectiveRate.toString(),
        npo_service_discount_percent: npoServiceDiscount.toString(),
        npo_retainer_discount_percent: npoRetainerDiscount.toString(),
        notes: '',
    });
    const packageForm = useForm({
        service_type: 'due_diligence',
        package_name: '',
        client_label: '',
        billing_model: 'fixed_fee',
        fixed_fee: '',
        hourly_rate: '',
        retainer_amount: '',
        purchase_price_min: '',
        purchase_price_max: '',
        scope_description: '',
        is_active: true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => form.reset('notes'),
        });
    }

    function submitPackage(event: FormEvent) {
        event.preventDefault();

        packageForm.post(packageStoreUrl, {
            preserveScroll: true,
            onSuccess: () =>
                packageForm.reset(
                    'package_name',
                    'client_label',
                    'fixed_fee',
                    'hourly_rate',
                    'retainer_amount',
                    'purchase_price_min',
                    'purchase_price_max',
                    'scope_description',
                ),
        });
    }

    function togglePackage(ratePackage: ServiceRatePackage) {
        router.patch(
            ratePackage.toggle_url,
            { is_active: !ratePackage.is_active },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Service rates" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Fees"
                    icon={BadgeDollarSign}
                    title="Service rates"
                    description="Set the GST-exclusive hourly rate, NPO discounts, and active workspace packages used by fee calculations and service activation."
                />

                <section className="grid gap-4 lg:grid-cols-[minmax(0,0.8fr)_minmax(360px,1fr)]">
                    <div className="rounded-md border bg-background p-4">
                        <div className="text-sm text-muted-foreground">
                            Current effective rate ex GST
                        </div>
                        <div className="mt-2 text-3xl font-semibold">
                            {formatMoney(effectiveRate, effectiveCurrency)}
                        </div>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {current ? (
                                <Badge variant="secondary">
                                    Effective{' '}
                                    {formatDate(current.effective_from)}
                                </Badge>
                            ) : (
                                <Badge variant="outline">Config fallback</Badge>
                            )}
                            <Badge variant="outline">{effectiveCurrency}</Badge>
                        </div>
                        {current?.notes && (
                            <p className="mt-4 text-sm text-muted-foreground">
                                {current.notes}
                            </p>
                        )}
                        <div className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <div className="text-muted-foreground">
                                    NPO service rate
                                </div>
                                <div className="font-medium">
                                    {formatMoney(
                                        effectiveNpoServiceRate,
                                        effectiveCurrency,
                                    )}
                                </div>
                                <div className="text-muted-foreground">
                                    {formatPercent(npoServiceDiscount)}
                                </div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">
                                    NPO retainer discount
                                </div>
                                <div className="font-medium">
                                    {formatPercent(npoRetainerDiscount)}
                                </div>
                            </div>
                        </div>
                    </div>

                    <form
                        onSubmit={submit}
                        className="grid gap-4 rounded-md border bg-background p-4"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="hourly_rate">
                                Hourly rate ex GST
                            </Label>
                            <Input
                                id="hourly_rate"
                                type="number"
                                min="0"
                                max="99999.99"
                                step="0.01"
                                value={form.data.hourly_rate}
                                onChange={(event) =>
                                    form.setData(
                                        'hourly_rate',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.hourly_rate} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="npo_service_discount_percent">
                                    NPO service discount %
                                </Label>
                                <Input
                                    id="npo_service_discount_percent"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value={
                                        form.data.npo_service_discount_percent
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            'npo_service_discount_percent',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        form.errors.npo_service_discount_percent
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="npo_retainer_discount_percent">
                                    NPO retainer discount %
                                </Label>
                                <Input
                                    id="npo_retainer_discount_percent"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value={
                                        form.data.npo_retainer_discount_percent
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            'npo_retainer_discount_percent',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        form.errors
                                            .npo_retainer_discount_percent
                                    }
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notes">Notes</Label>
                            <textarea
                                id="notes"
                                rows={4}
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                                className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError message={form.errors.notes} />
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>
                                <Save className="size-4" aria-hidden="true" />
                                Save rate
                            </Button>
                        </div>
                    </form>
                </section>

                <section className="grid gap-4 lg:grid-cols-[minmax(420px,0.8fr)_minmax(0,1fr)]">
                    <form
                        onSubmit={submitPackage}
                        className="grid gap-4 rounded-md border bg-background p-4"
                    >
                        <div className="flex items-center gap-2">
                            <Package className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">
                                Add workspace package
                            </h2>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="package_service_type">
                                    Service
                                </Label>
                                <select
                                    id="package_service_type"
                                    value={packageForm.data.service_type}
                                    onChange={(event) =>
                                        packageForm.setData(
                                            'service_type',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    <option value="due_diligence">
                                        Explore buying a business
                                    </option>
                                    <option value="entrepreneur">
                                        Test a new idea
                                    </option>
                                </select>
                                <InputError
                                    message={packageForm.errors.service_type}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="package_billing_model">
                                    Billing model
                                </Label>
                                <select
                                    id="package_billing_model"
                                    value={packageForm.data.billing_model}
                                    onChange={(event) =>
                                        packageForm.setData(
                                            'billing_model',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    <option value="fixed_fee">Fixed fee</option>
                                    <option value="hourly_retainer">
                                        Hourly retainer
                                    </option>
                                    <option value="proposal">Proposal</option>
                                </select>
                                <InputError
                                    message={packageForm.errors.billing_model}
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="package_name">
                                Internal package name
                            </Label>
                            <Input
                                id="package_name"
                                value={packageForm.data.package_name}
                                onChange={(event) =>
                                    packageForm.setData(
                                        'package_name',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={packageForm.errors.package_name}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="client_label">
                                Client-facing package label
                            </Label>
                            <Input
                                id="client_label"
                                value={packageForm.data.client_label}
                                onChange={(event) =>
                                    packageForm.setData(
                                        'client_label',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={packageForm.errors.client_label}
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="fixed_fee">
                                    Fixed fee ex GST
                                </Label>
                                <Input
                                    id="fixed_fee"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={packageForm.data.fixed_fee}
                                    onChange={(event) =>
                                        packageForm.setData(
                                            'fixed_fee',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={packageForm.errors.fixed_fee}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="hourly_rate">
                                    Hourly rate ex GST
                                </Label>
                                <Input
                                    id="hourly_rate"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={packageForm.data.hourly_rate}
                                    onChange={(event) =>
                                        packageForm.setData(
                                            'hourly_rate',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={packageForm.errors.hourly_rate}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="retainer_amount">
                                    Retainer ex GST
                                </Label>
                                <Input
                                    id="retainer_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={packageForm.data.retainer_amount}
                                    onChange={(event) =>
                                        packageForm.setData(
                                            'retainer_amount',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={packageForm.errors.retainer_amount}
                                />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="purchase_price_min">
                                    Purchase price min
                                </Label>
                                <Input
                                    id="purchase_price_min"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={packageForm.data.purchase_price_min}
                                    onChange={(event) =>
                                        packageForm.setData(
                                            'purchase_price_min',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        packageForm.errors.purchase_price_min
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="purchase_price_max">
                                    Purchase price max
                                </Label>
                                <Input
                                    id="purchase_price_max"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={packageForm.data.purchase_price_max}
                                    onChange={(event) =>
                                        packageForm.setData(
                                            'purchase_price_max',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        packageForm.errors.purchase_price_max
                                    }
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="scope_description">
                                Scope description
                            </Label>
                            <textarea
                                id="scope_description"
                                rows={5}
                                value={packageForm.data.scope_description}
                                onChange={(event) =>
                                    packageForm.setData(
                                        'scope_description',
                                        event.target.value,
                                    )
                                }
                                className="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError
                                message={packageForm.errors.scope_description}
                            />
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={packageForm.data.is_active}
                                onChange={(event) =>
                                    packageForm.setData(
                                        'is_active',
                                        event.target.checked,
                                    )
                                }
                            />
                            Active for advisor selection
                        </label>

                        <div className="flex justify-end">
                            <Button
                                type="submit"
                                disabled={packageForm.processing}
                            >
                                <Save className="size-4" aria-hidden="true" />
                                Save package
                            </Button>
                        </div>
                    </form>

                    <section className="space-y-3 rounded-md border bg-background p-4">
                        <div className="flex items-center gap-2">
                            <Package className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">
                                Active service packages
                            </h2>
                        </div>

                        <div className="overflow-hidden rounded-md border">
                            <table className="fsa-responsive-table table-fixed md:table-fixed">
                                <thead className="bg-muted/60 text-left">
                                    <tr>
                                        <th className="w-[16%] px-3 py-2 font-medium">
                                            Service
                                        </th>
                                        <th className="w-[18%] px-3 py-2 font-medium">
                                            Package
                                        </th>
                                        <th className="w-[16%] px-3 py-2 font-medium">
                                            Fee
                                        </th>
                                        <th className="w-[18%] px-3 py-2 font-medium">
                                            Purchase band
                                        </th>
                                        <th className="w-[22%] px-3 py-2 font-medium">
                                            Scope
                                        </th>
                                        <th className="w-[10%] px-3 py-2 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {packages.length === 0 ? (
                                        <tr>
                                            <td
                                                className="px-3 py-3 text-muted-foreground"
                                                colSpan={6}
                                            >
                                                No workspace packages have been
                                                configured yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        packages.map((ratePackage) => (
                                            <tr
                                                key={ratePackage.id}
                                                className="border-t"
                                            >
                                                <td
                                                    className="px-3 py-3"
                                                    data-label="Service"
                                                >
                                                    {serviceLabel(
                                                        ratePackage.service_type,
                                                    )}
                                                </td>
                                                <td
                                                    className="px-3 py-3"
                                                    data-label="Package"
                                                >
                                                    <div className="font-medium">
                                                        {
                                                            ratePackage.client_label
                                                        }
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {
                                                            ratePackage.package_name
                                                        }
                                                    </div>
                                                </td>
                                                <td
                                                    className="px-3 py-3"
                                                    data-label="Fee"
                                                >
                                                    {packageFee(ratePackage)}
                                                </td>
                                                <td
                                                    className="px-3 py-3"
                                                    data-label="Purchase band"
                                                >
                                                    {purchaseBand(ratePackage)}
                                                </td>
                                                <td
                                                    className="px-3 py-3 text-muted-foreground"
                                                    data-label="Scope"
                                                >
                                                    {
                                                        ratePackage.scope_description
                                                    }
                                                </td>
                                                <td
                                                    className="px-3 py-3"
                                                    data-label="Status"
                                                >
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            togglePackage(
                                                                ratePackage,
                                                            )
                                                        }
                                                    >
                                                        {ratePackage.is_active
                                                            ? 'Active'
                                                            : 'Inactive'}
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </section>

                <section className="space-y-3 rounded-md border bg-background p-4">
                    <div className="flex items-center gap-2">
                        <History className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Rate history</h2>
                    </div>

                    <div className="overflow-hidden rounded-md border">
                        <table className="fsa-responsive-table table-fixed md:table-fixed">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="w-[18%] px-3 py-2 font-medium">
                                        Rate ex GST
                                    </th>
                                    <th className="w-[14%] px-3 py-2 font-medium">
                                        Service discount
                                    </th>
                                    <th className="w-[14%] px-3 py-2 font-medium">
                                        Retainer discount
                                    </th>
                                    <th className="w-[18%] px-3 py-2 font-medium">
                                        Effective
                                    </th>
                                    <th className="w-[18%] px-3 py-2 font-medium">
                                        Updated by
                                    </th>
                                    <th className="w-[28%] px-3 py-2 font-medium">
                                        Notes
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {history.length === 0 ? (
                                    <tr>
                                        <td
                                            className="px-3 py-3 text-muted-foreground"
                                            colSpan={6}
                                        >
                                            No service-rate changes yet.
                                        </td>
                                    </tr>
                                ) : (
                                    history.map((rate) => (
                                        <tr key={rate.id} className="border-t">
                                            <td
                                                className="px-3 py-3 font-medium"
                                                data-label="Rate"
                                            >
                                                {formatMoney(
                                                    rate.hourly_rate,
                                                    rate.currency,
                                                )}
                                            </td>
                                            <td
                                                className="px-3 py-3"
                                                data-label="Service discount"
                                            >
                                                {formatPercent(
                                                    rate.npo_service_discount_percent,
                                                )}
                                            </td>
                                            <td
                                                className="px-3 py-3"
                                                data-label="Retainer discount"
                                            >
                                                {formatPercent(
                                                    rate.npo_retainer_discount_percent,
                                                )}
                                            </td>
                                            <td
                                                className="px-3 py-3"
                                                data-label="Effective"
                                            >
                                                {formatDate(
                                                    rate.effective_from,
                                                )}
                                            </td>
                                            <td
                                                className="px-3 py-3"
                                                data-label="Updated by"
                                            >
                                                {rate.created_by_name ??
                                                    'Admin'}
                                            </td>
                                            <td
                                                className="px-3 py-3 break-words text-muted-foreground"
                                                data-label="Notes"
                                            >
                                                {rate.notes ?? ''}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

function formatMoney(value: number, currency: string) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatDate(value: string | null) {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatPercent(value: number) {
    return `${new Intl.NumberFormat(undefined, {
        maximumFractionDigits: 2,
    }).format(value)}%`;
}

function serviceLabel(serviceType: ServiceRatePackage['service_type']) {
    return serviceType === 'due_diligence'
        ? 'Explore buying a business'
        : 'Test a new idea';
}

function packageFee(ratePackage: ServiceRatePackage) {
    if (ratePackage.billing_model === 'fixed_fee') {
        return ratePackage.fixed_fee !== null
            ? `${formatMoney(ratePackage.fixed_fee, ratePackage.currency)} ex GST`
            : 'Fixed fee not set';
    }

    if (ratePackage.billing_model === 'hourly_retainer') {
        const hourly =
            ratePackage.hourly_rate !== null
                ? `${formatMoney(ratePackage.hourly_rate, ratePackage.currency)} ex GST`
                : 'Hourly not set';
        const retainer =
            ratePackage.retainer_amount !== null
                ? `${formatMoney(ratePackage.retainer_amount, ratePackage.currency)} ex GST`
                : 'retainer not set';

        return `${hourly} / ${retainer}`;
    }

    return 'Proposal flow';
}

function purchaseBand(ratePackage: ServiceRatePackage) {
    if (
        ratePackage.purchase_price_min === null &&
        ratePackage.purchase_price_max === null
    ) {
        return '-';
    }

    const min =
        ratePackage.purchase_price_min !== null
            ? formatMoney(ratePackage.purchase_price_min, ratePackage.currency)
            : 'No min';
    const max =
        ratePackage.purchase_price_max !== null
            ? formatMoney(ratePackage.purchase_price_max, ratePackage.currency)
            : 'No max';

    return `${min} to ${max}`;
}

ServiceRatesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Service rates',
            href: '/admin/service-rates',
        },
    ],
};
