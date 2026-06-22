import { AlertCircle, CheckCircle2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type CompletionSummaryBadgesProps = {
    total: number;
    completed: number;
    itemSingular: string;
    itemPlural: string;
};

export function CompletionSummaryBadges({
    total,
    completed,
    itemSingular,
    itemPlural,
}: CompletionSummaryBadgesProps) {
    const safeCompleted = Math.min(Math.max(completed, 0), total);
    const outstanding = Math.max(total - safeCompleted, 0);
    const itemLabel = total === 1 ? itemSingular : itemPlural;
    const outstandingLabel = outstanding === 1 ? itemSingular : itemPlural;
    const completedLabel = safeCompleted === 1 ? itemSingular : itemPlural;

    return (
        <div className="flex flex-wrap items-center justify-end gap-1.5">
            <Tooltip>
                <TooltipTrigger asChild>
                    <Badge
                        variant="outline"
                        tabIndex={0}
                        className={
                            outstanding > 0
                                ? 'border-red-200 bg-red-50 text-red-700'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        }
                    >
                        <AlertCircle className="size-3" aria-hidden="true" />
                        {outstanding} outstanding
                    </Badge>
                </TooltipTrigger>
                <TooltipContent>
                    {outstanding === 0
                        ? `All ${itemLabel} are completed.`
                        : `${outstanding} ${outstandingLabel} still ${
                              outstanding === 1 ? 'needs' : 'need'
                          } to be completed.`}
                </TooltipContent>
            </Tooltip>

            <Tooltip>
                <TooltipTrigger asChild>
                    <Badge
                        variant="outline"
                        tabIndex={0}
                        className="border-emerald-200 bg-emerald-50 text-emerald-700"
                    >
                        <CheckCircle2 className="size-3" aria-hidden="true" />
                        {safeCompleted} completed
                    </Badge>
                </TooltipTrigger>
                <TooltipContent>
                    {safeCompleted} {completedLabel}{' '}
                    {safeCompleted === 1 ? 'is' : 'are'} completed.
                </TooltipContent>
            </Tooltip>

            <Tooltip>
                <TooltipTrigger asChild>
                    <Badge variant="outline" tabIndex={0}>
                        {total} total
                    </Badge>
                </TooltipTrigger>
                <TooltipContent>
                    {total} {itemLabel} in this section.
                </TooltipContent>
            </Tooltip>
        </div>
    );
}
