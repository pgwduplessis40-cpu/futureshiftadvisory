import { Head, router, useForm } from '@inertiajs/react';
import {
    BadgeDollarSign,
    History,
    Package,
    Pencil,
    Power,
    Save,
    Table2,
    Upload,
    X,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
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
    is_active: boolean;
    free_access_enabled: boolean;
    free_access_enabled_at: string | null;
    notes: string | null;
    created_by_name: string | null;
    created_at: string | null;
    toggle_url: string;
};

type EntrepreneurPackageScope = 'idea_validation' | 'plan_budget' | 'combo';

type DueDiligencePackageScope = 'dd_under_300k' | 'dd_300k_1m' | 'dd_1m_3m';

type PackageScope = EntrepreneurPackageScope | DueDiligencePackageScope;

type PackageScopeOption = {
    value: PackageScope;
    label: string;
    description: string;
};

type ServiceRatePackage = {
    id: string;
    service_type: 'due_diligence' | 'entrepreneur' | 'integration_scoping';
    package_scope: PackageScope | null;
    package_name: string;
    client_label: string;
    billing_model: string;
    fixed_fee: number | null;
    deposit_percent: number;
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
    payment_split?: PaymentSplit;
    update_url: string;
    toggle_url: string;
};

type PaymentSplit = {
    deposit_percent: number;
    card_deposit_amount: number | null;
    bank_transfer_amount: number | null;
    requires_bank_transfer: boolean;
};

type IntegrationFeeBand = {
    id: string;
    complexity_band: 'S' | 'M' | 'L' | 'XL';
    delivery_mode: 'inhouse' | 'lowcode' | 'partner' | 'mixed';
    fee_low: number;
    fee_mid: number;
    fee_high: number;
    currency: string;
    is_active: boolean;
    updated_by_name: string | null;
    updated_at: string | null;
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
    dueDiligencePackageScopes: PackageScopeOption[];
    entrepreneurPackageScopes: PackageScopeOption[];
    packageStoreUrl: string;
    integrationFeeBands: IntegrationFeeBand[];
    integrationFeeBandStoreUrl: string;
    integrationFeeBandImportUrl: string;
};

const defaultPackageFormData = {
    service_type: 'due_diligence',
    package_scope: 'dd_300k_1m',
    package_name: '',
    client_label: '',
    billing_model: 'fixed_fee',
    fixed_fee: '',
    deposit_percent: '100',
    hourly_rate: '',
    retainer_amount: '',
    purchase_price_min: '',
    purchase_price_max: '',
    scope_description: '',
    is_active: true,
};

