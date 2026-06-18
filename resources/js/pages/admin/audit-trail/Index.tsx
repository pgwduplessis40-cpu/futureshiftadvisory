import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarDays,
    History,
    RotateCcw,
    Search,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type AuditActor = {
    label: string;
    role: string | null;
    user_key: string | null;
    user_id: string | null;
    name: string | null;
    email: string | null;
    user_type: string | null;
};

type AuditEvent = {
    id: string;
    occurred_at: string | null;
    occurred_at_label: string | null;
    action: string;
    actor: AuditActor;
    client_id: string | null;
    subject_type: string | null;
    subject_id: string | null;
    before: unknown;
    after: unknown;
    ip: string | null;
    user_agent: string | null;
    request_id: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedEvents = {
    data: AuditEvent[];
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

type Filters = {
    q: string;
    action: string;
    actor: string;
    subject: string;
    date_from: string;
    date_to: string;
};

type Props = {
    events: PaginatedEvents;
    filters: Filters;
};

export default function AuditTrailIndex({ events, filters }: Props) {
    const [form, setForm] = useState<Filters>(filters);

    function submit(event: FormEvent) {
        event.preventDefault();

        router.get('/admin/audit-trail', compactFilters(form), {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function clearFilters() {
        const empty = {
            q: '',
            action: '',
            actor: '',
            subject: '',
            date_from: '',
            date_to: '',
        };

        setForm(empty);
        router.get('/admin/audit-trail', {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Audit trail" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Administration"
                    icon={History}
                    title="Audit trail"
                    description="Immutable record of platform activity."
                    actions={
                        <Badge variant="outline">
                            <ShieldCheck
                                className="size-3"
                                aria-hidden="true"
                            />
                            {events.total} events
                        </Badge>
                    }
                />

                <form
                    onSubmit={submit}
                    className="grid gap-3 rounded-md border bg-background p-4 lg:grid-cols-[1.4fr_1fr_1fr_1fr_auto_auto]"
                >
                    <Field label="Search" htmlFor="audit_q">
                        <Input
                            id="audit_q"
                            value={form.q}
                            onChange={(event) =>
                                setForm({ ...form, q: event.target.value })
                            }
                        />
                    </Field>
                    <Field label="Action" htmlFor="audit_action">
                        <Input
                            id="audit_action"
                            value={form.action}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    action: event.target.value,
                                })
                            }
                        />
                    </Field>
                    <Field label="Actor" htmlFor="audit_actor">
                        <Input
                            id="audit_actor"
                            value={form.actor}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    actor: event.target.value,
                                })
                            }
                        />
                    </Field>
                    <Field label="Subject" htmlFor="audit_subject">
                        <Input
                            id="audit_subject"
                            value={form.subject}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    subject: event.target.value,
                                })
                            }
                        />
                    </Field>
                    <Field label="From" htmlFor="audit_date_from">
                        <Input
                            id="audit_date_from"
                            type="date"
                            value={form.date_from}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    date_from: event.target.value,
                                })
                            }
                        />
                    </Field>
                    <Field label="To" htmlFor="audit_date_to">
                        <Input
                            id="audit_date_to"
                            type="date"
                            value={form.date_to}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    date_to: event.target.value,
                                })
                            }
                        />
                    </Field>

                    <div className="flex gap-2 lg:col-span-6">
                        <Button type="submit">
                            <Search className="size-4" aria-hidden="true" />
                            Search
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={clearFilters}
                        >
                            <RotateCcw className="size-4" aria-hidden="true" />
                            Reset
                        </Button>
                    </div>
                </form>

                <div className="overflow-hidden rounded-md border bg-background">
                    <table className="fsa-responsive-table">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Time</th>
                                <th className="px-3 py-2 font-medium">
                                    Action
                                </th>
                                <th className="px-3 py-2 font-medium">Actor</th>
                                <th className="px-3 py-2 font-medium">
                                    Subject
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Request
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Details
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {events.data.length > 0 ? (
                                events.data.map((event) => (
                                    <AuditEventRow
                                        key={event.id}
                                        event={event}
                                    />
                                ))
                            ) : (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-3 py-10 text-center text-sm text-muted-foreground"
                                    >
                                        No audit events match the current
                                        filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination events={events} />
            </div>
        </>
    );
}

