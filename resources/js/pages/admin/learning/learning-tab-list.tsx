import { ClipboardCheck, Info, ListChecks } from 'lucide-react';
import { cn } from '@/lib/utils';

export type LearningTab = 'actions' | 'impact_reviews' | 'information';

export function LearningTabList({
    activeTab,
    onChange,
}: {
    activeTab: LearningTab;
    onChange: (tab: LearningTab) => void;
}) {
    const tabs: Array<{
        key: LearningTab;
        label: string;
        description: string;
    }> = [
        {
            key: 'actions',
            label: 'Actions',
            description:
                'Approve, defer, reject, or roll back learning updates.',
        },
        {
            key: 'impact_reviews',
            label: 'Impact reviews',
            description:
                'Confirm the observed outcome of implemented learning changes.',
        },
        {
            key: 'information',
            label: 'Information',
            description: 'Review monitor layers, cadence, and run history.',
        },
    ];

    return (
        <div
            className="inline-flex w-full max-w-xl rounded-md border bg-muted/30 p-1"
            role="tablist"
            aria-label="Learning update sections"
        >
            {tabs.map((tab) => (
                <button
                    key={tab.key}
                    type="button"
                    role="tab"
                    aria-selected={activeTab === tab.key}
                    className={cn(
                        'flex flex-1 items-center justify-center gap-2 rounded-sm px-3 py-2 text-sm font-medium transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                        activeTab === tab.key
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                    onClick={() => onChange(tab.key)}
                    title={tab.description}
                >
                    {tab.key === 'actions' ? (
                        <ListChecks className="size-4" aria-hidden="true" />
                    ) : tab.key === 'impact_reviews' ? (
                        <ClipboardCheck className="size-4" aria-hidden="true" />
                    ) : (
                        <Info className="size-4" aria-hidden="true" />
                    )}
                    {tab.label}
                </button>
            ))}
        </div>
    );
}
