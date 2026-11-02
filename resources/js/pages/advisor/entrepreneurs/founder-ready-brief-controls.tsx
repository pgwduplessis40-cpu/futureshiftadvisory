import { Link, router } from '@inertiajs/react';
import { CheckCircle2, FileText, LoaderCircle } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
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
        url: string;
        finalisation_blocking_message: string | null;
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
        <div className="basis-full space-y-2">
            <Button type="button" size="sm" variant="outline" disabled>
                <FileText className="size-4" aria-hidden="true" />
                Founder-ready brief blocked
            </Button>
            <p className="text-sm text-muted-foreground">
                This brief is for external issue. Its release checks are
                separate from client progression.
            </p>
            <ul className="max-w-3xl list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                {lenderBrief.reasons.map((reason) => (
                    <li key={reason}>{reason}</li>
                ))}
            </ul>
        </div>
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
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const submitting = useRef(false);
    const reason =
        assessment.finalisation_blocking_message ??
        (blocked
            ? (blockingMessage ??
              'Run a reassessment with Plan–budget coherence before finalising.')
            : null);
    const explanation = ready
        ? 'Finalising completes this assessment, creates its report and moves the client to Advisory Ready. The approved executive summary is queued automatically when eligible. Advisory conversion is a separate action.'
        : 'Finalising completes this assessment and creates its report. A result below the readiness threshold returns the client to revision.';
    const finalise = () => {
        if (reason || submitting.current) {
            return;
        }

        submitting.current = true;
        setPending(true);
        setError(null);
        router.patch(
            assessment.finalise_url,
            {},
            {
                preserveScroll: true,
                onError: (errors) =>
                    setError(
                        Object.values(errors).join(' ') ||
                            'The assessment could not be finalised. Review the assessment and try again.',
                    ),
                onSuccess: () =>
                    toast.success(
                        ready
                            ? 'Assessment finalised and report created. The client is Advisory Ready; conversion is available separately.'
                            : 'Assessment finalised and report created. The client remains in revision.',
                    ),
                onFinish: () => {
                    submitting.current = false;
                    setPending(false);
                },
            },
        );
    };

    return (
        <div className="basis-full space-y-2">
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={reason !== null || pending}
                    aria-busy={pending}
                    className={
                        ready && reason === null
                            ? 'border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700 hover:text-white'
                            : undefined
                    }
                    title={reason ?? explanation}
                    onClick={finalise}
                >
                    {pending ? (
                        <LoaderCircle
                            className="size-4 animate-spin"
                            aria-hidden="true"
                        />
                    ) : (
                        <CheckCircle2 className="size-4" aria-hidden="true" />
                    )}
                    {pending ? 'Finalising report…' : 'Finalise report'}
                </Button>
                {reason || error ? (
                    <Button asChild size="sm" variant="outline">
                        <Link href={assessment.url}>Review assessment</Link>
                    </Button>
                ) : null}
            </div>
            {reason ? (
                <p role="status" className="text-sm text-amber-800">
                    {reason} Open “Review assessment” to complete the required
                    check.
                </p>
            ) : null}
            {error ? (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            ) : null}
            <p className="max-w-2xl text-sm text-muted-foreground">
                {explanation}
            </p>
        </div>
    );
}
