import { Head, useForm } from '@inertiajs/react';
import { CalendarClock, MailOpen, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Communication = {
    id: string;
    title: string;
    subject: string;
    template_key: string | null;
    audience_type: string;
    status: string;
    scheduled_at: string | null;
    sent_at: string | null;
    metrics: Record<string, number | string | null>;
    recipients_count: number;
};

type ClientOption = {
    id: string;
    name: string;
};

type TemplateOption = {
    key: string;
    label: string;
};

type BulkCommunicationForm = {
    title: string;
    template_key: string;
    subject: string;
    body: string;
    audience_type: 'selected_clients' | 'all_clients';
    selected_client_ids: string[];
    scheduled_at: string;
};

type Props = {
    communications: Communication[];
    clients: ClientOption[];
    templates: TemplateOption[];
    storeUrl: string;
};

const statusLabels: Record<string, string> = {
    scheduled: 'Scheduled',
    sent: 'Sent',
};

export default function BulkCommunicationsIndex({
    communications,
    clients,
    templates,
    storeUrl,
}: Props) {
    const defaultTemplate = templates[0]?.key ?? '';
    const form = useForm<BulkCommunicationForm>({
        title: '',
        template_key: defaultTemplate,
        subject: '',
        body: '',
        audience_type: 'selected_clients',
        selected_client_ids: [],
        scheduled_at: '',
    });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset(
                    'title',
                    'subject',
                    'body',
                    'selected_client_ids',
                    'scheduled_at',
                );
            },
        });
    };

    const toggleClient = (id: string, checked: boolean) => {
        form.setData(
            'selected_client_ids',
            checked
                ? Array.from(new Set([...form.data.selected_client_ids, id]))
                : form.data.selected_client_ids.filter(
                      (clientId) => clientId !== id,
                  ),
        );
    };

    return (
        <>
            <Head title="Bulk communications" />

            <div className="space-y-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <MailOpen className="size-4" aria-hidden="true" />
                            Communications
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            Bulk communications
                        </h1>
                    </div>
                    <Badge variant="secondary">
                        {communications.length} batches
                    </Badge>
                </header>

                <form
                    onSubmit={submit}
                    className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]"
                >
                    <section className="space-y-4 rounded-md border bg-background p-4">
                        <div className="grid gap-2 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="bulk_title">Title</Label>
                                <Input
                                    id="bulk_title"
                                    value={form.data.title}
                                    onChange={(event) =>
                                        form.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bulk_template">Template</Label>
                                <select
                                    id="bulk_template"
                                    value={form.data.template_key}
                                    onChange={(event) =>
                                        form.setData(
                                            'template_key',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    {templates.map((template) => (
                                        <option
                                            key={template.key}
                                            value={template.key}
                                        >
                                            {template.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={form.errors.template_key}
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="bulk_subject">Subject</Label>
                            <Input
                                id="bulk_subject"
                                value={form.data.subject}
                                onChange={(event) =>
                                    form.setData('subject', event.target.value)
                                }
                            />
                            <InputError message={form.errors.subject} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="bulk_body">Message</Label>
                            <textarea
                                id="bulk_body"
                                value={form.data.body}
                                onChange={(event) =>
                                    form.setData('body', event.target.value)
                                }
                                rows={10}
                                className="min-h-60 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError message={form.errors.body} />
                        </div>

                        <div className="grid gap-2 sm:max-w-xs">
                            <Label htmlFor="bulk_schedule">Schedule</Label>
                            <Input
                                id="bulk_schedule"
                                type="datetime-local"
                                value={form.data.scheduled_at}
                                onChange={(event) =>
                                    form.setData(
                                        'scheduled_at',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.scheduled_at} />
                        </div>

                        <Button type="submit" disabled={form.processing}>
                            <Send className="size-4" aria-hidden="true" />
                            Schedule
                        </Button>
                    </section>

                    <section className="space-y-4 rounded-md border bg-background p-4">
                        <div className="grid grid-cols-2 gap-2">
                            <Button
                                type="button"
                                variant={
                                    form.data.audience_type ===
                                    'selected_clients'
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() =>
                                    form.setData(
                                        'audience_type',
                                        'selected_clients',
                                    )
                                }
                            >
                                Selected
                            </Button>
                            <Button
                                type="button"
                                variant={
                                    form.data.audience_type === 'all_clients'
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() =>
                                    form.setData('audience_type', 'all_clients')
                                }
                            >
                                All clients
                            </Button>
                        </div>
                        <InputError message={form.errors.audience_type} />
                        <InputError message={errors.selected_client_ids} />

                        {form.data.audience_type === 'selected_clients' && (
                            <div className="grid max-h-96 gap-3 overflow-y-auto pr-1">
                                {clients.map((client) => (
                                    <label
                                        key={client.id}
                                        htmlFor={`client_${client.id}`}
                                        className="grid cursor-pointer grid-cols-[auto_minmax(0,1fr)] gap-3 rounded-md border p-3"
                                    >
                                        <Checkbox
                                            id={`client_${client.id}`}
                                            checked={form.data.selected_client_ids.includes(
                                                client.id,
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggleClient(
                                                    client.id,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <span className="truncate text-sm font-medium">
                                            {client.name}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        )}
                    </section>
                </form>

                <section className="space-y-3">
                    <h2 className="text-sm font-medium">Recent batches</h2>
                    {communications.length === 0 ? (
                        <p className="rounded-md border px-3 py-8 text-sm text-muted-foreground">
                            No bulk communications are scheduled.
                        </p>
                    ) : (
                        <div className="grid gap-3">
                            {communications.map((communication) => (
                                <article
                                    key={communication.id}
                                    className="grid gap-3 rounded-md border p-4 md:grid-cols-[minmax(0,1fr)_auto]"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge>
                                                {statusLabels[
                                                    communication.status
                                                ] ?? communication.status}
                                            </Badge>
                                            <Badge variant="outline">
                                                {communication.audience_type.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </Badge>
                                        </div>
                                        <h3 className="mt-2 truncate text-sm font-semibold">
                                            {communication.title}
                                        </h3>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {communication.subject}
                                        </p>
                                    </div>
                                    <dl className="grid grid-cols-3 gap-3 text-right text-sm">
                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Sent
                                            </dt>
                                            <dd className="font-medium">
                                                {metric(
                                                    communication,
                                                    'sent_count',
                                                )}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Opens
                                            </dt>
                                            <dd className="font-medium">
                                                {metric(
                                                    communication,
                                                    'opens_count',
                                                )}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Rate
                                            </dt>
                                            <dd className="font-medium">
                                                {openRate(communication)}
                                            </dd>
                                        </div>
                                    </dl>
                                    <div className="flex items-center gap-2 text-xs text-muted-foreground md:col-span-2">
                                        <CalendarClock
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {communication.sent_at
                                            ? formatDate(communication.sent_at)
                                            : formatDate(
                                                  communication.scheduled_at,
                                              )}
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function metric(communication: Communication, key: string): number {
    const value = communication.metrics[key];

    return typeof value === 'number' ? value : 0;
}

function openRate(communication: Communication): string {
    const value = communication.metrics.open_rate;

    return typeof value === 'number' ? `${Math.round(value * 100)}%` : '0%';
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not scheduled';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

BulkCommunicationsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Bulk communications',
            href: '/advisor/bulk-communications',
        },
    ],
};
