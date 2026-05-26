import { Activity } from 'lucide-react';
import { BusinessHealthRadar } from '@/components/insight/BusinessHealthRadar';
import type { BusinessHealthRadarAxis } from '@/components/insight/BusinessHealthRadar';
import { Badge } from '@/components/ui/badge';

type NpoHealthFinding = {
    id?: string;
    severity?: string;
    title?: string;
    body?: string;
    attributions?: Array<{
        claim?: string;
        source_reference?: string;
        [key: string]: unknown;
    }>;
};

type NpoHealthDimensionFindings = {
    dimension: string;
    label: string;
    findings: NpoHealthFinding[];
};

export type NpoHealthPayload = {
    npo_engagement_id: string;
    health_score: number | null;
    captured_at: string | null;
    tiriti_mode: string | null;
    axes: BusinessHealthRadarAxis[];
    findings: NpoHealthDimensionFindings[];
};

export function NpoHealthPanel({
    payload,
    title = 'NPO health',
}: {
    payload: NpoHealthPayload;
    title?: string;
}) {
    return (
        <section
            id="section-npo-health"
            className="space-y-5 rounded-md border bg-background p-4"
            aria-labelledby="npo-health-heading"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Activity className="size-4" aria-hidden="true" />
                    <h2 id="npo-health-heading" className="text-sm font-medium">
                        {title}
                    </h2>
                    <Badge variant="outline">
                        {payload.tiriti_mode
                            ? formatLabel(payload.tiriti_mode)
                            : 'Unconfigured'}
                    </Badge>
                </div>
                <Badge variant={scoreVariant(payload.health_score)}>
                    {payload.health_score === null
                        ? 'Pending'
                        : `${payload.health_score}/100`}
                </Badge>
            </div>

            <BusinessHealthRadar payload={payload} />

            {payload.findings.length > 0 && (
                <div className="grid gap-3 lg:grid-cols-4">
                    {payload.findings.map((dimension) => (
                        <article
                            key={dimension.dimension}
                            className="space-y-3 rounded-md border p-3"
                        >
                            <div className="flex items-center justify-between gap-2">
                                <h3 className="text-sm font-medium">
                                    {dimension.label}
                                </h3>
                                <Badge variant="outline">
                                    {dimension.findings.length}
                                </Badge>
                            </div>

                            {dimension.findings.length === 0 ? (
                                <p className="text-xs text-muted-foreground">
                                    No findings recorded.
                                </p>
                            ) : (
                                <div className="space-y-2">
                                    {dimension.findings.map(
                                        (finding, index) => (
                                            <article
                                                key={
                                                    finding.id ??
                                                    `${dimension.dimension}-${index}`
                                                }
                                                className="space-y-1 rounded-md bg-muted/40 p-3"
                                            >
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="text-sm font-medium">
                                                        {finding.title ??
                                                            'Finding'}
                                                    </span>
                                                    {finding.severity && (
                                                        <Badge
                                                            variant={severityVariant(
                                                                finding.severity,
                                                            )}
                                                        >
                                                            {formatLabel(
                                                                finding.severity,
                                                            )}
                                                        </Badge>
                                                    )}
                                                </div>
                                                {finding.body && (
                                                    <p className="text-sm text-muted-foreground">
                                                        {finding.body}
                                                    </p>
                                                )}
                                            </article>
                                        ),
                                    )}
                                </div>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function scoreVariant(
    score: number | null,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (score === null) {
        return 'outline';
    }

    if (score < 50) {
        return 'destructive';
    }

    if (score < 75) {
        return 'secondary';
    }

    return 'default';
}

function severityVariant(
    severity: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    return ['critical', 'high'].includes(severity) ? 'destructive' : 'outline';
}
