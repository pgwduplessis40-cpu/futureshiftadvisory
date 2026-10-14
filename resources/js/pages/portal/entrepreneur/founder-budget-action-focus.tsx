import { MapPinned } from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import type { FounderBudgetActionFocus } from './founder-action-deep-link';

const focusDetails: Record<
    FounderBudgetActionFocus,
    { label: string; selector: string; instruction: string }
> = {
    'fixed-costs': {
        label: 'Regular business costs',
        selector: '[data-founder-action-target="fixed-costs-add-row"]',
        instruction:
            'The Add another cost button is highlighted below. Select it to add a new regular cost row.',
    },
    'forecast-start-month': {
        label: 'Forecast start month',
        selector: '[data-founder-action-target="forecast-start-month"]',
        instruction:
            'Choose the month that should be Month 1 in your forecast, then confirm it against your milestones.',
    },
    'revenue-forecast': {
        label: 'Sales forecast',
        selector: '[data-founder-action-target="revenue-forecast"]',
        instruction:
            'Use this list to record the sales assumptions that fund the plan.',
    },
    'funding-sources': {
        label: 'Funding and cash',
        selector: '[data-founder-action-target="funding-sources"]',
        instruction:
            'Use this list to record real funding or cash that is available to the business.',
    },
    'budget-overview': {
        label: 'Budget editor',
        selector: '[data-founder-action-target="budget-overview"]',
        instruction:
            'This is the Budget editor used for the changes in your advisor’s message.',
    },
};

export function FounderBudgetActionFocus({
    focus,
    showGuide,
    children,
}: {
    focus: FounderBudgetActionFocus | null;
    showGuide: boolean;
    children: ReactNode;
}) {
    const container = useRef<HTMLDivElement>(null);
    const details = focus ? focusDetails[focus] : null;

    useEffect(() => {
        if (!details || !container.current) {
            return;
        }

        const highlighted = container.current.querySelector<HTMLElement>(
            details.selector,
        );
        const scrollFrame = window.requestAnimationFrame(() => {
            highlighted?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        });

        if (!showGuide || !highlighted) {
            return () => window.cancelAnimationFrame(scrollFrame);
        }

        highlighted.classList.add(
            'rounded-md',
            'ring-4',
            'ring-sky-500',
            'ring-offset-4',
            'shadow-lg',
        );

        return () => {
            window.cancelAnimationFrame(scrollFrame);
            highlighted.classList.remove(
                'rounded-md',
                'ring-4',
                'ring-sky-500',
                'ring-offset-4',
                'shadow-lg',
            );
        };
    }, [details, showGuide]);

    return (
        <div ref={container} className="space-y-4">
            {showGuide && details ? (
                <aside className="rounded-md border border-sky-200 bg-sky-50 p-3 text-sm text-sky-950">
                    <div className="flex items-start gap-2">
                        <MapPinned
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <div>
                            <p className="font-medium">
                                You are here: Financial assumptions →{' '}
                                {details.label}
                            </p>
                            <p className="mt-1 text-sky-900">
                                {details.instruction}
                            </p>
                        </div>
                    </div>
                </aside>
            ) : null}
            {children}
        </div>
    );
}
