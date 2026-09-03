import { Link, router } from '@inertiajs/react';
import {
    Bot,
    ChevronDown,
    ChevronUp,
    CheckCircle2,
    Eye,
    Pencil,
    Trophy,
    Upload,
} from 'lucide-react';
import FileDropzone from '@/components/file-dropzone';
import { FormattedTextarea } from '@/components/formatted-textarea';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    BudgetEditor,
    requirementId,
    sectionAutosaveStateLabel,
} from './plan-budget';
import {
    ActionPanel,
    IdeaValidationSnapshot,
    PlainLanguageGuide,
    formatDate,
    formatLabel,
    ideaFields,
} from './plan-dashboard-panels';
import {
    IDEA_VALIDATION_FIELD_MAX_LENGTH,
    PLAN_SECTION_BODY_MAX_LENGTH,
} from './plan-types';
import {
    PlanCompletionAction,
    PlanWorkspaceHistory,
    planChangesAreLocked,
} from './plan-workspace-submission';
import type { PlanWorkspace } from './use-plan-workspace';

export function PlanWorkspaceActions({
    workspace,
}: {
    workspace: PlanWorkspace;
}) {
    const {
        packageAccess,
        ideaValidation,
        ideaValidationVersions,
        plan,
        advisoryRequest,
        gamification,
        ideaForm,
        setShowValidatedIdeaForm,
        recallingIdea,
        restoringIdeaVersionId,
        phases,
        setSelectedKey,
        selectedRequirement,
        selectedSection,
        planCompletion,
        includesIdeaValidation,
        includesPlanBudget,
        hasIdeaValidation,
        hasPlan,
        planBuilderUnlocked,
        ideaValidationApproved,
        ideaChangesRequested,
        ideaValidationRecalled,
        ideaUnderAdvisorReview,
        showIdeaValidationEditor,
        ideaValidationSummary,
        nextSmallWin,
        sectionTitle,
        setSectionTitle,
        sectionBody,
        setSectionBody,
        supportingFile,
        setSupportingFile,
        supportingKey,
        sectionError,
        savingSection,
        assistingSection,
        assistantNotice,
        budgetForm,
        setBudgetForm,
        savingBudget,
        sectionAutosaveState,
        budgetAutosaveState,
        submitIdea,
        recallIdeaForRevision,
        restoreIdeaVersion,
        rememberWorkspacePosition,
        startPlan,
        submitPlan,
        requestAdvisory,
        assistRequirement,
        saveSection,
        saveBudget,
        acknowledgeBudgetFlag,
        dismissBudgetAdvisorNudge,
    } = workspace;
    const planChangesLocked = planChangesAreLocked(plan);

    return (
        <div className="space-y-6">
            <section className="space-y-3">
                <div>
                    <h2 className="text-base font-semibold">
                        Priority actions
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {packageAccess.package_scope_label}
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <ActionPanel
                        icon={Bot}
                        title="Idea validation"
                        value={
                            !includesIdeaValidation
                                ? 'Not included'
                                : ideaValidation
                                  ? planBuilderUnlocked
                                      ? 'Advisor approved'
                                      : ideaChangesRequested
                                        ? 'Changes requested'
                                        : ideaValidationRecalled
                                          ? 'Ready to revise'
                                          : 'Awaiting advisor gate'
                                  : 'Not submitted'
                        }
                        explanation="Idea validation captures the customer problem, solution, demand, and revenue logic before the plan builder opens."
                    >
                        {!includesIdeaValidation ? (
                            <Badge variant="outline">Not in package</Badge>
                        ) : !ideaValidation ? (
                            <Button asChild size="sm">
                                <a href="#idea-validation">
                                    Start idea validation
                                </a>
                            </Button>
                        ) : planBuilderUnlocked ? (
                            <Badge variant="secondary">Builder unlocked</Badge>
                        ) : ideaChangesRequested ? (
                            <Badge variant="outline">Changes requested</Badge>
                        ) : ideaValidationRecalled ? (
                            <Badge variant="outline">Ready to revise</Badge>
                        ) : (
                            <Badge variant="outline">Advisor review</Badge>
                        )}
                    </ActionPanel>

                    <PlanCompletionAction
                        includesPlanBudget={includesPlanBudget}
                        plan={plan}
                        planBuilderUnlocked={planBuilderUnlocked}
                        startPlan={startPlan}
                        submitPlan={submitPlan}
                    />

                    <ActionPanel
                        icon={Eye}
                        title="Assessment"
                        value={
                            !includesPlanBudget
                                ? 'Not included'
                                : plan?.latest_assessment
                                  ? `${formatLabel(plan.latest_assessment.overall_grade)}`
                                  : 'Pending'
                        }
                        explanation="Assessment appears once your advisor scores the submitted plan and finalises feedback."
                    >
                        {!includesPlanBudget ? (
                            <Badge variant="outline">Not in package</Badge>
                        ) : plan?.latest_assessment ? (
                            <Button asChild size="sm" variant="outline">
                                <Link href={plan.latest_assessment.url}>
                                    View assessment
                                </Link>
                            </Button>
                        ) : (
                            <Badge variant="outline">Advisor action</Badge>
                        )}
                    </ActionPanel>

                    <ActionPanel
                        icon={CheckCircle2}
                        title="Advisory"
                        value={
                            !includesPlanBudget
                                ? 'Not included'
                                : advisoryRequest.requested
                                  ? 'Requested'
                                  : advisoryRequest.available
                                    ? 'Available'
                                    : 'Locked'
                        }
                        explanation="Request advisory once the plan has been assessed as advisory ready. This asks your advisor to convert the plan into a standard advisory engagement."
                    >
                        {!includesPlanBudget ? (
                            <Badge variant="outline">Not in package</Badge>
                        ) : advisoryRequest.requested &&
                          advisoryRequest.thread_url ? (
                            <Button asChild size="sm" variant="outline">
                                <Link href={advisoryRequest.thread_url}>
                                    Open request
                                </Link>
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                size="sm"
                                disabled={!advisoryRequest.available}
                                onClick={requestAdvisory}
                            >
                                Request advisory
                            </Button>
                        )}
                    </ActionPanel>
                </div>
            </section>

            <section className="rounded-md border bg-background p-4">
                <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr] lg:items-center">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Trophy className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">
                                {gamification.enabled
                                    ? 'Next small win'
                                    : 'Next step'}
                            </h2>
                            <Badge variant="outline">
                                {nextSmallWin.badge}
                            </Badge>
                        </div>
                        <div className="mt-2 text-sm font-medium">
                            {nextSmallWin.title}
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {nextSmallWin.body}
                        </p>
                        <p className="mt-2 text-xs text-muted-foreground">
                            AI assist can help draft or simplify wording; your
                            advisor still reviews the evidence and agrees the
                            next step with you.
                        </p>
                        {nextSmallWin.action === 'Start idea validation' ? (
                            <div className="mt-3">
                                <Button asChild size="sm">
                                    <a href="#idea-validation">
                                        Start idea validation
                                    </a>
                                </Button>
                            </div>
                        ) : null}
                        {nextSmallWin.action === 'Revise idea validation' ? (
                            <div className="mt-3">
                                <Button asChild size="sm">
                                    <a href="#idea-validation">
                                        Revise idea validation
                                    </a>
                                </Button>
                            </div>
                        ) : null}
                        {nextSmallWin.action === 'Start plan' ? (
                            <div className="mt-3">
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={startPlan}
                                >
                                    Start plan
                                </Button>
                            </div>
                        ) : null}
                        {gamification.enabled && gamification.next_quest ? (
                            <p className="mt-1 text-xs text-muted-foreground">
                                Next quest: {gamification.next_quest.label} for{' '}
                                {gamification.next_quest.points} points.
                            </p>
                        ) : null}
                    </div>
                    <div className="space-y-2">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span>Plan progress</span>
                            <span>{planCompletion.percent}%</span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-emerald-500 transition-all"
                                style={{
                                    width: `${Math.min(100, Math.max(0, planCompletion.percent))}%`,
                                }}
                            />
                        </div>
                        {gamification.enabled ? (
                            <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                                <span>
                                    Streak {gamification.current_streak ?? 0}{' '}
                                    days
                                </span>
                                {(gamification.new_badge_count ?? 0) > 0 ? (
                                    <span>
                                        {gamification.new_badge_count} new badge
                                        {gamification.new_badge_count === 1
                                            ? ''
                                            : 's'}
                                    </span>
                                ) : null}
                            </div>
                        ) : null}
                    </div>
                </div>
            </section>

            <PlainLanguageGuide />

            {includesIdeaValidation ? (
                <section
                    id="idea-validation"
                    className="space-y-4 rounded-md border bg-background p-4"
                >
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">Step 1</Badge>
                                {ideaValidation ? (
                                    <Badge variant="outline">
                                        Version {ideaValidation.revision_number}
                                    </Badge>
                                ) : null}
                                <h2 className="text-sm font-medium">
                                    Idea validation
                                </h2>
                            </div>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Capture the customer problem, solution, demand,
                                and revenue logic before detailed plan work
                                starts.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {ideaValidationApproved ? (
                                <Badge variant="secondary">Gate passed</Badge>
                            ) : ideaChangesRequested ? (
                                <Badge variant="outline">
                                    Changes requested
                                </Badge>
                            ) : ideaValidationRecalled ? (
                                <Badge variant="outline">Ready to revise</Badge>
                            ) : ideaValidation ? (
                                <Badge variant="outline">Advisor review</Badge>
                            ) : (
                                <Badge variant="outline">Not submitted</Badge>
                            )}
                            {ideaValidationApproved ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        setShowValidatedIdeaForm(
                                            (current) => !current,
                                        )
                                    }
                                >
                                    {showIdeaValidationEditor ? (
                                        <ChevronUp
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        <ChevronDown
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    )}
                                    {showIdeaValidationEditor
                                        ? 'Roll up'
                                        : 'Review details'}
                                </Button>
                            ) : null}
                        </div>
                    </div>

                    {ideaChangesRequested &&
                    ideaValidation?.change_request_note ? (
                        <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950">
                            <div className="font-medium">Advisor feedback</div>
                            <p className="mt-1">
                                {ideaValidation.change_request_note}
                            </p>
                        </div>
                    ) : null}

                    {ideaValidationRecalled ? (
                        <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                            This validation has been removed from advisor
                            review. Update the answers below, then resubmit it
                            when ready.
                        </div>
                    ) : null}

                    {ideaValidation?.restored_from_revision_number ? (
                        <div className="text-xs text-muted-foreground">
                            Restored from version{' '}
                            {ideaValidation.restored_from_revision_number}
                        </div>
                    ) : null}

                    {ideaValidationApproved && !showIdeaValidationEditor ? (
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {ideaValidationSummary.map((item) => (
                                <div
                                    key={item.label}
                                    className="rounded-md border bg-muted/20 p-3"
                                >
                                    <div className="text-xs font-medium text-muted-foreground">
                                        {item.label}
                                    </div>
                                    <p className="mt-1 line-clamp-2 text-sm">
                                        {item.value || '-'}
                                    </p>
                                </div>
                            ))}
                            {ideaValidation?.advisor_gate_note ? (
                                <div className="rounded-md border bg-muted/20 p-3 md:col-span-2 xl:col-span-3">
                                    <div className="text-xs font-medium text-muted-foreground">
                                        Advisor note
                                    </div>
                                    <p className="mt-1 text-sm">
                                        {ideaValidation.advisor_gate_note}
                                    </p>
                                </div>
                            ) : null}
                        </div>
                    ) : showIdeaValidationEditor ? (
                        <form
                            className="grid gap-3 lg:grid-cols-2"
                            onSubmit={submitIdea}
                        >
                            {ideaChangesRequested || ideaValidationRecalled ? (
                                <div className="lg:col-span-2">
                                    <IdeaValidationSnapshot
                                        fields={ideaValidationSummary}
                                        revisionNumber={
                                            ideaValidation?.revision_number ??
                                            null
                                        }
                                        submittedAt={
                                            ideaValidation?.evaluated_at ?? null
                                        }
                                    />
                                </div>
                            ) : null}
                            {ideaFields.map((field) => {
                                const fieldValue = ideaForm.data[field.key];
                                const fieldId = `idea-validation-${field.key}`;

                                return (
                                    <div
                                        key={field.key}
                                        className="grid gap-1 text-sm"
                                    >
                                        <span className="flex items-center justify-between gap-3">
                                            <label htmlFor={fieldId}>
                                                {field.label}
                                            </label>
                                            <span className="shrink-0 text-xs font-normal text-muted-foreground tabular-nums">
                                                {fieldValue.length}
                                                {' / '}
                                                {
                                                    IDEA_VALIDATION_FIELD_MAX_LENGTH
                                                }
                                            </span>
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {field.plain}
                                        </span>
                                        <FormattedTextarea
                                            id={fieldId}
                                            value={fieldValue}
                                            onChange={(value) =>
                                                ideaForm.setData(
                                                    field.key,
                                                    value,
                                                )
                                            }
                                            maxLength={
                                                IDEA_VALIDATION_FIELD_MAX_LENGTH
                                            }
                                            rows={4}
                                            placeholder={field.placeholder}
                                            ariaLabel={field.label}
                                        />
                                        <InputError
                                            message={ideaForm.errors[field.key]}
                                        />
                                    </div>
                                );
                            })}
                            <div className="lg:col-span-2">
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={ideaForm.processing}
                                >
                                    {ideaForm.processing
                                        ? 'Submitting...'
                                        : ideaChangesRequested ||
                                            ideaValidationRecalled
                                          ? 'Resubmit idea validation'
                                          : ideaValidation
                                            ? 'Update idea validation'
                                            : 'Submit idea validation'}
                                </Button>
                                {ideaForm.recentlySuccessful ? (
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        Idea validation submitted for advisor
                                        review.
                                    </p>
                                ) : null}
                            </div>
                        </form>
                    ) : ideaUnderAdvisorReview ? (
                        <div className="space-y-3">
                            <div className="rounded-md border border-dashed bg-muted/20 p-4 text-sm text-muted-foreground">
                                Your idea validation is with your advisor. They
                                will either approve the builder gate or request
                                changes.
                            </div>
                            <IdeaValidationSnapshot
                                fields={ideaValidationSummary}
                                revisionNumber={
                                    ideaValidation?.revision_number ?? null
                                }
                                submittedAt={
                                    ideaValidation?.evaluated_at ?? null
                                }
                            />
                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={recallingIdea}
                                    onClick={recallIdeaForRevision}
                                >
                                    <Pencil
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {recallingIdea
                                        ? 'Recalling...'
                                        : 'Recall for revision'}
                                </Button>
                                <p className="text-xs text-muted-foreground">
                                    Removes this submission from advisor review
                                    while you update it.
                                </p>
                            </div>
                        </div>
                    ) : null}
                </section>
            ) : null}

            <section
                id="business-plan-requirements"
                className="space-y-4 rounded-md border bg-background p-4"
            >
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="text-sm font-medium">
                            Plan requirements
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {includesPlanBudget
                                ? 'Complete every required section and attach supporting evidence where it helps the advisor rely on the plan.'
                                : 'Business plan and budget are not included in this package.'}
                        </p>
                    </div>
                    {!plan && includesPlanBudget ? (
                        <Button
                            type="button"
                            size="sm"
                            onClick={startPlan}
                            disabled={!planBuilderUnlocked}
                        >
                            Start plan
                        </Button>
                    ) : null}
                </div>

                {!includesPlanBudget ? (
                    <div className="rounded-md border border-dashed bg-muted/20 p-4">
                        <h3 className="text-sm font-medium">
                            Not included in this package
                        </h3>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                            This package covers idea validation only. Your
                            advisor can invite you to Business Plan + Budget or
                            the bundle package if you decide to progress.
                        </p>
                    </div>
                ) : hasPlan ? (
                    <div className="grid items-start gap-6 xl:grid-cols-[minmax(15rem,0.58fr)_minmax(0,1.42fr)]">
                        <div className="space-y-3 xl:sticky xl:top-24 xl:self-start">
                            {phases.map((phase) => (
                                <div key={phase.key} className="space-y-2">
                                    <div className="text-xs font-medium text-muted-foreground">
                                        {phase.title}
                                    </div>
                                    {phase.requirements.map((requirement) => (
                                        <button
                                            key={requirementId(requirement)}
                                            type="button"
                                            className={cn(
                                                'w-full rounded-md border p-3 text-left text-sm transition-colors outline-none hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                                selectedRequirement &&
                                                    requirementId(
                                                        requirement,
                                                    ) ===
                                                        requirementId(
                                                            selectedRequirement,
                                                        ) &&
                                                    'border-foreground',
                                            )}
                                            onClick={() => {
                                                rememberWorkspacePosition();
                                                setSelectedKey(
                                                    requirementId(requirement),
                                                );
                                            }}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <div className="font-medium">
                                                        {requirement.title}
                                                    </div>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {
                                                            requirement.description
                                                        }
                                                    </p>
                                                </div>
                                                <Badge
                                                    variant={
                                                        requirement.complete
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {requirement.complete
                                                        ? 'Complete'
                                                        : 'Needed'}
                                                </Badge>
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            ))}
                        </div>

                        <div className="min-w-0 space-y-4 rounded-md border p-4 xl:self-start">
                            {selectedRequirement ? (
                                selectedRequirement.type === 'budget' &&
                                plan ? (
                                    planChangesLocked ? (
                                        <div className="rounded-md border bg-muted/30 p-4 text-sm">
                                            <p className="font-medium">
                                                Budget is with your advisor
                                            </p>
                                            <p className="mt-1 text-muted-foreground">
                                                This version is locked while it
                                                is reviewed. Your advisor will
                                                let you know if changes are
                                                needed.
                                            </p>
                                        </div>
                                    ) : (
                                        <BudgetEditor
                                            budget={plan.budget}
                                            form={budgetForm}
                                            plan={plan}
                                            ideaValidation={ideaValidation}
                                            gamification={gamification}
                                            saving={savingBudget}
                                            autosaveState={budgetAutosaveState}
                                            onFormChange={setBudgetForm}
                                            onSave={saveBudget}
                                            onAcknowledgeFlag={
                                                acknowledgeBudgetFlag
                                            }
                                            onDismissAdvisorNudge={
                                                dismissBudgetAdvisorNudge
                                            }
                                        />
                                    )
                                ) : (
                                    <>
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <h3 className="text-sm font-medium">
                                                    Complete requirement
                                                </h3>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {
                                                        selectedRequirement.description
                                                    }
                                                </p>
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                {selectedRequirement.key ===
                                                'executive-summary' ? (
                                                    <span className="max-w-xs text-sm text-muted-foreground">
                                                        Generated automatically
                                                        after a passing
                                                        assessment
                                                    </span>
                                                ) : (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            void assistRequirement()
                                                        }
                                                        disabled={
                                                            !plan ||
                                                            assistingSection ||
                                                            planChangesLocked
                                                        }
                                                    >
                                                        <Bot
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        {assistingSection
                                                            ? 'Assisting'
                                                            : 'AI assist'}
                                                    </Button>
                                                )}
                                                {selectedSection &&
                                                selectedRequirement.key !==
                                                    'executive-summary' ? (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                selectedSection.guidance_url,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                        disabled={
                                                            planChangesLocked
                                                        }
                                                    >
                                                        <Bot
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        Score draft
                                                    </Button>
                                                ) : null}
                                            </div>
                                        </div>
                                        {selectedRequirement.key ===
                                            'executive-summary' &&
                                        plan?.executive_summary ? (
                                            <div className="flex flex-wrap items-center gap-2 rounded-md border bg-muted/20 p-3 text-sm">
                                                <Badge
                                                    variant={
                                                        plan.executive_summary
                                                            .stale
                                                            ? 'destructive'
                                                            : plan
                                                                    .executive_summary
                                                                    .present
                                                              ? 'secondary'
                                                              : 'outline'
                                                    }
                                                >
                                                    {
                                                        plan.executive_summary
                                                            .status_label
                                                    }
                                                </Badge>
                                                {plan.executive_summary
                                                    .generated_at ? (
                                                    <span className="text-muted-foreground">
                                                        Generated{' '}
                                                        {formatDate(
                                                            plan
                                                                .executive_summary
                                                                .generated_at,
                                                        )}
                                                    </span>
                                                ) : null}
                                                {plan.executive_summary
                                                    .readiness_reason ? (
                                                    <span className="text-muted-foreground">
                                                        {
                                                            plan
                                                                .executive_summary
                                                                .readiness_reason
                                                        }
                                                    </span>
                                                ) : null}
                                            </div>
                                        ) : null}
                                        <label className="grid gap-1 text-sm">
                                            <span>Section title</span>
                                            <input
                                                value={sectionTitle}
                                                onChange={(event) =>
                                                    setSectionTitle(
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={
                                                    selectedRequirement.key ===
                                                        'executive-summary' ||
                                                    planChangesLocked
                                                }
                                                className="h-9 rounded-md border bg-background px-3 text-sm"
                                            />
                                        </label>
                                        <div className="grid gap-1 text-sm">
                                            <span className="flex items-center justify-between gap-3">
                                                <label htmlFor="entrepreneur-plan-section-body">
                                                    Plan detail
                                                </label>
                                                <span className="shrink-0 text-xs font-normal text-muted-foreground tabular-nums">
                                                    {sectionAutosaveStateLabel(
                                                        sectionAutosaveState,
                                                    )}
                                                    {sectionAutosaveState !==
                                                    'idle'
                                                        ? ' | '
                                                        : ''}
                                                    {sectionBody.length}
                                                    {' / '}
                                                    {
                                                        PLAN_SECTION_BODY_MAX_LENGTH
                                                    }
                                                </span>
                                            </span>
                                            <FormattedTextarea
                                                id="entrepreneur-plan-section-body"
                                                value={sectionBody}
                                                onChange={setSectionBody}
                                                rows={8}
                                                maxLength={
                                                    PLAN_SECTION_BODY_MAX_LENGTH
                                                }
                                                placeholder="Add the context, evidence, assumptions, decisions, and risks your advisor should rely on."
                                                disabled={
                                                    selectedRequirement.key ===
                                                        'executive-summary' ||
                                                    planChangesLocked
                                                }
                                            />
                                        </div>
                                        {selectedRequirement.key !==
                                        'executive-summary' ? (
                                            <FileDropzone
                                                key={supportingKey}
                                                id="entrepreneur-plan-support"
                                                files={
                                                    supportingFile
                                                        ? [supportingFile]
                                                        : []
                                                }
                                                label="Attach supporting document"
                                                disabled={planChangesLocked}
                                                onFilesChange={(files) =>
                                                    setSupportingFile(
                                                        files[0] ?? null,
                                                    )
                                                }
                                            />
                                        ) : null}
                                        <InputError
                                            message={sectionError ?? undefined}
                                        />
                                        {assistantNotice ? (
                                            <div className="rounded-md border bg-muted/30 p-3 text-sm">
                                                <div className="font-medium">
                                                    AI assistant
                                                </div>
                                                <p className="mt-1 whitespace-pre-line text-muted-foreground">
                                                    {assistantNotice}
                                                </p>
                                            </div>
                                        ) : null}
                                        {selectedSection?.guidance ? (
                                            <div className="rounded-md border bg-muted/30 p-3 text-sm">
                                                <div className="font-medium">
                                                    AI guidance
                                                </div>
                                                <p className="mt-1 text-muted-foreground">
                                                    {
                                                        selectedSection.guidance
                                                            .summary
                                                    }
                                                </p>
                                            </div>
                                        ) : null}
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={() => void saveSection()}
                                            disabled={
                                                !plan ||
                                                savingSection ||
                                                selectedRequirement.key ===
                                                    'executive-summary' ||
                                                planChangesLocked
                                            }
                                        >
                                            <Upload
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            {savingSection
                                                ? 'Saving'
                                                : 'Save requirement'}
                                        </Button>
                                    </>
                                )
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Select a requirement to start completing the
                                    business plan.
                                </p>
                            )}
                        </div>
                    </div>
                ) : (
                    <div className="rounded-md border border-dashed bg-muted/20 p-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h3 className="text-sm font-medium">
                                    {planBuilderUnlocked
                                        ? 'Plan sections are ready'
                                        : ideaChangesRequested
                                          ? 'Plan sections are waiting for your revised idea'
                                          : hasIdeaValidation
                                            ? 'Plan sections are waiting for advisor review'
                                            : 'Plan sections unlock after idea validation'}
                                </h3>
                                <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                                    {planBuilderUnlocked
                                        ? 'Start the business plan to open the section checklist and AI assist.'
                                        : ideaChangesRequested
                                          ? 'Your advisor requested changes to the idea validation. Update and resubmit it before these sections open.'
                                          : hasIdeaValidation
                                            ? 'Your idea validation has been submitted. Your advisor needs to approve it before these sections open.'
                                            : 'Complete idea validation first so your advisor can confirm the concept before detailed plan work starts.'}
                                </p>
                            </div>
                            {planBuilderUnlocked ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={startPlan}
                                >
                                    Start plan
                                </Button>
                            ) : !hasIdeaValidation ||
                              ideaChangesRequested ||
                              ideaValidationRecalled ? (
                                <Button asChild size="sm">
                                    <a href="#idea-validation">
                                        {ideaChangesRequested ||
                                        ideaValidationRecalled
                                            ? 'Revise idea validation'
                                            : 'Start idea validation'}
                                    </a>
                                </Button>
                            ) : null}
                        </div>
                    </div>
                )}
            </section>

            <PlanWorkspaceHistory
                plan={plan}
                ideaValidationVersions={ideaValidationVersions}
                restoringIdeaVersionId={restoringIdeaVersionId}
                restoreIdeaVersion={restoreIdeaVersion}
            />
        </div>
    );
}
