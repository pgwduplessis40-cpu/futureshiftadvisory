import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Mail, TrendingUp, UserRoundCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { CriterionDelta, EntrepreneurDetail } from './types';

type Props = {
    entrepreneur: EntrepreneurDetail;
};

export default function EntrepreneursShow({ entrepreneur }: Props) {
    return (
        <>
            <Head title={entrepreneur.name} />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {entrepreneur.name}
                        </h1>
                        <div className="text-sm text-muted-foreground">
                            {entrepreneur.email}
                        </div>
                    </div>
                    <Button asChild size="sm" variant="outline">
                        <Link href="/advisor/entrepreneurs">
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Back
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Metric
                        label="Stage"
                        value={entrepreneur.stage_label}
                        badge
                    />
                    <Metric
                        label="Invite"
                        value={
                            entrepreneur.invite_accepted_at
                                ? 'accepted'
                                : 'sent'
                        }
                    />
                    <Metric
                        label="Account"
                        value={entrepreneur.user_id ? 'linked' : 'pending'}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="space-y-4 rounded-md border p-4">
                        <div className="flex items-center gap-2">
                            <Mail className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">Invite</h2>
                        </div>
                        <dl className="grid gap-3 text-sm">
                            <Detail label="Email" value={entrepreneur.email} />
                            <Detail
                                label="Accepted"
                                value={formatDate(
                                    entrepreneur.invite_accepted_at,
                                )}
                            />
                            <Detail
                                label="Created"
                                value={formatDate(entrepreneur.created_at)}
                            />
                        </dl>
                    </section>

                    <section className="space-y-4 rounded-md border p-4">
                        <div className="flex items-center gap-2">
                            <UserRoundCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            <h2 className="text-sm font-medium">Concept</h2>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {entrepreneur.concept_summary || 'No summary yet.'}
                        </p>
                    </section>
                </div>

                {entrepreneur.latest_plan ? (
                    <section className="space-y-4 rounded-md border p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <TrendingUp
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-medium">
                                    Round progress
                                </h2>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Badge variant="secondary">
                                    {entrepreneur.latest_plan.status}
                                </Badge>
                                {entrepreneur.latest_plan.latest_grade ? (
                                    <Badge variant="outline">
                                        {gradeLabel(
                                            entrepreneur.latest_plan
                                                .latest_grade,
                                        )}
                                    </Badge>
                                ) : null}
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            <Metric
                                label="Assessments"
                                value={String(
                                    entrepreneur.latest_plan.assessment_count,
                                )}
                            />
                            <Metric
                                label="Latest round"
                                value={
                                    entrepreneur.latest_plan.latest_round
                                        ? String(
                                              entrepreneur.latest_plan
                                                  .latest_round,
                                          )
                                        : '-'
                                }
                            />
                            <Metric
                                label="Trajectory"
                                value={formatDelta(
                                    entrepreneur.latest_plan.latest_revision
                                        ?.trajectory_percent,
                                    '%',
                                )}
                            />
                        </div>

                        {entrepreneur.latest_plan.latest_revision ? (
                            <div className="grid gap-6 lg:grid-cols-2">
                                <ProgressList
                                    title="Biggest improvements"
                                    rows={
                                        entrepreneur.latest_plan.latest_revision
                                            .biggest_improvements
                                    }
                                    empty="No positive movement yet."
                                />
                                <ProgressList
                                    title="Remaining gaps"
                                    rows={
                                        entrepreneur.latest_plan.latest_revision
                                            .remaining_gaps
                                    }
                                    empty="No criteria below 60."
                                />
                            </div>
                        ) : null}
                    </section>
                ) : null}
            </div>
        </>
    );
}

function Metric({
    label,
    value,
    badge = false,
}: {
    label: string;
    value: string;
    badge?: boolean;
}) {
    return (
        <div className="rounded-md border p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-2 text-sm font-medium">
                {badge ? <Badge variant="secondary">{value}</Badge> : value}
            </div>
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
        <div className="grid grid-cols-[110px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value || '-'}</dd>
        </div>
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatDelta(value: number | null | undefined, suffix = ''): string {
    if (value === null || value === undefined) {
        return '-';
    }

    const sign = value > 0 ? '+' : '';

    return `${sign}${value.toFixed(1)}${suffix}`;
}

function gradeLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function ProgressList({
    title,
    rows,
    empty,
}: {
    title: string;
    rows: CriterionDelta[];
    empty: string;
}) {
    return (
        <div className="space-y-3">
            <h3 className="text-xs font-medium text-muted-foreground">
                {title}
            </h3>
            {rows.length > 0 ? (
                <div className="space-y-2">
                    {rows.map((row) => (
                        <div
                            key={`${row.criterion_number}-${row.direction}`}
                            className="flex items-center justify-between gap-3 text-sm"
                        >
                            <div className="min-w-0">
                                <div className="truncate font-medium">
                                    {row.criterion_name}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {row.previous_score ?? '-'} -&gt;{' '}
                                    {row.current_score}
                                </div>
                            </div>
                            <Badge
                                variant={
                                    row.delta >= 0 ? 'secondary' : 'outline'
                                }
                            >
                                {formatDelta(row.delta)}
                            </Badge>
                        </div>
                    ))}
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">{empty}</p>
            )}
        </div>
    );
}

EntrepreneursShow.layout = {
    breadcrumbs: [
        {
            title: 'Entrepreneurs',
            href: '/advisor/entrepreneurs',
        },
    ],
};
