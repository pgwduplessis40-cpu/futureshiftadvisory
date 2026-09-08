import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type PlanBudgetCoherenceSummary = {
    status_label: string;
    summary: string;
    approval_available: boolean;
    budget_support: {
        summary: string;
    };
    plan_correlation: {
        summary: string;
    };
    findings: {
        category: string;
        message: string;
        next_action: string;
    }[];
};

type Props = {
    coherence: PlanBudgetCoherenceSummary | null;
    heading?: 'h2' | 'h3';
    historicalMessage: string;
};

export function PlanBudgetCoherencePanel({
    coherence,
    heading = 'h2',
    historicalMessage,
}: Props) {
    const Heading = heading;

    if (coherence === null) {
        return (
            <section
                className="flex items-start gap-3 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950"
                role="alert"
            >
                <AlertTriangle
                    className="mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div className="space-y-1">
                    <Heading className="font-medium">
                        Plan–budget coherence not assessed
                    </Heading>
                    <p>{historicalMessage}</p>
                </div>
            </section>
        );
    }

    return (
        <section
            className={cn(
                'space-y-3 rounded-md border p-4 text-sm',
                coherence.approval_available
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-950'
                    : 'border-destructive/40 bg-destructive/5 text-foreground',
            )}
            role={coherence.approval_available ? 'status' : 'alert'}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex min-w-0 items-start gap-3">
                    {coherence.approval_available ? (
                        <CheckCircle2
                            className="mt-0.5 size-5 shrink-0"
                            aria-hidden="true"
                        />
                    ) : (
                        <AlertTriangle
                            className="mt-0.5 size-5 shrink-0 text-destructive"
                            aria-hidden="true"
                        />
                    )}
                    <div className="space-y-1">
                        <Heading className="font-medium">
                            Plan–budget coherence
                        </Heading>
                        <p className="max-w-4xl text-muted-foreground">
                            {coherence.summary}
                        </p>
                    </div>
                </div>
                <Badge
                    variant={
                        coherence.approval_available
                            ? 'secondary'
                            : 'destructive'
                    }
                >
                    {coherence.status_label}
                </Badge>
            </div>

            <div className="grid gap-3 md:grid-cols-2">
                <div className="rounded border bg-background/70 p-3">
                    <p className="font-medium">
                        Does the budget support the plan?
                    </p>
                    <p className="mt-1 text-muted-foreground">
                        {coherence.budget_support.summary}
                    </p>
                </div>
                <div className="rounded border bg-background/70 p-3">
                    <p className="font-medium">
                        Does the plan correlate to the budget?
                    </p>
                    <p className="mt-1 text-muted-foreground">
                        {coherence.plan_correlation.summary}
                    </p>
                </div>
            </div>

            {coherence.findings.length > 0 ? (
                <ul className="space-y-3 border-t pt-3">
                    {coherence.findings.map((finding, index) => (
                        <li
                            key={`${finding.category}-${finding.message}-${index}`}
                            className="space-y-1"
                        >
                            <p className="font-medium">{finding.message}</p>
                            <p className="text-muted-foreground">
                                Next action: {finding.next_action}
                            </p>
                        </li>
                    ))}
                </ul>
            ) : null}
        </section>
    );
}
