import { Link, usePage } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ActionPanel } from './plan-dashboard-panels';

type IdeaValidationPlanBudgetActionProps = {
    planBudgetUrl: string;
    isIdeaValidationOnly: boolean;
    ideaValidationApproved: boolean;
};

export function IdeaValidationPlanBudgetAction() {
    const { planBudgetUrl, isIdeaValidationOnly, ideaValidationApproved } =
        usePage<IdeaValidationPlanBudgetActionProps>().props;

    if (!isIdeaValidationOnly || !ideaValidationApproved) {
        return null;
    }

    return (
        <ActionPanel
            icon={ClipboardCheck}
            title="Business Plan & Budget"
            value="Available"
            explanation="Your advisor-approved Idea Validation is ready to carry into the Business Plan & Budget workspace after secure checkout."
        >
            <Button asChild size="sm" variant="outline">
                <Link href={planBudgetUrl}>View BP&amp;B options</Link>
            </Button>
        </ActionPanel>
    );
}
