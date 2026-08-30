import {
    Activity,
    ArrowRightLeft,
    Banknote,
    BarChart3,
    BrainCircuit,
    Clock,
    CreditCard,
    DatabaseZap,
    FileText,
    HeartHandshake,
    Inbox,
    Lightbulb,
    ListChecks,
    PieChart,
    ShieldAlert,
    Sparkles,
    TrendingUp,
    UsersRound,
} from 'lucide-react';
import type React from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { signalPanelTargetIds } from './defaults';
import { formatCurrency } from './formatters';
import type { DashboardProps } from './props';
import type {
    ActionPriority,
    ActionSummaryItem,
    DashboardTab,
    PracticeHealthPayload,
    PvWaterfallPayload,
    ScenarioPlanningPayload,
} from './types';

export function ActionCommandCentre({
    items,
    activeTab,
    onSelectTab,
}: {
    items: ActionSummaryItem[];
    activeTab: DashboardTab;
    onSelectTab: (tab: DashboardTab) => void;
}) {
    const sortedItems = [...items].sort(
        (a, b) =>
            actionPriorityRank(a.priority) - actionPriorityRank(b.priority) ||
            b.value - a.value ||
            a.label.localeCompare(b.label),
    );
    const criticalCount = items.filter(
        (item) => item.value > 0 && item.priority === 'critical',
    ).length;
    const totalOpen = items.reduce(
        (total, item) =>
            item.priority === 'neutral' ? total : total + item.value,
        0,
    );

    return (
        <section
            id="advisor-command-centre"
            className="space-y-3 rounded-md border bg-sidebar-accent/70 p-3"
        >
            <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <Activity className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Command centre</h2>
                    </div>
                    <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                        Hover each queue to see why it matters and the next
                        action to take.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge
                        variant={criticalCount > 0 ? 'destructive' : 'outline'}
                    >
                        {criticalCount} critical
                    </Badge>
                    <Badge variant="secondary">{totalOpen} open items</Badge>
                </div>
            </div>

            <div className="grid grid-cols-[repeat(auto-fit,minmax(160px,1fr))] gap-2">
                {sortedItems.map((item) => (
                    <ActionSummaryCard
                        key={item.key}
                        item={item}
                        activeTab={activeTab}
                        onSelectTab={onSelectTab}
                    />
                ))}
            </div>
        </section>
    );
}

function ActionSummaryCard({
    item,
    activeTab,
    onSelectTab,
}: {
    item: ActionSummaryItem;
    activeTab: DashboardTab;
    onSelectTab: (tab: DashboardTab) => void;
}) {
    const handleClick = (event: React.MouseEvent<HTMLAnchorElement>) => {
        if (!item.href.startsWith('#')) {
            return;
        }

        event.preventDefault();

        if (item.tab !== activeTab) {
            onSelectTab(item.tab);
        }

        window.setTimeout(() => {
            const target = document.getElementById(item.targetId);

            if (!target) {
                return;
            }

            const highlightClasses = [
                'ring-2',
                'ring-primary',
                'ring-offset-2',
                'ring-offset-background',
                'transition-shadow',
                'scroll-mt-24',
            ];
            const previousTabIndex = target.getAttribute('tabindex');
            const hadTabIndex = target.hasAttribute('tabindex');

            target.setAttribute('tabindex', previousTabIndex ?? '-1');
            target.classList.add(...highlightClasses);
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            target.focus({ preventScroll: true });
            window.history.replaceState(null, '', item.href);

            window.setTimeout(() => {
                target.classList.remove(...highlightClasses);

                if (hadTabIndex && previousTabIndex !== null) {
                    target.setAttribute('tabindex', previousTabIndex);
                } else {
                    target.removeAttribute('tabindex');
                }
            }, 2200);
        }, 0);
    };

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <a
                    href={item.href}
                    onClick={handleClick}
                    className={cn(
                        'flex min-h-[58px] items-center gap-2 rounded-md border p-2.5 transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                        actionCardClasses(item.priority, item.value),
                    )}
                >
                    <div className="rounded-md border bg-card p-1.5 text-muted-foreground">
                        {item.icon}
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-start justify-between gap-2">
                            <div className="truncate text-base leading-none font-semibold tabular-nums">
                                {item.displayValue ?? item.value}
                            </div>
                            <Badge
                                variant={actionBadgeVariant(item)}
                                className="shrink-0 px-1.5 py-0 text-[10px]"
                            >
                                {item.statusLabel ??
                                    (item.priority === 'critical'
                                        ? 'Critical'
                                        : item.priority === 'warning'
                                          ? 'Review'
                                          : 'Clear')}
                            </Badge>
                        </div>
                        <div className="mt-1 truncate text-xs font-medium">
                            {item.label}
                        </div>
                    </div>
                </a>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-sm">
                <div className="space-y-2">
                    <p className="font-medium">{item.explanation}</p>
                    <p className="text-muted-foreground">{item.nextStep}</p>
                </div>
            </TooltipContent>
        </Tooltip>
    );
}

