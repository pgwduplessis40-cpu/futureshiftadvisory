import { Head, Link } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Clock3,
    ExternalLink,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type ActivityEvent = {
    id: string;
    title: string;
    starts_at: string;
    kind: string;
    kind_label: string;
    status: string | null;
    description: string | null;
    href: string | null;
    all_day: boolean;
};

type Props = {
    title: string;
    subtitle: string;
    events: ActivityEvent[];
    emptyState: string;
};

type ViewMode = 'agenda' | 'work_week' | 'week' | 'month';

type EventGroup = {
    key: string;
    label: string;
    items: ActivityEvent[];
};

const viewLabels: Record<ViewMode, string> = {
    agenda: 'Agenda',
    work_week: 'Work week',
    week: 'Week',
    month: 'Month',
};

export default function ActivityCalendarIndex({
    title,
    subtitle,
    events,
    emptyState,
}: Props) {
    const [view, setView] = useState<ViewMode>('agenda');
    const [referenceDate, setReferenceDate] = useState<Date>(() => new Date());
    const visibleEvents = useMemo(
        () => events.filter((event) => eventInView(event, view, referenceDate)),
        [events, referenceDate, view],
    );
    const groupedEvents = useMemo(
        () => groupEvents(visibleEvents),
        [visibleEvents],
    );
    const eventsByDate = useMemo(
        () => indexEventsByDate(visibleEvents),
        [visibleEvents],
    );
    const counts = useMemo(() => {
        const now = new Date();

        return {
            total: events.length,
            upcoming: events.filter((event) => eventDate(event) >= now).length,
            deadlines: events.filter((event) => event.kind === 'deadline')
                .length,
        };
    }, [events]);
    const showDateNavigation = view !== 'agenda';

    const moveReferenceDate = (direction: -1 | 1) => {
        setReferenceDate((current) =>
            shiftReferenceDate(current, view, direction),
        );
    };

    return (
        <>
            <Head title={title} />

            <div className="space-y-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <CalendarDays
                                className="size-4"
                                aria-hidden="true"
                            />
                            Portal activity
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">{title}</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                            {subtitle}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="secondary">{counts.total} total</Badge>
                        <Badge variant="outline">
                            {counts.upcoming} upcoming
                        </Badge>
                        {counts.deadlines > 0 && (
                            <Badge variant="outline">
                                {counts.deadlines} deadlines
                            </Badge>
                        )}
                    </div>
                </header>

                <section className="space-y-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
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
                                        view === mode ? 'secondary' : 'ghost'
                                    }
                                    aria-pressed={view === mode}
                                    onClick={() => setView(mode)}
                                >
                                    {viewLabels[mode]}
                                </Button>
                            ))}
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {showDateNavigation && (
                                <>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => moveReferenceDate(-1)}
                                        aria-label={`Previous ${navigationUnitLabel(view)}`}
                                    >
                                        <ChevronLeft
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Previous
                                    </Button>
                                    <Badge
                                        variant="secondary"
                                        className="min-w-40 justify-center"
                                    >
                                        {calendarPeriodLabel(
                                            view,
                                            referenceDate,
                                        )}
                                    </Badge>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => moveReferenceDate(1)}
                                        aria-label={`Next ${navigationUnitLabel(view)}`}
                                    >
                                        Next
                                        <ChevronRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            setReferenceDate(new Date())
                                        }
                                    >
                                        <CalendarDays
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Today
                                    </Button>
                                </>
                            )}
                            <Badge variant="outline">
                                {visibleEvents.length} shown
                            </Badge>
                        </div>
                    </div>

                    {view === 'agenda' ? (
                        <AgendaEvents
                            groupedEvents={groupedEvents}
                            emptyState={emptyState}
                        />
                    ) : view === 'work_week' ? (
                        <WeekCalendar
                            referenceDate={referenceDate}
                            eventsByDate={eventsByDate}
                            workWeek
                        />
                    ) : view === 'week' ? (
                        <WeekCalendar
                            referenceDate={referenceDate}
                            eventsByDate={eventsByDate}
                        />
                    ) : (
                        <MonthCalendar
                            referenceDate={referenceDate}
                            eventsByDate={eventsByDate}
                        />
                    )}
                </section>
            </div>
        </>
    );
}

