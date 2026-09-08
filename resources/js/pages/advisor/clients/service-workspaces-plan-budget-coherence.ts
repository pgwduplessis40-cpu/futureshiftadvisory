export type StrategicBudgetAssessmentCriterion = {
    key: string;
    title: string;
    status: 'met' | 'review' | 'missing';
    status_label: string;
    score: number;
    summary: string;
    evidence: string[];
    blocking?: boolean;
    findings?: Array<{
        severity: string;
        message: string;
        next_action: string;
    }>;
};

export type StrategicBudgetPlanBudgetCoherence = {
    status: 'met' | 'review' | 'missing';
    status_label: string;
    score: number;
    summary: string;
    approval_available: boolean;
    approval_message: string;
    unresolved_count: number;
};

export type StrategicBudgetPlanBudgetReconciliation = {
    status: 'met' | 'review' | 'missing';
    status_label: string;
    score: number;
    summary: string;
    approval_available: boolean;
    approval_message: string;
    unresolved_count: number;
};

export type PlanBudgetApprovalState = {
    review_approved_or_later: boolean;
    review_submitted_or_later: boolean;
    business_plan_ready: boolean;
    locked: boolean;
    assessment_ready_for_approval: boolean;
    plan_budget_coherence_ready_for_approval: boolean;
    plan_budget_reconciliation_ready_for_approval: boolean;
    plan_budget_coherence: StrategicBudgetPlanBudgetCoherence;
    plan_budget_reconciliation: StrategicBudgetPlanBudgetReconciliation;
};

export function planBudgetApprovalBlockedReason(
    budget: PlanBudgetApprovalState,
): string {
    return budget.review_approved_or_later
        ? 'The BP&B assessment is already approved.'
        : !budget.review_submitted_or_later
          ? 'Approval unlocks after the client submits BP&B for advisor review.'
          : !budget.business_plan_ready
            ? 'Approval unlocks after every BP&B section is complete.'
            : budget.locked
              ? 'Approval unlocks after verified financial evidence is available.'
              : !budget.assessment_ready_for_approval
                ? 'Approval unlocks after the BP&B assessment has been run.'
                : !budget.plan_budget_coherence_ready_for_approval
                  ? budget.plan_budget_coherence.approval_message
                  : !budget.plan_budget_reconciliation_ready_for_approval
                    ? budget.plan_budget_reconciliation.approval_message
                    : 'Approve only after the assessment has been reviewed.';
}
