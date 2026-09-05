import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    ClipboardCheck,
    FileText,
    RefreshCw,
    Save,
    Scale,
    Send,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

type Criterion = {
    number: number;
    name: string;
    weight: number;
    score: number | null;
    score_band: string | null;
    score_scale: Record<string, number> | null;
    scoring_method: string | null;
    contribution: number | null;
    is_complete: boolean;
    source_label: string;
    rationale: string;
    context_hash: string | null;
    evidence_mode: string | null;
    evidence_section_count: number | null;
    budget_evidence_included: boolean;
    source_sections: SourceSection[];
};

type SourceSection = {
    section_id: string;
    title: string;
    requirement_key: string | null;
    updated_at: string | null;
    body_excerpt: string;
};

type EvidenceSection = {
    section_id: string;
    phase_title: string;
    title: string;
    requirement_key: string | null;
    updated_at: string | null;
    body: string;
};

type RevisionPriority = {
    title: string;
    score: number;
    what_is_missing: string;
    what_to_add_or_change: string;
    where_in_plan: string;
};

type Assessment = {
    id: string;
    round: number;
    status: string;
    overall_grade: string | null;
    weighted_score: number | null;
    threshold: number;
    requires_full_reassessment: boolean;
    automated_score_available: boolean;
    incomplete_criterion_numbers: number[];
    scoring: {
        is_calibrated: boolean;
        uses_complete_snapshot_evidence: boolean;
        uses_scoped_criterion_evidence: boolean;
        label: string;
        detail: string;
    };
    scoring_scope: {
        rescored_criterion_numbers: number[];
        reused_criterion_numbers: number[];
        advisor_review_required: boolean;
        advisor_review_confirmed_at: string | null;
        cross_plan_review_required: boolean;
        cross_plan_review_message: string | null;
    } | null;
    finalised_at: string | null;
    created_at: string | null;
    basis: {
        label: string;
        business_plan_id: string | null;
        business_plan_title: string | null;
        business_plan_status: string | null;
        business_plan_submitted_at: string | null;
        business_plan_updated_at: string | null;
        plan_snapshot_available: boolean;
        plan_snapshot_url: string | null;
        plan_snapshot_captured_at: string | null;
        summary: string;
    };
    rating_framework: {
        id: string | null;
        version: number | null;
        criteria_count: number;
        published_at: string | null;
        is_current: boolean;
        current_version: number | null;
        current_criteria_count: number | null;
        current_published_at: string | null;
        current_has_budget: boolean;
    };
    document_support: {
        attached_document_count: number;
        summary: string;
    };
    evidence_audit: {
        mode: string;
        label: string;
        section_count: number;
        includes_budget_evidence: boolean;
        sections: EvidenceSection[];
        budget_evidence: Record<string, unknown> | null;
    };
    mentor_notes: {
        overall_visible?: string;
        section_notes?: Record<string, string>;
    };
    criteria: Criterion[];
    explanation: string;
};

type Props = {
    profile: {
        id: string;
        name: string;
        email: string;
        assigned_advisor: {
            id: number;
            name: string;
            email: string;
        } | null;
    };
    assessment: Assessment;
    backUrl: string;
    backLabel?: string;
    reassessUrl?: string | null;
    advisorFeedback?: {
        feedback: string;
        proposed_reply: string;
        priorities: RevisionPriority[];
        suggested_feedback: string;
        suggested_reply: string;
        sent_at: string | null;
        action_url: string;
    } | null;
    advisorScoringReview?: {
        action_url: string;
    } | null;
};

