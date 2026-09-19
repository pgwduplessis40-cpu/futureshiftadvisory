import { Head, Link } from '@inertiajs/react';
import { Download, Eye, FileText, MessageSquare, Trophy } from 'lucide-react';
import { useState } from 'react';
import { DraftSaveStatus } from '@/components/portal/draft-save-status';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TooltipProvider } from '@/components/ui/tooltip';
import {
    TabList,
    displayStageLabel,
    IdeaValidationSnapshot,
    journeyLevelLabel,
} from './plan-dashboard-panels';
import { PlanWorkspaceActions } from './plan-workspace-actions';
import { PlanWorkspaceInformation } from './plan-workspace-information';
import type { PlanWorkspace } from './use-plan-workspace';

export function PlanWorkspaceLayout(workspace: PlanWorkspace) {
    const {
        profile,
        plan,
        gamification,
        urls,
        activeTab,
        setActiveTab,
        companyNameForm,
        companyNameAutosaveState,
        includesIdeaValidation,
        includesPlanBudget,
        ideaValidation,
        ideaValidationSummary,
        journey,
        requestGamificationDisablement,
    } = workspace;
    const [serviceView, setServiceView] = useState<
        'idea_validation' | 'plan_budget'
    >(includesPlanBudget ? 'plan_budget' : 'idea_validation');
    const isIdeaValidationOnly = includesIdeaValidation && !includesPlanBudget;
    const isPlanBudgetView = serviceView === 'plan_budget';
    const completionPercent = isPlanBudgetView
        ? journey.plan.completion.percent
        : journey.idea_validation.approved
          ? 100
          : 0;

    return (
        <TooltipProvider>
            <Head
                title={
                    isPlanBudgetView
                        ? 'Business Plan & Budget'
                        : 'Idea Validation'
                }
            />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {isPlanBudgetView
                                ? 'Business Plan & Budget'
                                : 'Idea Validation'}
                        </h1>
                        <div className="text-sm text-muted-foreground">
                            {profile.name} /{' '}
                            {displayStageLabel(
                                profile.stage,
                                profile.stage_label,
                            )}
                        </div>
                        {journey.tabs.idea_validation &&
                        journey.tabs.plan_budget ? (
                            <div
                                className="mt-3 inline-flex rounded-md border bg-muted/30 p-1"
                                role="tablist"
                                aria-label="Entrepreneur journey services"
                            >
                                {journey.tabs.advisory ? (
                                    <Link
                                        href={journey.advisory.url}
                                        className="rounded-sm px-3 py-1.5 text-sm font-medium text-muted-foreground hover:text-foreground"
                                    >
                                        Advisory
                                    </Link>
                                ) : null}
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={
                                        serviceView === 'plan_budget'
                                    }
                                    className={`rounded-sm px-3 py-1.5 text-sm font-medium ${
                                        serviceView === 'plan_budget'
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                    onClick={() =>
                                        setServiceView('plan_budget')
                                    }
                                >
                                    Business Plan &amp; Budget
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={
                                        serviceView === 'idea_validation'
                                    }
                                    className={`rounded-sm px-3 py-1.5 text-sm font-medium ${
                                        serviceView === 'idea_validation'
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                    onClick={() =>
                                        setServiceView('idea_validation')
                                    }
                                >
                                    Idea Validation
                                </button>
                            </div>
                        ) : null}
                        {includesPlanBudget && isPlanBudgetView ? (
                            <div className="mt-3 flex max-w-xl flex-col gap-2 sm:flex-row sm:items-end">
                                <label className="grid flex-1 gap-1 text-xs font-medium text-muted-foreground">
                                    Company / proposed company name
                                    <input
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                                        value={
                                            companyNameForm.data.company_name
                                        }
                                        onChange={(event) =>
                                            companyNameForm.setData(
                                                'company_name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="e.g. Harbour Studio Limited"
                                    />
                                </label>
                                <DraftSaveStatus
                                    draft={companyNameAutosaveState}
                                    className="pb-2"
                                    savedLabel="Company name saved automatically"
                                />
                            </div>
                        ) : null}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {isPlanBudgetView &&
                        plan?.budget.pack_available &&
                        plan.budget.budget_pack_pdf_url ? (
                            <Button asChild size="sm" variant="outline">
                                <a
                                    href={plan.budget.budget_pack_pdf_url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <FileText
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    View budget PDF
                                </a>
                            </Button>
                        ) : null}
                        {isPlanBudgetView && !isIdeaValidationOnly ? (
                            <>
                                <Button asChild size="sm" variant="outline">
                                    <a
                                        href={urls.preview}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Eye
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Preview business plan
                                    </a>
                                </Button>
                                <Button asChild size="sm" variant="outline">
                                    <a href={urls.previewDownload} download>
                                        <Download
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Download plan PDF
                                    </a>
                                </Button>
                            </>
                        ) : null}
                        <Button asChild size="sm" variant="outline">
                            <Link href={urls.messages}>
                                <MessageSquare
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Messages
                            </Link>
                        </Button>
                    </div>
                </div>

                {isPlanBudgetView ? (
                    <TabList activeTab={activeTab} onChange={setActiveTab} />
                ) : null}

                {gamification.enabled ? (
                    <section className="rounded-md border bg-background p-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Trophy
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    <h2 className="text-sm font-medium">
                                        Gamification enabled
                                    </h2>
                                    <Badge
                                        variant={
                                            gamification.disable_request_requested
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {gamification.disable_request_requested
                                            ? 'Disablement requested'
                                            : 'Active'}
                                    </Badge>
                                </div>
                                <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                                    <span>
                                        {journeyLevelLabel(
                                            gamification.current_level,
                                        )}
                                    </span>
                                    <span>
                                        {isPlanBudgetView
                                            ? 'Business Plan & Budget'
                                            : 'Idea Validation'}{' '}
                                        {completionPercent}%
                                    </span>
                                    <span>
                                        Journey points{' '}
                                        {gamification.points?.total ?? 0}
                                    </span>
                                    <span>
                                        Streak{' '}
                                        {gamification.current_streak ?? 0} days
                                    </span>
                                    {(gamification.new_badge_count ?? 0) > 0 ? (
                                        <span>
                                            {gamification.new_badge_count} new
                                            badges
                                        </span>
                                    ) : null}
                                </div>
                            </div>
                            {gamification.disable_request_requested &&
                            gamification.disable_request_thread_url ? (
                                <Button asChild size="sm" variant="outline">
                                    <Link
                                        href={
                                            gamification.disable_request_thread_url
                                        }
                                    >
                                        <MessageSquare
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Open request
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={requestGamificationDisablement}
                                >
                                    <MessageSquare
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Request disablement
                                </Button>
                            )}
                        </div>
                    </section>
                ) : null}

                {isIdeaValidationOnly || isPlanBudgetView ? (
                    activeTab === 'actions' ? (
                        <PlanWorkspaceActions workspace={workspace} />
                    ) : (
                        <PlanWorkspaceInformation workspace={workspace} />
                    )
                ) : (
                    <section className="space-y-4 rounded-md border bg-background p-4">
                        <div>
                            <h2 className="text-base font-semibold">
                                Validated idea
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                This is the approved evidence that carries into
                                your Business Plan &amp; Budget. Use it as the
                                starting point as you add delivery, operations,
                                pricing, costs, and financial detail.
                            </p>
                        </div>
                        <IdeaValidationSnapshot
                            fields={ideaValidationSummary}
                            revisionNumber={
                                ideaValidation?.revision_number ?? null
                            }
                            submittedAt={ideaValidation?.evaluated_at ?? null}
                        />
                        <div className="rounded-md border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">
                            <div className="font-medium">Advisor note</div>
                            <p className="mt-1">
                                Your idea has been validated. You demonstrated a
                                customer problem, a defined customer group, and
                                a potential solution. The next step is to build
                                the practical evidence needed for a viable
                                Business Plan &amp; Budget.
                            </p>
                            {ideaValidation?.advisor_gate_note ? (
                                <p className="mt-3 border-t border-sky-200 pt-3 text-sky-900">
                                    {ideaValidation.advisor_gate_note}
                                </p>
                            ) : null}
                        </div>
                    </section>
                )}
            </div>
        </TooltipProvider>
    );
}
