import { FileText, RefreshCw, Send } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    ActionPanel,
    IdeaValidationHistory,
    SubmittedPlanHistory,
} from './plan-dashboard-panels';
import type { PlanWorkspace } from './use-plan-workspace';

type SubmissionWorkspace = Pick<
    PlanWorkspace,
    | 'includesPlanBudget'
    | 'plan'
    | 'planBuilderUnlocked'
    | 'startPlan'
    | 'submitPlan'
>;

type HistoryWorkspace = Pick<
    PlanWorkspace,
    | 'plan'
    | 'ideaValidationVersions'
    | 'restoringIdeaVersionId'
    | 'restoreIdeaVersion'
>;

export function planChangesAreLocked(plan: PlanWorkspace['plan']): boolean {
    return ['submitted', 'assessing', 'finalised', 'launched'].includes(
        plan?.status ?? '',
    );
}

export function PlanCompletionAction({
    includesPlanBudget,
    plan,
    planBuilderUnlocked,
    startPlan,
    submitPlan,
}: SubmissionWorkspace) {
    const awaitingAdvisor = plan?.status === 'submitted';
    const assessmentInProgress = plan?.status === 'assessing';
    const changesLocked = planChangesAreLocked(plan);

    return (
        <ActionPanel
            icon={FileText}
            title="Plan completion"
            value={
                !includesPlanBudget
                    ? 'Not included'
                    : plan
                      ? awaitingAdvisor
                          ? 'With advisor'
                          : assessmentInProgress
                            ? 'Assessment in progress'
                            : changesLocked
                              ? 'With advisor'
                              : plan.requirements_complete
                                ? 'Complete'
                                : `${plan.missing_requirements.length} gaps`
                      : planBuilderUnlocked
                        ? 'Not started'
                        : 'Locked'
            }
            explanation={
                awaitingAdvisor
                    ? 'Your current plan has been sent to your advisor for review.'
                    : assessmentInProgress
                      ? 'Your advisor has started the assessment. The current version is locked for review.'
                      : changesLocked
                        ? 'The current version is locked while your advisor completes the next step.'
                        : 'Plan completion is based on all required business plan sections, not merely one section per phase.'
            }
        >
            {!includesPlanBudget ? (
                <Badge variant="outline">Not in package</Badge>
            ) : awaitingAdvisor ? (
                <Badge variant="secondary">Submitted for advisor review</Badge>
            ) : assessmentInProgress ? (
                <Badge variant="outline">Assessment in progress</Badge>
            ) : changesLocked ? (
                <Badge variant="secondary">With advisor</Badge>
            ) : plan ? (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={submitPlan}
                    disabled={!plan.requirements_complete}
                >
                    <Send className="size-4" aria-hidden="true" />
                    {plan.status === 'revising'
                        ? 'Resubmit for advisor review'
                        : 'Submit for advisor review'}
                </Button>
            ) : (
                <Button
                    type="button"
                    size="sm"
                    onClick={startPlan}
                    disabled={!planBuilderUnlocked}
                >
                    <RefreshCw className="size-4" aria-hidden="true" />
                    Start plan
                </Button>
            )}
        </ActionPanel>
    );
}

export function PlanWorkspaceHistory({
    plan,
    ideaValidationVersions,
    restoringIdeaVersionId,
    restoreIdeaVersion,
}: HistoryWorkspace) {
    return (
        <>
            {ideaValidationVersions.length > 1 ? (
                <IdeaValidationHistory
                    versions={ideaValidationVersions}
                    restoringVersionId={restoringIdeaVersionId}
                    onRestore={restoreIdeaVersion}
                />
            ) : null}
            {plan ? <SubmittedPlanHistory versions={plan.history} /> : null}
        </>
    );
}
