import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronUp,
    Copy,
    KeyRound,
    Mail,
    RotateCcw,
    Save,
    Send,
    Settings2,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { CompletionSummaryBadges } from '@/components/completion-summary-badges';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ProjectSettingField = {
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

type ProjectSettingGroup = {
    key: string;
    title: string;
    description: string;
    fields: ProjectSettingField[];
};

type Props = {
    groups: ProjectSettingGroup[];
    routes: {
        update: string;
        reset: string;
        test_email: string;
        test_slack: string;
    };
    microsoftRedirectUri: string;
};

export default function ProjectSettingsIndex({
    groups,
    routes,
    microsoftRedirectUri,
}: Props) {
    return (
        <>
            <Head title="Project settings" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Administration"
                    icon={Settings2}
                    title="Project settings"
                    description="Manage application delivery settings, notification endpoints, and Microsoft Graph configuration."
                />

                <div className="grid gap-4">
                    {groups.map((group) => (
                        <ProjectSettingsGroupForm
                            key={group.key}
                            group={group}
                            routes={routes}
                            microsoftRedirectUri={microsoftRedirectUri}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

function ProjectSettingsGroupForm({
    group,
    routes,
    microsoftRedirectUri,
}: {
    group: ProjectSettingGroup;
    routes: Props['routes'];
    microsoftRedirectUri: string;
}) {
    const form = useForm({
        group: group.key,
        settings: Object.fromEntries(
            group.fields.map((field) => [field.key, field.value]),
        ),
    });
    const testEmailForm = useForm({
        recipient: '',
    });
    const testSlackForm = useForm({
        slack_webhook: '',
    });
    const [expanded, setExpanded] = useState(false);
    const groupContentId = `project-settings-${group.key}`;
    const completed = group.fields.filter((field) => field.configured).length;

    function submit(event: FormEvent) {
        event.preventDefault();
        form.patch(routes.update, {
            preserveScroll: true,
            onSuccess: () => {
                const cleared = group.fields
                    .filter((field) => field.is_secret)
                    .reduce(
                        (settings, field) =>
                            setFieldValue(settings, field.key, ''),
                        form.data.settings,
                    );

                form.setData('settings', cleared);
            },
        });
    }

    function resetField(field: ProjectSettingField) {
        router.patch(
            routes.reset,
            { key: field.key },
            { preserveScroll: true },
        );
    }

    function sendTestEmail(event: FormEvent) {
        event.preventDefault();
        testEmailForm.post(routes.test_email, {
            preserveScroll: true,
            onSuccess: () => testEmailForm.reset('recipient'),
        });
    }

    function sendTestSlack(event: FormEvent) {
        event.preventDefault();
        testSlackForm.post(routes.test_slack, {
            preserveScroll: true,
        });
    }

    return (
        <section className="rounded-md border bg-background">
            <div className="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <GroupIcon groupKey={group.key} />
                        <h2 className="text-sm font-medium">{group.title}</h2>
                    </div>
                    <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                        {group.description}
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <CompletionSummaryBadges
                        total={group.fields.length}
                        completed={completed}
                        itemSingular="setting"
                        itemPlural="settings"
                    />
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        className="size-8"
                        aria-controls={groupContentId}
                        aria-expanded={expanded}
                        aria-label={
                            expanded
                                ? `Collapse ${group.title}`
                                : `Expand ${group.title}`
                        }
                        title={
                            expanded
                                ? `Collapse ${group.title}`
                                : `Expand ${group.title}`
                        }
                        onClick={() => setExpanded((current) => !current)}
                    >
                        {expanded ? (
                            <ChevronUp className="size-4" aria-hidden="true" />
                        ) : (
                            <ChevronDown
                                className="size-4"
                                aria-hidden="true"
                            />
                        )}
                    </Button>
                </div>
            </div>

            <div id={groupContentId} hidden={!expanded}>
                <form onSubmit={submit} className="space-y-4 p-4">
                    {group.key === 'microsoft_graph' ? (
                        <RedirectUriPanel value={microsoftRedirectUri} />
                    ) : null}

                    <div className="grid gap-4 lg:grid-cols-2">
                        {group.fields.map((field) => (
                            <SettingFieldControl
                                key={field.key}
                                field={field}
                                value={form.data.settings[field.key] ?? ''}
                                disabled={form.processing}
                                onChange={(value) =>
                                    form.setData(
                                        'settings',
                                        setFieldValue(
                                            form.data.settings,
                                            field.key,
                                            value,
                                        ),
                                    )
                                }
                                onReset={() => resetField(field)}
                            />
                        ))}
                    </div>

                    <InputError message={form.errors.settings} />

                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            <Save className="size-4" aria-hidden="true" />
                            Save
                        </Button>
                    </div>
                </form>

                {group.key === 'email_delivery' ? (
                    <form
                        onSubmit={sendTestEmail}
                        className="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-end"
                    >
                        <div className="grid min-w-0 flex-1 gap-2">
                            <Label htmlFor="test_email_recipient">
                                Test recipient
                            </Label>
                            <Input
                                id="test_email_recipient"
                                type="email"
                                value={testEmailForm.data.recipient}
                                onChange={(event) =>
                                    testEmailForm.setData(
                                        'recipient',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={testEmailForm.errors.recipient}
                            />
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={testEmailForm.processing}
                        >
                            <Send className="size-4" aria-hidden="true" />
                            Send test
                        </Button>
                    </form>
                ) : null}

                {group.key === 'logging_slack' ? (
                    <form
                        onSubmit={sendTestSlack}
                        className="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-end"
                    >
                        <div className="min-w-0 flex-1">
                            <InputError
                                message={testSlackForm.errors.slack_webhook}
                            />
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={testSlackForm.processing}
                        >
                            <Send className="size-4" aria-hidden="true" />
                            Send test
                        </Button>
                    </form>
                ) : null}
            </div>
        </section>
    );
}

function SettingFieldControl({
    field,
    value,
    disabled,
    onChange,
    onReset,
}: {
    field: ProjectSettingField;
    value: string;
    disabled: boolean;
    onChange: (value: string) => void;
    onReset: () => void;
}) {
    const inputId = field.key.replaceAll('.', '_');

    return (
        <div className="grid gap-2 rounded-md border p-3">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <Label htmlFor={inputId}>{field.label}</Label>
                    <div className="mt-1 text-xs break-words text-muted-foreground">
                        {field.config_path}
                    </div>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <SourceBadge field={field} />
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        disabled={field.source !== 'project' || disabled}
                        onClick={onReset}
                        aria-label={`Reset ${field.label}`}
                    >
                        <RotateCcw className="size-4" aria-hidden="true" />
                    </Button>
                </div>
            </div>

            {field.type === 'select' ? (
                <select
                    id={inputId}
                    value={value}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.value)}
                    className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {field.options.map((option) => (
                        <option key={option || 'none'} value={option}>
                            {option === '' ? 'None' : option}
                        </option>
                    ))}
                </select>
            ) : field.type === 'string_list' ? (
                <textarea
                    id={inputId}
                    rows={4}
                    value={value}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.value)}
                    className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                />
            ) : (
                <Input
                    id={inputId}
                    type={inputType(field)}
                    min={field.min ?? undefined}
                    max={field.max ?? undefined}
                    value={value}
                    disabled={disabled}
                    autoComplete="off"
                    onChange={(event) => onChange(event.target.value)}
                />
            )}

            {field.is_secret ? (
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <KeyRound className="size-3.5" aria-hidden="true" />
                    <span>
                        {field.last_four
                            ? `Stored ending ${field.last_four}`
                            : field.configured
                              ? 'Configured'
                              : 'Unset'}
                    </span>
                </div>
            ) : null}
        </div>
    );
}

function SourceBadge({ field }: { field: ProjectSettingField }) {
    if (field.source === 'project') {
        return <Badge variant="secondary">Project</Badge>;
    }

    if (field.configured) {
        return <Badge variant="outline">Config</Badge>;
    }

    return <Badge variant="outline">Unset</Badge>;
}

function RedirectUriPanel({ value }: { value: string }) {
    const copy = () => {
        if (navigator.clipboard) {
            void navigator.clipboard.writeText(value);
        }
    };

    return (
        <div className="grid gap-2 rounded-md border bg-muted/20 p-3">
            <Label htmlFor="microsoft_redirect_uri">Redirect URI</Label>
            <div className="grid gap-2 sm:grid-cols-[1fr_auto]">
                <Input id="microsoft_redirect_uri" value={value} readOnly />
                <Button
                    type="button"
                    variant="outline"
                    onClick={copy}
                    aria-label="Copy Microsoft redirect URI"
                >
                    <Copy className="size-4" aria-hidden="true" />
                    Copy
                </Button>
            </div>
        </div>
    );
}

function GroupIcon({ groupKey }: { groupKey: string }) {
    const className = 'size-4 text-muted-foreground';

    if (groupKey === 'email_delivery') {
        return <Mail className={className} aria-hidden="true" />;
    }

    if (groupKey === 'microsoft_graph') {
        return <KeyRound className={className} aria-hidden="true" />;
    }

    return <Settings2 className={className} aria-hidden="true" />;
}

function inputType(field: ProjectSettingField) {
    if (field.is_secret) {
        return 'password';
    }

    if (field.type === 'integer') {
        return 'number';
    }

    if (field.type === 'email') {
        return 'email';
    }

    if (field.type === 'url') {
        return 'url';
    }

    return 'text';
}

function setFieldValue(
    settings: Record<string, string>,
    key: string,
    value: string,
) {
    return {
        ...settings,
        [key]: value,
    };
}

ProjectSettingsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Project settings',
            href: '/admin/project-settings',
        },
    ],
};
