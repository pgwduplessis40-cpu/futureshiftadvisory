import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    CalendarPlus,
    CheckCircle2,
    Clock3,
    Edit3,
    ExternalLink,
    PlugZap,
    RefreshCw,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type ClientOption = {
    id: string;
    name: string;
    engagement_type: string | null;
};

type CalendarProvider = {
    provider: string;
    label: string;
    connected: boolean;
    connect_url: string;
};

type CalendarMeeting = {
    id: string;
    title: string;
    status: 'scheduled' | 'cancelled';
    scheduled_at: string | null;
    location: string | null;
    link: string | null;
    attendees: string[];
    calendar_synced: boolean;
    reminder_sent_at: string | null;
    client: {
        id: string | null;
        name: string | null;
        url: string | null;
    };
    brief: {
        id: string;
        status: string;
        red_flag_count: number;
        url: string | null;
    } | null;
    update_url: string;
    cancel_url: string;
};

type MeetingForm = {
    client_id: string;
    title: string;
    scheduled_at: string;
    location: string;
    link: string;
    attendees: string;
};

type Props = {
    clients: ClientOption[];
    meetings: CalendarMeeting[];
    providers: CalendarProvider[];
    storeUrl: string;
};

type ViewMode = 'agenda' | 'week' | 'month';

const viewLabels: Record<ViewMode, string> = {
    agenda: 'Agenda',
    week: 'Week',
    month: 'Month',
};

