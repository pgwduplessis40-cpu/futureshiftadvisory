import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    BrainCircuit,
    CheckCircle2,
    Clock3,
    CircleDollarSign,
    PlugZap,
    RotateCw,
    ShieldAlert,
} from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

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

type GovernanceNotice = {
    service: string;
    label: string;
    note: string;
};

type AiUsagePeriod = {
    requests: number;
    input_tokens: number;
    output_tokens: number;
    total_tokens: number;
    estimated_cost_usd: number;
};

type AiUsageBreakdown = AiUsagePeriod & {
    model: string;
};

type AiProviderAttemptsPeriod = {
    attempts: number;
    successes: number;
    retries: number;
    failures: number;
    latest_error: string | null;
    latest_at: string | null;
};

type AiUsage = {
    today: AiUsagePeriod;
    month: AiUsagePeriod;
    budget: {
        monthly_budget_usd: number | null;
        remaining_usd: number | null;
        percent_used: number | null;
        status: 'not_set' | 'within_budget' | 'exceeded';
    };
    breakdown: AiUsageBreakdown[];
    currency: {
        base: 'USD';
        nzd_rate: number | null;
        today_estimated_cost_nzd: number | null;
        month_estimated_cost_nzd: number | null;
    };
    official: {
        configured: boolean;
        status:
            | 'synced'
            | 'sync_failed'
            | 'invalid_admin_api_key'
            | 'admin_api_key_missing';
        month_cost_usd: number | null;
        last_synced_at: string | null;
        error: string | null;
        credit_balance_supported: boolean;
        credit_balance_usd: null;
    };
    provider_attempts: {
        today: AiProviderAttemptsPeriod;
        month: AiProviderAttemptsPeriod;
    };
    pricing: {
        basis: string;
        provider: string;
    };
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
    aiUsage: AiUsage;
    governanceNotices: GovernanceNotice[];
    generatedAt: string;
};

