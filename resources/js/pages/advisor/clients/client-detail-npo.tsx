import { useForm } from '@inertiajs/react';
import {
    Ban,
    Brain,
    CalendarClock,
    CheckCircle2,
    CreditCard,
    FileCheck2,
    FileText,
    Settings2,
    SlidersHorizontal,
    TrendingUp,
} from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatNumber, formatPercentage } from '@/lib/formatters';
import {
    Detail,
    formatCurrency,
    formatDate,
    formatLabel,
    formatMoney,
    severityVariant,
} from './client-detail-presenters';
import type {
    NpoConfigurationSummary,
    NpoConversionSummary,
    NpoFundingSummary,
    NpoGovernanceFinding,
    NpoGovernanceReviewSummary,
    NpoSocialEnterpriseSummary,
    NpoSocialEnterpriseAxis,
    NpoValueSummary,
    NpoWeightingSuggestion,
} from './client-detail-types';
export function NpoConversionPanel({
    conversion,
}: {
    conversion: NpoConversionSummary;
}) {
    const reportDeliveredForm = useForm<Record<string, never>>({});
    const declineForm = useForm<{ reason: string }>({
        reason: conversion.decline_reason ?? '',
    });
    const convertForm = useForm<Record<string, never>>({});
    const isConverted = conversion.status === 'converted';

    const submitDecline = (event: FormEvent) => {
        event.preventDefault();
        declineForm.patch(conversion.decline_url, { preserveScroll: true });
    };

    return (
        <section
            id="section-npo-conversion"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <CalendarClock className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Governance Review conversion
                    </h2>
                    <Badge
                        variant={
                            conversion.status === 'declined'
                                ? 'outline'
                                : 'secondary'
                        }
                    >
                        {conversion.status_label ??
                            formatLabel(conversion.status ?? '')}
                    </Badge>
                    {conversion.next_nudge_day && (
                        <Badge variant="destructive">
                            {conversion.next_nudge_day}d nudge due
                        </Badge>
                    )}
                </div>
                <div className="text-xs text-muted-foreground">
                    Re-engagement {formatDate(conversion.reengagement_due_at)}
                </div>
            </div>

            <dl className="grid gap-3 text-sm sm:grid-cols-3">
                <Detail
                    label="Report delivered"
                    value={formatDate(conversion.report_delivered_at)}
                />
                <Detail
                    label="Next review"
                    value={formatDate(conversion.reengagement_due_at)}
                />
                <Detail
                    label="Follow-up"
                    value={
                        conversion.next_nudge_day
                            ? `${conversion.next_nudge_day} days`
                            : 'Current'
                    }
                />
            </dl>

            {conversion.decline_reason && (
                <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                    {conversion.decline_reason}
                </div>
            )}

            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    disabled={reportDeliveredForm.processing || isConverted}
                    onClick={() =>
                        reportDeliveredForm.patch(
                            conversion.report_delivered_url,
                            { preserveScroll: true },
                        )
                    }
                >
                    <FileText className="size-4" aria-hidden="true" />
                    Report delivered
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={convertForm.processing || isConverted}
                    onClick={() =>
                        convertForm.patch(conversion.convert_url, {
                            preserveScroll: true,
                        })
                    }
                >
                    <CheckCircle2 className="size-4" aria-hidden="true" />
                    Convert
                </Button>
            </div>

            {!isConverted && (
                <form onSubmit={submitDecline} className="grid gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="npo_conversion_reason">
                            Decline reason
                        </Label>
                        <textarea
                            id="npo_conversion_reason"
                            value={declineForm.data.reason}
                            onChange={(event) =>
                                declineForm.setData(
                                    'reason',
                                    event.target.value,
                                )
                            }
                            rows={3}
                            className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <InputError message={declineForm.errors.reason} />
                    </div>
                    <div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={declineForm.processing}
                        >
                            <Ban className="size-4" aria-hidden="true" />
                            Save decline
                        </Button>
                    </div>
                </form>
            )}
        </section>
    );
}