export default function ServiceRatesIndex({
    current,
    fallback,
    history,
    storeUrl,
    packages,
    dueDiligencePackageScopes,
    entrepreneurPackageScopes,
    packageStoreUrl,
    integrationFeeBands,
    integrationFeeBandStoreUrl,
    integrationFeeBandImportUrl,
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
    const packageForm = useForm(defaultPackageFormData);
    const feeBandForm = useForm({
        complexity_band: 'M',
        delivery_mode: 'inhouse',
        fee_low: '',
        fee_mid: '',
        fee_high: '',
        currency: effectiveCurrency,
        is_active: true,
    });
    const importForm = useForm<{ pricing_file: File | null }>({
        pricing_file: null,
    });
    const [editingPackageId, setEditingPackageId] = useState<string | null>(
        null,
    );
    const editingPackage =
        packages.find((ratePackage) => ratePackage.id === editingPackageId) ??
        null;

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => form.reset('notes'),
        });
    }

    function submitPackage(event: FormEvent) {
        event.preventDefault();

        const onSuccess = () => {
            setEditingPackageId(null);
            packageForm.clearErrors();
            packageForm.setData(defaultPackageFormData);
        };

        if (editingPackage) {
            packageForm.patch(editingPackage.update_url, {
                preserveScroll: true,
                onSuccess,
            });

            return;
        }

        packageForm.post(packageStoreUrl, {
            preserveScroll: true,
            onSuccess,
        });
    }

    function editPackage(ratePackage: ServiceRatePackage) {
        setEditingPackageId(ratePackage.id);
        packageForm.clearErrors();
        packageForm.setData({
            service_type: ratePackage.service_type,
            package_scope:
                ratePackage.package_scope ??
                defaultScope(ratePackage.service_type),
            package_name: ratePackage.package_name,
            client_label: ratePackage.client_label,
            billing_model: ratePackage.billing_model,
            fixed_fee: valueToString(ratePackage.fixed_fee),
            deposit_percent: valueToString(ratePackage.deposit_percent),
            hourly_rate: valueToString(ratePackage.hourly_rate),
            retainer_amount: valueToString(ratePackage.retainer_amount),
            purchase_price_min: valueToString(ratePackage.purchase_price_min),
            purchase_price_max: valueToString(ratePackage.purchase_price_max),
            scope_description: ratePackage.scope_description,
            is_active: ratePackage.is_active,
        });
    }

    function cancelPackageEdit() {
        setEditingPackageId(null);
        packageForm.clearErrors();
        packageForm.setData(defaultPackageFormData);
    }

    function toggleRate(rate: ServiceRate) {
        const enablingFreeAccess = rate.is_active;

        if (
            enablingFreeAccess &&
            !window.confirm(
                'Deactivating the current service rate can enable free access if no other active rate is available. Continue?',
            )
        ) {
            return;
        }

        router.patch(
            rate.toggle_url,
            {
                is_active: !rate.is_active,
                free_access_acknowledged: enablingFreeAccess,
            },
            { preserveScroll: true },
        );
    }

    function togglePackage(ratePackage: ServiceRatePackage) {
        router.patch(
            ratePackage.toggle_url,
            { is_active: !ratePackage.is_active },
            { preserveScroll: true },
        );
    }

    function submitIntegrationFeeBand(event: FormEvent) {
        event.preventDefault();
        feeBandForm.post(integrationFeeBandStoreUrl, {
            preserveScroll: true,
            onSuccess: () =>
                feeBandForm.reset('fee_low', 'fee_mid', 'fee_high'),
        });
    }

    function importIntegrationFeeBands(event: FormEvent) {
        event.preventDefault();
        importForm.post(integrationFeeBandImportUrl, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => importForm.setData('pricing_file', null),
        });
    }

    function downloadIntegrationFeeBandTemplate() {
        const csv = [
            'complexity_band,delivery_mode,fee_low,fee_mid,fee_high,currency,is_active',
            'S,inhouse,3500,4500,5500,NZD,true',
            'M,inhouse,6500,8000,9500,NZD,true',
        ].join('\n');
        const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = 'integration-fee-bands.csv';
        anchor.click();
        URL.revokeObjectURL(url);
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
                        <div className="flex items-start justify-between gap-3">
                            <div className="text-sm text-muted-foreground">
                                Current effective rate ex GST
                            </div>
                            {current ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    aria-label={`${rateToggleActionLabel(current)} current service rate`}
                                    onClick={() => toggleRate(current)}
                                >
                                    <Power
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    {rateToggleActionLabel(current)}
                                </Button>
                            ) : null}
                        </div>
                        <div className="mt-2 text-3xl font-semibold">
                            {formatMoney(effectiveRate, effectiveCurrency)}
                        </div>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {current ? (
                                <>
                                    <Badge variant="secondary">
                                        Effective{' '}
                                        {formatDate(current.effective_from)}
                                    </Badge>
                                    <Badge variant="secondary">
                                        {rateStatusLabel(current)}
                                    </Badge>
                                </>
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
                                {editingPackage
                                    ? 'Edit workspace package'
                                    : 'Add workspace package'}
                            </h2>
                            {editingPackage ? (
                                <Badge variant="outline">
                                    {editingPackage.client_label}
                                </Badge>
                            ) : null}
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="package_service_type">
                                    Service
                                </Label>
                                <select
                                    id="package_service_type"
                                    value={packageForm.data.service_type}
                                    onChange={(event) => {
                                        const serviceType = event.target
                                            .value as ServiceRatePackage['service_type'];
                                        packageForm.setData(
                                            'service_type',
                                            serviceType,
                                        );
                                        packageForm.setData(
                                            'package_scope',
                                            defaultScope(serviceType),
                                        );
                                    }}
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    <option value="due_diligence">
                                        Explore buying a business
                                    </option>
                                    <option value="entrepreneur">
                                        Test new Business Idea
                                    </option>
                                    <option value="integration_scoping">
                                        Systems integration scoping
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

                        {packageForm.data.service_type === 'due_diligence' ? (
                            <div className="grid gap-2">
                                <Label htmlFor="package_scope">
                                    Buying a business package band
                                </Label>
                                <select
                                    id="package_scope"
                                    value={packageForm.data.package_scope}
                                    onChange={(event) =>
                                        packageForm.setData(
                                            'package_scope',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    {dueDiligencePackageScopes.map((scope) => (
                                        <option
                                            key={scope.value}
                                            value={scope.value}
                                        >
                                            {scope.label}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-xs text-muted-foreground">
                                    {
                                        dueDiligencePackageScopes.find(
                                            (scope) =>
                                                scope.value ===
                                                packageForm.data.package_scope,
                                        )?.description
                                    }
                                </p>
                                <InputError
                                    message={packageForm.errors.package_scope}
                                />
                            </div>
                        ) : null}

                        {packageForm.data.service_type === 'entrepreneur' ? (
                            <div className="grid gap-2">
                                <Label htmlFor="package_scope">
                                    Entrepreneur package path
                                </Label>
                                <select
                                    id="package_scope"
                                    value={packageForm.data.package_scope}
                                    onChange={(event) =>
                                        packageForm.setData(
                                            'package_scope',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    {entrepreneurPackageScopes.map((scope) => (
                                        <option
                                            key={scope.value}
                                            value={scope.value}
                                        >
                                            {scope.label}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-xs text-muted-foreground">
                                    {
                                        entrepreneurPackageScopes.find(
                                            (scope) =>
                                                scope.value ===
                                                packageForm.data.package_scope,
                                        )?.description
                                    }
                                </p>
                                <InputError
                                    message={packageForm.errors.package_scope}
                                />
                            </div>
                        ) : null}

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

                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
                                <Label htmlFor="deposit_percent">
                                    Card deposit %
                                </Label>
                                <Input
                                    id="deposit_percent"
                                    type="number"
                                    min="1"
                                    max="100"
                                    step="0.01"
                                    value={packageForm.data.deposit_percent}
                                    onChange={(event) =>
                                        packageForm.setData(
                                            'deposit_percent',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={packageForm.errors.deposit_percent}
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

                        {packageForm.data.billing_model === 'fixed_fee' &&
                        packageForm.data.fixed_fee !== '' ? (
                            <div className="rounded-md border bg-muted/30 p-3 text-sm">
                                <div className="font-medium">Payment split</div>
                                <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <span className="text-muted-foreground">
                                            Card deposit:{' '}
                                        </span>
                                        {formatMoney(
                                            formPaymentSplit(packageForm.data)
                                                .cardDeposit,
                                            effectiveCurrency,
                                        )}
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">
                                            Bank transfer balance:{' '}
                                        </span>
                                        {formatMoney(
                                            formPaymentSplit(packageForm.data)
                                                .bankTransfer,
                                            effectiveCurrency,
                                        )}
                                    </div>
                                </div>
                            </div>
                        ) : null}

                        {packageForm.data.service_type === 'due_diligence' ? (
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
                                        value={
                                            packageForm.data.purchase_price_min
                                        }
                                        onChange={(event) =>
                                            packageForm.setData(
                                                'purchase_price_min',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            packageForm.errors
                                                .purchase_price_min
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
                                        value={
                                            packageForm.data.purchase_price_max
                                        }
                                        onChange={(event) =>
                                            packageForm.setData(
                                                'purchase_price_max',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={
                                            packageForm.errors
                                                .purchase_price_max
                                        }
                                    />
                                </div>
                            </div>
                        ) : null}

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

                        <div className="flex justify-end gap-2">
                            {editingPackage ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={cancelPackageEdit}
                                >
                                    <X className="size-4" aria-hidden="true" />
                                    Cancel
                                </Button>
                            ) : null}
                            <Button
                                type="submit"
                                disabled={packageForm.processing}
                            >
                                <Save className="size-4" aria-hidden="true" />
                                {editingPackage
                                    ? 'Update package'
                                    : 'Save package'}
                            </Button>
                        </div>
                    </form>

                    <section className="space-y-3 rounded-md border bg-background p-4">
                        <div className="flex items-center gap-2">
                            <Package className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">
                                Service packages
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
                                            Actions
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
                                                    <div className="mt-2 flex flex-wrap gap-1">
                                                        {ratePackage.package_scope ? (
                                                            <Badge variant="outline">
                                                                {packageScopeLabel(
                                                                    ratePackage.package_scope,
                                                                )}
                                                            </Badge>
                                                        ) : null}
                                                        <Badge
                                                            variant={
                                                                ratePackage.is_active
                                                                    ? 'secondary'
                                                                    : 'outline'
                                                            }
                                                        >
                                                            {packageStatusLabel(
                                                                ratePackage,
                                                            )}
                                                        </Badge>
                                                    </div>
                                                </td>
                                                <td
                                                    className="px-3 py-3"
                                                    data-label="Fee"
                                                >
                                                    {packageFee(ratePackage)}
                                                    <PaymentSplitSummary
                                                        ratePackage={
                                                            ratePackage
                                                        }
                                                    />
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
                                                    data-label="Actions"
                                                >
                                                    <div className="flex flex-wrap gap-2">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                editPackage(
                                                                    ratePackage,
                                                                )
                                                            }
                                                        >
                                                            <Pencil
                                                                className="size-3.5"
                                                                aria-hidden="true"
                                                            />
                                                            Edit
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            aria-label={`${packageToggleActionLabel(ratePackage)} ${ratePackage.client_label}`}
                                                            onClick={() =>
                                                                togglePackage(
                                                                    ratePackage,
                                                                )
                                                            }
                                                        >
                                                            <Power
                                                                className="size-3.5"
                                                                aria-hidden="true"
                                                            />
                                                            {packageToggleActionLabel(
                                                                ratePackage,
                                                            )}
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </section>

                <section className="grid gap-4 lg:grid-cols-[minmax(360px,0.7fr)_minmax(0,1fr)]">
                    <div className="space-y-4 rounded-md border bg-background p-4">
                        <div className="flex items-center gap-2">
                            <Table2 className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">
                                Integration pricing
                            </h2>
                        </div>
                        <form
                            onSubmit={submitIntegrationFeeBand}
                            className="grid gap-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="integration_complexity_band">
                                        Complexity band
                                    </Label>
                                    <select
                                        id="integration_complexity_band"
                                        value={feeBandForm.data.complexity_band}
                                        onChange={(event) =>
                                            feeBandForm.setData(
                                                'complexity_band',
                                                event.target
                                                    .value as IntegrationFeeBand['complexity_band'],
                                            )
                                        }
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="S">S</option>
                                        <option value="M">M</option>
                                        <option value="L">L</option>
                                        <option value="XL">XL</option>
                                    </select>
                                    <InputError
                                        message={
                                            feeBandForm.errors.complexity_band
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="integration_delivery_mode">
                                        Delivery mode
                                    </Label>
                                    <select
                                        id="integration_delivery_mode"
                                        value={feeBandForm.data.delivery_mode}
                                        onChange={(event) =>
                                            feeBandForm.setData(
                                                'delivery_mode',
                                                event.target
                                                    .value as IntegrationFeeBand['delivery_mode'],
                                            )
                                        }
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="inhouse">
                                            In-house
                                        </option>
                                        <option value="lowcode">
                                            Low-code
                                        </option>
                                        <option value="partner">
                                            Delivery partner
                                        </option>
                                        <option value="mixed">Mixed</option>
                                    </select>
                                    <InputError
                                        message={
                                            feeBandForm.errors.delivery_mode
                                        }
                                    />
                                </div>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <BandInput
                                    id="integration_fee_low"
                                    label="Low ex GST"
                                    value={feeBandForm.data.fee_low}
                                    onChange={(value) =>
                                        feeBandForm.setData('fee_low', value)
                                    }
                                    error={feeBandForm.errors.fee_low}
                                />
                                <BandInput
                                    id="integration_fee_mid"
                                    label="Mid ex GST"
                                    value={feeBandForm.data.fee_mid}
                                    onChange={(value) =>
                                        feeBandForm.setData('fee_mid', value)
                                    }
                                    error={feeBandForm.errors.fee_mid}
                                />
                                <BandInput
                                    id="integration_fee_high"
                                    label="High ex GST"
                                    value={feeBandForm.data.fee_high}
                                    onChange={(value) =>
                                        feeBandForm.setData('fee_high', value)
                                    }
                                    error={feeBandForm.errors.fee_high}
                                />
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={feeBandForm.data.is_active}
                                    onChange={(event) =>
                                        feeBandForm.setData(
                                            'is_active',
                                            event.target.checked,
                                        )
                                    }
                                />
                                Active for quoting
                            </label>
                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    disabled={feeBandForm.processing}
                                >
                                    <Save
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Save band
                                </Button>
                            </div>
                        </form>
                        <form
                            onSubmit={importIntegrationFeeBands}
                            className="border-t pt-4"
                        >
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                                <div className="grid flex-1 gap-2">
                                    <Label htmlFor="integration_pricing_file">
                                        Pricing CSV
                                    </Label>
                                    <Input
                                        id="integration_pricing_file"
                                        type="file"
                                        accept=".csv,text/csv"
                                        onChange={(event) =>
                                            importForm.setData(
                                                'pricing_file',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={importForm.errors.pricing_file}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={downloadIntegrationFeeBandTemplate}
                                >
                                    Template
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        !importForm.data.pricing_file ||
                                        importForm.processing
                                    }
                                >
                                    <Upload
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Import
                                </Button>
                            </div>
                        </form>
                    </div>
                    <section className="space-y-3 rounded-md border bg-background p-4">
                        <div className="flex items-center gap-2">
                            <Table2 className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">
                                Current integration fee bands
                            </h2>
                        </div>
                        <div className="overflow-hidden rounded-md border">
                            <table className="fsa-responsive-table table-fixed md:table-fixed">
                                <thead className="bg-muted/60 text-left">
                                    <tr>
                                        <th className="w-[12%] px-3 py-2 font-medium">
                                            Band
                                        </th>
                                        <th className="w-[24%] px-3 py-2 font-medium">
                                            Delivery
                                        </th>
                                        <th className="w-[16%] px-3 py-2 font-medium">
                                            Low
                                        </th>
                                        <th className="w-[16%] px-3 py-2 font-medium">
                                            Mid
                                        </th>
                                        <th className="w-[16%] px-3 py-2 font-medium">
                                            High
                                        </th>
                                        <th className="w-[16%] px-3 py-2 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {integrationFeeBands.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-3 py-3 text-muted-foreground"
                                            >
                                                No integration fee bands
                                                configured.
                                            </td>
                                        </tr>
                                    ) : (
                                        integrationFeeBands.map((band) => (
                                            <tr
                                                key={band.id}
                                                className="border-t"
                                            >
                                                <td
                                                    className="px-3 py-3"
                                                    data-label="Band"
                                                >
                                                    <Badge variant="secondary">
                                                        {band.complexity_band}
                                                    </Badge>
                                                </td>
                                                <td
                                                    className="px-3 py-3"
                                                    data-label="Delivery"
                                                >
                                                    {band.delivery_mode.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </td>
                                                <td
                                                    className="px-3 py-3"
                                                    data-label="Low"
                                                >
                                                    {formatMoney(
                                                        band.fee_low,
                                                        band.currency,
                                                    )}
                                                </td>
                                                <td
                                                    className="px-3 py-3"
                                                    data-label="Mid"
                                                >
                                                    {formatMoney(
                                                        band.fee_mid,
                                                        band.currency,
                                                    )}
                                                </td>
                                                <td
                                                    className="px-3 py-3"
                                                    data-label="High"
                                                >
                                                    {formatMoney(
                                                        band.fee_high,
                                                        band.currency,
                                                    )}
                                                </td>
                                                <td
                                                    className="px-3 py-3"
                                                    data-label="Status"
                                                >
                                                    <Badge
                                                        variant={
                                                            band.is_active
                                                                ? 'secondary'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {band.is_active
                                                            ? 'Active'
                                                            : 'Inactive'}
                                                    </Badge>
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
                                    <th className="w-[12%] px-3 py-2 font-medium">
                                        Service discount
                                    </th>
                                    <th className="w-[12%] px-3 py-2 font-medium">
                                        Retainer discount
                                    </th>
                                    <th className="w-[15%] px-3 py-2 font-medium">
                                        Effective
                                    </th>
                                    <th className="w-[9%] px-3 py-2 font-medium">
                                        Status
                                    </th>
                                    <th className="w-[12%] px-3 py-2 font-medium">
                                        Updated by
                                    </th>
                                    <th className="w-[14%] px-3 py-2 font-medium">
                                        Notes
                                    </th>
                                    <th className="w-[8%] px-3 py-2 font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {history.length === 0 ? (
                                    <tr>
                                        <td
                                            className="px-3 py-3 text-muted-foreground"
                                            colSpan={8}
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
                                                data-label="Status"
                                            >
                                                <Badge
                                                    variant={
                                                        rate.is_active
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {rate.free_access_enabled
                                                        ? 'Free access enabled'
                                                        : rateStatusLabel(rate)}
                                                </Badge>
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
                                            <td
                                                className="px-3 py-3"
                                                data-label="Actions"
                                            >
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    aria-label={`${rateToggleActionLabel(rate)} service rate from ${formatDate(rate.effective_from)}`}
                                                    onClick={() =>
                                                        toggleRate(rate)
                                                    }
                                                >
                                                    <Power
                                                        className="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    {rateToggleActionLabel(
                                                        rate,
                                                    )}
                                                </Button>
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

function BandInput({
    id,
    label,
    value,
    onChange,
    error,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="number"
                min="0"
                step="0.01"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
            <InputError message={error} />
        </div>
    );
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

function valueToString(value: number | null) {
    return value === null ? '' : String(value);
}

function rateStatusLabel(rate: ServiceRate) {
    return rate.is_active ? 'Active' : 'Inactive';
}

function rateToggleActionLabel(rate: ServiceRate) {
    return rate.is_active ? 'Deactivate' : 'Activate';
}

function defaultScope(serviceType: ServiceRatePackage['service_type']) {
    if (serviceType === 'due_diligence') {
        return 'dd_300k_1m';
    }

    return serviceType === 'entrepreneur' ? 'combo' : '';
}

function serviceLabel(serviceType: ServiceRatePackage['service_type']) {
    return serviceType === 'due_diligence'
        ? 'Explore buying a business'
        : serviceType === 'entrepreneur'
          ? 'Test new Business Idea'
          : 'Systems integration scoping';
}

function packageScopeLabel(scope: PackageScope | null) {
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

function packageStatusLabel(ratePackage: ServiceRatePackage) {
    return ratePackage.is_active ? 'Active' : 'Inactive';
}

function packageToggleActionLabel(ratePackage: ServiceRatePackage) {
    return ratePackage.is_active ? 'Deactivate' : 'Activate';
}

function formPaymentSplit(data: typeof defaultPackageFormData) {
    const fixedFee = Number(data.fixed_fee || 0);
    const depositPercent = Math.min(
        Math.max(Number(data.deposit_percent || 100), 0),
        100,
    );
    const cardDeposit = roundCurrency(fixedFee * (depositPercent / 100));
    const bankTransfer = roundCurrency(Math.max(fixedFee - cardDeposit, 0));

    return { cardDeposit, bankTransfer };
}

function paymentSplit(ratePackage: ServiceRatePackage) {
    if (ratePackage.payment_split) {
        return {
            depositPercent: ratePackage.payment_split.deposit_percent,
            cardDeposit: ratePackage.payment_split.card_deposit_amount,
            bankTransfer: ratePackage.payment_split.bank_transfer_amount,
        };
    }

    if (ratePackage.fixed_fee === null) {
        return {
            depositPercent: 100,
            cardDeposit: null,
            bankTransfer: null,
        };
    }

    const depositPercent = Math.min(
        Math.max(ratePackage.deposit_percent ?? 100, 0),
        100,
    );
    const cardDeposit = roundCurrency(
        ratePackage.fixed_fee * (depositPercent / 100),
    );
    const bankTransfer = roundCurrency(
        Math.max(ratePackage.fixed_fee - cardDeposit, 0),
    );

    return { depositPercent, cardDeposit, bankTransfer };
}

function roundCurrency(value: number) {
    return Math.round(value * 100) / 100;
}

function PaymentSplitSummary({
    ratePackage,
}: {
    ratePackage: ServiceRatePackage;
}) {
    if (
        ratePackage.billing_model !== 'fixed_fee' ||
        ratePackage.fixed_fee === null
    ) {
        return null;
    }

    const split = paymentSplit(ratePackage);

    return (
        <div className="mt-2 space-y-0.5 text-xs text-muted-foreground">
            <div>
                Card deposit {formatPercent(split.depositPercent)}:{' '}
                {split.cardDeposit !== null
                    ? formatMoney(split.cardDeposit, ratePackage.currency)
                    : '-'}
            </div>
            <div>
                Bank transfer:{' '}
                {split.bankTransfer !== null
                    ? formatMoney(split.bankTransfer, ratePackage.currency)
                    : '-'}
            </div>
        </div>
    );
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
