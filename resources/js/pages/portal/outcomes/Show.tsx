import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
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
import { cn } from '@/lib/utils';

type Option = {
    value: string;
    label: string;
};

type FocusAreaStatus =
    | 'implemented'
    | 'partially_implemented'
    | 'not_started'
    | 'not_applicable';

type FocusAreaOutcome = {
    proposal_id: string | null;
    analysis_finding_id: string | null;
    module: string | null;
    title: string;
    status: FocusAreaStatus;
    implemented: boolean;
    notes: string;
};

type FollowUp = {
    id: string;
    subject_type: 'entrepreneur' | 'due_diligence';
    subject_label: string;
    subject_name: string;
    cadence_month: number;
    status: string;
    is_open: boolean;
    due_at: string | null;
    completed_at: string | null;
    engagement_completed_at: string | null;
    response: Record<string, unknown>;
    focus_area_outcomes: FocusAreaOutcome[];
    status_options: Option[];
};

type FormData = {
    status: string;
    still_trading: string;
    revenue_direction: string;
    revenue_growth_percent: string;
    recorded_price: string;
    implemented_recommendations: string;
    total_recommendations: string;
    focus_area_outcomes: FocusAreaOutcome[];
    comments: string;
};

type Props = {
    followUp: FollowUp;
    storeUrl: string;
    dashboardUrl: string;
};

const revenueDirections: Option[] = [
    { value: 'up', label: 'Revenue is up' },
    { value: 'flat', label: 'Revenue is flat' },
    { value: 'down', label: 'Revenue is down' },
    { value: 'not_started', label: 'Not trading yet' },
    { value: 'not_available', label: 'Not available' },
];

const focusAreaStatuses: Option[] = [
    { value: 'implemented', label: 'Implemented' },
    { value: 'partially_implemented', label: 'Partially implemented' },
    { value: 'not_started', label: 'Not started' },
    { value: 'not_applicable', label: 'Not applicable' },
];

