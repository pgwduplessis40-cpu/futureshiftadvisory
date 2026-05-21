import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    CheckCircle2,
    FileCheck2,
    HeartPulse,
    LockKeyhole,
    PauseCircle,
    RotateCcw,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { DataQualityBadge } from '@/components/data-quality/DataQualityBadge';
import type { DataQualitySummary } from '@/components/data-quality/DataQualityBadge';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { ClientSummary } from './types';

type ClientDetail = ClientSummary & {
    data_quality_summary: DataQualitySummary;
    wellbeing_trend: WellbeingPoint[] | null;
    offboarding: OffboardingSummary | null;
    status_options: StatusOption[];
    lifecycle_update_url: string;
    address: Record<string, string | null> | null;
    directors: Array<Record<string, string | null>>;
    registry_sources: Record<string, string>;
    engagement_type_locked: boolean;
    created_at: string | null;
};

type ConflictDeclaration = {
    id: string;
    declaration: {
        referral_type?: string;
        existing_relationship?: boolean;
        details?: string | null;
    };
    declared_at: string;
} | null;

type Props = {
    client: ClientDetail;
    conflictDeclaration: ConflictDeclaration;
};

type WellbeingPoint = {
    id: string;
    period_start: string | null;
    business_confidence: number;
    personal_coping: number;
    notes: string | null;
    submitted_at: string | null;
    submitted_by: string | null;
};

type OffboardingSummary = {
    id: string;
    triggered_at: string | null;
    reengagement_due: string | null;
    advisor_capacity_released: boolean;
};

type StatusOption = {
    value: string;
    label: string;
};

type LifecycleForm = {
    status: string;
    reason: string;
};

export default function ClientsShow({ client, conflictDeclaration }: Props) {
    const lifecycleForm = useForm<LifecycleForm>({
        status: client.status,
        reason: '',
    });

    const submitLifecycle = (status: string) => {
        lifecycleForm.setData('status', status);
        lifecycleForm.transform((data) => ({
            ...data,
            status,
        }));
        lifecycleForm.patch(client.lifecycle_update_url, {
            preserveScroll: true,
            onFinish: () =>
                lifecycleForm.transform((data) => ({
                    ...data,
                })),
        });
    };

    return (
        <>
            <Head title={client.legal_name} />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {client.legal_name}
                        </h1>
                        <div className="text-sm text-muted-foreground">
                            {client.engagement_type_label}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild size="sm" variant="outline">
                            <Link
                                href={`/advisor/clients/${client.id}/offboarding`}
                            >
                                <FileCheck2
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Offboard
                            </Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link href="/advisor/clients">
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Back
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Metric label="NZBN" value={client.nzbn ?? '-'} />
                    <Metric label="Lifecycle">
                        <Badge variant={statusVariant(client.status)}>
                            {client.status_label}
                        </Badge>
                    </Metric>
                    <Metric label="Data quality">
                        <DataQualityBadge
                            summary={client.data_quality_summary}
                        />
                    </Metric>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="space-y-4 rounded-md border p-4">
                        <h2 className="text-sm font-medium">Registry</h2>
                        <dl className="grid gap-3 text-sm">
                            <Detail label="Entity" value={client.entity_type} />
                            <Detail
                                label="Filing"
                                value={client.filing_status}
                            />
                            <Detail
                                label="Trading"
                                value={client.trading_name}
                            />
                        </dl>
                        <div className="flex flex-wrap gap-2">
                            {Object.entries(client.registry_sources).map(
                                ([service, badge]) => (
                                    <Badge key={service} variant="secondary">
                                        {service}: {badge}
                                    </Badge>
                                ),
                            )}
                        </div>
                    </section>

                    <section className="space-y-4 rounded-md border p-4">
                        <div className="flex items-center gap-2">
                            <h2 className="text-sm font-medium">Engagement</h2>
                            {client.engagement_type_locked && (
                                <Badge variant="outline">
                                    <LockKeyhole
                                        className="size-3"
                                        aria-hidden="true"
                                    />
                                    locked
                                </Badge>
                            )}
                        </div>
                        <dl className="grid gap-3 text-sm">
                            <Detail
                                label="Type"
                                value={client.engagement_type_label}
                            />
                            <Detail
                                label="Status"
                                value={client.status_label}
                            />
                            <Detail
                                label="Conflict"
                                value={
                                    conflictDeclaration ? 'declared' : 'missing'
                                }
                            />
                            <Detail
                                label="Offboarding"
                                value={
                                    client.offboarding
                                        ? formatDate(
                                              client.offboarding.triggered_at,
                                          )
                                        : 'not started'
                                }
                            />
                            <Detail
                                label="Relationship"
                                value={
                                    conflictDeclaration?.declaration
                                        .existing_relationship
                                        ? 'yes'
                                        : 'no'
                                }
                            />
                        </dl>
                    </section>
                </div>

                <section className="space-y-4 rounded-md border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <RotateCcw className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">Lifecycle</h2>
                            <Badge variant={statusVariant(client.status)}>
                                {client.status_label}
                            </Badge>
                        </div>
                        <div className="text-xs text-muted-foreground">
                            Portal access is revoked while suspended.
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="lifecycle_reason">Reason</Label>
                        <textarea
                            id="lifecycle_reason"
                            value={lifecycleForm.data.reason}
                            onChange={(event) =>
                                lifecycleForm.setData(
                                    'reason',
                                    event.target.value,
                                )
                            }
                            rows={3}
                            className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <InputError message={lifecycleForm.errors.reason} />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {lifecycleActions(client.status).map((action) => {
                            const Icon = lifecycleIcon(action.status);

                            return (
                                <Button
                                    key={action.status}
                                    type="button"
                                    variant={
                                        action.status === 'suspended'
                                            ? 'destructive'
                                            : 'outline'
                                    }
                                    disabled={lifecycleForm.processing}
                                    onClick={() =>
                                        submitLifecycle(action.status)
                                    }
                                >
                                    <Icon
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {action.label}
                                </Button>
                            );
                        })}
                    </div>
                    <InputError message={lifecycleForm.errors.status} />
                </section>

                {client.wellbeing_trend && (
                    <section className="space-y-4 rounded-md border p-4">
                        <div className="flex items-center gap-2">
                            <HeartPulse className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">Wellbeing</h2>
                        </div>
                        <WellbeingTrend points={client.wellbeing_trend} />
                    </section>
                )}
            </div>
        </>
    );
}

