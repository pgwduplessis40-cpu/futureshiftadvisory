import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type HealthLevel = 'green' | 'amber' | 'red';
type HealthIssue = {
    code: string;
    reason: string;
    action: string;
    http_status: number | null;
    request_id: string | null;
    occurred_at: string | null;
};
type Diagnostics = {
    availability: HealthLevel;
    response_time: HealthLevel | 'unknown';
    green_success_rate: number;
    amber_success_rate: number;
    green_latency_ms: number;
    amber_latency_ms: number;
    latency_red_min_samples: number;
    event_count: number;
    success_count: number;
    retry_count: number;
    failure_count: number;
    fallback_count: number;
    cached_count: number;
    latest_issue: HealthIssue | null;
};
export type ServiceHealth = {
    id: string;
    service: string;
    label: string;
    health: HealthLevel;
    display_health: HealthLevel | 'stale' | 'unknown';
    success_rate: number;
    p95_latency_ms: number | null;
    window_start: string | null;
    window_end: string | null;
    lag_seconds: number | null;
    fresh: boolean;
    diagnostics: Diagnostics | null;
};

export function HealthChecks({
    services,
    refreshing,
    onRefresh,
    credentialsUrl,
}: {
    services: ServiceHealth[];
    refreshing: boolean;
    onRefresh: () => void;
    credentialsUrl: string | null;
}) {
    return (
        <section
            className="space-y-4 rounded-md border bg-background p-4"
            aria-label="Integration health checks"
        >
            <div>
                <h2 className="text-sm font-medium">
                    Latest checks: reason and next action
                </h2>
                <p className="mt-1 text-xs text-muted-foreground">
                    Refresh checks Anthropic billing and summarises recent
                    activity. AI availability updates after an AI request;
                    refreshing does not run an assessment.
                </p>
            </div>
            {services.length === 0 && (
                <p>
                    No integration activity has been recorded yet. Use an
                    integration, then refresh to see its result.
                </p>
            )}
            {services.map((service) => (
                <article
                    className="space-y-3 rounded-md border p-4"
                    key={service.id}
                    aria-label={service.label}
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h3 className="font-medium">{service.label}</h3>
                        <Badge
                            variant={
                                service.display_health === 'red'
                                    ? 'destructive'
                                    : 'outline'
                            }
                        >
                            {statusLabel(service.display_health)}
                        </Badge>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Window: {date(service.window_start)} to{' '}
                        {date(service.window_end)}.{' '}
                        {service.diagnostics === null
                            ? 'Event count unavailable.'
                            : service.diagnostics.event_count +
                              ' recorded events (including retries and fallbacks).'}
                    </p>
                    {!service.fresh && (
                        <div className="rounded-md border p-3 text-sm">
                            <p className="font-medium">
                                Current health is unknown: this observation is
                                more than five minutes old.
                            </p>
                            <p>
                                No recent activity may simply mean the service
                                has not been used. Check when it was last used;
                                if activity is expected, ask the developer to
                                check request logging and the monitoring
                                scheduler.
                            </p>
                            <p className="mt-1 text-xs">
                                Last recorded health: {service.health}. The
                                details below describe that earlier window.
                            </p>
                        </div>
                    )}
                    {service.diagnostics === null ? (
                        <div className="text-sm">
                            <p>
                                Detailed reasons were not recorded for this
                                earlier sample (recorded health:{' '}
                                {service.health}).
                            </p>
                            {service.service === 'anthropic' && (
                                <p>
                                    Earlier Anthropic samples combined AI
                                    requests and billing checks.
                                </p>
                            )}
                            <p className="mt-1 font-medium">
                                Next action: refresh to summarise recent
                                activity. If there is no recent activity, the
                                next use of this integration will provide a new
                                observation.
                            </p>
                        </div>
                    ) : (
                        <CheckDetails
                            service={service}
                            diagnostics={service.diagnostics}
                            credentialsUrl={credentialsUrl}
                        />
                    )}
                    {service.service === 'anthropic_admin' && (
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={refreshing}
                            onClick={onRefresh}
                        >
                            Recheck billing sync
                        </Button>
                    )}
                </article>
            ))}
        </section>
    );
}

