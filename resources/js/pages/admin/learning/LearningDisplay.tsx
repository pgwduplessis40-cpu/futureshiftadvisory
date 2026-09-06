import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

export type PlainEnglishSummary = {
    what_we_learnt: string;
    why_it_matters: string;
    review_decision: string;
    signals: string[];
};

export function PlainEnglishSummaryBlock({
    summary,
    compact = false,
}: {
    summary: PlainEnglishSummary;
    compact?: boolean;
}) {
    return (
        <div className="text-sm">
            <p className="leading-6 font-medium text-foreground">
                {summary.what_we_learnt}
            </p>
            <p className="mt-2 text-xs leading-5 text-muted-foreground">
                {compact ? summary.review_decision : summary.why_it_matters}
            </p>
            {!compact && (
                <p className="mt-2 rounded-md border bg-muted/30 px-3 py-2 text-xs leading-5 text-muted-foreground">
                    <span className="font-medium text-foreground">
                        Decision needed:{' '}
                    </span>
                    {summary.review_decision}
                </p>
            )}
            {summary.signals.length > 0 && !compact && (
                <ul className="mt-2 space-y-1 text-xs text-muted-foreground">
                    {summary.signals.map((signal) => (
                        <li key={signal}>{signal}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export function TableStat({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="font-medium">{value}</div>
        </div>
    );
}

export function Metric({
    label,
    value,
    explanation,
}: {
    label: string;
    value: string;
    explanation?: string;
}) {
    const metric = (
        <div className="rounded-md border px-3 py-2">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-1 font-medium">{value}</dd>
        </div>
    );

    if (!explanation) {
        return metric;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>{metric}</TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {explanation}
            </TooltipContent>
        </Tooltip>
    );
}
