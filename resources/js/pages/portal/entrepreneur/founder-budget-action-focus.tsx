import { MapPinned } from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import type { FounderBudgetActionFocus } from './founder-action-deep-link';

const focusDetails: Record<
    FounderBudgetActionFocus,
    { label: string; target: string; instruction: string }
> = {
    'fixed-costs': {
        label: 'Regular business costs',
        target: 'What will you pay every month even if sales are slow?',
        instruction:
            'Use this cost list to check existing costs or select Add another cost for a new one.',
    },
    'forecast-start-month': {
        label: 'Forecast start month',
        target: 'Forecast start month',
        instruction:
            'Choose the month that should be Month 1 in your forecast, then confirm it against your milestones.',
    },
    'revenue-forecast': {
        label: 'Sales forecast',
        target: 'How will money come in?',
        instruction:
            'Use this list to record the sales assumptions that fund the plan.',
    },
    'funding-sources': {
        label: 'Funding and cash',
        target: 'What funding or cash is available?',
        instruction:
            'Use this list to record real funding or cash that is available to the business.',
    },
    'budget-overview': {
        label: 'Budget editor',
        target: 'Budget setup assistant',
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

        const target = Array.from(
            container.current.querySelectorAll<HTMLElement>(
                'div, h2, h3, h4, h5, label, span',
            ),
        ).find((element) => element.textContent?.trim() === details.target);
        const highlighted = target?.closest('section') ?? target;
        const scrollFrame = window.requestAnimationFrame(() => {
            highlighted?.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
        });

        if (!showGuide || !highlighted) {
            return () => window.cancelAnimationFrame(scrollFrame);
        }

        highlighted.classList.add(
            'rounded-md',
            'ring-2',
            'ring-sky-500',
            'ring-offset-4',
        );

        return () => {
            window.cancelAnimationFrame(scrollFrame);
            highlighted.classList.remove(
                'rounded-md',
                'ring-2',
                'ring-sky-500',
                'ring-offset-4',
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
