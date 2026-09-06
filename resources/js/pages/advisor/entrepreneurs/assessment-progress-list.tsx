import { InsightHoverCard } from '@/components/insight/InsightHoverCard';
import { Badge } from '@/components/ui/badge';
import type { CriterionDelta } from './types';

type Props = {
    title: string;
    rows: CriterionDelta[];
    empty: string;
    comparisonMode?: 'movement' | 'current';
};

export function AssessmentProgressList({
    title,
    rows,
    empty,
    comparisonMode = 'movement',
}: Props) {
    return (
        <div className="space-y-3">
            <h3 className="text-xs font-medium text-muted-foreground">
                {title}
            </h3>
            {rows.length > 0 ? (
                <div className="space-y-2">
                    {rows.map((row) => (
                        <InsightHoverCard
                            key={`${row.criterion_number}-${row.direction}`}
                            title={row.criterion_name}
                            rows={
                                comparisonMode === 'current'
                                    ? [
                                          {
                                              label: 'Current',
                                              value: row.current_score,
                                          },
                                      ]
                                    : [
                                          {
                                              label: 'Previous',
                                              value: row.previous_score ?? '-',
                                          },
                                          {
                                              label: 'Current',
                                              value: row.current_score,
                                          },
                                          {
                                              label: 'Movement',
                                              value: formatDelta(row.delta),
                                              tone:
                                                  row.delta >= 0
                                                      ? 'positive'
                                                      : 'negative',
                                          },
                                      ]
                            }
                            footer={
                                comparisonMode === 'current'
                                    ? 'Current score from an assessment basis change; it is not a comparison with the prior round.'
                                    : 'Movement compares this criterion against the previous round.'
                            }
                        >
                            <div className="flex cursor-help items-center justify-between gap-3 rounded-md border p-3 text-sm">
                                <div className="min-w-0">
                                    <div className="truncate font-medium">
                                        {row.criterion_name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {comparisonMode === 'current'
                                            ? `${row.current_score}/100`
                                            : `${row.previous_score ?? '-'} -> ${row.current_score}`}
                                    </div>
                                </div>
                                <Badge
                                    variant={
                                        comparisonMode === 'current'
                                            ? 'outline'
                                            : row.delta >= 0
                                              ? 'secondary'
                                              : 'outline'
                                    }
                                >
                                    {comparisonMode === 'current'
                                        ? 'Current'
                                        : formatDelta(row.delta)}
                                </Badge>
                            </div>
                        </InsightHoverCard>
                    ))}
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">{empty}</p>
            )}
        </div>
    );
}

function formatDelta(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '-';
    }

    return `${value >= 0 ? '+' : ''}${value.toFixed(1)}`;
}