function CheckDetails({
    service,
    diagnostics: d,
    credentialsUrl,
}: {
    service: ServiceHealth;
    diagnostics: Diagnostics;
    credentialsUrl: string | null;
}) {
    const issue = d.latest_issue;
    const slow = d.response_time === 'amber' || d.response_time === 'red';

    return (
        <>
            {slow && (
                <div className="text-sm">
                    <p className="font-medium">
                        Reason: response time exceeded the target.
                    </p>
                    <p>
                        Next action:{' '}
                        {service.service === 'anthropic'
                            ? 'If assessments are taking too long, ask the developer to review prompt size, output length and the configured AI response-time targets. Keep monitoring whether requests complete.'
                            : 'Ask the developer to review recent request timings and provider status. If delays continue, investigate network and provider response times.'}
                    </p>
                </div>
            )}
            {d.response_time === 'unknown' && (
                <p className="text-sm">
                    {issue === null
                        ? 'Response-time evidence is missing. Ask the developer to check latency recording if this continues after new requests.'
                        : 'Response time was not measured for this event. Resolve the issue below before rechecking.'}
                </p>
            )}
            {issue !== null && (
                <div className="space-y-1 text-sm">
                    <p className="font-medium">
                        Latest issue in this window: {issue.reason}
                    </p>
                    <p>Next action: {issue.action}</p>
                    <p className="text-xs text-muted-foreground">
                        Recorded {date(issue.occurred_at)}
                        {issue.http_status === null
                            ? ''
                            : ' · HTTP ' + issue.http_status}
                    </p>
                    {issue.request_id && (
                        <p className="text-xs break-all">
                            Support request ID: {issue.request_id}
                        </p>
                    )}
                    <div className="flex flex-wrap gap-3 pt-1">
                        {credentialsUrl &&
                            [
                                'credentials_missing',
                                'authentication',
                                'permission',
                            ].includes(issue.code) && (
                                <a className="underline" href={credentialsUrl}>
                                    Open integration credentials
                                </a>
                            )}
                        {service.service.startsWith('anthropic') && (
                            <a
                                className="underline"
                                href="https://status.claude.com"
                                target="_blank"
                                rel="noreferrer"
                            >
                                Check Anthropic service status
                            </a>
                        )}
                    </div>
                </div>
            )}
            <div className="grid gap-3 md:grid-cols-2">
                <div className="rounded-md bg-muted/40 p-3 text-sm">
                    <p className="font-medium">
                        Availability:{' '}
                        {d.availability === 'green'
                            ? 'Successful replies recorded'
                            : 'Requests need attention'}
                    </p>
                    <p>
                        {percent(service.success_rate)} successful events; green
                        target at least {percent(d.green_success_rate)}, amber
                        at least {percent(d.amber_success_rate)}.
                    </p>
                    <p>
                        {d.success_count} successes, {d.retry_count} retries,{' '}
                        {d.failure_count} failures, {d.fallback_count} fallbacks
                        and {d.cached_count} cached results.
                    </p>
                    {d.event_count < 5 && (
                        <p className="mt-1 text-xs">
                            Small sample: this window does not establish
                            sustained reliability.
                        </p>
                    )}
                </div>
                <div className="rounded-md bg-muted/40 p-3 text-sm">
                    <p className="font-medium">
                        Response time:{' '}
                        {slow
                            ? 'Slower than target'
                            : d.response_time === 'unknown'
                              ? 'Not measured'
                              : 'Within target'}
                    </p>
                    <p>
                        P95: {seconds(service.p95_latency_ms)}. Green target up
                        to {seconds(d.green_latency_ms)}; amber up to{' '}
                        {seconds(d.amber_latency_ms)}.
                    </p>
                    <p className="text-xs">
                        P95 is the time within which 95% of measured calls
                        completed.
                    </p>
                    {service.service === 'anthropic' && (
                        <p className="mt-1 text-xs">
                            Measures the full AI response. A latency-only red
                            requires at least {d.latency_red_min_samples}{' '}
                            events; fewer events remain a warning.
                        </p>
                    )}
                </div>
            </div>
            {service.fresh &&
                !slow &&
                issue === null &&
                d.response_time !== 'unknown' && (
                    <p className="text-sm">
                        No corrective action is indicated by this window.
                        Continue normal use.
                    </p>
                )}
        </>
    );
}

function statusLabel(health: ServiceHealth['display_health']): string {
    return {
        green: 'Green · Within targets',
        amber: 'Amber · Needs attention',
        red: 'Red · Action required',
        stale: 'Stale · Current health unknown',
        unknown: 'Unknown · Earlier sample',
    }[health];
}

function seconds(value: number | null): string {
    return value === null ? 'not measured' : Math.round(value / 100) / 10 + 's';
}

function percent(value: number): string {
    return Math.round(value * 1000) / 10 + '%';
}

function date(value: string | null): string {
    return value === null
        ? 'not recorded'
        : new Intl.DateTimeFormat('en-NZ', {
              month: 'short',
              day: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          }).format(new Date(value));
}
