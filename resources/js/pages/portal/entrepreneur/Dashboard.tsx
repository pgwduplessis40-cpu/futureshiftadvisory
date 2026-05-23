import { Head } from '@inertiajs/react';
import { ClipboardCheck, Hourglass } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

type EntrepreneurProfile = {
    id: string;
    name: string;
    email: string;
    stage: string;
    stage_label: string;
    concept_summary: string | null;
    latest_plan: {
        id: string;
        status: string;
        assessment_count: number;
        latest_grade: string | null;
        living_plan_next_update_at: string | null;
        living_plan_divergence_flags: {
            diverged?: boolean;
            remaining_gap_count?: number;
            advisory_readiness_attention?: boolean;
        } | null;
    } | null;
    advisory_readiness_signal: {
        score: number;
        surfaced_at: string | null;
    } | null;
} | null;

type Props = {
    profile: EntrepreneurProfile;
};

export default function EntrepreneurDashboard({ profile }: Props) {
    return (
        <>
            <Head title="Entrepreneur portal" />

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Entrepreneur portal
                        </h1>
                        <div className="text-sm text-muted-foreground">
                            {profile?.name ?? 'Profile pending'}
                        </div>
                    </div>
                    <Badge variant="secondary">
                        {profile?.stage_label ?? 'Onboarding'}
                    </Badge>
                </div>

                <section className="space-y-4 rounded-md border p-4">
                    <div className="flex items-center gap-2">
                        <Hourglass className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Progress</h2>
                    </div>
                    {profile?.latest_plan ? (
                        <dl className="grid gap-3 text-sm md:grid-cols-2">
                            <Detail
                                label="Plan"
                                value={profile.latest_plan.status}
                            />
                            <Detail
                                label="Grade"
                                value={
                                    profile.latest_plan.latest_grade
                                        ? gradeLabel(
                                              profile.latest_plan.latest_grade,
                                          )
                                        : null
                                }
                            />
                            <Detail
                                label="Assessments"
                                value={String(
                                    profile.latest_plan.assessment_count,
                                )}
                            />
                            <Detail
                                label="Next update"
                                value={formatDate(
                                    profile.latest_plan
                                        .living_plan_next_update_at,
                                )}
                            />
                        </dl>
                    ) : (
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Your invite is active and the entrepreneur module is
                            ready for your next step.
                        </p>
                    )}
                </section>

                <section className="space-y-4 rounded-md border p-4">
                    <div className="flex items-center gap-2">
                        <ClipboardCheck className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Profile</h2>
                    </div>
                    <dl className="grid gap-3 text-sm">
                        <Detail label="Email" value={profile?.email} />
                        <Detail
                            label="Concept"
                            value={profile?.concept_summary}
                        />
                    </dl>
                </section>

                {profile?.advisory_readiness_signal ? (
                    <section className="space-y-4 rounded-md border p-4">
                        <div className="flex items-center gap-2">
                            <ClipboardCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            <h2 className="text-sm font-medium">
                                Advisory readiness
                            </h2>
                        </div>
                        <dl className="grid gap-3 text-sm md:grid-cols-2">
                            <Detail
                                label="Score"
                                value={`${profile.advisory_readiness_signal.score.toFixed(1)}/100`}
                            />
                            <Detail
                                label="Surfaced"
                                value={formatDate(
                                    profile.advisory_readiness_signal
                                        .surfaced_at,
                                )}
                            />
                        </dl>
                    </section>
                ) : null}
            </div>
        </>
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
    }).format(new Date(value));
}

function gradeLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

EntrepreneurDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Entrepreneur portal',
            href: '/portal/entrepreneur',
        },
    ],
};
