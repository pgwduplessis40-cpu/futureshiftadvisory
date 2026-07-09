import { Head, useForm } from '@inertiajs/react';
import { Clock3, DatabaseZap, Send, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import {
    ExplainedSectionHeader,
    Explainer,
} from '@/components/explainer';
import type { Explanation } from '@/components/explainer';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
        scanner_result: string;
        url: string | null;
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

type PendingReview = {
    id: string;
    target_key: string;
    dataset: string;
    label: string;
    value: string;
    as_at: string | null;
    source: string;
    learning_update_id: string;
    status: string | null;
    submitted_at: string | null;
    evidence: {
        id: string;
        filename: string;
        scanner_result: string;
        url: string | null;
    } | null;
};

type Props = {
    datasets: string[];
    recordTargets: RecordTarget[];
    currentValues: CurrentValue[];
    pendingReviews: PendingReview[];
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
            sample_size: 5,
        },
        null,
        2,
    ),
    gst_rate: JSON.stringify(
        {
            tax_name: 'GST',
            jurisdiction: 'NZ',
            rate_percent: 15,
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
    'economic_indicator:company_tax_rate': JSON.stringify(
        {
            indicator: 'company_tax_rate',
            label: 'Company tax rate',
            value: 28,
            unit: 'percent',
            period_date: '2026-06-01',
        },
        null,
        2,
    ),
    gst_rate: JSON.stringify(
        {
            tax_name: 'GST',
            jurisdiction: 'NZ',
            rate_percent: 15,
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
    pendingReviews,
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
    const selectedPendingReview = pendingReviewForSelection(
        pendingReviews,
        selectedTargetKey,
        form.data.dataset,
    );
    const selectedTargetHasPendingReview =
        selectedPendingReview !== null && form.data.upload === null;

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
                    description="Manually enter governed economic, tax, valuation, and benchmark data that APIs cannot provide."
                />

                <section className="rounded-md border bg-background p-4">
                    <form
                        onSubmit={submit}
                        className="grid gap-4 lg:grid-cols-5"
                    >
                        <ExplainedSectionHeader
                            title="Record governed value"
                            description="Submit economic, tax, valuation, or benchmark values for governed review before they influence live calculations."
                            explanation={referenceExplanations.recordValue}
                            className="lg:col-span-5"
                        />
                        {selectedPendingReview && (
                            <div className="lg:col-span-5">
                                <Alert className="border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100">
                                    <Clock3
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    <AlertTitle>
                                        {selectedPendingReview.label} is pending
                                        review
                                    </AlertTitle>
                                    <AlertDescription>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span>
                                                {selectedPendingReview.value
                                                    ? `${selectedPendingReview.value} submitted`
                                                    : 'Submitted'}
                                                {selectedPendingReview.as_at
                                                    ? ` for ${selectedPendingReview.as_at}`
                                                    : ''}
                                                {selectedPendingReview.submitted_at
                                                    ? ` on ${formatDate(
                                                          selectedPendingReview.submitted_at,
                                                      )}`
                                                    : ''}
                                                .
                                            </span>
                                            <Badge variant="outline">
                                                {formatStatus(
                                                    selectedPendingReview.status,
                                                )}
                                            </Badge>
                                            {selectedPendingReview.evidence
                                                ?.url ? (
                                                <a
                                                    className="font-medium underline-offset-4 hover:underline"
                                                    href={
                                                        selectedPendingReview
                                                            .evidence.url
                                                    }
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    Evidence
                                                </a>
                                            ) : selectedPendingReview.evidence ? (
                                                <Badge variant="secondary">
                                                    {formatScannerStatus(
                                                        selectedPendingReview
                                                            .evidence
                                                            .scanner_result,
                                                    )}
                                                </Badge>
                                            ) : null}
                                        </div>
                                    </AlertDescription>
                                </Alert>
                            </div>
                        )}

                        {recordTargets.length > 0 && (
                            <div className="space-y-2">
                                <LabelWithExplanation
                                    explanation={
                                        referenceExplanations.dashboardItem
                                    }
                                >
                                    Dashboard item
                                </LabelWithExplanation>
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
                            <LabelWithExplanation
                                explanation={referenceExplanations.dataset}
                            >
                                Dataset
                            </LabelWithExplanation>
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
                            <LabelWithExplanation
                                htmlFor="source"
                                explanation={referenceExplanations.source}
                            >
                                Source
                            </LabelWithExplanation>
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
                            <LabelWithExplanation
                                htmlFor="as_at"
                                explanation={referenceExplanations.asAt}
                            >
                                As at
                            </LabelWithExplanation>
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
                            <LabelWithExplanation
                                htmlFor="upload"
                                explanation={referenceExplanations.dataImport}
                            >
                                Data import
                            </LabelWithExplanation>
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
                            <LabelWithExplanation
                                htmlFor="evidence_upload"
                                explanation={referenceExplanations.evidence}
                            >
                                Source evidence
                            </LabelWithExplanation>
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
                            <InputError message={form.errors.evidence_upload} />
                        </div>

                        <div className="space-y-2 lg:col-span-5">
                            <LabelWithExplanation
                                htmlFor="payload_json"
                                explanation={referenceExplanations.payload}
                            >
                                Payload
                            </LabelWithExplanation>
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
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    selectedTargetHasPendingReview
                                }
                            >
                                {selectedTargetHasPendingReview ? (
                                    <Clock3
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                ) : form.data.upload ||
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
                                {selectedTargetHasPendingReview
                                    ? 'Pending review'
                                    : 'Submit'}
                            </Button>
                        </div>
                    </form>
                </section>

                <section className="space-y-3 rounded-md border bg-background p-4">
                    <ExplainedSectionHeader
                        title="Current effective values"
                        explanation={referenceExplanations.currentValues}
                    />
                    <div className="overflow-hidden rounded-md border">
                        <table className="fsa-responsive-table table-fixed md:table-fixed">
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
                                            <td
                                                className="px-3 py-3"
                                                data-label="Dataset"
                                            >
                                                {formatDataset(value.dataset)}
                                            </td>
                                            <td
                                                className="px-3 py-3 break-words"
                                                data-label="Item"
                                            >
                                                {value.label}
                                            </td>
                                            <td
                                                className="px-3 py-3 break-words"
                                                data-label="Value"
                                            >
                                                {value.value}
                                            </td>
                                            <td
                                                className="px-3 py-3"
                                                data-label="As at"
                                            >
                                                {value.as_at}
                                            </td>
                                            <td
                                                className="px-3 py-3 break-words"
                                                data-label="Source"
                                            >
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
                    <ExplainedSectionHeader
                        title="Recent submissions"
                        explanation={referenceExplanations.recentSubmissions}
                    />
                    <div className="overflow-hidden rounded-md border">
                        <table className="fsa-responsive-table table-fixed md:table-fixed">
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
                                        <td
                                            className="px-3 py-3"
                                            data-label="Dataset"
                                        >
                                            {formatDataset(entry.dataset)}
                                        </td>
                                        <td
                                            className="px-3 py-3"
                                            data-label="As at"
                                        >
                                            {entry.as_at}
                                        </td>
                                        <td
                                            className="px-3 py-3 break-words"
                                            data-label="Source"
                                        >
                                            {entry.source}
                                        </td>
                                        <td
                                            className="px-3 py-3"
                                            data-label="Evidence"
                                        >
                                            {entry.evidence?.url ? (
                                                <a
                                                    className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                                                    href={entry.evidence.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    View
                                                </a>
                                            ) : entry.evidence ? (
                                                <Badge variant="secondary">
                                                    {formatScannerStatus(
                                                        entry.evidence
                                                            .scanner_result,
                                                    )}
                                                </Badge>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    None
                                                </span>
                                            )}
                                        </td>
                                        <td
                                            className="px-3 py-3"
                                            data-label="Status"
                                        >
                                            <Badge variant="outline">
                                                {entry.learning_update_status ??
                                                    'pending'}
                                            </Badge>
                                        </td>
                                        <td
                                            className="px-3 py-3"
                                            data-label="Submitted"
                                        >
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

function pendingReviewForSelection(
    pendingReviews: PendingReview[],
    targetKey: string,
    dataset: string,
): PendingReview | null {
    return (
        pendingReviews.find((review) => review.target_key === targetKey) ??
        pendingReviews.find(
            (review) =>
                targetKey === '' &&
                review.target_key === dataset &&
                review.dataset === dataset,
        ) ??
        null
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

function formatStatus(status: string | null): string {
    if (!status) {
        return 'Pending review';
    }

    return status
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function formatScannerStatus(status: string): string {
    if (status === 'error') {
        return 'Quarantined';
    }

    return formatStatus(status);
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString();
}

function LabelWithExplanation({
    htmlFor,
    explanation,
    children,
}: {
    htmlFor?: string;
    explanation: Explanation;
    children: string;
}) {
    return (
        <div className="flex items-center gap-2">
            <Label htmlFor={htmlFor}>{children}</Label>
            <Explainer explanation={explanation} />
        </div>
    );
}

const referenceExplanations = {
    recordValue: {
        title: 'Record governed value',
        what: 'This form submits reference data that can feed tax, valuation, benchmark, and dashboard calculations.',
        action: 'Select the correct dataset, enter the value payload, attach source evidence where possible, and submit for governed review.',
        why: 'Incorrect reference data can distort downstream calculations, so each value needs source and effective-date context.',
    },
    dashboardItem: {
        title: 'Dashboard item',
        what: 'The specific missing or due dashboard value this submission will address.',
        action: 'Choose the dashboard item you intend to update before editing the payload.',
        why: 'Linking the value to a dashboard item reduces the risk of updating the wrong reference measure.',
    },
    dataset: {
        title: 'Dataset',
        what: 'The reference data category, such as economic indicator, tax rate, valuation multiple, or benchmark.',
        action: 'Select the dataset that matches the payload structure.',
        why: 'The dataset determines how the backend validates and applies the value.',
    },
    source: {
        title: 'Source',
        what: 'Where the reference value came from, such as manual admin entry, official publication, or evidence file.',
        action: 'Use a source label that another reviewer can understand later.',
        why: 'Source traceability is essential when calculations are challenged or refreshed.',
    },
    asAt: {
        title: 'As at date',
        what: 'The effective date for the value being submitted.',
        action: 'Use the date the value applies from, not necessarily today’s entry date.',
        why: 'Effective dates prevent stale or future values from being applied to the wrong calculation period.',
    },
    dataImport: {
        title: 'Data import',
        what: 'An optional CSV, text, or spreadsheet import for reference values.',
        action: 'Use imports for structured data sets; otherwise enter the JSON payload manually.',
        why: 'Imports can reduce typing errors when multiple values need to be recorded.',
    },
    evidence: {
        title: 'Source evidence',
        what: 'An optional document or image that supports the submitted value.',
        action: 'Attach source evidence when the value came from a published table, official record, or advisor-reviewed source.',
        why: 'Evidence lets reviewers confirm the value before it influences live methodology outputs.',
    },
    payload: {
        title: 'Payload',
        what: 'The structured JSON value that will be validated and submitted for governed implementation.',
        action: 'Edit only the fields required for the selected dataset and keep units, labels, and dates consistent.',
        why: 'The payload is what the calculation services read, so formatting mistakes can block or corrupt implementation.',
    },
    currentValues: {
        title: 'Current effective values',
        what: 'The latest implemented values currently available to the app.',
        action: 'Check this table before submitting a replacement value.',
        why: 'Seeing the current value helps avoid duplicate submissions and accidental regressions.',
    },
    recentSubmissions: {
        title: 'Recent submissions',
        what: 'A history of reference data entries and their review status.',
        action: 'Use this to confirm whether a value is pending, implemented, or needs evidence follow-up.',
        why: 'Submission history provides the audit trail for governed reference data changes.',
    },
} satisfies Record<string, Explanation>;

ReferenceDataIndex.layout = {
    breadcrumbs: [
        {
            title: 'Reference data',
            href: '/admin/reference-data',
        },
    ],
};
