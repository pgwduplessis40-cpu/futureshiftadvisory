import { router } from '@inertiajs/react';
import { FileText } from 'lucide-react';
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
