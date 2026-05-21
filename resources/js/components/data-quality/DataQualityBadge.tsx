import { Info } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

export type DataQualityLevel = 'high' | 'medium' | 'low' | 'insufficient';

export type DataQualityComponent = {
    key: string;
    label: string;
    score: number;
    weight: number;
    summary: string;
    detail: string;
};

export type DataQualitySummary = {
    level: DataQualityLevel;
    label: string;
    score: number;
    message: string;
    components: DataQualityComponent[];
};

export function DataQualityBadge({
    summary,
    showMessage = true,
    className,
}: {
    summary: DataQualitySummary;
    showMessage?: boolean;
    className?: string;
}) {
    const needsImprovement =
        summary.level === 'low' || summary.level === 'insufficient';

    return (
        <div className={cn('space-y-2', className)}>
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            className="inline-flex items-center gap-1 rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            aria-label={`Data quality ${summary.label}: ${summary.message}`}
                        >
                            <Badge variant={variantFor(summary.level)}>
                                {summary.label}
                            </Badge>
                            <Info
                                className="size-3 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent className="max-w-80 text-left">
                        <div className="space-y-2">
                            <div>
                                <div className="font-medium">
                                    {summary.score}% data quality
                                </div>
                                <p className="mt-1 text-primary-foreground/80">
                                    {summary.message}
                                </p>
                            </div>
                            <div className="space-y-2">
                                {summary.components.map((component) => (
                                    <div
                                        key={component.key}
                                        className="space-y-0.5"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <span>{component.label}</span>
                                            <span>{component.score}%</span>
                                        </div>
                                        <p className="text-primary-foreground/80">
                                            {component.summary}
                                        </p>
                                        <p className="text-primary-foreground/70">
                                            {component.detail}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>

            {showMessage && needsImprovement && (
                <p className="text-xs font-medium text-amber-700 dark:text-amber-300">
                    Improve data first
                </p>
            )}
        </div>
    );
}

function variantFor(level: DataQualityLevel) {
    if (level === 'high') {
        return 'default';
    }

    if (level === 'medium') {
        return 'secondary';
    }

    if (level === 'low') {
        return 'outline';
    }

    return 'destructive';
}