function Metric({
    label,
    value,
    children,
}: {
    label: string;
    value?: string;
    children?: ReactNode;
}) {
    return (
        <div className="rounded-md border p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-2 text-sm font-medium">{children ?? value}</div>
        </div>
    );
}

function Detail({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="grid grid-cols-[120px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value || '-'}</dd>
        </div>
    );
}

function WellbeingTrend({ points }: { points: WellbeingPoint[] }) {
    if (points.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No wellbeing check-ins yet.
            </p>
        );
    }

    return (
        <div className="space-y-3">
            {points.map((point) => (
                <article key={point.id} className="grid gap-2 text-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="font-medium">
                            {formatMonth(point.period_start)}
                        </div>
                        <div className="text-muted-foreground">
                            {point.submitted_by ?? 'Client'}
                        </div>
                    </div>
                    <ScoreBar
                        label="Business confidence"
                        value={point.business_confidence}
                    />
                    <ScoreBar
                        label="Personal coping"
                        value={point.personal_coping}
                    />
                    {point.notes && (
                        <p className="rounded-md bg-muted px-3 py-2 text-muted-foreground">
                            {point.notes}
                        </p>
                    )}
                </article>
            ))}
        </div>
    );
}

function ScoreBar({ label, value }: { label: string; value: number }) {
    const width = `${Math.max(0, Math.min(100, (value / 5) * 100))}%`;

    return (
        <div className="grid gap-1">
            <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span>{label}</span>
                <span>{value}/5</span>
            </div>
            <div className="h-2 rounded-full bg-muted">
                <div
                    className="h-2 rounded-full bg-[var(--fs-admiralty)]"
                    style={{ width }}
                />
            </div>
        </div>
    );
}

function formatMonth(value: string | null) {
    if (!value) {
        return 'Current period';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

function formatDate(value: string | null) {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
    }).format(new Date(value));
}

function statusVariant(
    status: string,
): 'secondary' | 'destructive' | 'outline' {
    if (status === 'suspended') {
        return 'destructive';
    }

    if (status === 'active') {
        return 'secondary';
    }

    return 'outline';
}

function lifecycleActions(status: string) {
    if (status === 'active') {
        return [
            { status: 'paused', label: 'Pause' },
            { status: 'suspended', label: 'Suspend' },
            { status: 'offboarded', label: 'Mark offboarded' },
        ];
    }

    if (status === 'paused') {
        return [
            { status: 'active', label: 'Restore' },
            { status: 'suspended', label: 'Suspend' },
            { status: 'offboarded', label: 'Mark offboarded' },
        ];
    }

    return [{ status: 'active', label: 'Restore' }];
}

function lifecycleIcon(status: string) {
    if (status === 'paused') {
        return PauseCircle;
    }

    if (status === 'suspended') {
        return Ban;
    }

    if (status === 'offboarded') {
        return CheckCircle2;
    }

    return RotateCcw;
}

ClientsShow.layout = {
    breadcrumbs: [
        {
            title: 'Clients',
            href: '/advisor/clients',
        },
    ],
};
