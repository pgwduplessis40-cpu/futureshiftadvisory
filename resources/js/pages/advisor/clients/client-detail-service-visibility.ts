import type { AdvisorServiceWorkspacePayload } from '@/components/advisor/AdvisorServiceWorkspaceSwitcher';

export function hasActiveBusinessPlanBudgetWorkspace(
    workspaces: AdvisorServiceWorkspacePayload,
): boolean {
    return workspaces.items.some(
        (workspace) => workspace.key === 'dd_plan_budget',
    );
}
