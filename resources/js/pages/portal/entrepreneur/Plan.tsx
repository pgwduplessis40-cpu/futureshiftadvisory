import type { Props } from './plan-types';
import { PlanWorkspaceLayout } from './plan-workspace-layout';
import { usePlanWorkspace } from './use-plan-workspace';

export default function EntrepreneurPlan(props: Props) {
    return <PlanWorkspaceLayout {...usePlanWorkspace(props)} />;
}

EntrepreneurPlan.layout = {
    breadcrumbs: [
        {
            title: 'Business Plan',
            href: '/portal/entrepreneur/plan',
        },
    ],
};
