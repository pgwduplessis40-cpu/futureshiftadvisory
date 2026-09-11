import { Link } from '@inertiajs/react';
import { Bot, CheckCircle2, Eye } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ActionPanel, formatLabel } from './plan-dashboard-panels';
import { PlanCompletionAction } from './plan-workspace-submission';
import type { PlanWorkspace } from './use-plan-workspace';

type PriorityWorkspace = Pick<
    PlanWorkspace,
    | 'packageAccess'
    | 'ideaValidation'
    | 'plan'
    | 'advisoryRequest'
    | 'includesIdeaValidation'
    | 'includesPlanBudget'
    | 'planBuilderUnlocked'
    | 'ideaChangesRequested'
    | 'ideaValidationRecalled'
    | 'startPlan'
    | 'submitPlan'
    | 'requestAdvisory'
>;

export function shouldShowPlanWorkspacePriorityActions(
    includesIdeaValidation: boolean,
    includesPlanBudget: boolean,
): boolean {
    return !includesIdeaValidation || includesPlanBudget;
}

export function PlanWorkspacePriorityActions({
    workspace,
}: {
    workspace: PriorityWorkspace;
}) {
    const {
        packageAccess,
        ideaValidation,
        plan,
        advisoryRequest,
        includesIdeaValidation,
        includesPlanBudget,
        planBuilderUnlocked,
        ideaChangesRequested,
        ideaValidationRecalled,
        startPlan,
        submitPlan,
        requestAdvisory,
    } = workspace;

    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-base font-semibold">Priority actions</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {packageAccess.package_scope_label}
                </p>
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <ActionPanel
                    icon={Bot}
                    title="Idea validation"
                    value={
                        !includesIdeaValidation
                            ? 'Not included'
                            : ideaValidation
                              ? planBuilderUnlocked
                                  ? 'Advisor approved'
                                  : ideaChangesRequested
                                    ? 'Changes requested'
                                    : ideaValidationRecalled
                                      ? 'Ready to revise'
                                      : 'Awaiting advisor gate'
                              : 'Not submitted'
                    }
                    explanation="Idea validation captures the customer problem, solution, demand, and revenue logic before the plan builder opens."
                >
                    {!includesIdeaValidation ? (
                        <Badge variant="outline">Not in package</Badge>
                    ) : !ideaValidation ? (
                        <Button asChild size="sm">
                            <a href="#idea-validation">Start idea validation</a>
                        </Button>
                    ) : planBuilderUnlocked ? (
                        <Badge variant="secondary">Builder unlocked</Badge>
                    ) : ideaChangesRequested ? (
                        <Badge variant="outline">Changes requested</Badge>
                    ) : ideaValidationRecalled ? (
                        <Badge variant="outline">Ready to revise</Badge>
                    ) : (
                        <Badge variant="outline">Advisor review</Badge>
                    )}
                </ActionPanel>

                <PlanCompletionAction
                    includesPlanBudget={includesPlanBudget}
                    plan={plan}
                    planBuilderUnlocked={planBuilderUnlocked}
                    startPlan={startPlan}
                    submitPlan={submitPlan}
                />

                <ActionPanel
                    icon={Eye}
                    title="Assessment"
                    value={
                        !includesPlanBudget
                            ? 'Not included'
                            : plan?.latest_assessment
                              ? `${formatLabel(plan.latest_assessment.overall_grade)}`
                              : 'Pending'
                    }
                    explanation="Assessment appears once your advisor scores the submitted plan and finalises feedback."
                >
                    {!includesPlanBudget ? (
                        <Badge variant="outline">Not in package</Badge>
                    ) : plan?.latest_assessment ? (
                        <Button asChild size="sm" variant="outline">
                            <Link href={plan.latest_assessment.url}>
                                View assessment
                            </Link>
                        </Button>
                    ) : (
                        <Badge variant="outline">Advisor action</Badge>
                    )}
                </ActionPanel>

                <ActionPanel
                    icon={CheckCircle2}
                    title="Advisory"
                    value={
                        !includesPlanBudget
                            ? 'Not included'
                            : advisoryRequest.requested
                              ? 'Requested'
                              : advisoryRequest.available
                                ? 'Available'
                                : 'Locked'
                    }
                    explanation="Request advisory once the plan has been assessed as advisory ready. This asks your advisor to convert the plan into a standard advisory engagement."
                >
                    {!includesPlanBudget ? (
                        <Badge variant="outline">Not in package</Badge>
                    ) : advisoryRequest.requested &&
                      advisoryRequest.thread_url ? (
                        <Button asChild size="sm" variant="outline">
                            <Link href={advisoryRequest.thread_url}>
                                Open request
                            </Link>
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            size="sm"
                            disabled={!advisoryRequest.available}
                            onClick={requestAdvisory}
                        >
                            Request advisory
                        </Button>
                    )}
                </ActionPanel>
            </div>
        </section>
    );
}
