import { Head, router, useForm } from '@inertiajs/react';
import { FileSignature, RotateCcw, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type PartnerAgreementField = {
    key: string;
    group: string;
    label: string;
    type: string;
    config_path: string;
    is_secret: boolean;
    value: string;
    configured: boolean;
    source: 'project' | 'config' | string;
    last_four: string | null;
    options: string[];
    min: number | null;
    max: number | null;
    updated_at: string | null;
};

type PartnerAgreementGroup = {
    key: string;
    title: string;
    description: string;
    fields: PartnerAgreementField[];
};

type Props = {
    group: PartnerAgreementGroup;
    routes: {
        update: string;
        reset: string;
    };
};

export default function PartnerAgreementIndex({ group, routes }: Props) {
    const form = useForm({
        settings: Object.fromEntries(
            group.fields.map((field) => [field.key, field.value]),
        ),
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.patch(routes.update, { preserveScroll: true });
    }

    function resetField(field: PartnerAgreementField) {
        router.patch(
            routes.reset,
            { key: field.key },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Partner Agreement" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Administration"
                    icon={FileSignature}
                    title="Partner Agreement"
                    description="Maintain the agreement wording used when brokers and coaches are approved as Future Shift Advisory partners."
                />

                <section className="rounded-md border bg-muted/20 p-4 text-sm text-muted-foreground">
                    Updates affect newly generated partner agreements only.
                    Signed agreements remain unchanged for audit history.
                </section>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 lg:grid-cols-2">
                        {group.fields.map((field) => (
                            <AgreementField
                                key={field.key}
                                field={field}
                                value={form.data.settings[field.key] ?? ''}
                                disabled={form.processing}
                                onChange={(value) =>
                                    form.setData('settings', {
                                        ...form.data.settings,
                                        [field.key]: value,
                                    })
                                }
                                onReset={() => resetField(field)}
                            />
                        ))}
                    </div>

                    <InputError message={form.errors.settings} />

                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            <Save className="size-4" aria-hidden="true" />
                            Save partner agreement
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

function AgreementField({
    field,
    value,
    disabled,
    onChange,
    onReset,
}: {
    field: PartnerAgreementField;
    value: string;
    disabled: boolean;
    onChange: (value: string) => void;
    onReset: () => void;
}) {
    const inputId = field.key.replaceAll('.', '_');
    const resetDisabled = field.source !== 'project' || disabled;

    return (
        <div className="grid gap-3 rounded-md border bg-background p-4">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <Label htmlFor={inputId}>{field.label}</Label>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {fieldHelp(field.key)}
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <SourceBadge field={field} />
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span className="inline-flex">
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="outline"
                                    disabled={resetDisabled}
                                    onClick={onReset}
                                    aria-label={`Reset ${field.label} to default wording`}
                                >
                                    <RotateCcw
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </span>
                        </TooltipTrigger>
                        <TooltipContent side="left" className="max-w-xs">
                            {field.source === 'project'
                                ? `Reset ${field.label} to the default wording configured for the application.`
                                : `${field.label} is already using the default wording, so there is nothing to reset.`}
                        </TooltipContent>
                    </Tooltip>
                </div>
            </div>

            {field.type === 'text' ? (
                <textarea
                    id={inputId}
                    rows={8}
                    value={value}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.value)}
                    className="min-h-40 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                />
            ) : (
                <Input
                    id={inputId}
                    value={value}
                    disabled={disabled}
                    autoComplete="off"
                    onChange={(event) => onChange(event.target.value)}
                />
            )}
        </div>
    );
}

function SourceBadge({ field }: { field: PartnerAgreementField }) {
    const isSavedOverride = field.source === 'project';

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span tabIndex={0} className="outline-none">
                    <Badge variant={isSavedOverride ? 'secondary' : 'outline'}>
                        {isSavedOverride ? 'Saved override' : 'Default'}
                    </Badge>
                </span>
            </TooltipTrigger>
            <TooltipContent side="left" className="max-w-xs">
                {isSavedOverride
                    ? 'This wording is saved in the admin portal and overrides the application default.'
                    : 'This wording is the application default. Edit and save to create an admin-controlled version.'}
            </TooltipContent>
        </Tooltip>
    );
}

function fieldHelp(key: string): string {
    if (key.endsWith('.title')) {
        return 'Shown as the heading on newly generated broker and coach partner agreements.';
    }

    if (key.endsWith('.introduction')) {
        return 'Opening wording that frames the agreement before the detailed terms.';
    }

    if (key.endsWith('.standard_terms')) {
        return 'Terms that apply to every approved partner, regardless of partner type.';
    }

    if (key.endsWith('.broker_terms')) {
        return 'Additional terms applied only to broker partner agreements.';
    }

    if (key.endsWith('.coach_terms')) {
        return 'Additional terms applied only to coach partner agreements.';
    }

    return 'Partner agreement wording.';
}

PartnerAgreementIndex.layout = {
    breadcrumbs: [
        {
            title: 'Partner Agreement',
            href: '/admin/partner-agreement',
        },
    ],
};
