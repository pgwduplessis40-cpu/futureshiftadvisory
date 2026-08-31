import { router, useForm } from '@inertiajs/react';
import { Trophy } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Metric, formatDate, formatLabel } from './client-detail-presenters';
import type { FoundingAdvisorySummary } from './client-detail-types';
export function FoundingAdvisoryPanel({
    summary,
}: {
    summary: FoundingAdvisorySummary;
}) {
    const replan = useForm({
        reason: '',
        sales_pipeline: '',
        cash_funding: '',
        delivery_capacity: '',
        changed_assumptions: '',
        risks: '',
        advisor_decisions: '',
    });
    const visibleRoadmap = summary.draft_version ?? summary.current_version;
    const horizons = visibleRoadmap?.agenda.horizons ?? [];

    const submitReplan = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!summary.replan_url) {
            return;
        }

        replan.post(summary.replan_url, {
            preserveScroll: true,
            onSuccess: () => replan.reset(),
        });
    };

    return (
        <section
            id="section-founding-advisory"
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Trophy className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">
                            Founding Advisory roadmap
                        </h2>
                        <Badge variant="outline">{summary.status_label}</Badge>
                        {summary.replan_due ? (
                            <Badge variant="destructive">Review due</Badge>
                        ) : null}
                    </div>
                    <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                        Founding Baseline v{summary.baseline.version} remains
                        fixed. Publish only the active 90-day agenda; the next
                        two horizons stay visible and are revised with the
                        founder as evidence changes.
                    </p>
                </div>
                {summary.draft_version?.publish_url ? (
                    <Button
                        type="button"
                        size="sm"
                        onClick={() =>
                            router.patch(
                                summary.draft_version?.publish_url ?? '',
                                {},
                                {
                                    preserveScroll: true,
                                },
                            )
                        }
                    >
                        Publish roadmap v{summary.draft_version.version}
                    </Button>
                ) : null}
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Metric
                    label="Baseline plan"
                    value={summary.baseline.plan_title ?? '-'}
                />
                <Metric
                    label="Readiness"
                    value={
                        summary.baseline.assessment_score === null
                            ? '-'
                            : `${summary.baseline.assessment_score.toFixed(1)}/100`
                    }
                />
                <Metric
                    label="Next review"
                    value={formatDate(summary.replan_due_at)}
                />
                <Metric
                    label="Transition review"
                    value={formatDate(summary.transition_review_at)}
                />
            </div>

            {visibleRoadmap ? (
                <div className="grid gap-3 xl:grid-cols-3">
                    {horizons.map((horizon) => (
                        <article
                            key={horizon.key}
                            className="space-y-3 border p-3"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h3 className="text-sm font-medium">
                                    {horizon.label}
                                </h3>
                                <Badge
                                    variant={
                                        horizon.commitment === 'committed'
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    {formatLabel(horizon.commitment)}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {formatDate(horizon.starts_on)} to{' '}
                                {formatDate(horizon.ends_on)}
                            </p>
                            <ul className="space-y-2 text-sm text-muted-foreground">
                                {horizon.outcomes.map((outcome) => (
                                    <li key={outcome}>{outcome}</li>
                                ))}
                            </ul>
                            <div className="space-y-1 border-t pt-3 text-xs text-muted-foreground">
                                {horizon.milestones.map((milestone) => (
                                    <div key={milestone.title}>
                                        Day {milestone.due_day}:{' '}
                                        {milestone.title}
                                    </div>
                                ))}
                            </div>
                        </article>
                    ))}
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    The proposal is awaiting acceptance. A draft 270-day roadmap
                    is created after signature and remains advisor reviewable
                    until published.
                </p>
            )}

            {summary.can_replan && summary.replan_url ? (
                <form
                    className="grid gap-3 border-t pt-4"
                    onSubmit={submitReplan}
                >
                    <div>
                        <Label htmlFor="founding_replan_reason">
                            Replan reason
                        </Label>
                        <textarea
                            id="founding_replan_reason"
                            className="mt-1 min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            value={replan.data.reason}
                            onChange={(event) =>
                                replan.setData('reason', event.target.value)
                            }
                        />
                        <InputError message={replan.errors.reason} />
                    </div>
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {[
                            ['sales_pipeline', 'Sales and pipeline'],
                            ['cash_funding', 'Cash and funding'],
                            ['delivery_capacity', 'Delivery capacity'],
                            ['changed_assumptions', 'Changed assumptions'],
                            ['risks', 'Risks'],
                            ['advisor_decisions', 'Advisor decisions'],
                        ].map(([key, label]) => (
                            <div key={key}>
                                <Label htmlFor={`founding_${key}`}>
                                    {label}
                                </Label>
                                <textarea
                                    id={`founding_${key}`}
                                    className="mt-1 min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                    value={
                                        replan.data[
                                            key as keyof typeof replan.data
                                        ]
                                    }
                                    onChange={(event) =>
                                        replan.setData(
                                            key as keyof typeof replan.data,
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        ))}
                    </div>
                    <div>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={replan.processing}
                        >
                            {replan.processing
                                ? 'Generating roadmap'
                                : 'Generate next roadmap version'}
                        </Button>
                    </div>
                </form>
            ) : null}
        </section>
    );
}
