import { Head, useForm } from '@inertiajs/react';
import { DatabaseZap, Send, Upload } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
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
    evidence: {
        id: string;
        filename: string;
        url: string;
    } | null;
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

type RecordTarget = {
    key: string;
    dataset: string;
    indicator: string | null;
    label: string;
};

type Props = {
    datasets: string[];
    recordTargets: RecordTarget[];
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

const targetPayloads: Record<string, string> = {
    'economic_indicator:ocr': JSON.stringify(
        {
            indicator: 'ocr',
            label: 'OCR reference rate',
            value: 5.25,
            unit: 'percent',
            period_date: '2026-06-01',
        },
        null,
        2,
    ),
    'economic_indicator:cpi_annual': JSON.stringify(
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
    'economic_indicator:gdp_quarterly': JSON.stringify(
        {
            indicator: 'gdp_quarterly',
            label: 'GDP quarterly',
            value: 0.7,
            unit: 'percent_quarterly_change',
            period_date: '2026-06-01',
        },
        null,
        2,
    ),
    'economic_indicator:unemployment_rate': JSON.stringify(
        {
            indicator: 'unemployment_rate',
            label: 'Unemployment rate',
            value: 4.8,
            unit: 'percent',
            period_date: '2026-06-01',
        },
        null,
        2,
    ),
};

export default function ReferenceDataIndex({
    datasets,
    recordTargets,
    currentValues,
    entries,
}: Props) {
    const initialTarget = initialRecordTarget(recordTargets, datasets);
    const initialDataset =
        initialTarget?.dataset ?? datasets[0] ?? 'economic_indicator';
    const [selectedTargetKey, setSelectedTargetKey] = useState(
        initialTarget?.key ?? '',
    );

    const form = useForm<{
        dataset: string;
        source: string;
        as_at: string;
        payload_json: string;
        upload: File | null;
        evidence_upload: File | null;
    }>({
        dataset: initialDataset,
        source: 'manual_admin',
        as_at: new Date().toISOString().slice(0, 10),
        payload_json: sampleForTarget(initialTarget, initialDataset),
        upload: null,
        evidence_upload: null,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/admin/reference-data', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('upload', 'evidence_upload'),
        });
    }

    function setDataset(dataset: string) {
        const target =
            recordTargets.find((item) => item.dataset === dataset) ?? null;

        setSelectedTargetKey(target?.key ?? '');
        form.setData((data) => ({
            ...data,
            dataset,
            payload_json: sampleForTarget(target, dataset, data.payload_json),
        }));
    }

    function setRecordTarget(targetKey: string) {
        const target =
            recordTargets.find((item) => item.key === targetKey) ?? null;
        if (!target) {
            return;
        }

        setSelectedTargetKey(target.key);
        form.setData((data) => ({
            ...data,
            dataset: target.dataset,
            payload_json: sampleForTarget(target, target.dataset),
        }));
    }

    return (
        <>
            <Head title="Reference data" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Governed data"
                    icon={DatabaseZap}
                    title="Reference data"
                    description="Manually enter governed economic, valuation, and benchmark data that APIs cannot provide."
                />

                <section className="rounded-md border bg-background p-4">
                    <form
                        onSubmit={submit}
                        className="grid gap-4 lg:grid-cols-5"
                    >
                        {recordTargets.length > 0 && (
                            <div className="space-y-2">
                                <Label>Dashboard item</Label>
                                <Select
                                    value={selectedTargetKey}
                                    onValueChange={setRecordTarget}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {recordTargets.map((target) => (
                                            <SelectItem
                                                key={target.key}
                                                value={target.key}
                                            >
                                                {target.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

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
                                            {formatDataset(dataset)}
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
                            <Label htmlFor="upload">Data import</Label>
                            <Input
                                id="upload"
                                type="file"
                                accept=".csv,.txt,.xlsx,text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                onChange={(event) =>
                                    form.setData(
                                        'upload',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={form.errors.upload} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="evidence_upload">
                                Source evidence
                            </Label>
                            <Input
                                id="evidence_upload"
                                type="file"
                                accept=".png,.jpg,.jpeg,.webp,.pdf,image/png,image/jpeg,image/webp,application/pdf"
                                onChange={(event) =>
                                    form.setData(
                                        'evidence_upload',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.evidence_upload}
                            />
                        </div>

                        <div className="space-y-2 lg:col-span-5">
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

                        <div className="flex justify-end lg:col-span-5">
                            <Button type="submit" disabled={form.processing}>
                                {form.data.upload ||
                                form.data.evidence_upload ? (
                                    <Upload
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <Send
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                                Submit
                            </Button>
                        </div>
                    </form>
                </section>

                <section className="space-y-3 rounded-md border bg-background p-4">
                    <h2 className="text-sm font-medium">
                        Current effective values
                    </h2>
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
                                                {formatDataset(value.dataset)}
                                            </td>
                                            <td className="px-3 py-3 break-words">
                                                {value.label}
                                            </td>
                                            <td className="px-3 py-3 break-words">
                                                {value.value}
                                            </td>
                                            <td className="px-3 py-3">
                                                {value.as_at}
                                            </td>
                                            <td className="px-3 py-3 break-words">
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
                                    <th className="w-[20%] px-3 py-2 font-medium">
                                        Dataset
                                    </th>
                                    <th className="w-[14%] px-3 py-2 font-medium">
                                        As at
                                    </th>
                                    <th className="w-[20%] px-3 py-2 font-medium">
                                        Source
                                    </th>
                                    <th className="w-[14%] px-3 py-2 font-medium">
                                        Evidence
                                    </th>
                                    <th className="w-[16%] px-3 py-2 font-medium">
                                        Status
                                    </th>
                                    <th className="w-[16%] px-3 py-2 font-medium">
                                        Submitted
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {entries.map((entry) => (
                                    <tr key={entry.id} className="border-t">
                                        <td className="px-3 py-3">
                                            {formatDataset(entry.dataset)}
                                        </td>
                                        <td className="px-3 py-3">
                                            {entry.as_at}
                                        </td>
                                        <td className="px-3 py-3 break-words">
                                            {entry.source}
                                        </td>
                                        <td className="px-3 py-3">
                                            {entry.evidence ? (
                                                <a
                                                    className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                                                    href={entry.evidence.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    View
                                                </a>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    None
                                                </span>
                                            )}
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

function initialRecordTarget(
    recordTargets: RecordTarget[],
    datasets: string[],
): RecordTarget | null {
    const params =
        typeof window === 'undefined'
            ? new URLSearchParams()
            : new URLSearchParams(window.location.search);
    const requestedTarget = params.get('target');
    const requestedDataset = params.get('dataset');

    return (
        recordTargets.find((target) => target.key === requestedTarget) ??
        recordTargets.find((target) => target.dataset === requestedDataset) ??
        recordTargets.find((target) => target.dataset === datasets[0]) ??
        recordTargets[0] ??
        null
    );
}

function sampleForTarget(
    target: RecordTarget | null,
    dataset: string,
    fallback = '',
): string {
    if (target && targetPayloads[target.key]) {
        return targetPayloads[target.key];
    }

    return samplePayloads[dataset] ?? fallback;
}

function formatDataset(dataset: string): string {
    return dataset
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

ReferenceDataIndex.layout = {
    breadcrumbs: [
        {
            title: 'Reference data',
            href: '/admin/reference-data',
        },
    ],
};
