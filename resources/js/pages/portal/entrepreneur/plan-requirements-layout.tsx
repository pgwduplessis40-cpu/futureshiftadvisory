import { Check, PanelLeftClose, PanelLeftOpen } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type {
    PlanRequirementPayload,
    PlanTemplatePhasePayload,
} from './plan-types';

type PlanRequirementsLayoutProps = {
    phases: PlanTemplatePhasePayload[];
    selectedRequirement: PlanRequirementPayload | null;
    onSelect: (requirement: PlanRequirementPayload) => void;
    children: ReactNode;
};

function idFor(requirement: PlanRequirementPayload): string {
    return `${requirement.phase_key}:${requirement.key}`;
}

export function PlanRequirementsLayout({
    phases,
    selectedRequirement,
    onSelect,
    children,
}: PlanRequirementsLayoutProps) {
    const [collapsed, setCollapsed] = useState(false);

    return (
        <div
            className={cn(
                'grid items-start gap-6',
                collapsed
                    ? 'xl:grid-cols-[3.75rem_minmax(0,1fr)]'
                    : 'xl:grid-cols-[minmax(15rem,0.58fr)_minmax(0,1.42fr)]',
            )}
        >
            <nav
                className="space-y-3 xl:sticky xl:top-24 xl:self-start"
                aria-label="Plan requirements"
            >
                <div
                    className={cn(
                        'flex items-center gap-2',
                        collapsed && 'justify-center',
                    )}
                >
                    <Button
                        type="button"
                        size="icon"
                        variant="outline"
                        title={
                            collapsed
                                ? 'Expand plan requirements'
                                : 'Collapse plan requirements'
                        }
                        aria-label={
                            collapsed
                                ? 'Expand plan requirements'
                                : 'Collapse plan requirements'
                        }
                        onClick={() => setCollapsed((current) => !current)}
                    >
                        {collapsed ? (
                            <PanelLeftOpen
                                className="size-4"
                                aria-hidden="true"
                            />
                        ) : (
                            <PanelLeftClose
                                className="size-4"
                                aria-hidden="true"
                            />
                        )}
                    </Button>
                    {!collapsed ? (
                        <span className="text-xs text-muted-foreground">
                            Hide this list for more editing room
                        </span>
                    ) : null}
                </div>

                {phases.map((phase) => (
                    <div key={phase.key} className="space-y-2">
                        {!collapsed ? (
                            <div className="text-xs font-medium text-muted-foreground">
                                {phase.title}
                            </div>
                        ) : null}
                        {phase.requirements.map((requirement) => {
                            const selected =
                                idFor(requirement) ===
                                (selectedRequirement
                                    ? idFor(selectedRequirement)
                                    : '');

                            return (
                                <button
                                    key={idFor(requirement)}
                                    type="button"
                                    className={cn(
                                        'rounded-md border text-left text-sm transition-colors outline-none hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                        collapsed
                                            ? 'relative flex size-12 items-center justify-center p-1'
                                            : 'w-full p-3',
                                        selected && 'border-foreground',
                                        !requirement.complete &&
                                            collapsed &&
                                            'border-amber-400 bg-amber-50 text-amber-950',
                                    )}
                                    title={`${requirement.title}: ${requirement.complete ? 'Complete' : 'Needs attention'}`}
                                    aria-label={`${requirement.title}: ${requirement.complete ? 'Complete' : 'Needs attention'}`}
                                    onClick={() => {
                                        onSelect(requirement);

                                        if (collapsed) {
                                            setCollapsed(false);
                                        }
                                    }}
                                >
                                    {collapsed ? (
                                        <>
                                            {requirement.complete ? (
                                                <Check
                                                    className="size-5"
                                                    aria-hidden="true"
                                                />
                                            ) : (
                                                <span
                                                    className="font-medium"
                                                    aria-hidden="true"
                                                >
                                                    {requirement.title
                                                        .trim()
                                                        .slice(0, 1)
                                                        .toUpperCase()}
                                                </span>
                                            )}
                                            <span className="sr-only">
                                                {requirement.title}
                                            </span>
                                        </>
                                    ) : (
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <div className="font-medium">
                                                    {requirement.title}
                                                </div>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {requirement.description}
                                                </p>
                                            </div>
                                            <Badge
                                                variant={
                                                    requirement.complete
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {requirement.complete
                                                    ? 'Complete'
                                                    : 'Needed'}
                                            </Badge>
                                        </div>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                ))}
            </nav>

            {children}
        </div>
    );
}
