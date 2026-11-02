import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { AdvisorPlanBudgetReview } from './advisor-plan-budget-review';

export type PlanBudgetCoherenceSummary = {
    status_label: string;
    summary: string;
    approval_available: boolean;
    advisor_reviews?: { message: string; next_action: string }[];
    calculation_issues?: { message: string; next_action: string }[];
    review_completed?: boolean;
    review_note?: string | null;
    budget_support: {
        summary: string;
    };
    plan_correlation: {
        summary: string;
    };
    findings: {
        category: string;
        message: string;
        next_action: string;
    }[];
    actions: {
        key: string;
        severity: 'missing' | 'review' | string;
        title: string;
        destination: string;
        steps: string[];
    }[];
    advisories?: {
        key: string;
        category: string;
        message: string;
        next_action: string;
    }[];
};

type Props = {
    coherence: PlanBudgetCoherenceSummary | null;
    heading?: 'h2' | 'h3';
    historicalMessage: string;
    audience?: 'client' | 'advisor';
    reviewUrl?: string;
    recalculateUrl?: string;
};

export function PlanBudgetCoherencePanel({
    coherence,
    heading = 'h2',
    historicalMessage,
    audience = 'client',
    reviewUrl,
    recalculateUrl,
}: Props) {
    const Heading = heading;
    const advisories = coherence?.advisories ?? [];
    const actions = coherence?.actions ?? [];
    const advisor = audience === 'advisor';
    const reviews = coherence?.advisor_reviews ?? [];
    const calculations = coherence?.calculation_issues ?? [];

    if (coherence === null) {
        return (
            <section
                className="flex items-start gap-3 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950"
                role="alert"
            >
                <AlertTriangle
                    className="mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div className="space-y-1">
                    <Heading className="font-medium">
                        Plan–budget coherence not assessed
                    </Heading>
                    <p>{historicalMessage}</p>
                </div>
            </section>
        );
    }

    return (
        <section
            className={cn(
                'space-y-3 rounded-md border p-4 text-sm',
                coherence.approval_available
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-950'
                    : !advisor && actions.length === 0
                      ? 'border-amber-200 bg-amber-50 text-foreground'
                      : 'border-destructive/40 bg-destructive/5 text-foreground',
            )}
            role={coherence.approval_available ? 'status' : 'alert'}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex min-w-0 items-start gap-3">
                    {coherence.approval_available ? (
                        <CheckCircle2
                            className="mt-0.5 size-5 shrink-0"
                            aria-hidden="true"
                        />
                    ) : (
                        <AlertTriangle
                            className="mt-0.5 size-5 shrink-0 text-destructive"
                            aria-hidden="true"
                        />
                    )}
                    <div className="space-y-1">
                        <Heading className="font-medium">
                            Plan and Budget review
                        </Heading>
                        <p className="max-w-4xl text-muted-foreground">
                            {advisor || actions.length > 0
                                ? coherence.summary
                                : coherence.approval_available
                                  ? 'No further Budget changes are requested from you. Your advisor can complete the assessment and discuss the next stage.'
                                  : 'No Budget changes are requested from you. Your advisor is checking the financial findings before completing the assessment.'}
                        </p>
                    </div>
                </div>
                <Badge
                    variant={
                        coherence.approval_available
                            ? 'secondary'
                            : 'destructive'
                    }
                >
                    {!advisor &&
                    !coherence.approval_available &&
                    actions.length === 0
                        ? 'With your advisor'
                        : coherence.status_label}
                </Badge>
            </div>

            {advisor ? (
                <div className="grid gap-3 md:grid-cols-2">
                    <div className="rounded border bg-background/70 p-3">
                        <p className="font-medium">
                            Does the budget support the plan?
                        </p>
                        <p className="mt-1 text-muted-foreground">
                            {coherence.budget_support.summary}
                        </p>
                    </div>
                    <div className="rounded border bg-background/70 p-3">
                        <p className="font-medium">
                            Does the plan correlate to the budget?
                        </p>
                        <p className="mt-1 text-muted-foreground">
                            {coherence.plan_correlation.summary}
                        </p>
                    </div>
                </div>
            ) : null}

            {actions.length > 0 ? (
                <div className="space-y-3 border-t pt-3">
                    <p className="font-medium">Specific client updates</p>
                    <p className="rounded border bg-background/70 p-3 text-muted-foreground">
                        Open Business Plan & Budget, then select Financial
                        assumptions from the Plan requirements list on the left.
                        Your Budget changes save automatically, so you do not
                        need to find a Save button. After editing, wait until
                        the page shows “All changes saved,” then select Run
                        reassessment.
                    </p>
                    <ul className="space-y-3">
                        {actions.map((action) => (
                            <li key={action.key} className="space-y-2">
                                <p className="font-medium">{action.title}</p>
                                <p className="text-muted-foreground">
                                    Go to: {action.destination}
                                </p>
                                <ul className="list-disc space-y-1 pl-5 text-muted-foreground">
                                    {action.steps.map((step) => (
                                        <li key={step}>{step}</li>
                                    ))}
                                </ul>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}

            {advisor ? (
                <>
                    {calculations.length > 0 ? (
                        <FindingList
                            title="Calculation issues — advisor investigation"
                            findings={calculations}
                        />
                    ) : null}
                    {reviews.length > 0 ? (
                        <FindingList
                            title={
                                coherence.review_completed
                                    ? 'Advisor review completed'
                                    : 'Advisor decisions needed'
                            }
                            findings={reviews}
                            showNextAction={!coherence.review_completed}
                        />
                    ) : null}
                    {coherence.review_completed && coherence.review_note ? (
                        <p className="rounded border p-3">
                            Advisor decision: {coherence.review_note}
                        </p>
                    ) : null}
                    <AdvisorPlanBudgetReview
                        reviewUrl={reviewUrl}
                        recalculateUrl={recalculateUrl}
                        needsReview={
                            reviews.length > 0 && !coherence.review_completed
                        }
                        needsRecalculation={calculations.length > 0}
                    />
                </>
            ) : null}
            {advisor && advisories.length > 0 ? (
                <div className="space-y-3 border-t border-amber-200 pt-3">
                    <div className="space-y-1">
                        <p className="font-medium">
                            Before external issue — informational checks
                        </p>
                        <p className="text-muted-foreground">
                            These checks do not block this assessment or client
                            progression. They help the advisor prepare the plan
                            for external issue.
                        </p>
                    </div>
                    <ul className="space-y-3">
                        {advisories.map((advisory) => (
                            <li key={advisory.key} className="space-y-1">
                                <p className="font-medium">
                                    {advisory.message}
                                </p>
                                <p className="text-muted-foreground">
                                    Next action: {advisory.next_action}
                                </p>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </section>
    );
}

function FindingList({
    title,
    findings,
    showNextAction = true,
}: {
    title: string;
    findings: { message: string; next_action: string }[];
    showNextAction?: boolean;
}) {
    return (
        <section className="space-y-2 border-t pt-3">
            <h3 className="font-medium">{title}</h3>
            <ul className="space-y-3">
                {findings.map((finding, index) => (
                    <li key={index}>
                        <p>{finding.message}</p>
                        {showNextAction ? (
                            <p className="mt-1 text-muted-foreground">
                                {finding.next_action}
                            </p>
                        ) : null}
                    </li>
                ))}
            </ul>
        </section>
    );
}