function AuditEventRow({ event }: { event: AuditEvent }) {
    return (
        <tr className="border-t align-top">
            <td className="px-3 py-3" data-label="Time">
                <div className="flex items-center gap-2 text-sm whitespace-nowrap">
                    <CalendarDays
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    {event.occurred_at_label ?? 'Unknown'}
                </div>
            </td>
            <td className="px-3 py-3" data-label="Action">
                <div className="space-y-1">
                    <div className="font-medium">{event.action}</div>
                    {event.client_id ? (
                        <div className="text-xs break-all text-muted-foreground">
                            Client {event.client_id}
                        </div>
                    ) : null}
                </div>
            </td>
            <td className="px-3 py-3" data-label="Actor">
                <div className="space-y-1">
                    <div className="flex items-center gap-2 font-medium">
                        <UserRound
                            className="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span>{event.actor.label}</span>
                    </div>
                    <div className="flex flex-wrap gap-1">
                        {event.actor.role ? (
                            <Badge variant="outline">{event.actor.role}</Badge>
                        ) : null}
                        {event.actor.user_key ? (
                            <Badge variant="secondary">
                                User {event.actor.user_key}
                            </Badge>
                        ) : null}
                    </div>
                </div>
            </td>
            <td className="px-3 py-3" data-label="Subject">
                <div className="max-w-64 space-y-1">
                    <div>{event.subject_type ?? 'None'}</div>
                    {event.subject_id ? (
                        <div className="text-xs break-all text-muted-foreground">
                            {event.subject_id}
                        </div>
                    ) : null}
                </div>
            </td>
            <td className="px-3 py-3" data-label="Request">
                <div className="max-w-56 space-y-1 text-xs text-muted-foreground">
                    {event.request_id ? (
                        <div className="break-all">{event.request_id}</div>
                    ) : null}
                    {event.ip ? <div>{event.ip}</div> : null}
                    {event.user_agent ? (
                        <div className="line-clamp-2 break-words">
                            {event.user_agent}
                        </div>
                    ) : null}
                </div>
            </td>
            <td className="px-3 py-3" data-label="Details">
                <div className="min-w-64 space-y-2">
                    <JsonPanel label="Before" value={event.before} />
                    <JsonPanel label="After" value={event.after} />
                </div>
            </td>
        </tr>
    );
}

function Field({
    label,
    htmlFor,
    children,
}: {
    label: string;
    htmlFor: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
        </div>
    );
}

function JsonPanel({ label, value }: { label: string; value: unknown }) {
    if (value === null || typeof value === 'undefined') {
        return null;
    }

    return (
        <details className="rounded-md border bg-muted/20 p-2 text-xs">
            <summary className="cursor-pointer font-medium">{label}</summary>
            <pre className="mt-2 max-h-56 overflow-auto rounded-sm bg-background p-2 break-words whitespace-pre-wrap">
                {JSON.stringify(value, null, 2)}
            </pre>
        </details>
    );
}

function Pagination({ events }: { events: PaginatedEvents }) {
    if (events.links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-muted-foreground">
                Showing {events.from ?? 0} to {events.to ?? 0} of {events.total}
            </p>
            <div className="flex flex-wrap gap-2">
                {events.links.map((link, index) =>
                    link.url ? (
                        <Button
                            key={`${link.label}-${index}`}
                            asChild
                            size="sm"
                            variant={link.active ? 'default' : 'outline'}
                        >
                            <Link href={link.url}>
                                {cleanLinkLabel(link.label)}
                            </Link>
                        </Button>
                    ) : (
                        <span
                            key={`${link.label}-${index}`}
                            className={cn(
                                'inline-flex h-8 items-center rounded-md border px-3 text-sm text-muted-foreground opacity-60',
                            )}
                        >
                            {cleanLinkLabel(link.label)}
                        </span>
                    ),
                )}
            </div>
        </div>
    );
}

function compactFilters(filters: Filters) {
    return Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value.trim() !== ''),
    );
}

function cleanLinkLabel(label: string) {
    return label.replace('&laquo;', 'Previous').replace('&raquo;', 'Next');
}

AuditTrailIndex.layout = {
    breadcrumbs: [
        {
            title: 'Audit trail',
            href: '/admin/audit-trail',
        },
    ],
};
