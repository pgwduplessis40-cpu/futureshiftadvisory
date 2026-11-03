import {
    ArrowRight,
    FileText,
    ChartNoAxesCombined,
    BookOpenCheck,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { EntrepreneurDetail } from './types';

type Brief = NonNullable<EntrepreneurDetail['latest_plan']>['lender_brief'];

export function BriefWorkflow({ brief }: { brief: Brief }) {
    const issues = brief.can_generate
        ? (brief.plan_guidance?.issues ?? [])
        : [];

    return (
        <div className="space-y-4">
            <p className="max-w-3xl text-sm text-muted-foreground">
                The client explains the business in the plan. Those assumptions
                guide the budget. The reviewed plan and calculated forecast
                become one funding brief.
            </p>
            <ol
                className="grid gap-3 lg:grid-cols-3"
                aria-label="Plan to funding brief"
            >
                {[
                    {
                        title: '1. Business plan',
                        icon: FileText,
                        text: 'Offers, customers, prices, capacity and the evidence behind them.',
                    },
                    {
                        title: '2. Budget',
                        icon: ChartNoAxesCombined,
                        text: 'Sales, costs and cash based on that plan. Differences are checked beside the inputs.',
                    },
                    {
                        title: '3. Founder brief',
                        icon: BookOpenCheck,
                        text: 'A concise business case, funding requirement and forecast for the investor or financier.',
                    },
                ].map(({ title, icon: Icon, text }) => (
                    <li
                        key={title}
                        className="rounded-md border bg-muted/20 p-4"
                    >
                        <div className="flex items-center gap-2 font-medium">
                            <Icon aria-hidden="true" className="size-4" />
                            {title}
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {text}
                        </p>
                    </li>
                ))}
            </ol>
            {!brief.active ? (
                <div className="space-y-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <p className="font-medium">
                        {issues.length > 0
                            ? 'Check these differences in the source plan and budget'
                            : 'Next step'}
                    </p>
                    {issues.length > 0 ? (
                        <ul className="grid gap-3 lg:grid-cols-2">
                            {issues.slice(0, 4).map((issue) => (
                                <li
                                    key={issue.key}
                                    className="space-y-1 rounded-md border border-amber-200 bg-white p-3"
                                >
                                    <p className="font-medium">{issue.title}</p>
                                    <p>{issue.detail}</p>
                                    <p className="text-muted-foreground">
                                        {issue.action}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="max-w-3xl">
                            {brief.generation_reason ??
                                brief.reasons[0] ??
                                'Review the saved plan and budget, then run the assessment.'}
                        </p>
                    )}
                    {issues.length > 4 ? (
                        <p>
                            {issues.length - 4} more{' '}
                            {issues.length - 4 === 1
                                ? 'difference appears'
                                : 'differences appear'}{' '}
                            in the budget guidance and review findings below.
                        </p>
                    ) : null}
                    <Button asChild size="sm" variant="outline">
                        <a href="#business-plan-budget">
                            Open business plan &amp; budget
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </a>
                    </Button>
                </div>
            ) : null}
        </div>
    );
}