export function NpoGovernanceReviewPanel({
    summary,
}: {
    summary: NpoGovernanceReviewSummary;
}) {
    const runForm = useForm<Record<string, never>>({});

    return (
        <section
            id="section-npo-governance-review"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <FileCheck2 className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Governance Review workflow
                    </h2>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Badge variant="secondary">
                                {summary.pending_review_count} pending
                            </Badge>
                        </TooltipTrigger>
                        <TooltipContent side="bottom" className="max-w-xs">
                            Advisor review is required before governance
                            findings can be used in a client-facing report.
                        </TooltipContent>
                    </Tooltip>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={runForm.processing}
                    onClick={() =>
                        runForm.post(summary.run_url, { preserveScroll: true })
                    }
                >
                    <Brain className="size-4" aria-hidden="true" />
                    Run analysis
                </Button>
            </div>

            <dl className="grid gap-3 text-sm sm:grid-cols-4">
                <Detail
                    label="Findings"
                    value={summary.findings_count.toString()}
                />
                <Detail
                    label="High priority"
                    value={summary.high_priority_count.toString()}
                />
                <Detail
                    label="Reviewed"
                    value={summary.reviewed_count.toString()}
                />
                <Detail
                    label="Report ready"
                    value={summary.can_generate_report ? 'Yes' : 'No'}
                />
            </dl>

            {summary.findings.length === 0 ? (
                <p className="rounded-md border px-3 py-6 text-sm text-muted-foreground">
                    No governance findings generated yet.
                </p>
            ) : (
                <div className="grid gap-3">
                    {summary.findings.map((finding) => (
                        <NpoGovernanceFindingCard
                            key={finding.id}
                            finding={finding}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}

export function NpoGovernanceFindingCard({
    finding,
}: {
    finding: NpoGovernanceFinding;
}) {
    const form = useForm({ advisor_notes: finding.advisor_notes ?? '' });

    return (
        <article className="grid gap-3 rounded-md border p-3 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.55fr)]">
            <div className="space-y-2">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={severityVariant(finding.severity)}>
                        {formatLabel(finding.severity)}
                    </Badge>
                    <Badge variant="outline">
                        {formatLabel(finding.status)}
                    </Badge>
                    <span className="text-xs text-muted-foreground">
                        {formatLabel(finding.category)}
                    </span>
                </div>
                <h3 className="text-sm font-medium">{finding.title}</h3>
                <p className="text-sm text-muted-foreground">{finding.body}</p>
            </div>
            <div className="grid gap-2">
                <textarea
                    value={form.data.advisor_notes}
                    onChange={(event) =>
                        form.setData('advisor_notes', event.target.value)
                    }
                    className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    placeholder="Advisor review notes"
                />
                <Button
                    type="button"
                    size="sm"
                    disabled={form.processing || finding.status === 'reviewed'}
                    onClick={() =>
                        form.patch(finding.review_url, {
                            preserveScroll: true,
                        })
                    }
                >
                    <CheckCircle2 className="size-4" aria-hidden="true" />
                    Mark reviewed
                </Button>
            </div>
        </article>
    );
}

export function NpoConfigurationPanel({
    configuration,
}: {
    configuration: NpoConfigurationSummary;
}) {
    const defaultSocialType =
        configuration.social_enterprise_type ??
        configuration.social_enterprise_type_options[0]?.value ??
        '';
    const selectedSuggestion =
        configuration.social_enterprise_type_options.find(
            (option) => option.value === defaultSocialType,
        ) ?? configuration.social_enterprise_type_options[0];
    const form = useForm({
        legal_structure: configuration.legal_structure,
        tiriti_decision_guide: configuration.tiriti_decision_guide,
        tiriti_mode:
            configuration.tiriti_mode ?? configuration.tiriti_suggested_mode,
        social_enterprise: configuration.social_enterprise,
        social_enterprise_type: defaultSocialType,
        commercial_weight:
            configuration.commercial_weight ??
            selectedSuggestion?.commercial_weight ??
            50,
        mission_weight:
            configuration.mission_weight ??
            selectedSuggestion?.mission_weight ??
            50,
    });
    const errors = form.errors as Record<string, string | undefined>;
    const suggestedMode = Object.values(form.data.tiriti_decision_guide).some(
        Boolean,
    )
        ? 'standalone'
        : 'woven';

    const setGuideAnswer = (key: string, checked: boolean) => {
        const nextGuide = {
            ...form.data.tiriti_decision_guide,
            [key]: checked,
        };
        const nextSuggestedMode = Object.values(nextGuide).some(Boolean)
            ? 'standalone'
            : 'woven';

        form.setData({
            ...form.data,
            tiriti_decision_guide: nextGuide,
            tiriti_mode: nextSuggestedMode,
        });
    };

    const setSocialEnterpriseType = (value: string) => {
        const suggestion = configuration.social_enterprise_type_options.find(
            (option) => option.value === value,
        );

        form.setData({
            ...form.data,
            social_enterprise_type: value,
            commercial_weight:
                suggestion?.commercial_weight ?? form.data.commercial_weight,
            mission_weight:
                suggestion?.mission_weight ?? form.data.mission_weight,
        });
    };

    const applyWeighting = (suggestion: NpoWeightingSuggestion) => {
        form.setData({
            ...form.data,
            social_enterprise: true,
            social_enterprise_type: suggestion.value,
            commercial_weight: suggestion.commercial_weight,
            mission_weight: suggestion.mission_weight,
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.patch(configuration.update_url, { preserveScroll: true });
    };

    return (
        <section
            id="section-npo-configuration"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Settings2 className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">NPO configuration</h2>
                    <Badge variant="secondary">
                        {configuration.sub_type_label}
                    </Badge>
                    <Badge variant="outline">
                        {configuration.tiriti_mode_label ??
                            formatLabel(form.data.tiriti_mode)}
                    </Badge>
                </div>
                <div className="text-xs text-muted-foreground">
                    Suggested {formatLabel(suggestedMode)}
                </div>
            </div>

            <form onSubmit={submit} className="space-y-5">
                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-3 border-l pl-3">
                        <div className="flex items-center gap-2">
                            <Badge variant="outline">1</Badge>
                            <h3 className="text-sm font-medium">
                                Legal structure
                            </h3>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="npo_legal_structure">
                                Structure
                            </Label>
                            <Select
                                value={form.data.legal_structure}
                                onValueChange={(value) =>
                                    form.setData('legal_structure', value)
                                }
                            >
                                <SelectTrigger id="npo_legal_structure">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {configuration.legal_structure_options.map(
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
                            <InputError message={form.errors.legal_structure} />
                        </div>
                    </div>

                    <div className="space-y-3 border-l pl-3">
                        <div className="flex items-center gap-2">
                            <Badge variant="outline">2</Badge>
                            <h3 className="text-sm font-medium">
                                Te Tiriti mode
                            </h3>
                        </div>
                        <div className="grid gap-3">
                            {configuration.tiriti_decision_questions.map(
                                (question) => (
                                    <label
                                        key={question.key}
                                        htmlFor={`tiriti_${question.key}`}
                                        className="flex gap-2 text-sm leading-5"
                                    >
                                        <Checkbox
                                            id={`tiriti_${question.key}`}
                                            checked={
                                                form.data.tiriti_decision_guide[
                                                    question.key
                                                ] ?? false
                                            }
                                            onCheckedChange={(checked) =>
                                                setGuideAnswer(
                                                    question.key,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <span>{question.label}</span>
                                    </label>
                                ),
                            )}
                            <InputError
                                message={errors.tiriti_decision_guide}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="tiriti_mode">Mode</Label>
                            <Select
                                value={form.data.tiriti_mode}
                                onValueChange={(value) =>
                                    form.setData('tiriti_mode', value)
                                }
                            >
                                <SelectTrigger id="tiriti_mode">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {configuration.tiriti_mode_options.map(
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
                            <InputError message={form.errors.tiriti_mode} />
                        </div>
                    </div>

                    <div className="space-y-3 border-l pl-3">
                        <div className="flex items-center gap-2">
                            <Badge variant="outline">3</Badge>
                            <h3 className="text-sm font-medium">
                                Social enterprise
                            </h3>
                        </div>
                        <label
                            htmlFor="npo_social_enterprise"
                            className="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                id="npo_social_enterprise"
                                checked={form.data.social_enterprise}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'social_enterprise',
                                        checked === true,
                                    )
                                }
                            />
                            <span>Dual commercial and mission scorecard</span>
                        </label>
                        <InputError message={form.errors.social_enterprise} />

                        <div className="grid gap-2">
                            <Label htmlFor="social_enterprise_type">Type</Label>
                            <Select
                                value={form.data.social_enterprise_type}
                                onValueChange={setSocialEnterpriseType}
                                disabled={!form.data.social_enterprise}
                            >
                                <SelectTrigger id="social_enterprise_type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {configuration.social_enterprise_type_options.map(
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
                            <InputError
                                message={form.errors.social_enterprise_type}
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="commercial_weight">
                                    Commercial
                                </Label>
                                <Input
                                    id="commercial_weight"
                                    type="number"
                                    min={0}
                                    max={100}
                                    value={form.data.commercial_weight}
                                    disabled={!form.data.social_enterprise}
                                    onChange={(event) =>
                                        form.setData(
                                            'commercial_weight',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.commercial_weight}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="mission_weight">Mission</Label>
                                <Input
                                    id="mission_weight"
                                    type="number"
                                    min={0}
                                    max={100}
                                    value={form.data.mission_weight}
                                    disabled={!form.data.social_enterprise}
                                    onChange={(event) =>
                                        form.setData(
                                            'mission_weight',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.mission_weight}
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="overflow-hidden rounded-md border">
                    <table className="fsa-responsive-table">
                        <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">
                                    Commercial
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Mission
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Apply
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {configuration.social_enterprise_type_options.map(
                                (suggestion) => (
                                    <tr
                                        key={suggestion.value}
                                        className="border-t"
                                    >
                                        <td
                                            className="px-3 py-2"
                                            data-label="Type"
                                        >
                                            {suggestion.label}
                                        </td>
                                        <td
                                            className="px-3 py-2"
                                            data-label="Commercial"
                                        >
                                            {suggestion.commercial_weight}%
                                        </td>
                                        <td
                                            className="px-3 py-2"
                                            data-label="Mission"
                                        >
                                            {suggestion.mission_weight}%
                                        </td>
                                        <td
                                            className="px-3 py-2 text-left md:text-right"
                                            data-label="Apply"
                                        >
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    applyWeighting(suggestion)
                                                }
                                            >
                                                <SlidersHorizontal
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Apply
                                            </Button>
                                        </td>
                                    </tr>
                                ),
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        <CheckCircle2 className="size-4" aria-hidden="true" />
                        Save configuration
                    </Button>
                </div>
            </form>
        </section>
    );
}

export function NpoFundingPanel({ funding }: { funding: NpoFundingSummary }) {
    return (
        <section
            id="section-npo-funding"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <CreditCard className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">NPO funding</h2>
                    <Badge variant="secondary">
                        {funding.records.length} records
                    </Badge>
                    {funding.alerts.length > 0 && (
                        <Badge variant="destructive">
                            {funding.alerts.length} alerts
                        </Badge>
                    )}
                </div>
                <Badge variant="outline">
                    {formatLabel(funding.concentration.risk_level)} risk
                </Badge>
            </div>

            <dl className="grid gap-3 text-sm sm:grid-cols-3">
                <Detail
                    label="Active funding"
                    value={formatCurrency(
                        funding.concentration.total_active_amount,
                    )}
                />
                <Detail
                    label="Largest funder"
                    value={funding.concentration.largest_funder_name ?? '-'}
                />
                <Detail
                    label="Concentration"
                    value={formatPercentage(
                        funding.concentration.largest_funder_ratio,
                    )}
                />
            </dl>

            {funding.alerts.length > 0 && (
                <div className="grid gap-2">
                    {funding.alerts.map((alert) => (
                        <div
                            key={alert.id}
                            className="flex flex-col gap-1 rounded-md border bg-muted/30 p-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <div className="font-medium">
                                    {alert.funder_name ?? 'Funder'}
                                </div>
                                <div className="text-muted-foreground">
                                    {alert.message}
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge
                                    variant={
                                        alert.severity === 'critical'
                                            ? 'destructive'
                                            : 'outline'
                                    }
                                >
                                    {formatLabel(alert.severity)}
                                </Badge>
                                <span className="text-xs text-muted-foreground">
                                    {formatDate(alert.due_on)}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <div className="overflow-hidden rounded-md border">
                <table className="fsa-responsive-table">
                    <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="px-3 py-2 font-medium">Funder</th>
                            <th className="px-3 py-2 font-medium">Amount</th>
                            <th className="px-3 py-2 font-medium">Report</th>
                            <th className="px-3 py-2 font-medium">Renewal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {funding.records.map((record) => (
                            <tr key={record.id} className="border-t">
                                <td className="px-3 py-2" data-label="Funder">
                                    <div className="font-medium">
                                        {record.funder_name ?? 'Funder'}
                                    </div>
                                    {record.funder_needs_verification && (
                                        <Badge variant="outline">Verify</Badge>
                                    )}
                                </td>
                                <td className="px-3 py-2" data-label="Amount">
                                    {formatMoney(
                                        record.grant_amount,
                                        record.currency,
                                    )}
                                </td>
                                <td className="px-3 py-2" data-label="Report">
                                    {formatDate(record.reporting_deadline)}
                                </td>
                                <td className="px-3 py-2" data-label="Renewal">
                                    {record.renewal_probability === null
                                        ? '-'
                                        : `${record.renewal_probability}%`}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export function NpoValuePanel({ values }: { values: NpoValueSummary }) {
    return (
        <section
            id="section-npo-value"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <TrendingUp className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        NPO value calculations
                    </h2>
                    <Badge variant="secondary">
                        {values.calculations.length} latest
                    </Badge>
                </div>
            </div>

            <div className="grid gap-3 lg:grid-cols-2">
                {values.calculations.map((calculation) => (
                    <article
                        key={calculation.id}
                        className="space-y-3 rounded-md border bg-muted/20 p-3"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="font-medium">
                                {calculation.label}
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                {calculation.impact_governance && (
                                    <Badge variant="secondary">
                                        {
                                            calculation.impact_governance
                                                .verification_label
                                        }
                                    </Badge>
                                )}
                                <Badge
                                    variant={
                                        calculation.rating === 'critical' ||
                                        calculation.rating === 'high_cost'
                                            ? 'destructive'
                                            : 'outline'
                                    }
                                >
                                    {formatLabel(calculation.rating)}
                                </Badge>
                            </div>
                        </div>
                        {calculation.impact_governance && (
                            <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                                <span>
                                    Theory:{' '}
                                    {formatLabel(
                                        calculation.impact_governance
                                            .theory_of_change_status,
                                    )}
                                </span>
                                <span>
                                    Stakeholders:{' '}
                                    {formatLabel(
                                        calculation.impact_governance
                                            .stakeholder_involvement_status,
                                    )}
                                </span>
                            </div>
                        )}
                        <div className="grid gap-2 text-sm sm:grid-cols-3">
                            <Detail
                                label="Low"
                                value={formatProjectionValue(
                                    calculation.projection_low,
                                    calculation.projections[0]?.unit,
                                )}
                            />
                            <Detail
                                label="Mid"
                                value={formatProjectionValue(
                                    calculation.projection_mid,
                                    calculation.projections[0]?.unit,
                                )}
                            />
                            <Detail
                                label="High"
                                value={formatProjectionValue(
                                    calculation.projection_high,
                                    calculation.projections[0]?.unit,
                                )}
                            />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {calculation.mission_framing}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {calculation.stable_assumption_disclosure}
                        </p>
                    </article>
                ))}
            </div>
        </section>
    );
}

export function formatProjectionValue(value: number, unit?: string): string {
    if (unit === 'beneficiaries') {
        return `${formatNumber(value, { maximumFractionDigits: 1 })} beneficiaries`;
    }

    return formatCurrency(value);
}

export function NpoSocialEnterprisePanel({
    summary,
}: {
    summary: NpoSocialEnterpriseSummary;
}) {
    const { scorecard, tension_analysis } = summary;

    return (
        <section
            id="section-npo-social-enterprise"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <SlidersHorizontal className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Social enterprise scorecard
                    </h2>
                </div>
                <Badge variant="secondary">
                    Blended {scorecard.blended_score.toFixed(1)}
                </Badge>
            </div>

            <div className="grid gap-3 md:grid-cols-3">
                <Detail
                    label={`Commercial (${scorecard.commercial_weight}%)`}
                    value={`${scorecard.commercial_score}/100`}
                />
                <Detail
                    label={`Mission (${scorecard.mission_weight}%)`}
                    value={`${scorecard.mission_score}/100`}
                />
                <Detail
                    label="Blended"
                    value={`${scorecard.blended_score.toFixed(1)}/100`}
                />
            </div>

            <div className="grid gap-3 lg:grid-cols-2">
                <AxisList
                    title="Commercial radar"
                    axes={scorecard.commercial_axes}
                />
                <AxisList title="Mission radar" axes={scorecard.mission_axes} />
            </div>

            {tension_analysis && (
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <h3 className="text-sm font-medium">Tensions</h3>
                        <Badge
                            variant={
                                tension_analysis.is_releasable
                                    ? 'secondary'
                                    : 'outline'
                            }
                        >
                            {formatLabel(tension_analysis.review_status)}
                        </Badge>
                    </div>
                    <div className="grid gap-3">
                        {tension_analysis.tensions.map((tension) => (
                            <article
                                key={`${tension.type}-${tension.title}`}
                                className="rounded-md border bg-muted/20 p-3"
                            >
                                <div className="mb-2 flex flex-wrap items-center gap-2">
                                    <Badge variant="outline">
                                        {formatLabel(tension.type)}
                                    </Badge>
                                    <div className="font-medium">
                                        {tension.title}
                                    </div>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {tension.commercial_implication}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {tension.mission_implication}
                                </p>
                            </article>
                        ))}
                    </div>
                </div>
            )}
        </section>
    );
}

export function AxisList({
    title,
    axes,
}: {
    title: string;
    axes: NpoSocialEnterpriseAxis[];
}) {
    return (
        <div className="space-y-2 rounded-md border p-3">
            <h3 className="text-sm font-medium">{title}</h3>
            <div className="grid gap-2 text-sm">
                {axes.map((axis) => (
                    <div
                        key={`${title}-${axis.dimension}`}
                        className="flex items-center justify-between gap-3"
                    >
                        <span className="text-muted-foreground">
                            {axis.label}
                        </span>
                        <span className="font-medium">
                            {axis.score === null ? '-' : `${axis.score}/100`}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}
