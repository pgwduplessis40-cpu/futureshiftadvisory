import { Head, useForm } from '@inertiajs/react';
import { BadgeDollarSign, History, Save } from 'lucide-react';
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
};

export default function ServiceRatesIndex({
    current,
    fallback,
    history,
    storeUrl,
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

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => form.reset('notes'),
        });
    }

    return (
        <>
            <Head title="Service rates" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Fees"
                    icon={BadgeDollarSign}
                    title="Service rates"
                    description="Set the hourly rate and NPO discounts used by fee calculations."
                />

                <section className="grid gap-4 lg:grid-cols-[minmax(0,0.8fr)_minmax(360px,1fr)]">
                    <div className="rounded-md border bg-background p-4">
                        <div className="text-sm text-muted-foreground">
                            Current effective rate
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
                            <Label htmlFor="hourly_rate">Hourly rate</Label>
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

                <section className="space-y-3 rounded-md border bg-background p-4">
                    <div className="flex items-center gap-2">
                        <History className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Rate history</h2>
                    </div>

                    <div className="overflow-hidden rounded-md border">
                        <table className="w-full table-fixed text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="w-[18%] px-3 py-2 font-medium">
                                        Rate
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
                                            <td className="px-3 py-3 font-medium">
                                                {formatMoney(
                                                    rate.hourly_rate,
                                                    rate.currency,
                                                )}
                                            </td>
                                            <td className="px-3 py-3">
                                                {formatPercent(
                                                    rate.npo_service_discount_percent,
                                                )}
                                            </td>
                                            <td className="px-3 py-3">
                                                {formatPercent(
                                                    rate.npo_retainer_discount_percent,
                                                )}
                                            </td>
                                            <td className="px-3 py-3">
                                                {formatDate(
                                                    rate.effective_from,
                                                )}
                                            </td>
                                            <td className="px-3 py-3">
                                                {rate.created_by_name ??
                                                    'Admin'}
                                            </td>
                                            <td className="px-3 py-3 break-words text-muted-foreground">
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

ServiceRatesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Service rates',
            href: '/admin/service-rates',
        },
    ],
};
