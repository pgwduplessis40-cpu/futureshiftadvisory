import { Badge } from '@/components/ui/badge';
import type { PlanPhasePayload } from './plan-types';

export function CompletedPlanRequirements({
    phases,
}: {
    phases: PlanPhasePayload[];
}) {
    const requirements = phases.flatMap((phase) => phase.requirements);
    const completed = requirements.filter(
        (requirement) => requirement.complete,
    );

    return (
        <details className="rounded-md border bg-muted/20 p-4">
            <summary className="flex cursor-pointer list-none flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="text-sm font-medium">
                        Completed plan requirements
                    </h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        This submitted version is read-only while your advisor
                        completes the review.
                    </p>
                </div>
                <Badge variant="secondary">
                    {completed.length}/{requirements.length} complete
                </Badge>
            </summary>

            <div className="mt-4 space-y-3 border-t pt-4">
                {phases.map((phase) => {
                    const phaseCompleted = phase.requirements.filter(
                        (requirement) => requirement.complete,
                    );

                    return (
                        <details
                            key={phase.key}
                            className="rounded-md border bg-background p-3"
                        >
                            <summary className="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3">
                                <span className="text-sm font-medium">
                                    {phase.title}
                                </span>
                                <Badge variant="secondary">
                                    {phaseCompleted.length}/
                                    {phase.requirements.length} complete
                                </Badge>
                            </summary>
                            <ul className="mt-3 divide-y border-t">
                                {phase.requirements.map((requirement) => (
                                    <li
                                        key={requirement.key}
                                        className="flex items-center justify-between gap-3 py-3 text-sm"
                                    >
                                        <span>{requirement.title}</span>
                                        <Badge variant="secondary">
                                            Complete
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        </details>
                    );
                })}
            </div>
        </details>
    );
}