export default function IntegrationHealthIndex({
    summary,
    services,
    recentAlerts,
    aiUsage,
    governanceNotices,
    generatedAt,
}: Props) {
    const [refreshing, setRefreshing] = useState(false);
    const failedProviderAttemptsToday =
        aiUsage.provider_attempts.today.failures +
        aiUsage.provider_attempts.today.retries;

    const refreshHealth = () => {
        router.post(
            '/admin/integration-health/refresh',
            {},
            {
                preserveScroll: true,
                onStart: () => setRefreshing(true),
                onFinish: () => setRefreshing(false),
            },
        );
    };

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
                    <div className="flex flex-col gap-3 sm:items-end">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={refreshing}
                            onClick={refreshHealth}
                        >
                            <RotateCw
                                className={cn(
                                    'size-4',
                                    refreshing && 'animate-spin',
                                )}
                                aria-hidden="true"
                            />
                            Refresh
                        </Button>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-5">
                            <Metric
                                label="Services"
                                value={summary.total}
                                explanation="Total integrations represented by the latest rollup."
                            />
                            <Metric
                                label="Green"
                                value={summary.green}
                                explanation="Services meeting success, latency, and freshness thresholds."
                            />
                            <Metric
                                label="Amber"
                                value={summary.amber}
                                explanation="Services with degraded signals that need watching."
                            />
                            <Metric
                                label="Red"
                                value={summary.red}
                                explanation="Services currently breaching health thresholds."
                            />
                            <Metric
                                label="Stale"
                                value={summary.stale}
                                explanation="Services whose latest sample is older than the freshness threshold."
                            />
                        </div>
                    </div>
                </header>

                {governanceNotices.length > 0 ? (
                    <section className="space-y-3">
                        {governanceNotices.map((notice) => (
                            <Alert key={notice.service}>
                                <ShieldAlert
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <AlertTitle className="flex flex-wrap items-center gap-2">
                                    {notice.service}
                                    <Badge variant="outline">
                                        {notice.label}
                                    </Badge>
                                </AlertTitle>
                                <AlertDescription>
                                    {notice.note}
                                </AlertDescription>
                            </Alert>
                        ))}
                    </section>
                ) : null}

                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-2">
                            <BrainCircuit
                                className="size-4"
                                aria-hidden="true"
                            />
                            <h2 className="text-sm font-medium">
                                AI usage & cost
                            </h2>
                        </div>
                        <Badge
                            variant={
                                aiUsage.official.configured
                                    ? 'secondary'
                                    : 'outline'
                            }
                        >
                            <CircleDollarSign
                                className="size-3"
                                aria-hidden="true"
                            />
                            {officialBadgeLabel(aiUsage)}
                        </Badge>
                    </div>

                    <div className="grid gap-3 md:grid-cols-4">
                        <Metric
                            label="Today"
                            value={formatCurrency(
                                aiUsage.today.estimated_cost_usd,
                            )}
                            explanation={`${formatNumber(aiUsage.today.total_tokens)} tokens across ${aiUsage.today.requests} AI calls today.`}
                        />
                        <Metric
                            label="Month to date"
                            value={formatCurrency(
                                aiUsage.month.estimated_cost_usd,
                            )}
                            explanation={`${formatNumber(aiUsage.month.total_tokens)} tokens across ${aiUsage.month.requests} AI calls this month.`}
                        />
                        <Metric
                            label="Budget"
                            value={budgetValue(aiUsage)}
                            explanation={budgetExplanation(aiUsage)}
                        />
                        <Metric
                            label="Provider attempts"
                            value={formatNumber(
                                aiUsage.provider_attempts.today.attempts,
                            )}
                            explanation={`${formatNumber(failedProviderAttemptsToday)} failed or retried Anthropic attempts today. These can consume provider credit even when no token usage is returned.`}
                        />
                    </div>

                    <div className="grid gap-4 lg:grid-cols-[1fr_22rem]">
                        <div className="overflow-hidden rounded-md border">
                            <table className="fsa-responsive-table">
                                <thead className="bg-muted/60 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            Model
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Calls
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Tokens
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Est. cost
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {aiUsage.breakdown.length === 0 ? (
                                        <tr>
                                            <td
                                                className="px-3 py-8 text-sm text-muted-foreground"
                                                colSpan={4}
                                            >
                                                No completed AI responses
                                                recorded this month.
                                            </td>
                                        </tr>
                                    ) : (
                                        aiUsage.breakdown.map((model) => (
                                            <tr
                                                key={model.model}
                                                className="border-t"
                                            >
                                                <td
                                                    className="px-3 py-2 font-medium"
                                                    data-label="Model"
                                                >
                                                    {model.model}
                                                </td>
                                                <td
                                                    className="px-3 py-2"
                                                    data-label="Calls"
                                                >
                                                    {model.requests}
                                                </td>
                                                <td
                                                    className="px-3 py-2"
                                                    data-label="Tokens"
                                                >
                                                    {formatNumber(
                                                        model.total_tokens,
                                                    )}
                                                </td>
                                                <td
                                                    className="px-3 py-2"
                                                    data-label="Est. cost"
                                                >
                                                    {formatCurrency(
                                                        model.estimated_cost_usd,
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="space-y-3 rounded-md border p-3 text-sm">
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Input tokens
                                </span>
                                <span className="font-medium">
                                    {formatNumber(aiUsage.month.input_tokens)}
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Output tokens
                                </span>
                                <span className="font-medium">
                                    {formatNumber(aiUsage.month.output_tokens)}
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted-foreground">
                                    NZD estimate
                                </span>
                                <span className="font-medium">
                                    {aiUsage.currency
                                        .month_estimated_cost_nzd === null
                                        ? 'Set rate'
                                        : formatCurrency(
                                              aiUsage.currency
                                                  .month_estimated_cost_nzd,
                                              'NZD',
                                          )}
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Official Anthropic cost
                                </span>
                                <span className="font-medium">
                                    {aiUsage.official.month_cost_usd === null
                                        ? officialStatusLabel(aiUsage)
                                        : formatCurrency(
                                              aiUsage.official.month_cost_usd,
                                          )}
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Admin API status
                                </span>
                                <Badge variant={officialVariant(aiUsage)}>
                                    {officialStatusLabel(aiUsage)}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Anthropic attempts
                                </span>
                                <span className="font-medium">
                                    {formatNumber(
                                        aiUsage.provider_attempts.today
                                            .attempts,
                                    )}{' '}
                                    today
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Failed or retried
                                </span>
                                <span className="font-medium">
                                    {formatNumber(failedProviderAttemptsToday)}
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted-foreground">
                                    Credit balance
                                </span>
                                <Badge variant="outline">Not exposed</Badge>
                            </div>
                            {aiUsage.provider_attempts.today.latest_error && (
                                <p className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                    Latest provider error:{' '}
                                    {
                                        aiUsage.provider_attempts.today
                                            .latest_error
                                    }
                                </p>
                            )}
                            {aiUsage.official.last_synced_at && (
                                <p className="text-xs text-muted-foreground">
                                    Synced{' '}
                                    {formatDate(
                                        aiUsage.official.last_synced_at,
                                    )}
                                </p>
                            )}
                            {aiUsage.official.error && (
                                <p className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive">
                                    {aiUsage.official.error}
                                </p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Local token totals include completed responses;
                                failed provider attempts may still appear in
                                Claude Console. Prepaid credit balance must be
                                checked there.
                            </p>
                        </div>
                    </div>
                </section>

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
                            <table className="fsa-responsive-table">
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
                                            <td
                                                className="px-3 py-2"
                                                data-label="Service"
                                            >
                                                <div className="font-medium">
                                                    {service.service}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {formatDate(
                                                        service.window_end,
                                                    )}
                                                </div>
                                            </td>
                                            <td
                                                className="px-3 py-2"
                                                data-label="Health"
                                            >
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
                                            <td
                                                className="px-3 py-2"
                                                data-label="Success"
                                            >
                                                {formatPercent(
                                                    service.success_rate,
                                                )}
                                            </td>
                                            <td
                                                className="px-3 py-2"
                                                data-label="P95"
                                            >
                                                {service.p95_latency_ms === null
                                                    ? 'n/a'
                                                    : `${service.p95_latency_ms}ms`}
                                            </td>
                                            <td
                                                className="px-3 py-2"
                                                data-label="Lag"
                                            >
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

function Metric({
    label,
    value,
    explanation,
}: {
    label: string;
    value: number | string;
    explanation: string;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <div className="rounded-md border bg-background px-4 py-3">
                    <div className="text-xs text-muted-foreground">{label}</div>
                    <div className="mt-1 text-lg font-semibold">{value}</div>
                </div>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {explanation}
            </TooltipContent>
        </Tooltip>
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

function formatNumber(value: number): string {
    return new Intl.NumberFormat().format(value);
}

function formatCurrency(value: number, currency = 'USD'): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        maximumFractionDigits: value >= 1 ? 2 : 4,
    }).format(value);
}

function budgetValue(aiUsage: AiUsage): string {
    if (aiUsage.budget.monthly_budget_usd === null) {
        return 'Not set';
    }

    if (aiUsage.budget.percent_used === null) {
        return formatCurrency(aiUsage.budget.monthly_budget_usd);
    }

    return formatPercent(aiUsage.budget.percent_used);
}

function officialBadgeLabel(aiUsage: AiUsage): string {
    return aiUsage.official.status === 'synced'
        ? 'Official cost synced'
        : 'Estimated only';
}

function officialStatusLabel(aiUsage: AiUsage): string {
    switch (aiUsage.official.status) {
        case 'synced':
            return 'Synced';
        case 'sync_failed':
            return 'Sync failed';
        case 'invalid_admin_api_key':
            return 'Invalid admin key';
        case 'admin_api_key_missing':
            return 'Key missing';
    }
}

function officialVariant(
    aiUsage: AiUsage,
): 'secondary' | 'outline' | 'destructive' {
    if (aiUsage.official.status === 'synced') {
        return 'secondary';
    }

    if (aiUsage.official.status === 'admin_api_key_missing') {
        return 'outline';
    }

    return 'destructive';
}

function budgetExplanation(aiUsage: AiUsage): string {
    if (aiUsage.budget.monthly_budget_usd === null) {
        return 'Set AI_MONTHLY_BUDGET_USD to show budget use and overrun warnings.';
    }

    const remaining = aiUsage.budget.remaining_usd ?? 0;

    if (aiUsage.budget.status === 'exceeded') {
        return `Monthly budget exceeded by ${formatCurrency(Math.abs(remaining))}.`;
    }

    return `${formatCurrency(remaining)} remaining from the monthly AI budget.`;
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
