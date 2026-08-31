import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Detail,
    displayStageLabel,
    formatDate,
    formatLabel,
} from './plan-dashboard-panels';
import type { PlanWorkspace } from './use-plan-workspace';

export function PlanWorkspaceInformation({
    workspace,
}: {
    workspace: PlanWorkspace;
}) {
    const { profile, plan, reports, advisoryRequest } = workspace;

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <section className="space-y-4 rounded-md border bg-background p-4">
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-sm font-medium">Assessment reports</h2>
                    <Badge variant="outline">{reports.length}</Badge>
                </div>
                {reports.length > 0 ? (
                    <div className="divide-y rounded-md border">
                        {reports.map((report) => (
                            <article
                                key={report.id}
                                className="flex flex-wrap items-center justify-between gap-3 p-3"
                            >
                                <div>
                                    <div className="text-sm font-medium">
                                        {report.title}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {formatDate(report.generated_at)}
                                    </div>
                                </div>
                                <Button asChild size="sm" variant="outline">
                                    <a
                                        href={
                                            report.view_url ??
                                            report.download_url
                                        }
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        View
                                    </a>
                                </Button>
                            </article>
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Reports appear after your advisor finalises an
                        assessment.
                    </p>
                )}
            </section>

            <section className="space-y-4 rounded-md border bg-background p-4">
                <h2 className="text-sm font-medium">Current profile</h2>
                <dl className="grid gap-3 text-sm">
                    <Detail label="Email" value={profile.email} />
                    <Detail
                        label="Stage"
                        value={displayStageLabel(
                            profile.stage,
                            profile.stage_label,
                        )}
                    />
                    <Detail label="Concept" value={profile.concept_summary} />
                    <Detail
                        label="Plan status"
                        value={plan ? formatLabel(plan.status) : null}
                    />
                </dl>
                {advisoryRequest.blockers.length > 0 ? (
                    <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                        {advisoryRequest.blockers.join(' ')}
                    </div>
                ) : null}
            </section>
        </div>
    );
}