export default function EntrepreneurAssessment({
    profile,
    assessment,
    backUrl,
    backLabel = 'Dashboard',
    reassessUrl = null,
    advisorFeedback = null,
    advisorScoringReview = null,
}: Props) {
    const framework = assessment.rating_framework;
    const usesOldRubric = framework.is_current === false;
    const advisorFeedbackStateKey = [
        assessment.id,
        advisorFeedback?.feedback,
        advisorFeedback?.proposed_reply,
        advisorFeedback?.sent_at,
    ].join(':');
    const [advisorFeedbackDraft, setAdvisorFeedbackDraft] = useState<{
        key: string;
        feedback: string;
        proposedReply: string;
    } | null>(null);
    const [feedbackPending, setFeedbackPending] = useState(false);
    const [regeneratingDraft, setRegeneratingDraft] = useState(false);
    const [feedbackErrors, setFeedbackErrors] = useState<
        Record<string, string | undefined>
    >({});
    const feedback =
        advisorFeedbackDraft?.key === advisorFeedbackStateKey
            ? advisorFeedbackDraft.feedback
            : (advisorFeedback?.feedback ?? '');
    const proposedReply =
        advisorFeedbackDraft?.key === advisorFeedbackStateKey
            ? advisorFeedbackDraft.proposedReply
            : (advisorFeedback?.proposed_reply ?? '');

    const updateAdvisorFeedbackDraft = (
        nextFeedback: string,
        nextProposedReply: string,
    ) => {
        setAdvisorFeedbackDraft({
            key: advisorFeedbackStateKey,
            feedback: nextFeedback,
            proposedReply: nextProposedReply,
        });
    };

    const useSuggestedReply = () => {
        if (!advisorFeedback) {
            return;
        }

        updateAdvisorFeedbackDraft(feedback, advisorFeedback.suggested_reply);
        setFeedbackErrors((errors) => ({
            ...errors,
            proposed_reply: undefined,
        }));

        if (proposedReply.trim() === advisorFeedback.suggested_reply.trim()) {
            toast.message(
                'The suggested reply is already loaded. Use Send reply to founder when you are ready.',
            );

            return;
        }

        toast.success('Suggested reply loaded into the draft.');
    };

    const submitAdvisorFeedback = (sendToFounder: boolean) => {
        if (!advisorFeedback || feedbackPending) {
            return;
        }

        router.patch(
            advisorFeedback.action_url,
            {
                feedback,
                proposed_reply: proposedReply,
                send_to_founder: sendToFounder,
            },
            {
                preserveScroll: true,
                onStart: () => {
                    setFeedbackPending(true);
                    setFeedbackErrors({});
                },
                onError: (errors) => {
                    setFeedbackErrors({
                        feedback:
                            typeof errors.feedback === 'string'
                                ? errors.feedback
                                : undefined,
                        proposed_reply:
                            typeof errors.proposed_reply === 'string'
                                ? errors.proposed_reply
                                : undefined,
                    });
                },
                onFinish: () => setFeedbackPending(false),
            },
        );
    };

    const regenerateAdvisorFeedbackDraft = () => {
        if (!advisorFeedback || regeneratingDraft || feedbackPending) {
            return;
        }

        router.reload({
            only: ['advisorFeedback'],
            onStart: () => setRegeneratingDraft(true),
            onSuccess: () => {
                setAdvisorFeedbackDraft(null);
                setFeedbackErrors({});
                toast.success('Plain-language draft regenerated.');
            },
            onError: () =>
                toast.error(
                    'The plain-language draft could not be regenerated. Please try again.',
                ),
            onFinish: () => setRegeneratingDraft(false),
        });
    };

    return (
        <>
            <Head title={`Assessment round ${assessment.round}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-2">
                        <Button asChild size="sm" variant="ghost">
                            <Link href={backUrl}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {backLabel}
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-xl font-semibold">
                                Assessment round {assessment.round}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {profile.name}
                            </p>
                        </div>
                    </div>
                    <Badge variant="secondary">
                        {formatLabel(assessment.status)}
                    </Badge>
                </div>

                {usesOldRubric ? (
                    <section className="grid gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 md:grid-cols-[1fr_auto] md:items-start">
                        <div className="flex gap-3">
                            <AlertTriangle
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <div className="space-y-1">
                                <h2 className="font-medium">
                                    This round uses an older rubric
                                </h2>
                                <p>
                                    Round {assessment.round} was scored with{' '}
                                    {formatRubricVersion(framework.version)} (
                                    {framework.criteria_count} criteria). The
                                    current published rubric is{' '}
                                    {formatRubricVersion(
                                        framework.current_version,
                                    )}{' '}
                                    ({framework.current_criteria_count ?? '-'}{' '}
                                    criteria
                                    {framework.current_has_budget
                                        ? ', including Budget'
                                        : ''}
                                    ). Run a new assessment round to apply the
                                    current rubric; this historical round stays
                                    unchanged.
                                </p>
                            </div>
                        </div>
                        {reassessUrl ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="border-amber-300 bg-white text-amber-950 hover:bg-amber-100"
                                onClick={() =>
                                    router.post(
                                        reassessUrl,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <RefreshCw
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Run reassessment
                            </Button>
                        ) : null}
                    </section>
                ) : null}

                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <Scale className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">
                                Score summary
                            </h2>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">
                                {assessment.basis.label}
                            </Badge>
                            {assessment.basis.plan_snapshot_url ? (
                                <Button asChild size="sm" variant="outline">
                                    <a
                                        href={
                                            assessment.basis.plan_snapshot_url
                                        }
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
                                    onClick={() =>
                                        router.patch(
                                            advisorScoringReview.action_url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Confirm scoring scope
                                </Button>
                            </div>
                        ) : assessment.scoring_scope
                              .advisor_review_confirmed_at ? (
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

                {assessment.requires_full_reassessment ? (
                    <section className="flex flex-wrap items-center justify-between gap-4 rounded-md border border-amber-300 bg-amber-50 p-4 text-amber-950">
                        <div className="flex min-w-0 items-start gap-3">
                            <AlertTriangle
                                className="mt-0.5 size-5 shrink-0"
                                aria-hidden="true"
                            />
                            <div className="space-y-1">
                                <h2 className="text-sm font-medium">
                                    {assessment.automated_score_available
                                        ? 'Calibrated reassessment required'
                                        : 'Assessment unavailable'}
                                </h2>
                                <p className="max-w-4xl text-sm">
                                    {assessment.scoring.detail}
                                </p>
                            </div>
                        </div>
                        {reassessUrl ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="border-amber-300 bg-white text-amber-950 hover:bg-amber-100"
                                onClick={() =>
                                    router.post(
                                        reassessUrl,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <RefreshCw
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Run fresh assessment
                            </Button>
                        ) : null}
                    </section>
                ) : null}

                <section className="space-y-3 rounded-md border bg-background p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-medium">
                                Assessment evidence audit
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {assessment.evidence_audit.label}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="outline">
                                {assessment.evidence_audit.section_count} plan{' '}
                                sections
                            </Badge>
                            <Badge variant="outline">
                                {assessment.evidence_audit
                                    .includes_budget_evidence
                                    ? 'Budget included'
                                    : 'Budget unavailable'}
                            </Badge>
                        </div>
                    </div>
                    <details className="border-t pt-3">
                        <summary className="cursor-pointer text-sm font-medium text-foreground">
                            View captured evidence
                        </summary>
                        <div className="mt-3 space-y-4">
                            {assessment.evidence_audit.sections.map(
                                (section, index) => (
                                    <div
                                        key={`${section.section_id || section.title}-${index}`}
                                        className="border-b pb-4 last:border-b-0 last:pb-0"
                                    >
                                        <div className="flex flex-wrap items-center gap-2 text-xs">
                                            {section.phase_title ? (
                                                <span className="text-muted-foreground">
                                                    {section.phase_title}
                                                </span>
                                            ) : null}
                                            <span className="font-medium text-foreground">
                                                {section.title}
                                            </span>
                                            {section.requirement_key ? (
                                                <Badge variant="outline">
                                                    {section.requirement_key}
                                                </Badge>
                                            ) : null}
                                        </div>
                                        <p className="mt-2 text-xs leading-5 whitespace-pre-wrap text-muted-foreground">
                                            {section.body ||
                                                'No text captured.'}
                                        </p>
                                    </div>
                                ),
                            )}
                            {assessment.evidence_audit.budget_evidence ? (
                                <div className="border-t pt-4">
                                    <div className="text-xs font-medium text-foreground">
                                        Budget evidence
                                    </div>
                                    <pre className="mt-2 max-h-96 overflow-auto bg-muted/30 p-3 text-xs break-words whitespace-pre-wrap text-muted-foreground">
                                        {JSON.stringify(
                                            assessment.evidence_audit
                                                .budget_evidence,
                                            null,
                                            2,
                                        )}
                                    </pre>
                                </div>
                            ) : null}
                        </div>
                    </details>
                </section>

                {advisorFeedback ? (
                    <section className="space-y-4 rounded-md border bg-background p-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <ClipboardCheck
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-medium">
                                    Advisor feedback
                                </h2>
                            </div>
                            {advisorFeedback.sent_at ? (
                                <Badge variant="secondary">
                                    Sent {formatDate(advisorFeedback.sent_at)}
                                </Badge>
                            ) : (
                                <Badge variant="outline">Draft</Badge>
                            )}
                        </div>

                        {advisorFeedback.priorities.length > 0 ? (
                            <div className="space-y-3">
                                <div className="space-y-1">
                                    <h3 className="text-sm font-medium">
                                        Revision priorities
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        Three focused updates for the next
                                        revision.
                                    </p>
                                </div>
                                <div className="divide-y rounded-md border">
                                    {advisorFeedback.priorities.map(
                                        (priority, index) => (
                                            <article
                                                key={`${priority.title}-${index}`}
                                                className="grid gap-3 p-3 text-sm lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1fr)_minmax(0,0.8fr)]"
                                            >
                                                <div className="space-y-1">
                                                    <h4 className="font-medium">
                                                        {index + 1}.{' '}
                                                        {priority.title}
                                                    </h4>
                                                    <Badge variant="outline">
                                                        {priority.score.toFixed(
                                                            0,
                                                        )}
                                                        /100
                                                    </Badge>
                                                    <p className="text-xs font-medium text-muted-foreground">
                                                        What is missing
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        {
                                                            priority.what_is_missing
                                                        }
                                                    </p>
                                                </div>
                                                <div className="space-y-1">
                                                    <p className="text-xs font-medium text-muted-foreground">
                                                        What to add/change
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        {
                                                            priority.what_to_add_or_change
                                                        }
                                                    </p>
                                                </div>
                                                <div className="space-y-1">
                                                    <p className="text-xs font-medium text-muted-foreground">
                                                        Where in the plan
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        {priority.where_in_plan}
                                                    </p>
                                                </div>
                                            </article>
                                        ),
                                    )}
                                </div>
                            </div>
                        ) : null}

                        <div className="flex flex-wrap justify-end gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={feedbackPending || regeneratingDraft}
                                onClick={regenerateAdvisorFeedbackDraft}
                            >
                                <RefreshCw
                                    className={
                                        regeneratingDraft
                                            ? 'size-4 animate-spin'
                                            : 'size-4'
                                    }
                                    aria-hidden="true"
                                />
                                {regeneratingDraft
                                    ? 'Regenerating draft'
                                    : 'Regenerate plain-language draft'}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={feedbackPending || regeneratingDraft}
                                onClick={useSuggestedReply}
                            >
                                Reset reply to suggested draft
                            </Button>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <label className="grid gap-2 text-sm font-medium">
                                Advisor assessment summary
                                <Textarea
                                    value={feedback}
                                    onChange={(event) =>
                                        updateAdvisorFeedbackDraft(
                                            event.target.value,
                                            proposedReply,
                                        )
                                    }
                                    rows={12}
                                    aria-invalid={Boolean(
                                        feedbackErrors.feedback,
                                    )}
                                />
                                <InputError message={feedbackErrors.feedback} />
                            </label>

                            <label className="grid gap-2 text-sm font-medium">
                                Proposed reply to founder
                                <Textarea
                                    value={proposedReply}
                                    onChange={(event) =>
                                        updateAdvisorFeedbackDraft(
                                            feedback,
                                            event.target.value,
                                        )
                                    }
                                    rows={12}
                                    aria-invalid={Boolean(
                                        feedbackErrors.proposed_reply,
                                    )}
                                />
                                <InputError
                                    message={feedbackErrors.proposed_reply}
                                />
                            </label>
                        </div>

                        <div className="flex flex-wrap justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={
                                    feedbackPending ||
                                    regeneratingDraft ||
                                    feedback.trim().length < 10
                                }
                                onClick={() => submitAdvisorFeedback(false)}
                            >
                                <Save className="size-4" aria-hidden="true" />
                                {feedbackPending
                                    ? 'Saving feedback'
                                    : 'Save feedback'}
                            </Button>
                            <Button
                                type="button"
                                disabled={
                                    feedbackPending ||
                                    regeneratingDraft ||
                                    feedback.trim().length < 10 ||
                                    proposedReply.trim().length < 10
                                }
                                onClick={() => submitAdvisorFeedback(true)}
                            >
                                <Send className="size-4" aria-hidden="true" />
                                {feedbackPending
                                    ? 'Sending reply'
                                    : 'Send reply to founder'}
                            </Button>
                        </div>
                    </section>
                ) : null}

                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex items-center gap-2">
                        <FileText className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">
                            Evidence support
                        </h2>
                    </div>
                    <dl className="grid gap-3 text-sm md:grid-cols-2">
                        <Detail
                            label="Attached documents"
                            value={String(
                                assessment.document_support
                                    .attached_document_count,
                            )}
                        />
                        <Detail
                            label="Advisor"
                            value={profile.assigned_advisor?.name}
                        />
                    </dl>
                    <p className="max-w-4xl text-sm text-muted-foreground">
                        {assessment.document_support.summary}
                    </p>
                </section>

                {!advisorFeedback && assessment.mentor_notes.overall_visible ? (
                    <section className="space-y-4 rounded-md border bg-background p-4">
                        <div className="flex items-center gap-2">
                            <ClipboardCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            <h2 className="text-sm font-medium">
                                Advisor notes
                            </h2>
                        </div>
                        <p className="max-w-4xl text-sm text-muted-foreground">
                            {assessment.mentor_notes.overall_visible}
                        </p>
                    </section>
                ) : null}

                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <ClipboardCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            <h2 className="text-sm font-medium">
                                Criterion scores
                            </h2>
                        </div>
                        <Badge variant="outline">
                            {assessment.criteria.length}
                        </Badge>
                    </div>

                    <div className="divide-y rounded-md border">
                        {assessment.criteria.map((criterion) => (
                            <article
                                key={criterion.number}
                                className="grid gap-3 p-3 text-sm lg:grid-cols-[1fr_auto]"
                            >
                                <div className="min-w-0 space-y-1">
                                    <div className="font-medium">
                                        {criterion.number}. {criterion.name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {criterion.source_label}
                                    </div>
                                    {criterion.score_band ? (
                                        <div className="text-xs text-muted-foreground">
                                            Model-selected band:{' '}
                                            <span className="font-medium text-foreground">
                                                {formatLabel(
                                                    criterion.score_band,
                                                )}
                                            </span>{' '}
                                            ({formatScoreScale(criterion)})
                                        </div>
                                    ) : null}
                                    {criterion.rationale ? (
                                        <p className="max-w-3xl text-xs text-muted-foreground">
                                            {criterion.rationale}
                                        </p>
                                    ) : null}
                                    {criterion.source_sections.length > 0 ? (
                                        <details className="mt-3 border-t pt-3">
                                            <summary className="cursor-pointer text-xs font-medium text-foreground">
                                                Focused evidence (
                                                {
                                                    criterion.source_sections
                                                        .length
                                                }{' '}
                                                of{' '}
                                                {criterion.evidence_section_count ??
                                                    criterion.source_sections
                                                        .length}{' '}
                                                submitted plan sections
                                                {criterion.budget_evidence_included
                                                    ? ' and budget evidence'
                                                    : ''}
                                                )
                                            </summary>
                                            <div className="mt-2 grid gap-2">
                                                {criterion.source_sections.map(
                                                    (section, index) => (
                                                        <div
                                                            key={`${criterion.number}-${section.section_id || index}`}
                                                            className="border-b py-2 last:border-b-0"
                                                        >
                                                            <div className="flex flex-wrap items-center gap-2 text-xs">
                                                                <span className="font-medium text-foreground">
                                                                    {
                                                                        section.title
                                                                    }
                                                                </span>
                                                                {section.requirement_key ? (
                                                                    <Badge variant="outline">
                                                                        {
                                                                            section.requirement_key
                                                                        }
                                                                    </Badge>
                                                                ) : null}
                                                                {section.updated_at ? (
                                                                    <span className="text-muted-foreground">
                                                                        Updated{' '}
                                                                        {formatDateTime(
                                                                            section.updated_at,
                                                                        )}
                                                                    </span>
                                                                ) : null}
                                                            </div>
                                                            {section.body_excerpt ? (
                                                                <p className="mt-1 text-xs text-muted-foreground">
                                                                    {
                                                                        section.body_excerpt
                                                                    }
                                                                </p>
                                                            ) : null}
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </details>
                                    ) : null}
                                </div>
                                <div className="flex flex-wrap items-center gap-2 lg:justify-end">
                                    <Badge
                                        variant={
                                            assessment.automated_score_available
                                                ? 'outline'
                                                : 'destructive'
                                        }
                                    >
                                        {assessment.automated_score_available &&
                                        criterion.score !== null
                                            ? `${criterion.score.toFixed(1)}/100`
                                            : 'Unavailable'}
                                    </Badge>
                                    <span className="text-xs text-muted-foreground">
                                        {criterion.weight.toFixed(1)}% weight
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {criterion.contribution !== null
                                            ? `${criterion.contribution.toFixed(1)} pts`
                                            : 'Unavailable'}
                                    </span>
                                </div>
                            </article>
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}

function formatScoreScale(criterion: Criterion): string {
    if (!criterion.score_band || !criterion.score_scale) {
        return criterion.score === null
            ? 'Unavailable'
            : `${criterion.score.toFixed(1)}/100`;
    }

    const score = criterion.score_scale[criterion.score_band];

    return typeof score === 'number'
        ? `${score}/100 approved scale`
        : criterion.score === null
          ? 'Unavailable'
          : `${criterion.score.toFixed(1)}/100`;
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

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
    }).format(new Date(value));
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function formatRubricVersion(value: number | null): string {
    return value ? `rubric v${value}` : 'the assigned rubric';
}

EntrepreneurAssessment.layout = {
    breadcrumbs: [
        {
            title: 'Assessment',
            href: '/portal/entrepreneur',
        },
    ],
};
