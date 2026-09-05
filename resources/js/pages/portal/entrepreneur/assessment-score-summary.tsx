import { ClipboardCheck, FileText, Scale } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatNzDate } from '@/lib/formatters';

type AssessmentScoreSummaryData = {
    automated_score_available: boolean;
    weighted_score: number | null;
    overall_grade: string | null;
    threshold: number;
    finalised_at: string | null;
    basis: {
        label: string;
        business_plan_submitted_at: string | null;
        business_plan_updated_at: string | null;
        plan_snapshot_url: string | null;
        plan_snapshot_captured_at: string | null;
        summary: string;
    };
    scoring: {
        label: string;
    };
    scoring_scope: {
        rescored_criterion_numbers: number[];
        reused_criterion_numbers: number[];
        advisor_review_confirmed_at: string | null;
        cross_plan_review_required: boolean;
        cross_plan_review_message: string | null;
    } | null;
    evidence_audit: {
        label: string;
    };
    explanation: string;
};

type Props = {
    assessment: AssessmentScoreSummaryData;
    advisorScoringReview: {
        action_url: string;
    } | null;
    onConfirmScoringScope?: () => void;
};

export function AssessmentScoreSummary({
    assessment,
    advisorScoringReview,
    onConfirmScoringScope,
}: Props) {
    return (
        <>
            <section className="space-y-4 rounded-md border bg-background p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <Scale className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Score summary</h2>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">
                            {assessment.basis.label}
                        </Badge>
                        {assessment.basis.plan_snapshot_url ? (
                            <Button asChild size="sm" variant="outline">
                                <a
                                    href={assessment.basis.plan_snapshot_url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <FileText
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Submitted plan
                                </a>
                            </Button>
                        ) : (
                            <Badge variant="secondary">
                                Snapshot unavailable
                            </Badge>
                        )}
                    </div>
                </div>
                <dl className="grid gap-3 text-sm md:grid-cols-2">
                    <Detail
                        label="Banded readiness indicator"
                        value={
                            assessment.automated_score_available &&
                            assessment.weighted_score !== null
                                ? `${Math.round(assessment.weighted_score)}/100`
                                : 'Unavailable'
                        }
                    />
                    <Detail
                        label="Grade"
                        value={
                            assessment.automated_score_available &&
                            assessment.overall_grade !== null
                                ? formatLabel(assessment.overall_grade)
                                : 'Unavailable'
                        }
                    />
                    <Detail
                        label="Threshold"
                        value={`${assessment.threshold.toFixed(0)}/100`}
                    />
                    <Detail
                        label="Completed"
                        value={formatDate(assessment.finalised_at)}
                    />
                    <Detail
                        label="Plan submitted"
                        value={formatDate(
                            assessment.basis.business_plan_submitted_at,
                        )}
                    />
                    <Detail
                        label="Plan updated"
                        value={formatDateTime(
                            assessment.basis.business_plan_updated_at,
                        )}
                    />
                    <Detail
                        label="Snapshot captured"
                        value={formatDateTime(
                            assessment.basis.plan_snapshot_captured_at,
                        )}
                    />
                    <Detail
                        label="Scoring method"
                        value={assessment.scoring.label}
                    />
                    <Detail
                        label="Evidence used"
                        value={assessment.evidence_audit.label}
                    />
                </dl>
                <p className="max-w-4xl text-sm text-muted-foreground">
                    {assessment.explanation}
                </p>
                <p className="max-w-4xl text-sm text-muted-foreground">
                    {assessment.basis.summary}
                </p>
            </section>

            {assessment.scoring_scope ? (
                <section className="space-y-3 rounded-md border bg-background p-4">
                    <div className="flex items-start gap-3">
                        <ClipboardCheck
                            className="mt-0.5 size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <div className="space-y-1 text-sm">
                            <h2 className="font-medium">
                                Reassessment evidence scope
                            </h2>
                            <p className="text-muted-foreground">
                                Rescored criteria:{' '}
                                {assessment.scoring_scope
                                    .rescored_criterion_numbers.length > 0
                                    ? assessment.scoring_scope.rescored_criterion_numbers.join(
                                          ', ',
                                      )
                                    : 'None'}
                                . Unchanged evidence retained criteria:{' '}
                                {assessment.scoring_scope
                                    .reused_criterion_numbers.length > 0
                                    ? assessment.scoring_scope.reused_criterion_numbers.join(
                                          ', ',
                                      )
                                    : 'None'}
                                .
                            </p>
                            {assessment.scoring_scope
                                .cross_plan_review_required ? (
                                <p className="text-amber-800">
                                    {
                                        assessment.scoring_scope
                                            .cross_plan_review_message
                                    }
                                </p>
                            ) : null}
                        </div>
                    </div>
                    {advisorScoringReview ? (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-3">
                            <p className="text-sm text-muted-foreground">
                                Advisor confirmation is required before this
                                round can be finalised.
                            </p>
                            <Button
                                type="button"
                                size="sm"
                                onClick={onConfirmScoringScope}
                            >
                                Confirm scoring scope
                            </Button>
                        </div>
                    ) : assessment.scoring_scope.advisor_review_confirmed_at ? (
                        <p className="border-t pt-3 text-sm text-muted-foreground">
                            Advisor review confirmed{' '}
                            {formatDateTime(
                                assessment.scoring_scope
                                    .advisor_review_confirmed_at,
                            )}
                            .
                        </p>
                    ) : null}
                </section>
            ) : null}
        </>
    );
}

function Detail({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="grid grid-cols-[140px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value || '-'}</dd>
        </div>
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return formatNzDate(value);
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return formatNzDate(value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}
