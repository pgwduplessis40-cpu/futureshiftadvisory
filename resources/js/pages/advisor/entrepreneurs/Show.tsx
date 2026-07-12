import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowUpRight,
    Banknote,
    CheckCircle2,
    ClipboardCheck,
    Copy,
    FileText,
    Flame,
    Mail,
    MessageSquare,
    Pencil,
    RefreshCw,
    Save,
    TrendingUp,
    Trophy,
    UserRoundCheck,
    XCircle,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { InsightHoverCard } from '@/components/insight/InsightHoverCard';
import type { InsightHoverCardRow } from '@/components/insight/InsightHoverCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type {
    CriterionDelta,
    EntrepreneurDetail,
    EntrepreneurDocument,
    ServiceOption,
} from './types';

type Props = {
    entrepreneur: EntrepreneurDetail;
    serviceOptions: ServiceOption[];
};

type InviteDetailsForm = {
    name: string;
    email: string;
    intended_package_scope: string;
    concept_summary: string;
};

export default function EntrepreneursShow({
    entrepreneur,
    serviceOptions,
}: Props) {
    const latestAssessment = entrepreneur.latest_plan?.latest_assessment;
    const latestAssessmentUsesCurrentRubric =
        latestAssessment?.rating_framework.is_current ?? true;
    const canRunAssessment =
        !!entrepreneur.latest_plan &&
        (!latestAssessment || !latestAssessmentUsesCurrentRubric);
    const gamification = entrepreneur.gamification;
    const [gamificationEnabled, setGamificationEnabled] = useState(
        gamification.enabled,
    );
    const [gamificationPending, setGamificationPending] = useState(false);
    const [gateNote, setGateNote] = useState('');
    const [changeRequestNote, setChangeRequestNote] = useState('');
    const [ideaRefreshPending, setIdeaRefreshPending] = useState(false);
    const [copiedInviteEmail, setCopiedInviteEmail] = useState(false);
    const [editingInvite, setEditingInvite] = useState(false);
    const inviteForm = useForm<InviteDetailsForm>({
        name: entrepreneur.name,
        email: entrepreneur.email,
        intended_package_scope: entrepreneur.intended_package_scope,
        concept_summary: entrepreneur.concept_summary ?? '',
    });
    const inviteErrors = inviteForm.errors as Record<
        string,
        string | undefined
    >;
    const ideaValidation = entrepreneur.idea_validation;
    const ideaRefreshInFlight =
        (ideaValidation?.refresh_status === 'queued' ||
            ideaValidation?.refresh_status === 'running') &&
        !ideaValidation?.refresh_stale;
    const ideaRefreshBusy = ideaRefreshPending || ideaRefreshInFlight;
    const ideaRefreshFailed =
        ideaValidation?.refresh_status === 'failed' ||
        ideaValidation?.refresh_stale;
    const ideaRefreshButtonLabel = ideaRefreshInFlight
        ? ideaValidation?.refresh_status === 'running'
            ? 'AI review running'
            : 'AI review queued'
        : ideaRefreshPending
          ? 'Queueing'
          : ideaRefreshFailed
            ? 'Retry AI review'
            : 'Rerun AI review';
    const ideaRefreshFailure = ideaValidation?.refresh_failure ?? '';
    const ideaRecalled = Boolean(ideaValidation?.recalled_at);
    const ideaRefreshProviderTransient =
        /status\s+(429|500|502|503|504|529)\b/i.test(ideaRefreshFailure) ||
        /timeout|timed out|overloaded/i.test(ideaRefreshFailure);
    const ideaRefreshFailureMessage = ideaRefreshFailure
        ? ideaRefreshProviderTransient
            ? `AI review reached Anthropic but did not return a usable result. ${ideaRefreshFailure}. Repeated retries may consume API credit; wait a few minutes before retrying.`
            : `AI review did not complete. ${ideaRefreshFailure}`
        : 'AI review did not complete. Retry the AI review or continue manual review with the submitted answers.';
    const submittedIdeaFields = ideaValidation
        ? [
              { label: 'Problem', value: ideaValidation.problem },
              {
                  label: 'Target customer',
                  value: ideaValidation.target_customer,
              },
              { label: 'Solution', value: ideaValidation.solution },
              {
                  label: 'Value proposition',
                  value: ideaValidation.value_proposition,
              },
              { label: 'Demand signal', value: ideaValidation.demand_signal },
              { label: 'Revenue model', value: ideaValidation.revenue_model },
          ]
        : [];
    const ideaGateStatus = ideaValidation
        ? ideaValidation.advisor_gate_passed_at
            ? 'approved'
            : ideaValidation.advisor_gate_status
        : null;
    const ideaGateLabel = ideaValidation
        ? ideaGateStatus === 'approved'
            ? 'Gate passed'
            : ideaGateStatus === 'changes_requested'
              ? 'Changes requested'
              : ideaGateStatus === 'recalled'
                ? 'Recalled for revision'
                : 'Gate needed'
        : 'Needed';
    const ideaGateBadgeVariant: 'secondary' | 'destructive' | 'outline' =
        ideaGateStatus === 'approved'
            ? 'secondary'
            : ideaGateStatus === 'changes_requested'
              ? 'destructive'
              : 'outline';
    const ideaVerdict = firstSentence(ideaValidation?.summary ?? null);
    const ideaSummaryRemainder = summaryRemainder(
        ideaValidation?.summary ?? null,
        ideaVerdict,
    );
    const canDecideIdeaGate =
        !!ideaValidation &&
        !ideaValidation.advisor_gate_passed_at &&
        !ideaRecalled &&
        ideaGateStatus !== 'changes_requested';

    const copyText = (text: string, onCopied: (value: boolean) => void) => {
        void navigator.clipboard.writeText(text).then(() => {
            onCopied(true);
            window.setTimeout(() => onCopied(false), 1800);
        });
    };
    const copyInviteEmail = () =>
        copyText(entrepreneur.email, setCopiedInviteEmail);

    const startInviteEdit = () => {
        inviteForm.setData({
            name: entrepreneur.name,
            email: entrepreneur.email,
            intended_package_scope: entrepreneur.intended_package_scope,
            concept_summary: entrepreneur.concept_summary ?? '',
        });
        inviteForm.clearErrors();
        setEditingInvite(true);
    };

    const cancelInviteEdit = () => {
        inviteForm.clearErrors();
        setEditingInvite(false);
    };

    const saveInviteDetails = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!entrepreneur.invite_update_url) {
            return;
        }

        inviteForm.patch(entrepreneur.invite_update_url, {
            preserveScroll: true,
            onSuccess: () => setEditingInvite(false),
        });
    };

    const resendInvite = () => {
        if (!entrepreneur.invite_resend_url) {
            return;
        }

        router.post(
            entrepreneur.invite_resend_url,
            {},
            { preserveScroll: true },
        );
    };
    const cancelInvite = () => {
        if (!entrepreneur.invite_cancel_url) {
            return;
        }

        if (
            !window.confirm(
                `Cancel the pending invite for ${entrepreneur.name}? The current invite link will stop working.`,
            )
        ) {
            return;
        }

        router.delete(entrepreneur.invite_cancel_url, { preserveScroll: true });
    };

    /* eslint-disable react-hooks/set-state-in-effect */
    useEffect(() => {
        setGamificationEnabled(gamification.enabled);
    }, [gamification.enabled]);

    useEffect(() => {
        if (!ideaRefreshInFlight) {
            return;
        }

        const interval = window.setInterval(() => {
            router.reload({
                only: ['entrepreneur'],
            });
        }, 8000);

        return () => window.clearInterval(interval);
    }, [ideaRefreshInFlight]);
    /* eslint-enable react-hooks/set-state-in-effect */

    const toggleGamification = () => {
        const nextEnabled = !gamificationEnabled;

        setGamificationEnabled(nextEnabled);
        setGamificationPending(true);

        router.patch(
            gamification.toggle_url,
            { enabled: nextEnabled },
            {
                preserveScroll: true,
                onError: () => setGamificationEnabled(!nextEnabled),
                onFinish: () => setGamificationPending(false),
            },
        );
    };

    const refreshIdeaValidation = () => {
        if (!ideaValidation?.refresh_url) {
            return;
        }

        setIdeaRefreshPending(true);
        router.post(
            ideaValidation.refresh_url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setIdeaRefreshPending(false),
            },
        );
    };

    const approveIdeaGate = () => {
        if (!ideaValidation?.gate_url) {
            return;
        }

        router.patch(
            ideaValidation.gate_url,
            {
                advisor_gate_note: gateNote,
            },
            {
                preserveScroll: true,
                onSuccess: () => setGateNote(''),
            },
        );
    };

    const requestIdeaChanges = () => {
        if (!ideaValidation?.request_changes_url) {
            return;
        }

        router.patch(
            ideaValidation.request_changes_url,
            {
                change_request_note: changeRequestNote,
            },
            {
                preserveScroll: true,
                onSuccess: () => setChangeRequestNote(''),
            },
        );
    };

    return (
        <>
            <Head title={entrepreneur.name} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {entrepreneur.name}
                        </h1>
                        <div className="text-sm text-muted-foreground">
                            {entrepreneur.email}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild size="sm">
                            <Link href={entrepreneur.messages.url}>
                                <MessageSquare
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Messages
                            </Link>
                        </Button>
                        {entrepreneur.conversion.available ? (
                            <Button
                                type="button"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        entrepreneur.conversion.convert_url,
                                    )
                                }
                            >
                                Convert to client
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            size="sm"
                            variant={
                                gamificationEnabled ? 'secondary' : 'outline'
                            }
                            disabled={gamificationPending}
                            onClick={toggleGamification}
                        >
                            <Flame className="size-4" aria-hidden="true" />
                            {gamificationPending
                                ? gamificationEnabled
                                    ? 'Enabling gamification'
                                    : 'Disabling gamification'
                                : gamificationEnabled
                                  ? 'Gamification enabled'
                                  : 'Enable gamification'}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={copyInviteEmail}
                        >
                            <Copy className="size-4" aria-hidden="true" />
                            {copiedInviteEmail ? 'Email copied' : 'Copy email'}
                        </Button>
                        {latestAssessment ? (
                            <Button asChild size="sm" variant="outline">
                                <Link href={latestAssessment.url}>
                                    <ClipboardCheck
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Assessment
                                </Link>
                            </Button>
                        ) : null}
                        <Button asChild size="sm" variant="outline">
                            <Link
                                href={`/advisor/entrepreneurs/${entrepreneur.id}/surveys`}
                            >
                                <ClipboardCheck
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Surveys
                            </Link>
                        </Button>
                        <Button asChild size="sm" variant="ghost">
                            <Link href="/advisor/entrepreneurs">
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Back
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <ActionMetric
                        label="Stage"
                        value={entrepreneur.stage_label}
                        badge
                        href="#round-progress"
                        drillLabel="Review progress"
                        icon={
                            <UserRoundCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                        }
                        hoverTitle="Entrepreneur stage"
                        rows={[
                            {
                                label: 'Current stage',
                                value: entrepreneur.stage_label,
                            },
                            {
                                label: 'Account',
                                value: entrepreneur.user_id
                                    ? 'Linked'
                                    : 'Pending',
                                tone: entrepreneur.user_id
                                    ? 'positive'
                                    : 'muted',
                            },
                        ]}
                        footer="Stage is driven by invite, onboarding, assessment, and advisory readiness progress."
                    />
                    <ActionMetric
                        label="Messages"
                        value={`${entrepreneur.messages.threads_count} threads`}
                        href={entrepreneur.messages.url}
                        drillLabel="Open messages"
                        icon={
                            <MessageSquare
                                className="size-4"
                                aria-hidden="true"
                            />
                        }
                        hoverTitle="Message activity"
                        rows={[
                            {
                                label: 'Unread',
                                value: entrepreneur.messages.unread_count,
                                tone:
                                    entrepreneur.messages.unread_count > 0
                                        ? 'negative'
                                        : 'muted',
                            },
                            {
                                label: 'Latest activity',
                                value: formatDate(
                                    entrepreneur.messages.latest_activity_at,
                                ),
                            },
                        ]}
                        footer="Use messages for advisor-founder follow-up without leaving the entrepreneur record."
                    />
                    <ActionMetric
                        label="Assessment"
                        value={
                            latestAssessment
                                ? `${latestAssessment.weighted_score.toFixed(1)}/100`
                                : 'Not started'
                        }
                        href={latestAssessment?.url ?? '#round-progress'}
                        drillLabel={
                            latestAssessment
                                ? 'View assessment'
                                : 'Review progress'
                        }
                        icon={
                            <ClipboardCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                        }
                        hoverTitle="Latest assessment"
                        rows={[
                            {
                                label: 'Round',
                                value: latestAssessment?.round ?? '-',
                            },
                            {
                                label: 'Grade',
                                value: latestAssessment
                                    ? gradeLabel(latestAssessment.overall_grade)
                                    : '-',
                            },
                            {
                                label: 'Completed',
                                value: formatDate(
                                    latestAssessment?.finalised_at ?? null,
                                ),
                            },
                        ]}
                        footer="The score is the weighted assessment result from the latest advisory readiness round."
                    />
                    <ActionMetric
                        label="Documents"
                        value={`${entrepreneur.documents.length} recent`}
                        href="#documents"
                        drillLabel="Review documents"
                        icon={
                            <FileText className="size-4" aria-hidden="true" />
                        }
                        hoverTitle="Recent evidence"
                        rows={[
                            {
                                label: 'Clean files',
                                value: entrepreneur.documents.filter(
                                    (document) =>
                                        document.scanner_result === 'clean',
                                ).length,
                                tone: 'positive',
                            },
                            {
                                label: 'Latest upload',
                                value: formatDate(
                                    entrepreneur.documents[0]?.uploaded_at ??
                                        null,
                                ),
                            },
                        ]}
                        footer="Documents are evidence supplied through the entrepreneur portal or advisor messages."
                    />
                </div>

                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-medium">
                                Action panel
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Advisor next steps for this entrepreneur.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button asChild size="sm">
                                <Link href={entrepreneur.messages.url}>
                                    <MessageSquare
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Message founder
                                </Link>
                            </Button>
                            {latestAssessment ? (
                                <Button asChild size="sm" variant="outline">
                                    <Link href={latestAssessment.url}>
                                        <ClipboardCheck
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        View assessment
                                    </Link>
                                </Button>
                            ) : null}
                            {canRunAssessment ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            entrepreneur.latest_plan
                                                ?.assess_url ?? '',
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {latestAssessment ? (
                                        <RefreshCw
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        <ClipboardCheck
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    )}
                                    {latestAssessment
                                        ? 'Run reassessment'
                                        : 'Run assessment'}
                                </Button>
                            ) : null}
                            {latestAssessment &&
                            !latestAssessment.finalised_at ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.patch(
                                            latestAssessment.finalise_url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <CheckCircle2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Finalise report
                                </Button>
                            ) : null}
                            <Button asChild size="sm" variant="outline">
                                <a href="#documents">
                                    <FileText
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Evidence
                                </a>
                            </Button>
                        </div>
                    </div>

                    {latestAssessment && !latestAssessmentUsesCurrentRubric ? (
                        <div className="grid gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950 md:grid-cols-[1fr_auto] md:items-center">
                            <div className="flex gap-3">
                                <AlertTriangle
                                    className="mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p className="font-medium">
                                        Latest assessment uses an older rubric
                                    </p>
                                    <p>
                                        Round {latestAssessment.round} uses
                                        {formatRubricVersion(
                                            latestAssessment.rating_framework
                                                .version,
                                        )}{' '}
                                        with{' '}
                                        {
                                            latestAssessment.rating_framework
                                                .criteria_count
                                        }{' '}
                                        criteria. The current published rubric
                                        is{' '}
                                        {formatRubricVersion(
                                            latestAssessment.rating_framework
                                                .current_version,
                                        )}{' '}
                                        with{' '}
                                        {latestAssessment.rating_framework
                                            .current_criteria_count ?? '-'}{' '}
                                        criteria
                                        {latestAssessment.rating_framework
                                            .current_has_budget
                                            ? ', including Budget'
                                            : ''}
                                        .
                                    </p>
                                </div>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="border-amber-300 bg-white text-amber-950 hover:bg-amber-100"
                                onClick={() =>
                                    router.post(
                                        entrepreneur.latest_plan?.assess_url ??
                                            '',
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
                        </div>
                    ) : null}
                </section>

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="space-y-4 rounded-md border bg-background p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <ClipboardCheck
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-medium">
                                    Founder readiness
                                </h2>
                            </div>
                            <Badge variant="outline">
                                {entrepreneur.readiness.completed
                                    ? `${entrepreneur.readiness.score?.toFixed(1)}/100`
                                    : 'Needed'}
                            </Badge>
                        </div>
                        <dl className="grid gap-3 text-sm">
                            <Detail
                                label="Outcome"
                                value={
                                    entrepreneur.readiness.outcome
                                        ? gradeLabel(
                                              entrepreneur.readiness.outcome,
                                          )
                                        : null
                                }
                            />
                            <Detail
                                label="Assessed"
                                value={formatDate(
                                    entrepreneur.readiness.assessed_at,
                                )}
                            />
                        </dl>
                        <Button
                            asChild
                            size="sm"
                            variant={
                                entrepreneur.readiness.completed
                                    ? 'outline'
                                    : 'default'
                            }
                            className="w-full justify-start"
                        >
                            <Link href={entrepreneur.readiness.action_url}>
                                <ClipboardCheck
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {entrepreneur.readiness.action_label}
                            </Link>
                        </Button>
                    </section>

                    <section className="space-y-4 rounded-md border bg-background p-4 lg:col-span-2">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <UserRoundCheck
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-medium">
                                    Idea validation
                                </h2>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                {ideaValidation ? (
                                    <Badge variant="outline">
                                        Version {ideaValidation.revision_number}
                                    </Badge>
                                ) : null}
                                {ideaValidation?.ai_deferred &&
                                !ideaRecalled ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={ideaRefreshBusy}
                                        onClick={refreshIdeaValidation}
                                    >
                                        <RefreshCw
                                            className={cn(
                                                'size-4',
                                                ideaRefreshBusy &&
                                                    'animate-spin',
                                            )}
                                            aria-hidden="true"
                                        />
                                        {ideaRefreshButtonLabel}
                                    </Button>
                                ) : null}
                                <Badge variant={ideaGateBadgeVariant}>
                                    {ideaGateLabel}
                                </Badge>
                            </div>
                        </div>
                        {ideaValidation ? (
                            <div className="space-y-5">
                                {ideaValidation.ai_deferred && !ideaRecalled ? (
                                    <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                                        <p>
                                            {ideaRefreshInFlight
                                                ? 'AI review is queued and will update this record when complete.'
                                                : ideaRefreshFailed
                                                  ? ideaRefreshFailureMessage
                                                  : 'AI review is deferred. Use the submitted answers for manual review or rerun AI once the provider is live.'}
                                        </p>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            disabled={ideaRefreshBusy}
                                            onClick={refreshIdeaValidation}
                                            className="border-amber-300 bg-white text-amber-950 hover:bg-amber-100"
                                        >
                                            <RefreshCw
                                                className={cn(
                                                    'size-4',
                                                    ideaRefreshBusy &&
                                                        'animate-spin',
                                                )}
                                                aria-hidden="true"
                                            />
                                            {ideaRefreshButtonLabel}
                                        </Button>
                                    </div>
                                ) : null}

                                {ideaRecalled ? (
                                    <div className="rounded-md border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
                                        The founder recalled this validation for
                                        revision. It is no longer in the advisor
                                        review queue.
                                    </div>
                                ) : null}

                                {ideaGateStatus === 'changes_requested' &&
                                ideaValidation.change_request_note ? (
                                    <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                                        <p className="font-medium">
                                            Advisor feedback sent to founder
                                        </p>
                                        <p className="mt-1">
                                            {ideaValidation.change_request_note}
                                        </p>
                                        <p className="mt-2 text-xs">
                                            Requested{' '}
                                            {formatDate(
                                                ideaValidation.changes_requested_at,
                                            )}
                                        </p>
                                    </div>
                                ) : null}

                                <div className="rounded-md border bg-muted/30 p-3">
                                    <p className="text-xs font-medium text-muted-foreground uppercase">
                                        Decision read
                                    </p>
                                    <p className="mt-2 text-sm font-medium">
                                        {ideaVerdict ||
                                            'Submitted for advisor review.'}
                                    </p>
                                    {ideaSummaryRemainder ? (
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {ideaSummaryRemainder}
                                        </p>
                                    ) : null}
                                </div>

                                <dl className="grid gap-3 text-sm">
                                    {submittedIdeaFields.map((field) => (
                                        <Detail
                                            key={field.label}
                                            label={field.label}
                                            value={field.value}
                                        />
                                    ))}
                                </dl>

                                <div className="grid gap-3 rounded-md border bg-muted/20 p-3 text-sm md:grid-cols-2">
                                    <Detail
                                        label="AI uncertainty"
                                        value={
                                            ideaValidation.uncertainty
                                                ? gradeLabel(
                                                      ideaValidation.uncertainty,
                                                  )
                                                : null
                                        }
                                    />
                                    <Detail
                                        label="Cohort comparison"
                                        value={
                                            ideaValidation.past_plan_pattern
                                                ?.note ??
                                            cohortPatternLabel(
                                                ideaValidation.past_plan_pattern,
                                            )
                                        }
                                    />
                                    <Detail
                                        label="Restored from"
                                        value={
                                            ideaValidation.restored_from_revision_number
                                                ? `Version ${ideaValidation.restored_from_revision_number}`
                                                : null
                                        }
                                    />
                                </div>

                                {ideaValidation.viability_alerts.length > 0 ? (
                                    <div className="space-y-2 text-sm">
                                        <h3 className="text-xs font-medium text-muted-foreground">
                                            Viability alerts
                                        </h3>
                                        {ideaValidation.viability_alerts.map(
                                            (alert, index) => (
                                                <div
                                                    key={`${alert.type ?? 'alert'}-${index}`}
                                                    className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-amber-950"
                                                >
                                                    {alert.message ??
                                                        'Review this validation detail before approval.'}
                                                </div>
                                            ),
                                        )}
                                    </div>
                                ) : null}

                                {canDecideIdeaGate ? (
                                    <div className="grid gap-4 border-t pt-4 lg:grid-cols-2">
                                        <div className="space-y-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                                            <div>
                                                <p className="font-medium">
                                                    Request changes
                                                </p>
                                                <p className="mt-1">
                                                    Keeps the founder in Idea
                                                    Validation and reopens the
                                                    form for resubmission.
                                                </p>
                                            </div>
                                            <label className="grid gap-1">
                                                <span>Founder feedback</span>
                                                <textarea
                                                    value={changeRequestNote}
                                                    onChange={(event) =>
                                                        setChangeRequestNote(
                                                            event.target.value,
                                                        )
                                                    }
                                                    rows={4}
                                                    className="rounded-md border bg-background px-3 py-2 text-sm text-foreground"
                                                    placeholder="Tell the founder what to rethink, validate, or strengthen before resubmitting."
                                                />
                                            </label>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={
                                                    changeRequestNote.trim()
                                                        .length < 10
                                                }
                                                onClick={requestIdeaChanges}
                                                className="border-amber-300 bg-white text-amber-950 hover:bg-amber-100"
                                            >
                                                <MessageSquare
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Request changes
                                            </Button>
                                        </div>

                                        <div className="space-y-3 rounded-md border bg-muted/20 p-3 text-sm">
                                            <div>
                                                <p className="font-medium">
                                                    Approve builder gate
                                                </p>
                                                <p className="mt-1 text-muted-foreground">
                                                    Opens the business plan
                                                    builder and records your
                                                    gate note for audit.
                                                </p>
                                            </div>
                                            <label className="grid gap-1">
                                                <span>Idea gate note</span>
                                                <textarea
                                                    value={gateNote}
                                                    onChange={(event) =>
                                                        setGateNote(
                                                            event.target.value,
                                                        )
                                                    }
                                                    rows={4}
                                                    className="rounded-md border bg-background px-3 py-2 text-sm"
                                                    placeholder="Record why the idea is ready to enter the business plan builder."
                                                />
                                            </label>
                                            <Button
                                                type="button"
                                                size="sm"
                                                disabled={
                                                    gateNote.trim().length < 10
                                                }
                                                onClick={approveIdeaGate}
                                            >
                                                <CheckCircle2
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Approve builder gate
                                            </Button>
                                        </div>
                                    </div>
                                ) : ideaGateStatus === 'changes_requested' ? (
                                    <div className="rounded-md border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
                                        Waiting for the founder to revise and
                                        resubmit the idea validation.
                                    </div>
                                ) : ideaGateStatus === 'approved' ? (
                                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-950">
                                        Builder gate approved.{' '}
                                        {ideaValidation.advisor_gate_note
                                            ? `Gate note: ${ideaValidation.advisor_gate_note}`
                                            : null}
                                    </div>
                                ) : null}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No idea validation submitted yet.
                            </p>
                        )}
                    </section>

                    <section className="space-y-4 rounded-md border bg-background p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <FileText
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-medium">Reports</h2>
                            </div>
                            <Badge variant="outline">
                                {entrepreneur.reports.length}
                            </Badge>
                        </div>
                        {entrepreneur.reports.length > 0 ? (
                            <div className="space-y-2">
                                {entrepreneur.reports
                                    .slice(0, 2)
                                    .map((report) => (
                                        <Button
                                            key={report.id}
                                            asChild
                                            size="sm"
                                            variant="outline"
                                            className="w-full justify-start"
                                        >
                                            <a
                                                href={
                                                    report.view_url ??
                                                    report.download_url
                                                }
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <ArrowUpRight
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                View{' '}
                                                {formatDate(
                                                    report.generated_at,
                                                )}
                                            </a>
                                        </Button>
                                    ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Generated after assessment finalisation.
                            </p>
                        )}
                    </section>
                </div>

                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="space-y-2">
                            <div className="flex items-center gap-2">
                                <Trophy className="size-4" aria-hidden="true" />
                                <h2 className="text-sm font-medium">
                                    Gamification
                                </h2>
                            </div>
                            <Badge
                                variant={
                                    gamificationEnabled
                                        ? 'secondary'
                                        : 'outline'
                                }
                            >
                                {gamificationEnabled
                                    ? 'Gamification enabled'
                                    : 'Gamification disabled'}
                            </Badge>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            variant={
                                gamificationEnabled ? 'outline' : 'default'
                            }
                            disabled={gamificationPending}
                            onClick={toggleGamification}
                        >
                            {gamificationPending
                                ? gamificationEnabled
                                    ? 'Enabling'
                                    : 'Disabling'
                                : gamificationEnabled
                                  ? 'Disable gamification'
                                  : 'Enable gamification'}
                        </Button>
                    </div>

                    {gamificationEnabled ? (
                        <div className="grid gap-4 md:grid-cols-3">
                            <ActionMetric
                                label="Journey"
                                value={gamification.current_level?.label ?? '-'}
                                hoverTitle="Journey level"
                                rows={[
                                    {
                                        label: 'Stage',
                                        value:
                                            gamification.current_level
                                                ?.stage_label ?? '-',
                                    },
                                    {
                                        label: 'Phase',
                                        value:
                                            gamification.current_level?.phase ??
                                            '-',
                                    },
                                ]}
                            />
                            <ActionMetric
                                label="Plan completion"
                                value={`${gamification.plan_completion?.percent ?? 0}%`}
                                hoverTitle="Plan completion"
                                rows={[
                                    {
                                        label: 'Completed',
                                        value: `${gamification.plan_completion?.completed ?? 0}/${gamification.plan_completion?.total ?? 0}`,
                                    },
                                    {
                                        label: 'Next badge',
                                        value:
                                            gamification.next_milestone
                                                ?.label ?? 'Complete',
                                    },
                                ]}
                            />
                            <ActionMetric
                                label="Badges"
                                value={`${gamification.badges?.length ?? 0} earned`}
                                hoverTitle="Earned badges"
                                rows={[
                                    {
                                        label: 'New',
                                        value:
                                            gamification.new_badge_count ?? 0,
                                    },
                                    {
                                        label: 'Streak',
                                        value: `${gamification.current_streak ?? 0} days`,
                                    },
                                ]}
                            />
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Off for this entrepreneur.
                        </p>
                    )}
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section
                        id="invite"
                        className="space-y-4 rounded-md border bg-background p-4"
                    >
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <Mail className="size-4" aria-hidden="true" />
                                <h2 className="text-sm font-medium">Invite</h2>
                            </div>
                            <HoverBadge
                                label={
                                    entrepreneur.invite_accepted_at
                                        ? 'Accepted'
                                        : entrepreneur.invite_delivery_label
                                }
                                title="Invite status"
                                rows={[
                                    {
                                        label: 'Invite email',
                                        value: entrepreneur.email,
                                    },
                                    {
                                        label: 'Accepted',
                                        value: formatDate(
                                            entrepreneur.invite_accepted_at,
                                        ),
                                    },
                                    {
                                        label: 'Expires',
                                        value: formatDate(
                                            entrepreneur.invite_expires_at,
                                        ),
                                    },
                                    {
                                        label: 'Delivery',
                                        value: entrepreneur.invite_delivery_label,
                                    },
                                ]}
                            />
                        </div>
                        {editingInvite ? (
                            <form
                                onSubmit={saveInviteDetails}
                                className="space-y-4"
                            >
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="invite-name">
                                            Name
                                        </Label>
                                        <Input
                                            id="invite-name"
                                            value={inviteForm.data.name}
                                            onChange={(event) =>
                                                inviteForm.setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            required
                                        />
                                        <InputError
                                            message={inviteForm.errors.name}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="invite-email">
                                            Email
                                        </Label>
                                        <Input
                                            id="invite-email"
                                            type="email"
                                            value={inviteForm.data.email}
                                            onChange={(event) =>
                                                inviteForm.setData(
                                                    'email',
                                                    event.target.value,
                                                )
                                            }
                                            required
                                        />
                                        <InputError
                                            message={inviteForm.errors.email}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="invite-package-scope">
                                        Invite service
                                    </Label>
                                    <Select
                                        value={
                                            inviteForm.data
                                                .intended_package_scope
                                        }
                                        onValueChange={(value) =>
                                            inviteForm.setData(
                                                'intended_package_scope',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="invite-package-scope"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select client access" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {serviceOptions.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={
                                            inviteForm.errors
                                                .intended_package_scope
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="invite-concept-summary">
                                        Concept summary
                                    </Label>
                                    <textarea
                                        id="invite-concept-summary"
                                        value={inviteForm.data.concept_summary}
                                        onChange={(event) =>
                                            inviteForm.setData(
                                                'concept_summary',
                                                event.target.value,
                                            )
                                        }
                                        rows={4}
                                        className="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                    <InputError
                                        message={
                                            inviteForm.errors.concept_summary
                                        }
                                    />
                                </div>

                                {entrepreneur.invite_delivery_label ===
                                'Email sent' ? (
                                    <p className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                                        If the email or invite service changes,
                                        the current invite link will be stopped.
                                        Use Resend invite after saving to send a
                                        fresh link to the corrected address.
                                    </p>
                                ) : null}

                                <InputError message={inviteErrors.invite} />

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={inviteForm.processing}
                                    >
                                        <Save
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Save details
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={cancelInviteEdit}
                                        disabled={inviteForm.processing}
                                    >
                                        Cancel edit
                                    </Button>
                                </div>
                            </form>
                        ) : (
                            <>
                                <dl className="grid gap-3 text-sm">
                                    <Detail
                                        label="Email"
                                        value={entrepreneur.email}
                                    />
                                    <Detail
                                        label="Invite service"
                                        value={
                                            entrepreneur.intended_package_scope_label
                                        }
                                    />
                                    <Detail
                                        label="Accepted"
                                        value={formatDate(
                                            entrepreneur.invite_accepted_at,
                                        )}
                                    />
                                    <Detail
                                        label="Expires"
                                        value={formatDate(
                                            entrepreneur.invite_expires_at,
                                        )}
                                    />
                                    <Detail
                                        label="Created"
                                        value={formatDate(
                                            entrepreneur.created_at,
                                        )}
                                    />
                                    <Detail
                                        label="Delivery"
                                        value={
                                            entrepreneur.invite_delivery_label
                                        }
                                    />
                                </dl>
                                <div className="flex flex-wrap gap-2">
                                    {entrepreneur.invite_update_url ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={startInviteEdit}
                                        >
                                            <Pencil
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Edit invite
                                        </Button>
                                    ) : null}
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={copyInviteEmail}
                                    >
                                        <Copy
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {copiedInviteEmail
                                            ? 'Copied email'
                                            : 'Copy invite email'}
                                    </Button>
                                    {entrepreneur.invite_resend_url ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={resendInvite}
                                        >
                                            <RefreshCw
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Resend invite
                                        </Button>
                                    ) : null}
                                    {entrepreneur.invite_cancel_url ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="destructive"
                                            onClick={cancelInvite}
                                        >
                                            <XCircle
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Cancel invite
                                        </Button>
                                    ) : null}
                                </div>
                            </>
                        )}
                    </section>

                    <section
                        id="concept"
                        className="space-y-4 rounded-md border bg-background p-4"
                    >
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <UserRoundCheck
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-medium">Concept</h2>
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <Link href={entrepreneur.messages.url}>
                                    <MessageSquare
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Discuss
                                </Link>
                            </Button>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {entrepreneur.concept_summary || 'No summary yet.'}
                        </p>
                    </section>
                </div>

                {entrepreneur.latest_plan ? (
                    <section
                        id="round-progress"
                        className="space-y-4 rounded-md border bg-background p-4"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <TrendingUp
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-medium">
                                    Round progress
                                </h2>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <HoverBadge
                                    label={entrepreneur.latest_plan.status}
                                    title="Plan status"
                                    rows={[
                                        {
                                            label: 'Plan title',
                                            value: entrepreneur.latest_plan
                                                .title,
                                        },
                                        {
                                            label: 'Assessments',
                                            value: entrepreneur.latest_plan
                                                .assessment_count,
                                        },
                                    ]}
                                />
                                {entrepreneur.latest_plan.latest_grade ? (
                                    <HoverBadge
                                        label={gradeLabel(
                                            entrepreneur.latest_plan
                                                .latest_grade,
                                        )}
                                        variant="outline"
                                        title="Latest grade"
                                        rows={[
                                            {
                                                label: 'Round',
                                                value:
                                                    entrepreneur.latest_plan
                                                        .latest_round ?? '-',
                                            },
                                            {
                                                label: 'Score',
                                                value: latestAssessment
                                                    ? `${latestAssessment.weighted_score.toFixed(1)}/100`
                                                    : '-',
                                            },
                                        ]}
                                    />
                                ) : null}
                                {latestAssessment ? (
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={latestAssessment.url}>
                                            <ArrowUpRight
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            View assessment
                                        </Link>
                                    </Button>
                                ) : null}
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            <ActionMetric
                                label="Assessments"
                                value={String(
                                    entrepreneur.latest_plan.assessment_count,
                                )}
                                href={latestAssessment?.url}
                                drillLabel="Open latest"
                                hoverTitle="Assessment count"
                                rows={[
                                    {
                                        label: 'Total rounds',
                                        value: entrepreneur.latest_plan
                                            .assessment_count,
                                    },
                                    {
                                        label: 'Latest round',
                                        value:
                                            entrepreneur.latest_plan
                                                .latest_round ?? '-',
                                    },
                                ]}
                                footer="Each round captures scored criteria and advisor notes for the plan."
                            />
                            <ActionMetric
                                label="Latest round"
                                value={
                                    entrepreneur.latest_plan.latest_round
                                        ? String(
                                              entrepreneur.latest_plan
                                                  .latest_round,
                                          )
                                        : '-'
                                }
                                href={latestAssessment?.url}
                                drillLabel="View round"
                                hoverTitle="Latest assessment round"
                                rows={[
                                    {
                                        label: 'Status',
                                        value: latestAssessment
                                            ? gradeLabel(
                                                  latestAssessment.status,
                                              )
                                            : '-',
                                    },
                                    {
                                        label: 'Completed',
                                        value: formatDate(
                                            latestAssessment?.finalised_at ??
                                                null,
                                        ),
                                    },
                                ]}
                            />
                            <ActionMetric
                                label="Trajectory"
                                value={formatDelta(
                                    entrepreneur.latest_plan.latest_revision
                                        ?.trajectory_percent,
                                    '%',
                                )}
                                href="#round-progress"
                                drillLabel="Review movement"
                                hoverTitle="Trajectory"
                                rows={[
                                    {
                                        label: 'Overall movement',
                                        value: formatDelta(
                                            entrepreneur.latest_plan
                                                .latest_revision?.overall_delta,
                                        ),
                                    },
                                    {
                                        label: 'Revision round',
                                        value:
                                            entrepreneur.latest_plan
                                                .latest_revision?.round ?? '-',
                                    },
                                ]}
                                footer="Trajectory compares the latest plan revision against the prior scoring baseline."
                            />
                            <ActionMetric
                                label="Budget"
                                value={formatRunway(
                                    entrepreneur.latest_plan.budget
                                        .calculated_runway_months,
                                    entrepreneur.latest_plan.budget
                                        .runway_open_ended,
                                )}
                                icon={
                                    <Banknote
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                }
                                hoverTitle="Budget runway"
                                rows={[
                                    {
                                        label: 'Status',
                                        value: gradeLabel(
                                            entrepreneur.latest_plan.budget
                                                .status,
                                        ),
                                    },
                                    {
                                        label: 'Expected runway',
                                        value: formatRunway(
                                            entrepreneur.latest_plan.budget
                                                .expected_runway_months,
                                            false,
                                        ),
                                    },
                                    {
                                        label: 'After launch',
                                        value: formatCurrency(
                                            entrepreneur.latest_plan.budget
                                                .available_after_launch,
                                        ),
                                    },
                                    {
                                        label: 'Active warnings',
                                        value: entrepreneur.latest_plan.budget
                                            .active_flags.length,
                                        tone:
                                            entrepreneur.latest_plan.budget
                                                .active_flags.length > 0
                                                ? 'negative'
                                                : 'muted',
                                    },
                                ]}
                                footer={
                                    entrepreneur.latest_plan.budget.active_flags
                                        .length > 0
                                        ? entrepreneur.latest_plan.budget.active_flags
                                              .map((flag) => flag.title)
                                              .join(' ')
                                        : 'No unresolved budget warnings.'
                                }
                            />
                        </div>

                        {entrepreneur.latest_plan.latest_revision ? (
                            <div className="grid gap-6 lg:grid-cols-2">
                                <ProgressList
                                    title="Biggest improvements"
                                    rows={
                                        entrepreneur.latest_plan.latest_revision
                                            .biggest_improvements
                                    }
                                    empty="No positive movement yet."
                                />
                                <ProgressList
                                    title="Remaining gaps"
                                    rows={
                                        entrepreneur.latest_plan.latest_revision
                                            .remaining_gaps
                                    }
                                    empty="No criteria below 60."
                                />
                            </div>
                        ) : null}
                    </section>
                ) : null}

                <div className="grid gap-6 lg:grid-cols-2">
                    <section
                        id="documents"
                        className="space-y-4 rounded-md border bg-background p-4"
                    >
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <FileText
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-medium">
                                    Recent documents
                                </h2>
                            </div>
                            <Badge variant="outline">
                                {entrepreneur.documents.length}
                            </Badge>
                        </div>

                        {entrepreneur.documents.length > 0 ? (
                            <div className="divide-y rounded-md border">
                                {entrepreneur.documents.map((document) => (
                                    <DocumentRow
                                        key={document.id}
                                        document={document}
                                    />
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No evidence documents have been uploaded yet.
                            </p>
                        )}
                    </section>

                    <section className="space-y-4 rounded-md border bg-background p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <MessageSquare
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-medium">
                                    Messages
                                </h2>
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <Link href={entrepreneur.messages.url}>
                                    <ArrowUpRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Open
                                </Link>
                            </Button>
                        </div>
                        <dl className="grid gap-3 text-sm">
                            <Detail
                                label="Threads"
                                value={String(
                                    entrepreneur.messages.threads_count,
                                )}
                            />
                            <Detail
                                label="Unread"
                                value={String(
                                    entrepreneur.messages.unread_count,
                                )}
                            />
                            <Detail
                                label="Latest activity"
                                value={formatDate(
                                    entrepreneur.messages.latest_activity_at,
                                )}
                            />
                        </dl>
                    </section>
                </div>
            </div>
        </>
    );
}

function ActionMetric({
    label,
    value,
    badge = false,
    href,
    drillLabel = 'Open',
    icon,
    hoverTitle,
    rows,
    footer,
}: {
    label: string;
    value: string;
    badge?: boolean;
    href?: string;
    drillLabel?: string;
    icon?: ReactNode;
    hoverTitle: string;
    rows: InsightHoverCardRow[];
    footer?: ReactNode;
}) {
    const className = cn(
        'block min-h-28 rounded-md border bg-background p-4 text-left transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
        href ? 'hover:bg-muted/50' : 'cursor-help',
    );
    const content = (
        <>
            <div className="flex items-start justify-between gap-3">
                <div className="text-xs text-muted-foreground">{label}</div>
                {icon ? (
                    <span className="text-muted-foreground">{icon}</span>
                ) : null}
            </div>
            <div className="mt-3 text-sm font-medium">
                {badge ? <Badge variant="secondary">{value}</Badge> : value}
            </div>
            {href ? (
                <div className="mt-4 inline-flex items-center gap-1 text-xs font-medium text-primary">
                    {drillLabel}
                    <ArrowUpRight className="size-3" aria-hidden="true" />
                </div>
            ) : null}
        </>
    );

    const trigger =
        href && href.startsWith('#') ? (
            <a href={href} className={className}>
                {content}
            </a>
        ) : href ? (
            <Link href={href} className={className}>
                {content}
            </Link>
        ) : (
            <div className={className}>{content}</div>
        );

    return (
        <InsightHoverCard
            title={hoverTitle}
            rows={rows}
            drillHref={href && !href.startsWith('#') ? href : undefined}
            drillLabel={drillLabel}
            footer={footer}
        >
            {trigger}
        </InsightHoverCard>
    );
}

function HoverBadge({
    label,
    title,
    rows,
    variant = 'secondary',
}: {
    label: string;
    title: string;
    rows: InsightHoverCardRow[];
    variant?: 'default' | 'secondary' | 'destructive' | 'outline';
}) {
    return (
        <InsightHoverCard title={title} rows={rows}>
            <Badge variant={variant} className="cursor-help">
                {label}
            </Badge>
        </InsightHoverCard>
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
            <dd className="min-w-0 break-words">{value || '-'}</dd>
        </div>
    );
}

function DocumentRow({ document }: { document: EntrepreneurDocument }) {
    return (
        <InsightHoverCard
            title={document.original_filename}
            rows={[
                { label: 'Category', value: categoryLabel(document.category) },
                {
                    label: 'Scan',
                    value: scannerLabel(document.scanner_result),
                    tone:
                        document.scanner_result === 'clean'
                            ? 'positive'
                            : 'muted',
                },
                { label: 'Uploaded', value: formatDate(document.uploaded_at) },
                {
                    label: 'Uploaded by',
                    value: document.uploaded_by_name ?? '-',
                },
            ]}
            drillHref={document.url}
            drillLabel="Open document"
            drillNewWindow
            footer="Advisor document access opens the stored evidence file when the scan state allows it."
        >
            <a
                href={document.url}
                target="_blank"
                rel="noopener noreferrer"
                className="grid gap-2 p-3 text-sm transition-colors outline-none hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:grid-cols-[minmax(0,1fr)_auto]"
            >
                <div className="min-w-0">
                    <div className="truncate font-medium">
                        {document.original_filename}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {categoryLabel(document.category)}
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                    <Badge
                        variant={
                            document.scanner_result === 'infected'
                                ? 'destructive'
                                : document.scanner_result === 'clean'
                                  ? 'secondary'
                                  : 'outline'
                        }
                    >
                        {scannerLabel(document.scanner_result)}
                    </Badge>
                    <span className="text-xs text-muted-foreground">
                        {formatDate(document.uploaded_at)}
                    </span>
                    <ArrowUpRight className="size-4" aria-hidden="true" />
                </div>
            </a>
        </InsightHoverCard>
    );
}

function firstSentence(value: string | null): string | null {
    const text = value?.trim();

    if (!text) {
        return null;
    }

    const match = text.match(/^(.+?[.!?])(\s|$)/);

    return match?.[1] ?? text;
}

function summaryRemainder(
    summary: string | null | undefined,
    first: string | null,
): string | null {
    const text = summary?.trim();

    if (!text || !first || text === first) {
        return null;
    }

    return text.slice(first.length).trim() || null;
}

function cohortPatternLabel(
    pattern:
        | {
              source_reference?: string;
              cohort?: number;
              industry?: string;
              note?: string;
          }
        | null
        | undefined,
): string | null {
    if (!pattern) {
        return null;
    }

    if (pattern.cohort && pattern.industry) {
        return `${pattern.cohort} prior ${gradeLabel(pattern.industry)} patterns`;
    }

    return pattern.source_reference ?? null;
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatDelta(value: number | null | undefined, suffix = ''): string {
    if (value === null || value === undefined) {
        return '-';
    }

    const sign = value > 0 ? '+' : '';

    return `${sign}${value.toFixed(1)}${suffix}`;
}

function formatRubricVersion(value: number | null): string {
    return value ? `rubric v${value}` : 'the assigned rubric';
}

function gradeLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function formatCurrency(value: number | null | undefined): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'NZD',
        maximumFractionDigits: 0,
    }).format(value ?? 0);
}

function formatRunway(
    months: number | null | undefined,
    openEnded: boolean,
): string {
    if (months === null || months === undefined) {
        return '-';
    }

    return openEnded ? `${months}+ months` : `${months} months`;
}

function categoryLabel(value: string): string {
    return gradeLabel(value);
}

function scannerLabel(value: string): string {
    return gradeLabel(value);
}

function ProgressList({
    title,
    rows,
    empty,
}: {
    title: string;
    rows: CriterionDelta[];
    empty: string;
}) {
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
                            rows={[
                                {
                                    label: 'Previous',
                                    value: row.previous_score ?? '-',
                                },
                                { label: 'Current', value: row.current_score },
                                {
                                    label: 'Movement',
                                    value: formatDelta(row.delta),
                                    tone:
                                        row.delta >= 0
                                            ? 'positive'
                                            : 'negative',
                                },
                            ]}
                            footer="Movement compares this criterion against the previous round."
                        >
                            <div className="flex cursor-help items-center justify-between gap-3 rounded-md border p-3 text-sm">
                                <div className="min-w-0">
                                    <div className="truncate font-medium">
                                        {row.criterion_name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {row.previous_score ?? '-'} -&gt;{' '}
                                        {row.current_score}
                                    </div>
                                </div>
                                <Badge
                                    variant={
                                        row.delta >= 0 ? 'secondary' : 'outline'
                                    }
                                >
                                    {formatDelta(row.delta)}
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

EntrepreneursShow.layout = {
    breadcrumbs: [
        {
            title: 'Entrepreneurs',
            href: '/advisor/entrepreneurs',
        },
    ],
};
