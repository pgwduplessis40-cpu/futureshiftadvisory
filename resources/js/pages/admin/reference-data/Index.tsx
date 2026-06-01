import { Head, useForm } from '@inertiajs/react';
import { DatabaseZap, Send, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type ReferenceDataEntry = {
    id: string;
    dataset: string;
    as_at: string | null;
    source: string;
    learning_update_id: string;
    learning_update_status: string | null;
    created_at: string | null;
};

type CurrentValue = {
    dataset: string;
    label: string;
    value: string;
    as_at: string | null;
    source: string;
};

type Props = {
    datasets: string[];
    currentValues: CurrentValue[];
    entries: ReferenceDataEntry[];
};

const samplePayloads: Record<string, string> = {
    economic_indicator: JSON.stringify(
        {
            indicator: 'cpi_annual',
            label: 'CPI annual',
            value: 3.4,
            unit: 'percent',
            period_date: '2026-06-01',
        },
        null,
        2,
    ),
    valuation_multiple: JSON.stringify(
        {
            industry_code: 'M6962',
            industry_label: 'Management advice',
            metric: 'ebitda',
            multiple_low: 2.4,
            multiple_mid: 3.1,
            multiple_high: 3.8,
            quarter: '2026Q2',
        },
        null,
        2,
    ),
    industry_wacc: JSON.stringify(
        {
            industry_code: 'M6962',
            industry_label: 'Management advice',
            wacc_rate: 0.1125,
            cost_of_equity: 0.14,
            cost_of_debt: 0.075,
            equity_weight: 0.7,
            debt_weight: 0.3,
            quarter: '2026Q2',
        },
        null,
        2,
    ),
    cpb_benchmark: JSON.stringify(
        {
            programme_type: 'community_services',
            size_band: 'medium',
            cost_per_beneficiary: 875,
        },
        null,
        2,
    ),
};

export default function ReferenceDataIndex({
    datasets,
    currentValues,
    entries,
}: Props) {
    const form = useForm<{
        dataset: string;
        source: string;
        as_at: string;
        payload_json: string;
        upload: File | null;
    }>({
        dataset: datasets[0] ?? 'economic_indicator',
        source: 'manual_admin',
        as_at: new Date().toISOString().slice(0, 10),
        payload_json: samplePayloads[datasets[0] ?? 'economic_indicator'] ?? '',
        upload: null,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/admin/reference-data', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('upload'),
        });
    }

    function setDataset(dataset: string) {
        form.setData((data) => ({
            ...data,
            dataset,
            payload_json: samplePayloads[dataset] ?? data.payload_json,
        }));
    }

    return (
        <>
            <Head title="Reference data" />

            <div className="space-y-6">
                <header className="flex items-center gap-2">
                    <DatabaseZap className="size-5" aria-hidden="true" />
                    <h1 className="text-xl font-semibold">Reference data</h1>
                </header>

                <section className="rounded-md border bg-background p-4">
                    <form onSubmit={submit} className="grid gap-4 lg:grid-cols-4">
                        <div className="space-y-2">
                            <Label>Dataset</Label>
                            <Select
                                value={form.data.dataset}
                                onValueChange={setDataset}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {datasets.map((dataset) => (
                                        <SelectItem
                                            key={dataset}
                                            value={dataset}
                                        >
                                            {dataset.replaceAll('_', ' ')}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.dataset} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="source">Source</Label>
                            <Input
                                id="source"
                                value={form.data.source}
                                onChange={(event) =>
                                    form.setData('source', event.target.value)
                                }
                            />
                            <InputError message={form.errors.source} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="as_at">As at</Label>
                            <Input
                                id="as_at"
                                type="date"
                                value={form.data.as_at}
                                onChange={(event) =>
                                    form.setData('as_at', event.target.value)
                                }
                            />
                            <InputError message={form.errors.as_at} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="upload">Upload</Label>
                            <Input
                                id="upload"
                                type="file"
                                accept=".csv,text/csv,text/plain"
                                onChange={(event) =>
                                    form.setData(
                                        'upload',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={form.errors.upload} />
                        </div>

                        <div className="space-y-2 lg:col-span-4">
                            <Label htmlFor="payload_json">Payload</Label>
                            <textarea
                                id="payload_json"
                                className="min-h-56 w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                value={form.data.payload_json}
                                onChange={(event) =>
                                    form.setData(
                                        'payload_json',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.payload_json} />
                        </div>

                        <div className="flex justify-end lg:col-span-4">
                            <Button type="submit" disabled={form.processing}>
                                {form.data.upload ? (
                                    <Upload className="size-4" aria-hidden="true" />
                                ) : (
                                    <Send className="size-4" aria-hidden="true" />
                                )}
                                Submit
                            </Button>
                        </div>
                    </form>
                </section>

                <section className="space-y-3 rounded-md border bg-background p-4">
                    <h2 className="text-sm font-medium">Current effective values</h2>
                    <div className="overflow-hidden rounded-md border">
                        <table className="w-full table-fixed text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="w-[22%] px-3 py-2 font-medium">
                                        Dataset
                                    </th>
                                    <th className="w-[28%] px-3 py-2 font-medium">
                                        Item
                                    </th>
                                    <th className="w-[16%] px-3 py-2 font-medium">
                                        Value
                                    </th>
                                    <th className="w-[16%] px-3 py-2 font-medium">
                                        As at
                                    </th>
                                    <th className="w-[18%] px-3 py-2 font-medium">
                                        Source
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {currentValues.length === 0 ? (
                                    <tr>
                                        <td
                                            className="px-3 py-3 text-muted-foreground"
                                            colSpan={5}
                                        >
                                            No implemented reference data yet.
                                        </td>
                                    </tr>
                                ) : (
                                    currentValues.map((value, index) => (
                                        <tr
                                            key={`${value.dataset}-${value.label}-${index}`}
                                            className="border-t"
                                        >
                                            <td className="px-3 py-3">
                                                {value.dataset.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </td>
                                            <td className="break-words px-3 py-3">
                                                {value.label}
                                            </td>
                                            <td className="break-words px-3 py-3">
                                                {value.value}
                                            </td>
                                            <td className="px-3 py-3">
                                                {value.as_at}
                                            </td>
                                            <td className="break-words px-3 py-3">
                                                {value.source}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="space-y-3 rounded-md border bg-background p-4">
                    <h2 className="text-sm font-medium">Recent submissions</h2>
                    <div className="overflow-hidden rounded-md border">
                        <table className="w-full table-fixed text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="w-[24%] px-3 py-2 font-medium">
                                        Dataset
                                    </th>
                                    <th className="w-[18%] px-3 py-2 font-medium">
                                        As at
                                    </th>
                                    <th className="w-[22%] px-3 py-2 font-medium">
                                        Source
                                    </th>
                                    <th className="w-[18%] px-3 py-2 font-medium">
                                        Status
                                    </th>
                                    <th className="w-[18%] px-3 py-2 font-medium">
                                        Submitted
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {entries.map((entry) => (
                                    <tr key={entry.id} className="border-t">
                                        <td className="px-3 py-3">
                                            {entry.dataset.replaceAll('_', ' ')}
                                        </td>
                                        <td className="px-3 py-3">
                                            {entry.as_at}
                                        </td>
                                        <td className="break-words px-3 py-3">
                                            {entry.source}
                                        </td>
                                        <td className="px-3 py-3">
                                            <Badge variant="outline">
                                                {entry.learning_update_status ??
                                                    'pending'}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-3">
                                            {entry.created_at
                                                ? new Date(
                                                      entry.created_at,
                                                  ).toLocaleDateString()
                                                : ''}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

ReferenceDataIndex.layout = {
    breadcrumbs: [
        {
            title: 'Reference data',
            href: '/admin/reference-data',
        },
    ],
};
