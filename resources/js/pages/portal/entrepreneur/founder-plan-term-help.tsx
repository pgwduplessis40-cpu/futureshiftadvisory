import { Explainer } from '@/components/explainer';
import type { Explanation } from '@/components/explainer';

type FounderPlanTerm =
    | 'customer-problem'
    | 'demand-evidence'
    | 'operating-model'
    | 'revenue-model'
    | 'solution'
    | 'target-customer'
    | 'value-proposition';

const explanations: Record<FounderPlanTerm, Explanation> = {
    'customer-problem': {
        title: 'Customer problem',
        what: 'A real difficulty, cost, risk, or frustration a specific person or business experiences.',
        action: 'Describe who has the problem, when it happens, and what it costs them before describing your solution.',
        why: 'A useful plan begins with a problem people recognise and are willing to solve.',
    },
    'demand-evidence': {
        title: 'Demand signal',
        what: 'Evidence that real people want, need, or will pay attention to the proposed offer.',
        action: 'Use interviews, quotes, bookings, deposits, pilots, repeat requests, or sales. Say what happened and how many people were involved.',
        why: 'It separates a promising idea from an assumption that still needs testing.',
    },
    'operating-model': {
        title: 'Operating model',
        what: 'The practical way the business delivers its offer: people, process, location, suppliers, tools, and capacity.',
        action: 'Explain how one customer order moves from first contact to delivery and what resources are needed.',
        why: 'It connects the promise to the real costs and capacity used in the budget.',
    },
    'revenue-model': {
        title: 'Revenue model',
        what: 'How the business earns, collects, and keeps income over time.',
        action: 'Explain who pays, what they pay for, the price, and whether payment is one-off, recurring, or both.',
        why: 'It gives the budget a credible basis for sales timing and cash receipts.',
    },
    solution: {
        title: 'Solution',
        what: 'The specific product or service that helps the customer solve the problem.',
        action: 'Describe the first practical offer, what the customer receives, and the result it creates.',
        why: 'A clear first offer makes pricing, delivery, costs, and customer feedback easier to test.',
    },
    'target-customer': {
        title: 'Target customer',
        what: 'The first specific group most likely to have the problem and buy the offer.',
        action: 'Name a narrow group, where they are, and the situation that makes the problem important to them.',
        why: 'Trying to serve everyone makes demand, marketing, pricing, and sales assumptions less reliable.',
    },
    'value-proposition': {
        title: 'Value proposition',
        what: 'The clear reason a customer would choose this offer instead of an alternative or doing nothing.',
        action: 'State the useful outcome in plain language, such as saved time, less risk, lower cost, or better results.',
        why: 'It helps show why demand and pricing assumptions are believable.',
    },
};

const ideaFieldTerms: Partial<Record<string, FounderPlanTerm>> = {
    'Demand signal': 'demand-evidence',
    Problem: 'customer-problem',
    'Revenue model': 'revenue-model',
    Solution: 'solution',
    'Target customer': 'target-customer',
    'Value proposition': 'value-proposition',
};

export function FounderPlanTermLabel({
    term,
    children,
}: {
    term: FounderPlanTerm;
    children: string;
}) {
    return (
        <span className="inline-flex items-center gap-1">
            <span>{children}</span>
            <Explainer
                explanation={explanations[term]}
                label={`Explain ${children}`}
                triggerClassName="size-5 border-transparent bg-transparent"
                contentClassName="text-left"
            />
        </span>
    );
}

export function IdeaValidationFieldLabel({ label }: { label: string }) {
    const term = ideaFieldTerms[label];

    return term ? (
        <FounderPlanTermLabel term={term}>{label}</FounderPlanTermLabel>
    ) : (
        label
    );
}
