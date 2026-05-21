import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock3,
    PlugZap,
    ShieldAlert,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';

type HealthLevel = 'green' | 'amber' | 'red';

type ServiceHealth = {
    id: string;
    service: string;
    health: HealthLevel;
    success_rate: number;
    p95_latency_ms: number | null;
    window_start: string | null;
    window_end: string | null;
    lag_seconds: number | null;
    fresh: boolean;
};

type HealthAlert = {
    id: string;
    service: string;
    stuck_started_at: string | null;
    last_red_window_end: string | null;
    notified_at: string | null;
};

type Props = {
    summary: {
        total: number;
        green: number;
        amber: number;
        red: number;
        stale: number;
    };
    services: ServiceHealth[];
    recentAlerts: HealthAlert[];
    generatedAt: string;
};

export default function IntegrationHealthIndex({
    summary,
    services,
    recentAlerts,
    generatedAt,
}: Props) {
    return (
        <>
            <Head title="API health" />

            <div className="space-y-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <PlugZap className="size-4" aria-hidden="true" />
                            Integration monitoring
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            API health
                        </h1>
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-5">
                        <Metric label="Services" value={summary.total} />
                        <Metric label="Green" value={summary.green} />
                        <Metric label="Amber" value={summary.amber} />
                        <Metric label="Red" value={summary.red} />
                        <Metric label="Stale" value={summary.stale} />
                    </div>
                </header>

                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <Clock3 className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">
                                Latest rollups
                            </h2>
                        </div>
                        <span className="text-xs text-muted-foreground">
                            {formatDate(generatedAt)}
                        </span>
                    </div>

                    {services.length === 0 ? (
                        <p className="rounded-md border px-3 py-8 text-sm text-muted-foreground">
                            No integration health samples recorded.
                        </p>
                    ) : (
                        <div className="overflow-hidden rounded-md border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/60 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            Service
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Health
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Success
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            P95
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Lag
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {services.map((service) => (
                                        <tr
                                            key={service.id}
                                            className="border-t"
                                        >
                                            <td className="px-3 py-2">
                                                <div className="font-medium">
                                                    {service.service}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {formatDate(
                                                        service.window_end,
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-3 py-2">
                                                <Badge
                                                    variant={healthVariant(
                                                        service.health,
                                                    )}
                                                >
                                                    <HealthIcon
                                                        health={service.health}
                                                    />
                                                    {service.health}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-2">
                                                {formatPercent(
                                                    service.success_rate,
                                                )}
                                            </td>
                                            <td className="px-3 py-2">
                                                {service.p95_latency_ms === null
                                                    ? 'n/a'
                                                    : `${service.p95_latency_ms}ms`}
                                            </td>
                                            <td className="px-3 py-2">
                                                <Badge
                                                    variant={
                                                        service.fresh
                                                            ? 'outline'
                                                            : 'destructive'
                                                    }
                                                >
                                                    {formatLag(
                                                        service.lag_seconds,
                                                    )}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex items-center gap-2">
                        <ShieldAlert className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">
                            Stuck-red alerts
                        </h2>
                    </div>

                    {recentAlerts.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No stuck-red alerts have fired.
                        </p>
                    ) : (
                        <div className="divide-y rounded-md border">
                            {recentAlerts.map((alert) => (
                                <div
                                    key={alert.id}
                                    className="grid gap-2 p-3 sm:grid-cols-[1fr_auto]"
                                >
                                    <div>
                                        <div className="text-sm font-medium">
                                            {alert.service}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            Since{' '}
                                            {formatDate(alert.stuck_started_at)}
                                        </div>
                                    </div>
                                    <div className="text-sm text-muted-foreground sm:text-right">
                                        {formatDate(alert.notified_at)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-md border bg-background px-4 py-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-lg font-semibold">{value}</div>
        </div>
    );
}

function HealthIcon({ health }: { health: HealthLevel }) {
    if (health === 'green') {
        return <CheckCircle2 className="size-3" aria-hidden="true" />;
    }

    return <AlertTriangle className="size-3" aria-hidden="true" />;
}

function healthVariant(
    health: HealthLevel,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (health === 'green') {
        return 'secondary';
    }

    if (health === 'amber') {
        return 'outline';
    }

    return 'destructive';
}

function formatPercent(value: number): string {
    return `${Math.round(value * 1000) / 10}%`;
}

function formatLag(value: number | null): string {
    if (value === null) {
        return 'unknown';
    }

    if (value < 60) {
        return `${value}s`;
    }

    return `${Math.floor(value / 60)}m`;
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

IntegrationHealthIndex.layout = {
    breadcrumbs: [
        {
            title: 'API health',
            href: '/admin/integration-health',
        },
    ],
};
