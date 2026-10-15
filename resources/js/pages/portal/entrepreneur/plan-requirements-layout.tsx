import { PanelLeftClose, PanelLeftOpen } from 'lucide-react';
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

export function planRequirementsGridClass(collapsed: boolean): string {
    return cn(
        'relative grid items-start gap-6',
        collapsed
            ? 'xl:grid-cols-[minmax(0,1fr)]'
            : 'xl:grid-cols-[minmax(15rem,0.58fr)_minmax(0,1.42fr)]',
    );
}

export function planRequirementsNavigationClass(collapsed: boolean): string {
    return cn(
        'space-y-3 xl:self-start',
        collapsed
            ? 'xl:absolute xl:top-0 xl:left-0 xl:z-10 xl:max-w-xs xl:rounded-md xl:border xl:bg-background/95 xl:p-2 xl:shadow-sm xl:backdrop-blur'
            : 'xl:sticky xl:top-24',
    );
}

export function PlanRequirementsLayout({
    phases,
    selectedRequirement,
    onSelect,
    children,
}: PlanRequirementsLayoutProps) {
    const [collapsed, setCollapsed] = useState(false);
    const incompleteCount = phases
        .flatMap((phase) => phase.requirements)
        .filter((requirement) => !requirement.complete).length;

    return (
        <div className={planRequirementsGridClass(collapsed)}>
            <nav
                className={planRequirementsNavigationClass(collapsed)}
                aria-label="Plan requirements"
                data-co-browse-target="entrepreneur.plan.steps"
            >
                <div
                    className={cn(
                        'flex items-center gap-2',
                        collapsed && 'flex-col items-stretch',
                    )}
                >
                    <Button
                        type="button"
                        variant="outline"
                        className={cn('shrink-0', collapsed && 'justify-start')}
                        title={
                            collapsed
                                ? 'Show plan steps'
                                : 'Minimise plan steps'
                        }
                        aria-label={
                            collapsed
                                ? 'Show plan steps'
                                : 'Minimise plan steps'
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
                        <span>
                            {collapsed ? 'Plan steps' : 'Minimise steps'}
                        </span>
                    </Button>
                    <span className="text-xs text-muted-foreground">
                        {collapsed
                            ? `${selectedRequirement?.title ?? 'No step selected'}${incompleteCount > 0 ? ` · ${incompleteCount} need attention` : ' · all complete'}`
                            : 'Hide this list for more editing room'}
                    </span>
                </div>

                {!collapsed
                    ? phases.map((phase) => (
                          <div key={phase.key} className="space-y-2">
                              <div className="text-xs font-medium text-muted-foreground">
                                  {phase.title}
                              </div>
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
                                              'w-full p-3',
                                              selected && 'border-foreground',
                                          )}
                                          title={`${requirement.title}: ${requirement.complete ? 'Complete' : 'Needs attention'}`}
                                          aria-label={`${requirement.title}: ${requirement.complete ? 'Complete' : 'Needs attention'}`}
                                          onClick={() => {
                                              onSelect(requirement);
                                          }}
                                      >
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
                                      </button>
                                  );
                              })}
                          </div>
                      ))
                    : null}
            </nav>

            <div className={cn('min-w-0', collapsed && 'xl:pt-20')}>
                {children}
            </div>
        </div>
    );
}