type ActionSummaryInput = Pick<
    DashboardProps,
    | 'cashFlowStatus'
    | 'redFlags'
    | 'documentVerificationFlags'
    | 'clientTransferQueue'
    | 'serviceActivationRequests'
    | 'entrepreneurReviews'
    | 'strategicPlanDeployments'
    | 'pendingTermsReacceptance'
    | 'proposalStatus'
    | 'paymentStatus'
    | 'feeStatus'
    | 'operationalHealth'
    | 'aiOperationalAlert'
    | 'npoPendingConversions'
    | 'npoFunding'
    | 'referenceDataTasks'
    | 'panelOperations'
> & {
    pvWaterfall: PvWaterfallPayload;
    practiceHealth: PracticeHealthPayload;
    scenarioPlanning: ScenarioPlanningPayload;
};

export function buildActionSummaryItems({
    cashFlowStatus,
    redFlags,
    documentVerificationFlags,
    clientTransferQueue,
    serviceActivationRequests,
    entrepreneurReviews,
    strategicPlanDeployments,
    pendingTermsReacceptance,
    proposalStatus,
    paymentStatus,
    feeStatus,
    operationalHealth,
    aiOperationalAlert,
    pvWaterfall,
    practiceHealth,
    npoPendingConversions,
    npoFunding,
    referenceDataTasks,
    scenarioPlanning,
    panelOperations,
}: ActionSummaryInput): ActionSummaryItem[] {
    const paymentActionCount =
        paymentStatus.summary.failed + paymentStatus.summary.retrying;
    const feesDisabled = feeStatus.free_access_mode;
    const proposalActionCount =
        proposalStatus.summary.expired + proposalStatus.summary.expiring_soon;
    const learningActionCount =
        panelOperations.learning.summary.detected +
        panelOperations.learning.summary.staged;
    const referenceDataActionCount =
        referenceDataTasks.summary.missing +
        referenceDataTasks.summary.overdue +
        referenceDataTasks.summary.due_soon;
    const brokerApprovalActionCount = panelOperations.approvals.summary.broker;
    const coachApprovalActionCount = panelOperations.approvals.summary.coach;
    const operationalHealthActionCount =
        operationalHealth.summary.failed + operationalHealth.summary.warning;
    const serviceActivationAction =
        serviceActivationRequests.summary.total > 0
            ? {
                  key: 'service-activation-requests',
                  label: 'Quote requests',
                  value: serviceActivationRequests.summary.total,
                  statusLabel:
                      serviceActivationRequests.summary.dd_plan_budget > 0
                          ? 'BP&B'
                          : 'Review',
                  href: '#advisor-service-activation-requests',
                  targetId: 'advisor-service-activation-requests',
                  tab: 'priorities' as const,
                  priority: 'warning' as const,
                  explanation:
                      'Quote requests are client-requested service activations waiting for FSA to choose the package, scope, and fee.',
                  nextStep:
                      'Open the request, select the active service-rate package, then let the client complete payment and scope acceptance.',
                  icon: <Banknote className="size-4" aria-hidden="true" />,
              }
            : null;
    const aiOperationalAction =
        aiOperationalAlert.available &&
        aiOperationalAlert.action_url &&
        aiOperationalAlert.total > 0
            ? {
                  key: 'ai-provider-health',
                  label: 'AI provider',
                  value: aiOperationalAlert.total,
                  statusLabel: 'Attention',
                  href: aiOperationalAlert.action_url,
                  targetId: 'advisor-command-centre',
                  tab: 'priorities' as const,
                  priority: 'critical' as const,
                  explanation:
                      'An AI provider failure needs administrator attention. Client and founder work stays available while the provider issue is investigated.',
                  nextStep:
                      aiOperationalAlert.reason ??
                      'Open API health to review the provider failure and current usage costs.',
                  icon: <BrainCircuit className="size-4" aria-hidden="true" />,
              }
            : null;
    const operationalHealthAction =
        operationalHealth.index_url && operationalHealthActionCount > 0
            ? {
                  key: 'app-checks',
                  label: 'App checks',
                  value: operationalHealthActionCount,
                  statusLabel:
                      operationalHealth.summary.failed > 0
                          ? 'Errors'
                          : 'Warnings',
                  href: operationalHealth.index_url,
                  targetId: 'advisor-command-centre',
                  tab: 'priorities' as const,
                  priority:
                      operationalHealth.summary.failed > 0
                          ? ('critical' as const)
                          : ('warning' as const),
                  explanation:
                      'App checks are daily synthetic route and preview checks for HTTP 500, 403, unexpected redirects, and document-preview failures.',
                  nextStep:
                      operationalHealth.latest_issue?.issue_summary ??
                      'Open App checks to review the latest operational finding and recurrence history.',
                  icon: <Activity className="size-4" aria-hidden="true" />,
              }
            : null;
    const clientTransferAction =
        clientTransferQueue.available && clientTransferQueue.action_url
            ? {
                  key: 'client-transfers',
                  label: clientTransferQueue.can_review
                      ? 'Client transfers'
                      : 'My transfers',
                  value: clientTransferQueue.total,
                  statusLabel: clientTransferQueue.can_review
                      ? 'Review'
                      : 'Awaiting',
                  href: clientTransferQueue.action_url,
                  targetId: 'advisor-command-centre',
                  tab: 'priorities' as const,
                  priority:
                      clientTransferQueue.total > 0
                          ? ('warning' as const)
                          : ('neutral' as const),
                  explanation: clientTransferQueue.can_review
                      ? 'Client transfer requests are waiting for a super administrator to approve or reject the ownership change.'
                      : 'Client transfer requests are waiting for a super administrator to review the proposed ownership change.',
                  nextStep: clientTransferQueue.can_review
                      ? 'Open Client allocations, review the reason and receiving advisor, then approve or reject the request.'
                      : 'Open the transfer request to review its status and the client context while the administrator decision is pending.',
                  icon: (
                      <ArrowRightLeft className="size-4" aria-hidden="true" />
                  ),
              }
            : null;
    const redFlagAction =
        redFlags.summary.open > 0
            ? {
                  key: 'red-flags',
                  label: 'AI red flags',
                  value: redFlags.summary.open,
                  href: '#advisor-red-flags',
                  targetId: 'advisor-red-flags',
                  tab: 'priorities' as const,
                  priority:
                      redFlags.summary.unacknowledged > 0
                          ? ('critical' as const)
                          : ('warning' as const),
                  explanation:
                      'AI red flags are advisor review prompts raised from analysed client evidence, findings, and risk triggers.',
                  nextStep:
                      'Open the risk review panel, acknowledge new items, and resolve risks once they have been reviewed with the client context.',
                  icon: <ShieldAlert className="size-4" aria-hidden="true" />,
              }
            : null;

    return [
        ...(aiOperationalAction ? [aiOperationalAction] : []),
        ...(operationalHealthAction ? [operationalHealthAction] : []),
        ...(serviceActivationAction ? [serviceActivationAction] : []),
        {
            key: 'cash-flow-risks',
            label: 'Cash flow risks',
            value: cashFlowStatus.summary.action_required,
            href: '#advisor-cash-flow-risks',
            targetId: 'advisor-cash-flow-risks',
            tab: 'priorities',
            priority:
                cashFlowStatus.summary.negative > 0
                    ? 'critical'
                    : cashFlowStatus.summary.watch > 0
                      ? 'warning'
                      : 'neutral',
            explanation:
                'Cash flow risks show clients with negative operating cash flow, short budget runway, or a forecast that has not reached cash-flow positive.',
            nextStep:
                'Open the risk panel, review the affected client, then update the budget, funding plan, debtor cadence, or strategic milestones.',
            icon: <Banknote className="size-4" aria-hidden="true" />,
        },
        ...(redFlagAction ? [redFlagAction] : []),
        ...(clientTransferAction ? [clientTransferAction] : []),
        {
            key: 'documents',
            label: 'Document review',
            value: documentVerificationFlags.length,
            href: '#advisor-documents',
            targetId: 'advisor-documents',
            tab: 'priorities',
            priority:
                documentVerificationFlags.length > 0 ? 'warning' : 'neutral',
            explanation:
                'Document review flags surface uploaded evidence that needs advisor verification.',
            nextStep:
                'Open each flagged document, confirm evidence quality, and clear or escalate the flag.',
            icon: <FileText className="size-4" aria-hidden="true" />,
        },
        {
            key: 'idea-validation-reviews',
            label: 'Idea reviews',
            value: entrepreneurReviews.summary.idea_validations,
            href: '#advisor-entrepreneur-reviews',
            targetId: 'advisor-entrepreneur-reviews',
            tab: 'priorities',
            priority:
                entrepreneurReviews.summary.idea_validations > 0
                    ? 'warning'
                    : 'neutral',
            explanation:
                'Idea reviews are entrepreneur concept validations waiting for advisor gate approval before the plan builder opens.',
            nextStep:
                'Open the entrepreneur record, review the submitted idea validation, then approve the builder gate with an advisor note.',
            icon: <Lightbulb className="size-4" aria-hidden="true" />,
        },
        {
            key: 'business-plan-reviews',
            label: 'Plan reviews',
            value: entrepreneurReviews.summary.business_plans,
            href: '#advisor-entrepreneur-reviews',
            targetId: 'advisor-entrepreneur-reviews',
            tab: 'priorities',
            priority:
                entrepreneurReviews.summary.business_plans > 0
                    ? 'warning'
                    : 'neutral',
            explanation:
                'Plan reviews are entrepreneur business plans submitted for assessment or waiting for final advisor review.',
            nextStep:
                'Open the entrepreneur record, run the assessment if needed, then finalise the feedback report.',
            icon: <FileText className="size-4" aria-hidden="true" />,
        },
        {
            key: 'strategic-plan-deployments',
            label: 'Strategic plans',
            value: strategicPlanDeployments.summary.total,
            href: '#advisor-strategic-plan-deployments',
            targetId: 'advisor-strategic-plan-deployments',
            tab: 'priorities',
            priority:
                strategicPlanDeployments.summary.total > 0
                    ? 'warning'
                    : 'neutral',
            explanation:
                'Strategic plan actions include signed proposals that need a plan generated and draft plans ready for deployment.',
            nextStep:
                'Generate missing strategic plans, then review draft plans with the client before deploying milestones.',
            icon: <ListChecks className="size-4" aria-hidden="true" />,
        },
        {
            key: 'terms',
            label: 'Terms re-acceptance',
            value: pendingTermsReacceptance.total,
            href: '#advisor-terms',
            targetId: 'advisor-terms',
            tab: 'priorities',
            priority:
                pendingTermsReacceptance.total > 0 ? 'warning' : 'neutral',
            explanation:
                'Client contacts in this queue must accept the latest terms before continuing portal workflows.',
            nextStep:
                'Follow up with the listed client contacts or confirm the terms gate is working as expected.',
            icon: <ShieldAlert className="size-4" aria-hidden="true" />,
        },
        {
            key: 'fee-status',
            label: 'Rates & fees',
            value: feesDisabled ? 1 : 0,
            displayValue: feesDisabled ? 'Free access' : 'Active',
            statusLabel: feesDisabled ? 'Inactive' : 'Active',
            href:
                feeStatus.can_manage && feeStatus.manage_url
                    ? feeStatus.manage_url
                    : '#advisor-dashboard-priorities',
            targetId: 'advisor-dashboard-priorities',
            tab: 'priorities',
            priority: feesDisabled ? 'warning' : 'neutral',
            explanation: feesDisabled
                ? 'Rates and fees are inactive. The app is in free-access mode, so fee calculations and package payments are set to zero.'
                : 'Rates and fees are active. Fee calculations and payment steps use the current admin service-rate settings.',
            nextStep:
                feesDisabled && feeStatus.can_manage && feeStatus.manage_url
                    ? 'Open Service rates when you are ready to reactivate fees and Stripe-backed collection.'
                    : feesDisabled
                      ? 'Ask a super admin to reactivate Service rates when charging should resume.'
                      : 'No action is required while rates and fees remain active.',
            icon: <Banknote className="size-4" aria-hidden="true" />,
        },
        {
            key: 'payments',
            label: 'Payment exceptions',
            value: paymentActionCount,
            href: '#advisor-payments',
            targetId: 'advisor-payments',
            tab: 'priorities',
            priority:
                paymentStatus.summary.failed > 0
                    ? 'critical'
                    : paymentActionCount > 0
                      ? 'warning'
                      : 'neutral',
            explanation:
                'Payment exceptions indicate failed or retrying transactions that may block delivery or renewals.',
            nextStep:
                'Open failed payments, retry where available, or contact the client for updated billing details.',
            icon: <CreditCard className="size-4" aria-hidden="true" />,
        },
        {
            key: 'proposals',
            label: 'Proposal expiry',
            value: proposalActionCount,
            href: '#advisor-proposals',
            targetId: 'advisor-proposals',
            tab: 'priorities',
            priority:
                proposalStatus.summary.expired > 0
                    ? 'critical'
                    : proposalActionCount > 0
                      ? 'warning'
                      : 'neutral',
            explanation:
                'Proposal expiry flags released proposals that need renewal, recall, or advisor follow-up.',
            nextStep:
                'Open expiring proposals and decide whether to renew, recall, or progress client sign-off.',
            icon: <FileText className="size-4" aria-hidden="true" />,
        },
        {
            key: 'pv-waterfall',
            label: 'PV waterfall',
            value: pvWaterfall.summary.clients,
            displayValue: formatCurrency(pvWaterfall.summary.target_pv),
            statusLabel: 'View',
            href: '#advisor-pv-waterfall',
            targetId: 'advisor-pv-waterfall',
            tab: 'priorities',
            priority: 'neutral',
            explanation:
                'PV waterfall bridges current portfolio value to modelled upside using improvement opportunities and risk-mitigation value.',
            nextStep:
                'Open the waterfall to see the featured client and hover each movement to understand the annual benefit, years, discount rate, and method.',
            icon: <TrendingUp className="size-4" aria-hidden="true" />,
        },
        {
            key: 'practice-health',
            label: 'Practice health',
            value: practiceHealth.summary.active_clients,
            displayValue: `${practiceHealth.summary.active_clients} active`,
            statusLabel: 'View',
            href: '#advisor-practice-health',
            targetId: 'advisor-practice-health',
            tab: 'priorities',
            priority: 'neutral',
            explanation:
                'Practice health measures the advisor portfolio position: active clients, revenue under management, target PV, released proposals, generated reports, and open red flags.',
            nextStep:
                'Open Practice health to see whether advisory work is converting into value, reports, and manageable risk across the visible portfolio.',
            icon: <PieChart className="size-4" aria-hidden="true" />,
        },
        {
            key: 'scenario-planning',
            label: 'Scenario planning',
            value: scenarioPlanning.summary.scenarios,
            displayValue: `${scenarioPlanning.summary.scenarios} scenarios`,
            statusLabel: 'View',
            href: '#advisor-scenario-planning',
            targetId: 'advisor-scenario-planning',
            tab: 'priorities',
            priority: 'neutral',
            explanation:
                'Scenario planning tracks prepared what-if cases and their PV impact so advisors can compare options before recommending action.',
            nextStep:
                'Open Scenario planning to review available scenarios or confirm no scenario work has been prepared yet.',
            icon: <BarChart3 className="size-4" aria-hidden="true" />,
        },
        {
            key: 'broker-approvals',
            label: 'Broker approvals',
            value: brokerApprovalActionCount,
            href: '#advisor-panel-approvals',
            targetId: 'advisor-panel-approvals',
            tab: 'signals',
            priority: brokerApprovalActionCount > 0 ? 'warning' : 'neutral',
            explanation:
                'Broker approvals count submitted broker applications waiting for advisor or admin review.',
            nextStep:
                'Open the partner approval queue, review the broker application, then approve, request more information, or decline it.',
            icon: <UsersRound className="size-4" aria-hidden="true" />,
        },
        {
            key: 'coach-approvals',
            label: 'Coach approvals',
            value: coachApprovalActionCount,
            href: '#advisor-panel-approvals',
            targetId: 'advisor-panel-approvals',
            tab: 'signals',
            priority: coachApprovalActionCount > 0 ? 'warning' : 'neutral',
            explanation:
                'Coach approvals count submitted coach applications waiting for advisor or admin review.',
            nextStep:
                'Open the partner approval queue, review the coach application, then approve, request more information, or decline it.',
            icon: <HeartHandshake className="size-4" aria-hidden="true" />,
        },
        {
            key: 'npo-funding',
            label: 'NPO funding',
            value: npoFunding.summary.active_alerts,
            href: '#advisor-npo-funding',
            targetId: 'advisor-npo-funding',
            tab: 'signals',
            priority:
                npoFunding.summary.critical_alerts > 0
                    ? 'critical'
                    : npoFunding.summary.active_alerts > 0
                      ? 'warning'
                      : 'neutral',
            explanation:
                'NPO funding alerts track deadlines or funder requirements that could affect client delivery.',
            nextStep:
                'Review critical alerts first and contact the client before the due date slips.',
            icon: <HeartHandshake className="size-4" aria-hidden="true" />,
        },
        {
            key: 'npo-conversions',
            label: 'NPO nudges',
            value: npoPendingConversions.summary.nudge_due,
            href: '#advisor-npo-conversions',
            targetId: 'advisor-npo-conversions',
            tab: 'signals',
            priority:
                npoPendingConversions.summary.nudge_due > 0
                    ? 'warning'
                    : 'neutral',
            explanation:
                'NPO nudges show delivered Governance Reviews where re-engagement is due.',
            nextStep:
                'Open the client record and decide whether to nudge, defer, or mark the conversion outcome.',
            icon: <Clock className="size-4" aria-hidden="true" />,
        },
        {
            key: 'broker-referrals',
            label: 'Broker referrals',
            value: panelOperations.broker.summary.active,
            href: '#advisor-broker-referrals',
            targetId: 'advisor-broker-referrals',
            tab: 'signals',
            priority:
                panelOperations.broker.summary.active > 0
                    ? 'warning'
                    : 'neutral',
            explanation:
                'Broker referrals track active hand-offs to broker partners and their current stage.',
            nextStep:
                'Open referral details, confirm progress, and chase stale partner responses.',
            icon: <Inbox className="size-4" aria-hidden="true" />,
        },
        {
            key: 'coach-referrals',
            label: 'Coach referrals',
            value: panelOperations.coach.summary.active,
            href: '#advisor-coach-referrals',
            targetId: 'advisor-coach-referrals',
            tab: 'signals',
            priority:
                panelOperations.coach.summary.active > 0
                    ? 'warning'
                    : 'neutral',
            explanation:
                'Coach referrals track founder or client support hand-offs that need follow-through.',
            nextStep:
                'Open the hand-off, confirm the coach stage, and update the referral once progressed.',
            icon: <HeartHandshake className="size-4" aria-hidden="true" />,
        },
        {
            key: 'reference-data',
            label: 'Reference data',
            value: referenceDataActionCount,
            href: '#advisor-reference-data-tasks',
            targetId: 'advisor-reference-data-tasks',
            tab: 'signals',
            priority:
                referenceDataTasks.summary.missing +
                    referenceDataTasks.summary.overdue >
                0
                    ? 'warning'
                    : referenceDataActionCount > 0
                      ? 'warning'
                      : 'neutral',
            explanation:
                'Reference-data tasks show manual economic, valuation, WACC, and NPO benchmark figures that are due for refresh.',
            nextStep:
                'Open Reference Data and record a new value, then approve and implement it through the learning queue.',
            icon: <DatabaseZap className="size-4" aria-hidden="true" />,
        },
        {
            key: 'learning-queue',
            label: 'Learning queue',
            value: learningActionCount,
            href: '#advisor-learning-queue',
            targetId: 'advisor-learning-queue',
            tab: 'signals',
            priority: learningActionCount > 0 ? 'warning' : 'neutral',
            explanation:
                'Learning queue items are governed model, questionnaire, and methodology updates awaiting review.',
            nextStep:
                'Review staged updates before approving changes into the live advisory workflow.',
            icon: <Sparkles className="size-4" aria-hidden="true" />,
        },
    ];
}

function actionPriorityRank(priority: ActionPriority): number {
    if (priority === 'critical') {
        return 0;
    }

    if (priority === 'warning') {
        return 1;
    }

    return 2;
}

function actionBadgeVariant(
    item: ActionSummaryItem,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (item.value === 0) {
        return 'outline';
    }

    if (item.priority === 'critical') {
        return 'destructive';
    }

    if (item.priority === 'warning') {
        return 'secondary';
    }

    return 'outline';
}

function actionCardClasses(priority: ActionPriority, value: number): string {
    if (value === 0 || priority === 'neutral') {
        return 'bg-card hover:bg-white dark:hover:bg-card/90';
    }

    if (priority === 'critical') {
        return 'border-destructive/40 bg-destructive/5 hover:bg-destructive/10';
    }

    return 'border-amber-300 bg-amber-50/60 hover:bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10 dark:hover:bg-amber-500/15';
}

export function initialDashboardTab(): DashboardTab {
    if (typeof window === 'undefined') {
        return 'priorities';
    }

    return signalPanelTargetIds.has(window.location.hash.replace('#', ''))
        ? 'signals'
        : 'priorities';
}
