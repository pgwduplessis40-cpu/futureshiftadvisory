import { router } from '@inertiajs/react';
import { CheckCircle2, FileText } from 'lucide-react';
import { InsightHoverCard } from '@/components/insight/InsightHoverCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { EntrepreneurDetail } from './types';

type LenderBrief = NonNullable<
    EntrepreneurDetail['latest_plan']
>['lender_brief'];

type Props = {
    lenderBrief: LenderBrief | undefined;
};

type ConversionProps = {
    conversion: EntrepreneurDetail['conversion'];
};

type FinaliseReportActionProps = {
    assessment: {
        threshold: number;
        finalise_url: string;
    };
    ready: boolean;
    blocked: boolean;
    blockingMessage: string | null;
};

export function AdvisoryConversionAction({ conversion }: ConversionProps) {
    return (
        <>
            {conversion.request_active ? (
                <Badge
                    variant="secondary"
                    title="Activated from the current finalised, passing Business Plan & Budget assessment."
                >
                    Advisory request active
                </Badge>
            ) : null}
            {conversion.available ? (
                <Button
                    type="button"
                    size="sm"
                    title="The finalised, passing assessment activated this conversion request. Creating the advisory client remains an explicit advisor decision."
                    onClick={() =>
                        window.confirm(
                            'Create this entrepreneur as an advisory client now? This is the explicit conversion step after the activated request.',
                        ) && router.post(conversion.convert_url)
                    }
                >
                    Create advisory client
                </Button>
            ) : null}
        </>
    );
}

export function FounderReadyBriefAction({ lenderBrief }: Props) {
    if (!lenderBrief) {
        return null;
    }

    return lenderBrief.active ? (
        <Button
            asChild
            size="sm"
            className="bg-emerald-600 text-white hover:bg-emerald-700"
            title="Compact lender decision brief: executive summary, financial position, selected highlights, and three-year outlook."
        >
            <a href={lenderBrief.document_url} target="_blank" rel="noreferrer">
                <FileText className="size-4" aria-hidden="true" />
                Generate founder-ready brief
            </a>
        </Button>
    ) : (
        <Button
            type="button"
            size="sm"
            variant="outline"
            disabled
            title={lenderBrief.reasons.join(' ')}
        >
            <FileText className="size-4" aria-hidden="true" />
            Founder-ready brief blocked
        </Button>
    );
}

export function FounderReadyBriefBadge({ lenderBrief }: Props) {
    if (!lenderBrief) {
        return null;
    }

    return (
        <InsightHoverCard
            title="Founder-ready lender brief"
            rows={[
                {
                    label: 'Release status',
                    value: lenderBrief.active ? 'Available' : 'Blocked',
                },
                {
                    label: 'Open controls',
                    value: lenderBrief.reasons.length,
                },
            ]}
        >
            <Badge
                variant={lenderBrief.active ? 'secondary' : 'destructive'}
                className="cursor-help"
            >
                {lenderBrief.label}
            </Badge>
        </InsightHoverCard>
    );
}

export function PlanBudgetFinaliseReportAction({
    assessment,
    ready,
    blocked,
    blockingMessage,
}: FinaliseReportActionProps) {
    return (
        <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={blocked}
            className={
                ready
                    ? 'border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700 hover:text-white'
                    : undefined
            }
            title={
                blocked
                    ? (blockingMessage ??
                      'Run a reassessment with Plan–budget coherence before finalising.')
                    : ready
                      ? `Score meets the ${assessment.threshold}/100 advisory-readiness threshold. Finalising activates the advisory conversion request and automatically queues the approved executive summary for this assessed plan and budget.`
                      : 'Finalise the assessment report and record the advisor outcome.'
            }
            onClick={() =>
                router.patch(
                    assessment.finalise_url,
                    {},
                    { preserveScroll: true },
                )
            }
        >
            <CheckCircle2 className="size-4" aria-hidden="true" />
            Finalise report
        </Button>
    );
}