export default function AdvisorCalendarIndex({
    clients,
    meetings,
    providers,
    storeUrl,
}: Props) {
    const [view, setView] = useState<ViewMode>('agenda');
    const [editing, setEditing] = useState<CalendarMeeting | null>(null);
    const form = useForm<MeetingForm>({
        client_id: clients[0]?.id ?? '',
        title: '',
        scheduled_at: nextHourInput(),
        location: '',
        link: '',
        attendees: '',
    });

    const visibleMeetings = useMemo(
        () => meetings.filter((meeting) => inView(meeting, view)),
        [meetings, view],
    );
    const groupedMeetings = useMemo(
        () => groupMeetings(visibleMeetings),
        [visibleMeetings],
    );
    const counts = useMemo(
        () => ({
            scheduled: meetings.filter(
                (meeting) => meeting.status === 'scheduled',
            ).length,
            cancelled: meetings.filter(
                (meeting) => meeting.status === 'cancelled',
            ).length,
            briefs: meetings.filter((meeting) => meeting.brief !== null).length,
        }),
        [meetings],
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editing) {
            form.patch(editing.update_url, {
                preserveScroll: true,
                onSuccess: resetForm,
            });

            return;
        }

        form.post(storeUrl, {
            preserveScroll: true,
            onSuccess: resetForm,
        });
    };

    const editMeeting = (meeting: CalendarMeeting) => {
        setEditing(meeting);
        form.setData({
            client_id: meeting.client.id ?? clients[0]?.id ?? '',
            title: meeting.title,
            scheduled_at: toDateTimeInput(meeting.scheduled_at),
            location: meeting.location ?? '',
            link: meeting.link ?? '',
            attendees: meeting.attendees.join(', '),
        });
    };

    const resetForm = () => {
        setEditing(null);
        form.setData({
            client_id: clients[0]?.id ?? '',
            title: '',
            scheduled_at: nextHourInput(),
            location: '',
            link: '',
            attendees: '',
        });
        form.clearErrors();
    };

    const cancelMeeting = (meeting: CalendarMeeting) => {
        if (!window.confirm('Cancel this meeting?')) {
            return;
        }

        router.delete(meeting.cancel_url, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Calendar" />

            <div className="space-y-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <CalendarDays
                                className="size-4"
                                aria-hidden="true"
                            />
                            Advisor schedule
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            Calendar
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="secondary">
                            {counts.scheduled} scheduled
                        </Badge>
                        <Badge variant="outline">{counts.briefs} briefs</Badge>
                        {counts.cancelled > 0 && (
                            <Badge variant="outline">
                                {counts.cancelled} cancelled
                            </Badge>
                        )}
                    </div>
                </header>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                    <section className="space-y-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="grid grid-cols-3 rounded-md border p-1 sm:w-80">
                                {(['agenda', 'week', 'month'] as ViewMode[]).map(
                                    (mode) => (
                                        <Button
                                            key={mode}
                                            type="button"
                                            size="sm"
                                            variant={
                                                view === mode
                                                    ? 'secondary'
                                                    : 'ghost'
                                            }
                                            onClick={() => setView(mode)}
                                        >
                                            {viewLabels[mode]}
                                        </Button>
                                    ),
                                )}
                            </div>
                            <Badge variant="outline">
                                {visibleMeetings.length} shown
                            </Badge>
                        </div>

                        {groupedMeetings.length === 0 ? (
                            <div className="rounded-md border p-6 text-sm text-muted-foreground">
                                No meetings in this view.
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {groupedMeetings.map((group) => (
                                    <div
                                        key={group.key}
                                        className="space-y-2"
                                    >
                                        <div className="text-sm font-medium">
                                            {group.label}
                                        </div>
                                        <div className="space-y-2">
                                            {group.items.map((meeting) => (
                                                <MeetingRow
                                                    key={meeting.id}
                                                    meeting={meeting}
                                                    onEdit={editMeeting}
                                                    onCancel={cancelMeeting}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>

                    <aside className="space-y-4">
                        <form
                            onSubmit={submit}
                            className="space-y-4 rounded-md border bg-background p-4"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="text-sm font-medium">
                                    {editing ? 'Edit meeting' : 'New meeting'}
                                </h2>
                                {editing && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={resetForm}
                                    >
                                        Clear
                                    </Button>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="calendar_client">Client</Label>
                                <select
                                    id="calendar_client"
                                    value={form.data.client_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'client_id',
                                            event.target.value,
                                        )
                                    }
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    {clients.map((client) => (
                                        <option
                                            key={client.id}
                                            value={client.id}
                                        >
                                            {client.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.client_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="calendar_title">Title</Label>
                                <Input
                                    id="calendar_title"
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
                                <Label htmlFor="calendar_scheduled">
                                    Scheduled
                                </Label>
                                <Input
                                    id="calendar_scheduled"
                                    type="datetime-local"
                                    value={form.data.scheduled_at}
                                    onChange={(event) =>
                                        form.setData(
                                            'scheduled_at',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.scheduled_at}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="calendar_location">
                                    Location
                                </Label>
                                <Input
                                    id="calendar_location"
                                    value={form.data.location}
                                    onChange={(event) =>
                                        form.setData(
                                            'location',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.location} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="calendar_link">Link</Label>
                                <Input
                                    id="calendar_link"
                                    value={form.data.link}
                                    onChange={(event) =>
                                        form.setData('link', event.target.value)
                                    }
                                />
                                <InputError message={form.errors.link} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="calendar_attendees">
                                    Attendees
                                </Label>
                                <Input
                                    id="calendar_attendees"
                                    value={form.data.attendees}
                                    onChange={(event) =>
                                        form.setData(
                                            'attendees',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.attendees} />
                            </div>

                            <Button
                                type="submit"
                                disabled={form.processing || clients.length === 0}
                            >
                                {editing ? (
                                    <RefreshCw
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <CalendarPlus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                                {editing ? 'Update' : 'Create'}
                            </Button>
                        </form>

                        <section className="space-y-3 rounded-md border bg-background p-4">
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="text-sm font-medium">
                                    Optional sync
                                </h2>
                                <Badge variant="outline">Add-on</Badge>
                            </div>
                            <div className="grid gap-2">
                                {providers.map((provider) => (
                                    <div
                                        key={provider.provider}
                                        className="flex items-center justify-between gap-3 rounded-md border p-3"
                                    >
                                        <div className="min-w-0">
                                            <div className="text-sm font-medium">
                                                {provider.label}
                                            </div>
                                            <Badge
                                                variant={
                                                    provider.connected
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                                className="mt-1"
                                            >
                                                {provider.connected
                                                    ? 'Connected'
                                                    : 'Off'}
                                            </Badge>
                                        </div>
                                        {provider.connected ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link href="/settings/calendar">
                                                    <CheckCircle2
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Manage
                                                </Link>
                                            </Button>
                                        ) : (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <a href={provider.connect_url}>
                                                    <PlugZap
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Connect
                                                </a>
                                            </Button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}

function MeetingRow({
    meeting,
    onEdit,
    onCancel,
}: {
    meeting: CalendarMeeting;
    onEdit: (meeting: CalendarMeeting) => void;
    onCancel: (meeting: CalendarMeeting) => void;
}) {
    const cancelled = meeting.status === 'cancelled';

    return (
        <article
            className={cn(
                'rounded-md border bg-background p-4',
                cancelled && 'opacity-65',
            )}
        >
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0 space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-sm font-medium break-words">
                            {meeting.title}
                        </h3>
                        <Badge variant={cancelled ? 'outline' : 'secondary'}>
                            {meeting.status}
                        </Badge>
                        {meeting.calendar_synced && (
                            <Badge variant="outline">Synced</Badge>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                        <span className="inline-flex items-center gap-1">
                            <Clock3 className="size-3.5" aria-hidden="true" />
                            {formatDateTime(meeting.scheduled_at)}
                        </span>
                        {meeting.client.url ? (
                            <Link
                                href={meeting.client.url}
                                className="inline-flex items-center gap-1 text-foreground underline-offset-4 hover:underline"
                            >
                                {meeting.client.name ?? 'Client'}
                                <ExternalLink
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                            </Link>
                        ) : (
                            <span>{meeting.client.name ?? 'Client'}</span>
                        )}
                        {meeting.location && <span>{meeting.location}</span>}
                        {meeting.attendees.length > 0 && (
                            <span>{meeting.attendees.length} attendees</span>
                        )}
                    </div>
                    {meeting.brief && (
                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <Badge variant="outline">
                                Brief {meeting.brief.status}
                            </Badge>
                            {meeting.brief.red_flag_count > 0 && (
                                <Badge variant="destructive">
                                    {meeting.brief.red_flag_count} red flags
                                </Badge>
                            )}
                        </div>
                    )}
                </div>

                {!cancelled && (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => onEdit(meeting)}
                        >
                            <Edit3 className="size-4" aria-hidden="true" />
                            Edit
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => onCancel(meeting)}
                        >
                            <Trash2 className="size-4" aria-hidden="true" />
                            Cancel
                        </Button>
                    </div>
                )}
            </div>
        </article>
    );
}

function inView(meeting: CalendarMeeting, view: ViewMode): boolean {
    if (view === 'agenda') {
        return true;
    }

    if (!meeting.scheduled_at) {
        return false;
    }

    const date = new Date(meeting.scheduled_at);
    const now = new Date();

    if (view === 'week') {
        const start = startOfWeek(now);
        const end = new Date(start);
        end.setDate(start.getDate() + 7);

        return date >= start && date < end;
    }

    return (
        date.getFullYear() === now.getFullYear() &&
        date.getMonth() === now.getMonth()
    );
}

function groupMeetings(meetings: CalendarMeeting[]) {
    const groups = new Map<string, CalendarMeeting[]>();

    meetings.forEach((meeting) => {
        const key = meeting.scheduled_at
            ? new Date(meeting.scheduled_at).toISOString().slice(0, 10)
            : 'unscheduled';
        groups.set(key, [...(groups.get(key) ?? []), meeting]);
    });

    return Array.from(groups.entries()).map(([key, items]) => ({
        key,
        label:
            key === 'unscheduled'
                ? 'Unscheduled'
                : new Intl.DateTimeFormat(undefined, {
                      dateStyle: 'full',
                  }).format(new Date(`${key}T00:00:00`)),
        items,
    }));
}

function startOfWeek(date: Date): Date {
    const start = new Date(date);
    const day = (start.getDay() + 6) % 7;
    start.setDate(start.getDate() - day);
    start.setHours(0, 0, 0, 0);

    return start;
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return 'Not scheduled';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function toDateTimeInput(value: string | null): string {
    if (!value) {
        return nextHourInput();
    }

    const date = new Date(value);
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);

    return local.toISOString().slice(0, 16);
}

function nextHourInput(): string {
    const date = new Date();
    date.setHours(date.getHours() + 1, 0, 0, 0);

    return toDateTimeInput(date.toISOString());
}

AdvisorCalendarIndex.layout = {
    breadcrumbs: [
        {
            title: 'Calendar',
            href: '/advisor/calendar',
        },
    ],
};
