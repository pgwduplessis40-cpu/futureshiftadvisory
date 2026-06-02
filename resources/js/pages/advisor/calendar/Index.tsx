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
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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

type ViewMode = 'agenda' | 'work_week' | 'week' | 'month';

const viewLabels: Record<ViewMode, string> = {
    agenda: 'Agenda',
    work_week: 'Work week',
    week: 'Week',
    month: 'Month',
};

type MeetingActions = {
    onEdit: (meeting: CalendarMeeting) => void;
    onCancel: (meeting: CalendarMeeting) => void;
};

type MeetingGroup = {
    key: string;
    label: string;
    items: CalendarMeeting[];
};

export default function AdvisorCalendarIndex({
    clients,
    meetings,
    providers,
    storeUrl,
}: Props) {
    const [view, setView] = useState<ViewMode>('agenda');
    const [editing, setEditing] = useState<CalendarMeeting | null>(null);
    const referenceDate = useMemo(() => new Date(), []);
    const form = useForm<MeetingForm>({
        client_id: clients[0]?.id ?? '',
        title: '',
        scheduled_at: nextHourInput(),
        location: '',
        link: '',
        attendees: '',
    });

    const visibleMeetings = useMemo(
        () =>
            meetings.filter((meeting) => inView(meeting, view, referenceDate)),
        [meetings, referenceDate, view],
    );
    const groupedMeetings = useMemo(
        () => groupMeetings(visibleMeetings),
        [visibleMeetings],
    );
    const meetingsByDate = useMemo(
        () => indexMeetingsByDate(visibleMeetings),
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
                        <h1 className="mt-1 text-xl font-semibold">Calendar</h1>
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
                            <div className="grid grid-cols-4 rounded-md border p-1 sm:w-[420px]">
                                {(
                                    [
                                        'agenda',
                                        'work_week',
                                        'week',
                                        'month',
                                    ] as ViewMode[]
                                ).map((mode) => (
                                    <Button
                                        key={mode}
                                        type="button"
                                        size="sm"
                                        className="px-2 text-xs sm:px-3 sm:text-sm"
                                        variant={
                                            view === mode
                                                ? 'secondary'
                                                : 'ghost'
                                        }
                                        aria-pressed={view === mode}
                                        onClick={() => setView(mode)}
                                    >
                                        {viewLabels[mode]}
                                    </Button>
                                ))}
                            </div>
                            <Badge variant="outline">
                                {visibleMeetings.length} shown
                            </Badge>
                        </div>

                        {view === 'agenda' ? (
                            <AgendaMeetings
                                groupedMeetings={groupedMeetings}
                                onEdit={editMeeting}
                                onCancel={cancelMeeting}
                            />
                        ) : view === 'work_week' ? (
                            <WeekCalendar
                                referenceDate={referenceDate}
                                meetingsByDate={meetingsByDate}
                                workWeek
                                onEdit={editMeeting}
                                onCancel={cancelMeeting}
                            />
                        ) : view === 'week' ? (
                            <WeekCalendar
                                referenceDate={referenceDate}
                                meetingsByDate={meetingsByDate}
                                onEdit={editMeeting}
                                onCancel={cancelMeeting}
                            />
                        ) : (
                            <MonthCalendar
                                referenceDate={referenceDate}
                                meetingsByDate={meetingsByDate}
                                onEdit={editMeeting}
                            />
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
                                disabled={
                                    form.processing || clients.length === 0
                                }
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

function AgendaMeetings({
    groupedMeetings,
    onEdit,
    onCancel,
}: {
    groupedMeetings: MeetingGroup[];
} & MeetingActions) {
    if (groupedMeetings.length === 0) {
        return (
            <div className="rounded-md border p-6 text-sm text-muted-foreground">
                No meetings in this view.
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {groupedMeetings.map((group) => (
                <div key={group.key} className="space-y-2">
                    <div className="text-sm font-medium">{group.label}</div>
                    <div className="space-y-2">
                        {group.items.map((meeting) => (
                            <MeetingRow
                                key={meeting.id}
                                meeting={meeting}
                                onEdit={onEdit}
                                onCancel={onCancel}
                            />
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

function WeekCalendar({
    referenceDate,
    meetingsByDate,
    workWeek = false,
    onEdit,
    onCancel,
}: {
    referenceDate: Date;
    meetingsByDate: Map<string, CalendarMeeting[]>;
    workWeek?: boolean;
} & MeetingActions) {
    const days = workWeek
        ? buildWorkWeekDays(referenceDate)
        : buildWeekDays(referenceDate);
    const todayKey = localDateKey(new Date());

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-sm font-medium">
                    {formatWeekRange(days[0], days[days.length - 1])}
                </h2>
                <Badge variant="outline">
                    {workWeek ? 'Work week view' : 'Week view'}
                </Badge>
            </div>

            <div className="overflow-x-auto rounded-md border bg-background">
                <div
                    className={cn(
                        'grid divide-x',
                        workWeek
                            ? 'min-w-[620px] grid-cols-5'
                            : 'min-w-[760px] grid-cols-7',
                    )}
                >
                    {days.map((day) => {
                        const key = localDateKey(day);
                        const items = meetingsByDate.get(key) ?? [];

                        return (
                            <div
                                key={key}
                                className={cn(
                                    'min-h-80 p-3',
                                    key === todayKey && 'bg-muted/30',
                                )}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <div className="text-xs font-medium text-muted-foreground uppercase">
                                            {formatWeekday(day)}
                                        </div>
                                        <div className="mt-1 text-lg font-semibold">
                                            {formatDayNumber(day)}
                                        </div>
                                    </div>
                                    {items.length > 0 && (
                                        <Badge variant="secondary">
                                            {items.length}
                                        </Badge>
                                    )}
                                </div>

                                <div className="mt-4 space-y-2">
                                    {items.length === 0 ? (
                                        <div className="rounded-md border border-dashed p-3 text-xs text-muted-foreground">
                                            No meetings
                                        </div>
                                    ) : (
                                        items.map((meeting) => (
                                            <CalendarMeetingBlock
                                                key={meeting.id}
                                                meeting={meeting}
                                                onEdit={onEdit}
                                                onCancel={onCancel}
                                            />
                                        ))
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

function MonthCalendar({
    referenceDate,
    meetingsByDate,
    onEdit,
}: {
    referenceDate: Date;
    meetingsByDate: Map<string, CalendarMeeting[]>;
    onEdit: (meeting: CalendarMeeting) => void;
}) {
    const days = buildMonthDays(referenceDate);
    const weekdayLabels = buildWeekDays(
        startOfWeek(startOfMonth(referenceDate)),
    );
    const todayKey = localDateKey(new Date());

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-sm font-medium">
                    {formatMonthLabel(referenceDate)}
                </h2>
                <Badge variant="outline">Month view</Badge>
            </div>

            <div className="overflow-x-auto rounded-md border bg-background">
                <div className="min-w-[760px]">
                    <div className="grid grid-cols-7 border-b bg-muted/30">
                        {weekdayLabels.map((day) => (
                            <div
                                key={localDateKey(day)}
                                className="px-3 py-2 text-xs font-medium text-muted-foreground uppercase"
                            >
                                {formatWeekday(day)}
                            </div>
                        ))}
                    </div>

                    <div className="grid grid-cols-7">
                        {days.map((day, index) => {
                            const key = localDateKey(day);
                            const items = meetingsByDate.get(key) ?? [];
                            const visibleItems = items.slice(0, 3);
                            const moreCount =
                                items.length - visibleItems.length;

                            return (
                                <div
                                    key={key}
                                    className={cn(
                                        'min-h-32 border-r border-b p-2',
                                        (index + 1) % 7 === 0 && 'border-r-0',
                                        index >= 35 && 'border-b-0',
                                        !sameMonth(day, referenceDate) &&
                                            'bg-muted/20 text-muted-foreground',
                                        key === todayKey && 'bg-muted/40',
                                    )}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span
                                            className={cn(
                                                'flex size-7 items-center justify-center rounded-md text-sm font-medium',
                                                key === todayKey &&
                                                    'bg-primary text-primary-foreground',
                                            )}
                                        >
                                            {formatDayNumber(day)}
                                        </span>
                                        {items.length > 0 && (
                                            <Badge variant="secondary">
                                                {items.length}
                                            </Badge>
                                        )}
                                    </div>

                                    <div className="mt-2 space-y-1.5">
                                        {visibleItems.map((meeting) => (
                                            <CalendarMeetingBlock
                                                key={meeting.id}
                                                meeting={meeting}
                                                dense
                                                onEdit={onEdit}
                                            />
                                        ))}
                                        {moreCount > 0 && (
                                            <div className="text-xs text-muted-foreground">
                                                +{moreCount} more
                                            </div>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </div>
    );
}

function CalendarMeetingBlock({
    meeting,
    dense = false,
    onEdit,
    onCancel,
}: {
    meeting: CalendarMeeting;
    dense?: boolean;
    onEdit: (meeting: CalendarMeeting) => void;
    onCancel?: (meeting: CalendarMeeting) => void;
}) {
    const cancelled = meeting.status === 'cancelled';

    return (
        <article
            className={cn(
                'rounded-md border bg-background p-2 text-xs shadow-xs',
                cancelled && 'opacity-65',
            )}
        >
            <button
                type="button"
                className="block w-full min-w-0 text-left disabled:cursor-default"
                disabled={cancelled}
                onClick={() => onEdit(meeting)}
            >
                <span
                    className="block truncate font-medium"
                    title={meeting.title}
                >
                    {meeting.title}
                </span>
                <span className="mt-1 flex items-center gap-1 text-muted-foreground">
                    <Clock3 className="size-3" aria-hidden="true" />
                    {formatTime(meeting.scheduled_at)}
                </span>
                {!dense && meeting.client.name && (
                    <span
                        className="mt-1 block truncate text-muted-foreground"
                        title={meeting.client.name}
                    >
                        {meeting.client.name}
                    </span>
                )}
            </button>

            {meeting.brief?.red_flag_count ? (
                <Badge
                    variant="destructive"
                    className={cn('mt-2', dense && 'px-1.5 text-[10px]')}
                >
                    {meeting.brief.red_flag_count} red flags
                </Badge>
            ) : null}

            {!cancelled && !dense && onCancel && (
                <div className="mt-2 flex gap-1">
                    <IconActionButton
                        label="Edit meeting"
                        onClick={() => onEdit(meeting)}
                    >
                        <Edit3 className="size-3.5" aria-hidden="true" />
                    </IconActionButton>
                    <IconActionButton
                        label="Cancel meeting"
                        onClick={() => onCancel(meeting)}
                    >
                        <Trash2 className="size-3.5" aria-hidden="true" />
                    </IconActionButton>
                </div>
            )}
        </article>
    );
}

function IconActionButton({
    label,
    children,
    onClick,
}: {
    label: string;
    children: ReactNode;
    onClick: () => void;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    className="size-7"
                    aria-label={label}
                    onClick={onClick}
                >
                    {children}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
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

function inView(
    meeting: CalendarMeeting,
    view: ViewMode,
    referenceDate: Date,
): boolean {
    if (view === 'agenda') {
        return true;
    }

    const date = meetingDate(meeting);

    if (!date) {
        return false;
    }

    if (view === 'week' || view === 'work_week') {
        const start = startOfWeek(referenceDate);
        const end = addDays(start, view === 'work_week' ? 5 : 7);

        return date >= start && date < end;
    }

    return (
        date.getFullYear() === referenceDate.getFullYear() &&
        date.getMonth() === referenceDate.getMonth()
    );
}

function groupMeetings(meetings: CalendarMeeting[]): MeetingGroup[] {
    const groups = new Map<string, CalendarMeeting[]>();

    meetings.forEach((meeting) => {
        const date = meetingDate(meeting);
        const key = date ? localDateKey(date) : 'unscheduled';
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

function indexMeetingsByDate(meetings: CalendarMeeting[]) {
    const dates = new Map<string, CalendarMeeting[]>();

    meetings.forEach((meeting) => {
        const date = meetingDate(meeting);

        if (!date) {
            return;
        }

        const key = localDateKey(date);
        dates.set(key, [...(dates.get(key) ?? []), meeting]);
    });

    return dates;
}

function meetingDate(meeting: CalendarMeeting): Date | null {
    if (!meeting.scheduled_at) {
        return null;
    }

    const date = new Date(meeting.scheduled_at);

    return Number.isNaN(date.getTime()) ? null : date;
}

function startOfWeek(date: Date): Date {
    const start = new Date(date);
    const day = (start.getDay() + 6) % 7;
    start.setDate(start.getDate() - day);
    start.setHours(0, 0, 0, 0);

    return start;
}

function startOfMonth(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), 1);
}

function buildWeekDays(referenceDate: Date): Date[] {
    const start = startOfWeek(referenceDate);

    return Array.from({ length: 7 }, (_, index) => addDays(start, index));
}

function buildWorkWeekDays(referenceDate: Date): Date[] {
    const start = startOfWeek(referenceDate);

    return Array.from({ length: 5 }, (_, index) => addDays(start, index));
}

function buildMonthDays(referenceDate: Date): Date[] {
    const start = startOfWeek(startOfMonth(referenceDate));

    return Array.from({ length: 42 }, (_, index) => addDays(start, index));
}

function addDays(date: Date, days: number): Date {
    const next = new Date(date);
    next.setDate(next.getDate() + days);

    return next;
}

function sameMonth(left: Date, right: Date): boolean {
    return (
        left.getFullYear() === right.getFullYear() &&
        left.getMonth() === right.getMonth()
    );
}

function localDateKey(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function formatWeekday(date: Date): string {
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
    }).format(date);
}

function formatDayNumber(date: Date): string {
    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
    }).format(date);
}

function formatMonthLabel(date: Date): string {
    return new Intl.DateTimeFormat(undefined, {
        month: 'long',
        year: 'numeric',
    }).format(date);
}

function formatWeekRange(start: Date, end: Date): string {
    const startOptions: Intl.DateTimeFormatOptions =
        start.getFullYear() === end.getFullYear()
            ? { month: 'short', day: 'numeric' }
            : { month: 'short', day: 'numeric', year: 'numeric' };

    return `${new Intl.DateTimeFormat(undefined, startOptions).format(
        start,
    )} - ${new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(end)}`;
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return 'Not scheduled';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Not scheduled';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function formatTime(value: string | null): string {
    if (!value) {
        return 'No time';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'No time';
    }

    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
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
