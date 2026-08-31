import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Eye, MessageSquare, Trophy } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TooltipProvider } from '@/components/ui/tooltip';
import {
    TabList,
    displayStageLabel,
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
        includesPlanBudget,
        requestGamificationDisablement,
    } = workspace;

    return (
        <TooltipProvider>
            <Head title="Business plan" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Business plan workspace
                        </h1>
                        <div className="text-sm text-muted-foreground">
                            {profile.name} /{' '}
                            {displayStageLabel(
                                profile.stage,
                                profile.stage_label,
                            )}
                        </div>
                        {includesPlanBudget ? (
                            <form
                                className="mt-3 flex max-w-xl flex-col gap-2 sm:flex-row sm:items-end"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    companyNameForm.post(
                                        urls.companyNameUpdate,
                                        {
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                            >
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
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={companyNameForm.processing}
                                >
                                    Save name
                                </Button>
                            </form>
                        ) : null}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild size="sm" variant="outline">
                            <a
                                href={urls.preview}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Eye className="size-4" aria-hidden="true" />
                                Preview business plan
                            </a>
                        </Button>
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

                {plan && !plan.external_issue_readiness.external_issue_ready ? (
                    <section className="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-950">
                        <div className="flex items-start gap-3">
                            <AlertTriangle
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <div>
                                <div className="font-medium">
                                    {plan.external_issue_readiness.label}
                                </div>
                                <p className="mt-1 text-red-900">
                                    Evidence coverage:{' '}
                                    {
                                        plan.external_issue_readiness
                                            .evidence_supported_responses
                                    }
                                    /
                                    {
                                        plan.external_issue_readiness
                                            .completed_responses
                                    }{' '}
                                    completed responses.
                                </p>
                                <ul className="mt-2 list-disc space-y-1 pl-5 text-red-900">
                                    {plan.external_issue_readiness.reasons.map(
                                        (reason) => (
                                            <li key={reason}>{reason}</li>
                                        ),
                                    )}
                                </ul>
                            </div>
                        </div>
                    </section>
                ) : null}

                <TabList activeTab={activeTab} onChange={setActiveTab} />

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
                                        Plan{' '}
                                        {gamification.plan_completion
                                            ?.percent ?? 0}
                                        %
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

                {activeTab === 'actions' ? (
                    <PlanWorkspaceActions workspace={workspace} />
                ) : (
                    <PlanWorkspaceInformation workspace={workspace} />
                )}
            </div>
        </TooltipProvider>
    );
}
