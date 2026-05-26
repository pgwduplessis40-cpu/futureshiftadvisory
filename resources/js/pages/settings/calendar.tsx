import { Head, router } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    PlugZap,
    RefreshCw,
    Unplug,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type CalendarProvider = {
    provider: string;
    label: string;
    connected: boolean;
    connect_url: string;
};

type CalendarConnection = {
    id: string;
    provider: string;
    label: string;
    external_account_email: string | null;
    status: string;
    token_expires_at: string | null;
    last_synced_at: string | null;
    sync_url: string;
    revoke_url: string;
};

type ExternalEvent = {
    id: string;
    provider: string | null;
    provider_label: string | null;
    title: string | null;
    starts_at: string | null;
    ends_at: string | null;
    location: string | null;
    attendees: string[];
};

type Props = {
    providers: CalendarProvider[];
    connections: CalendarConnection[];
    externalEvents: ExternalEvent[];
};

export default function CalendarSettings({
    providers,
    connections,
    externalEvents,
}: Props) {
    const sync = (connection: CalendarConnection) => {
        router.post(connection.sync_url, {}, { preserveScroll: true });
    };

    const revoke = (connection: CalendarConnection) => {
        router.patch(connection.revoke_url, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Calendar settings" />

            <div className="space-y-8">
                <Heading
                    variant="small"
                    title="Calendar"
                    description="Connect advisor calendars and keep meetings in sync"
                />

                <section className="space-y-3">
                    <h2 className="text-sm font-medium">Providers</h2>
                    <div className="grid gap-3">
                        {providers.map((provider) => (
                            <div
                                key={provider.provider}
                                className="flex flex-col gap-3 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="min-w-0 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="text-sm font-medium">
                                            {provider.label}
                                        </p>
                                        <Badge
                                            variant={
                                                provider.connected
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {provider.connected
                                                ? 'Connected'
                                                : 'Available'}
                                        </Badge>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {provider.connected
                                            ? 'Ready for two-way meeting sync.'
                                            : 'Use OAuth to connect this provider.'}
                                    </p>
                                </div>
                                <Button size="sm" asChild>
                                    <a href={provider.connect_url}>
                                        <PlugZap
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Connect
                                    </a>
                                </Button>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-sm font-medium">Connections</h2>
                        <Badge variant="outline">{connections.length}</Badge>
                    </div>

                    {connections.length === 0 ? (
                        <p className="rounded-md border p-4 text-sm text-muted-foreground">
                            No calendars connected yet.
                        </p>
                    ) : (
                        <div className="divide-y rounded-md border">
                            {connections.map((connection) => (
                                <div
                                    key={connection.id}
                                    className="space-y-4 p-4"
                                >
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="min-w-0 space-y-2">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="text-sm font-medium">
                                                    {connection.label}
                                                </p>
                                                <Badge
                                                    variant={statusVariant(
                                                        connection.status,
                                                    )}
                                                >
                                                    {formatLabel(
                                                        connection.status,
                                                    )}
                                                </Badge>
                                            </div>
                                            <p className="text-sm break-words text-muted-foreground">
                                                {connection.external_account_email ??
                                                    'No account email returned'}
                                            </p>
                                            <div className="grid gap-1 text-xs text-muted-foreground sm:grid-cols-2">
                                                <span>
                                                    Last sync:{' '}
                                                    {formatDateTime(
                                                        connection.last_synced_at,
                                                    )}
                                                </span>
                                                <span>
                                                    Token expires:{' '}
                                                    {formatDateTime(
                                                        connection.token_expires_at,
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => sync(connection)}
                                            >
                                                <RefreshCw
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Sync
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    revoke(connection)
                                                }
                                            >
                                                <Unplug
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Disconnect
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>

                <section className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="text-sm font-medium">
                            Synced external events
                        </h2>
                        <Badge variant="outline">{externalEvents.length}</Badge>
                    </div>

                    {externalEvents.length === 0 ? (
                        <p className="rounded-md border p-4 text-sm text-muted-foreground">
                            No external-only events pulled from calendars.
                        </p>
                    ) : (
                        <div className="divide-y rounded-md border">
                            {externalEvents.map((event) => (
                                <div
                                    key={event.id}
                                    className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <CalendarClock
                                                className="size-4 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            <p className="text-sm font-medium">
                                                {event.title ??
                                                    'External event'}
                                            </p>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {[
                                                formatDateTime(event.starts_at),
                                                event.location,
                                                event.attendees.length > 0
                                                    ? `${event.attendees.length} attendees`
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' - ')}
                                        </p>
                                    </div>
                                    <Badge variant="secondary">
                                        <CheckCircle2
                                            className="size-3"
                                            aria-hidden="true"
                                        />
                                        {event.provider_label ?? event.provider}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function formatDateTime(value: string | null): string {
    if (value === null) {
        return 'Not yet';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatLabel(value: string): string {
    return value.replaceAll('_', ' ');
}

function statusVariant(
    value: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (value === 'connected') {
        return 'secondary';
    }

    if (value === 'error') {
        return 'destructive';
    }

    return 'outline';
}

CalendarSettings.layout = {
    breadcrumbs: [
        {
            title: 'Calendar settings',
            href: '/settings/calendar',
        },
    ],
};