function AgendaEvents({
    groupedEvents,
    emptyState,
}: {
    groupedEvents: EventGroup[];
    emptyState: string;
}) {
    if (groupedEvents.length === 0) {
        return (
            <div className="rounded-md border p-6 text-sm text-muted-foreground">
                {emptyState}
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {groupedEvents.map((group) => (
                <div key={group.key} className="space-y-2">
                    <div className="text-sm font-medium">{group.label}</div>
                    <div className="space-y-2">
                        {group.items.map((event) => (
                            <ActivityRow key={event.id} event={event} />
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

function WeekCalendar({
    referenceDate,
    eventsByDate,
    workWeek = false,
}: {
    referenceDate: Date;
    eventsByDate: Map<string, ActivityEvent[]>;
    workWeek?: boolean;
}) {
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

            <div className="grid gap-3 md:hidden">
                {days.map((day) => {
                    const key = localDateKey(day);
                    const items = eventsByDate.get(key) ?? [];

                    return (
                        <section
                            key={key}
                            className={cn(
                                'rounded-md border bg-background p-3',
                                key === todayKey &&
                                    'border-primary/40 bg-muted/30',
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
                                <Badge
                                    variant={
                                        items.length > 0
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    {items.length}
                                </Badge>
                            </div>
                            <div className="mt-3 space-y-2">
                                {items.length === 0 ? (
                                    <div className="rounded-md border border-dashed p-3 text-xs text-muted-foreground">
                                        No activity
                                    </div>
                                ) : (
                                    items.map((event) => (
                                        <ActivityBlock
                                            key={event.id}
                                            event={event}
                                        />
                                    ))
                                )}
                            </div>
                        </section>
                    );
                })}
            </div>

            <div className="hidden overflow-x-auto rounded-md border bg-background md:block">
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
                        const items = eventsByDate.get(key) ?? [];

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
                                            No activity
                                        </div>
                                    ) : (
                                        items.map((event) => (
                                            <ActivityBlock
                                                key={event.id}
                                                event={event}
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
    eventsByDate,
}: {
    referenceDate: Date;
    eventsByDate: Map<string, ActivityEvent[]>;
}) {
    const days = buildMonthDays(referenceDate);
    const weekdayLabels = buildWeekDays(
        startOfWeek(startOfMonth(referenceDate)),
    );
    const todayKey = localDateKey(new Date());
    const mobileDays = days.filter((day) => {
        const key = localDateKey(day);

        return (
            sameMonth(day, referenceDate) &&
            (eventsByDate.get(key)?.length ?? 0) > 0
        );
    });

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-sm font-medium">
                    {formatMonthLabel(referenceDate)}
                </h2>
                <Badge variant="outline">Month view</Badge>
            </div>

            <div className="grid gap-3 md:hidden">
                {mobileDays.length === 0 ? (
                    <div className="rounded-md border border-dashed bg-background p-4 text-sm text-muted-foreground">
                        No activity this month.
                    </div>
                ) : (
                    mobileDays.map((day) => {
                        const key = localDateKey(day);
                        const items = eventsByDate.get(key) ?? [];

                        return (
                            <section
                                key={key}
                                className={cn(
                                    'rounded-md border bg-background p-3',
                                    key === todayKey &&
                                        'border-primary/40 bg-muted/30',
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
                                    <Badge variant="secondary">
                                        {items.length}
                                    </Badge>
                                </div>
                                <div className="mt-3 space-y-2">
                                    {items.map((event) => (
                                        <ActivityBlock
                                            key={event.id}
                                            event={event}
                                        />
                                    ))}
                                </div>
                            </section>
                        );
                    })
                )}
            </div>

            <div className="hidden overflow-x-auto rounded-md border bg-background md:block">
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
                            const items = eventsByDate.get(key) ?? [];
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
                                        {visibleItems.map((event) => (
                                            <ActivityBlock
                                                key={event.id}
                                                event={event}
                                                dense
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

function ActivityRow({ event }: { event: ActivityEvent }) {
    return (
        <article className="rounded-md border bg-background p-4">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0 space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-sm font-medium break-words">
                            {event.title}
                        </h3>
                        <Badge variant="secondary">{event.kind_label}</Badge>
                        {event.status && (
                            <Badge variant="outline">{event.status}</Badge>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                        <span className="inline-flex items-center gap-1">
                            <Clock3 className="size-3.5" aria-hidden="true" />
                            {formatDateTime(event)}
                        </span>
                        {event.description && <span>{event.description}</span>}
                    </div>
                </div>

                {event.href && (
                    <ActivityLink href={event.href} className="shrink-0">
                        Open
                    </ActivityLink>
                )}
            </div>
        </article>
    );
}

function ActivityBlock({
    event,
    dense = false,
}: {
    event: ActivityEvent;
    dense?: boolean;
}) {
    const body = (
        <>
            <span className="block truncate font-medium" title={event.title}>
                {event.title}
            </span>
            <span className="mt-1 flex items-center gap-1 text-muted-foreground">
                <Clock3 className="size-3" aria-hidden="true" />
                {event.all_day ? 'All day' : formatTime(event.starts_at)}
            </span>
            {!dense && (
                <span className="mt-1 block truncate text-muted-foreground">
                    {event.kind_label}
                    {event.status ? ` - ${event.status}` : ''}
                </span>
            )}
        </>
    );

    return (
        <article className="rounded-md border bg-background p-2 text-xs shadow-xs">
            {event.href ? (
                <ActivityLink href={event.href} compact>
                    {body}
                </ActivityLink>
            ) : (
                <div className="min-w-0">{body}</div>
            )}
        </article>
    );
}

function ActivityLink({
    href,
    children,
    className,
    compact = false,
}: {
    href: string;
    children: ReactNode;
    className?: string;
    compact?: boolean;
}) {
    const classes = compact
        ? 'block w-full min-w-0 text-left'
        : cn(
              'inline-flex items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm font-medium shadow-xs hover:bg-accent hover:text-accent-foreground',
              className,
          );

    if (isExternalHref(href)) {
        return (
            <a href={href} className={classes} target="_blank" rel="noreferrer">
                {children}
                {!compact && <ExternalLink className="size-4" />}
            </a>
        );
    }

    return (
        <Link href={href} className={classes}>
            {children}
            {!compact && <ExternalLink className="size-4" />}
        </Link>
    );
}

function eventInView(
    event: ActivityEvent,
    view: ViewMode,
    referenceDate: Date,
): boolean {
    if (view === 'agenda') {
        return true;
    }

    const date = eventDate(event);

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

function groupEvents(events: ActivityEvent[]): EventGroup[] {
    const groups = new Map<string, ActivityEvent[]>();

    events.forEach((event) => {
        const key = localDateKey(eventDate(event));
        groups.set(key, [...(groups.get(key) ?? []), event]);
    });

    return Array.from(groups.entries()).map(([key, items]) => ({
        key,
        label: new Intl.DateTimeFormat(undefined, {
            dateStyle: 'full',
        }).format(new Date(`${key}T00:00:00`)),
        items,
    }));
}

function indexEventsByDate(events: ActivityEvent[]) {
    const dates = new Map<string, ActivityEvent[]>();

    events.forEach((event) => {
        const key = localDateKey(eventDate(event));
        dates.set(key, [...(dates.get(key) ?? []), event]);
    });

    return dates;
}

function eventDate(event: ActivityEvent): Date {
    return new Date(event.starts_at);
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

function shiftReferenceDate(
    date: Date,
    view: ViewMode,
    direction: -1 | 1,
): Date {
    if (view === 'month') {
        return new Date(date.getFullYear(), date.getMonth() + direction, 1);
    }

    if (view === 'week' || view === 'work_week') {
        return addDays(date, direction * 7);
    }

    return date;
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

function calendarPeriodLabel(view: ViewMode, referenceDate: Date): string {
    if (view === 'month') {
        return formatMonthLabel(referenceDate);
    }

    if (view === 'work_week') {
        const days = buildWorkWeekDays(referenceDate);

        return formatWeekRange(days[0], days[days.length - 1]);
    }

    if (view === 'week') {
        const days = buildWeekDays(referenceDate);

        return formatWeekRange(days[0], days[days.length - 1]);
    }

    return 'All activity';
}

function navigationUnitLabel(view: ViewMode): string {
    if (view === 'work_week') {
        return 'work week';
    }

    return view;
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

function formatDateTime(event: ActivityEvent): string {
    const date = eventDate(event);

    if (event.all_day) {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
        }).format(date);
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function formatTime(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function isExternalHref(href: string): boolean {
    return /^https?:\/\//i.test(href);
}

ActivityCalendarIndex.layout = {
    breadcrumbs: [
        {
            title: 'Calendar',
            href: '/calendar',
        },
    ],
};