export default function OutcomeFollowUpShow({
    followUp,
    storeUrl,
    dashboardUrl,
}: Props) {
    const focusAreaOutcomes = focusAreaOutcomeValues(
        followUp.response.focus_area_outcomes ?? followUp.focus_area_outcomes,
    );
    const initialFocusAreaCounts = focusAreaCounts(focusAreaOutcomes);
    const form = useForm<FormData>({
        status: textValue(followUp.response.status),
        still_trading: booleanValue(followUp.response.still_trading),
        revenue_direction:
            textValue(followUp.response.revenue_direction) || 'not_available',
        revenue_growth_percent: numberValue(
            followUp.response.revenue_growth_percent,
        ),
        recorded_price: numberValue(followUp.response.recorded_price),
        implemented_recommendations:
            numberValue(followUp.response.implemented_recommendations) ||
            (focusAreaOutcomes.length > 0
                ? String(initialFocusAreaCounts.implemented)
                : ''),
        total_recommendations:
            numberValue(followUp.response.total_recommendations) ||
            (focusAreaOutcomes.length > 0
                ? String(initialFocusAreaCounts.total)
                : ''),
        focus_area_outcomes: focusAreaOutcomes,
        comments: textValue(followUp.response.comments),
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(storeUrl, { preserveScroll: true });
    };

    const title =
        followUp.subject_type === 'due_diligence'
            ? 'Post-engagement buying outcome'
            : 'Post-engagement idea outcome';

    const setFocusAreaStatus = (index: number, status: FocusAreaStatus) => {
        const next = form.data.focus_area_outcomes.map((area, areaIndex) =>
            areaIndex === index
                ? { ...area, status, implemented: status === 'implemented' }
                : area,
        );
        const counts = focusAreaCounts(next);

        form.setData({
            ...form.data,
            focus_area_outcomes: next,
            implemented_recommendations: String(counts.implemented),
            total_recommendations: String(counts.total),
        });
    };

    const setFocusAreaNotes = (index: number, notes: string) => {
        form.setData(
            'focus_area_outcomes',
            form.data.focus_area_outcomes.map((area, areaIndex) =>
                areaIndex === index ? { ...area, notes } : area,
            ),
        );
    };

    return (
        <>
            <Head title={title} />

            <main className="mx-auto max-w-4xl space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <Link
                            href={dashboardUrl}
                            className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Dashboard
                        </Link>
                        <h1 className="mt-3 text-xl font-semibold">{title}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {followUp.subject_label} / {followUp.subject_name}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="secondary">
                            {followUp.cadence_month} month follow-up
                        </Badge>
                        <Badge variant="outline">{followUp.status}</Badge>
                    </div>
                </div>

                <section className="rounded-md border bg-background p-4 text-sm text-muted-foreground">
                    This follow-up helps Future Shift Advisory measure whether
                    advice turned into commercial progress. Please be direct:
                    the answers are attributed to your account and are used for
                    governed learning, not automatic changes to advice or
                    pricing.
                </section>

                <form onSubmit={submit} className="space-y-4">
                    <section className="rounded-md border bg-background p-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field label="Current outcome">
                                <Select
                                    value={form.data.status}
                                    disabled={!followUp.is_open}
                                    onValueChange={(value) =>
                                        form.setData('status', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select outcome" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {followUp.status_options.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.status} />
                            </Field>

                            <Field
                                label={
                                    followUp.subject_type === 'due_diligence'
                                        ? 'Is the business still trading?'
                                        : 'Are you still trading?'
                                }
                            >
                                <div className="grid grid-cols-2 gap-2">
                                    <ToggleButton
                                        active={form.data.still_trading === '1'}
                                        disabled={!followUp.is_open}
                                        onClick={() =>
                                            form.setData('still_trading', '1')
                                        }
                                    >
                                        Yes
                                    </ToggleButton>
                                    <ToggleButton
                                        active={form.data.still_trading === '0'}
                                        disabled={!followUp.is_open}
                                        onClick={() =>
                                            form.setData('still_trading', '0')
                                        }
                                    >
                                        No
                                    </ToggleButton>
                                </div>
                                <InputError
                                    message={form.errors.still_trading}
                                />
                            </Field>

                            <Field label="Revenue direction">
                                <Select
                                    value={form.data.revenue_direction}
                                    disabled={!followUp.is_open}
                                    onValueChange={(value) =>
                                        form.setData('revenue_direction', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select revenue direction" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {revenueDirections.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={form.errors.revenue_direction}
                                />
                            </Field>

                            <Field label="Revenue growth %">
                                <Input
                                    type="number"
                                    step="0.1"
                                    value={form.data.revenue_growth_percent}
                                    disabled={!followUp.is_open}
                                    placeholder="Optional"
                                    onChange={(event) =>
                                        form.setData(
                                            'revenue_growth_percent',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.revenue_growth_percent}
                                />
                            </Field>

                            {followUp.subject_type === 'due_diligence' ? (
                                <Field label="Actual purchase price NZD">
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={form.data.recorded_price}
                                        disabled={!followUp.is_open}
                                        placeholder="Optional if no acquisition"
                                        onChange={(event) =>
                                            form.setData(
                                                'recorded_price',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.recorded_price}
                                    />
                                </Field>
                            ) : null}

                            <Field label="Recommendations implemented">
                                <Input
                                    type="number"
                                    min={0}
                                    step={1}
                                    value={
                                        form.data.implemented_recommendations
                                    }
                                    disabled={!followUp.is_open}
                                    onChange={(event) =>
                                        form.setData(
                                            'implemented_recommendations',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        form.errors.implemented_recommendations
                                    }
                                />
                            </Field>

                            <Field label="Total recommendations">
                                <Input
                                    type="number"
                                    min={0}
                                    step={1}
                                    value={form.data.total_recommendations}
                                    disabled={!followUp.is_open}
                                    onChange={(event) =>
                                        form.setData(
                                            'total_recommendations',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.total_recommendations}
                                />
                            </Field>
                        </div>

                        {form.data.focus_area_outcomes.length > 0 ? (
                            <Field
                                label="Proposal focus areas"
                                className="mt-4"
                            >
                                <div className="space-y-3">
                                    {form.data.focus_area_outcomes.map(
                                        (area, index) => (
                                            <div
                                                key={
                                                    area.analysis_finding_id ??
                                                    `${area.title}-${index}`
                                                }
                                                className="grid gap-3 rounded-md border border-border bg-card p-3"
                                            >
                                                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                    <p className="text-sm font-medium text-foreground">
                                                        {area.title ||
                                                            'Advisory focus area'}
                                                    </p>
                                                    {area.module ? (
                                                        <Badge variant="outline">
                                                            {area.module.replace(
                                                                /_/g,
                                                                ' ',
                                                            )}
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                                <div className="grid gap-3 md:grid-cols-[minmax(0,16rem)_1fr]">
                                                    <Select
                                                        value={area.status}
                                                        disabled={
                                                            !followUp.is_open
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            setFocusAreaStatus(
                                                                index,
                                                                value as FocusAreaStatus,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {focusAreaStatuses.map(
                                                                (option) => (
                                                                    <SelectItem
                                                                        key={
                                                                            option.value
                                                                        }
                                                                        value={
                                                                            option.value
                                                                        }
                                                                    >
                                                                        {
                                                                            option.label
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    <Input
                                                        value={area.notes}
                                                        disabled={
                                                            !followUp.is_open
                                                        }
                                                        placeholder="Optional note"
                                                        onChange={(event) =>
                                                            setFocusAreaNotes(
                                                                index,
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                                <InputError
                                    message={form.errors.focus_area_outcomes}
                                />
                            </Field>
                        ) : null}

                        <Field label="Comments" className="mt-4">
                            <textarea
                                value={form.data.comments}
                                disabled={!followUp.is_open}
                                rows={5}
                                className="min-h-28 w-full rounded-md border border-input bg-card px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50"
                                placeholder="What changed, which recommendations were implemented, and what still needs attention?"
                                onChange={(event) =>
                                    form.setData('comments', event.target.value)
                                }
                            />
                            <InputError message={form.errors.comments} />
                        </Field>
                    </section>

                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            disabled={!followUp.is_open || form.processing}
                        >
                            <CheckCircle2
                                className="size-4"
                                aria-hidden="true"
                            />
                            Submit outcome
                        </Button>
                    </div>
                </form>
            </main>
        </>
    );
}

function Field({
    label,
    className,
    children,
}: {
    label: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('grid gap-2', className)}>
            <Label>{label}</Label>
            {children}
        </div>
    );
}

function ToggleButton({
    active,
    disabled,
    onClick,
    children,
}: {
    active: boolean;
    disabled: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'h-9 rounded-md border px-3 text-sm font-medium transition-colors disabled:opacity-50',
                active
                    ? 'border-[var(--fs-admiralty)] bg-[var(--fs-admiralty)] text-white'
                    : 'bg-card hover:bg-muted',
            )}
        >
            {children}
        </button>
    );
}

function textValue(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

function numberValue(value: unknown): string {
    return typeof value === 'number' || typeof value === 'string'
        ? String(value)
        : '';
}

function booleanValue(value: unknown): string {
    if (value === true) {
        return '1';
    }

    if (value === false) {
        return '0';
    }

    return '';
}

function focusAreaOutcomeValues(value: unknown): FocusAreaOutcome[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((item): FocusAreaOutcome | null => {
            if (!isRecord(item)) {
                return null;
            }

            const status = focusAreaStatusValue(item.status);

            return {
                proposal_id: nullableTextValue(item.proposal_id),
                analysis_finding_id: nullableTextValue(
                    item.analysis_finding_id,
                ),
                module: nullableTextValue(item.module),
                title: textValue(item.title) || 'Advisory focus area',
                status,
                implemented:
                    status === 'implemented' || item.implemented === true,
                notes: textValue(item.notes),
            };
        })
        .filter((item): item is FocusAreaOutcome => item !== null);
}

function focusAreaStatusValue(value: unknown): FocusAreaStatus {
    return value === 'implemented' ||
        value === 'partially_implemented' ||
        value === 'not_started' ||
        value === 'not_applicable'
        ? value
        : 'not_started';
}

function focusAreaCounts(items: FocusAreaOutcome[]): {
    implemented: number;
    total: number;
} {
    const counted = items.filter((item) => item.status !== 'not_applicable');

    return {
        implemented: counted.filter((item) => item.status === 'implemented')
            .length,
        total: counted.length,
    };
}

function nullableTextValue(value: unknown): string | null {
    const text = textValue(value);

    return text === '' ? null : text;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}
