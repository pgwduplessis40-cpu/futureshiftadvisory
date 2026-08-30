import { Deferred, Head } from '@inertiajs/react';
import { Activity } from 'lucide-react';
import { useState } from 'react';
import { DocumentVerificationFlagPanel } from '@/components/verification/DocumentVerificationFlagPanel';
import { dashboard } from '@/routes';
import {
    ActionCommandCentre,
    buildActionSummaryItems,
    initialDashboardTab,
} from './dashboard/ActionCommandCentre';
import {
    Metric,
    MyClientsHealth,
    CashFlowRiskPanel,
    StrategicPlanDeploymentPanel,
    PendingTermsReacceptance,
} from './dashboard/ClientHealthPanels';
import {
    AdvisorPortfolioFallback,
    AdvisorSignalsFallback,
    DashboardSection,
    DashboardTabButton,
} from './dashboard/DashboardLayout';
import {
    emptyCoachSignals,
    emptyEconomicIndicators,
    emptyFunnelAnalytics,
    emptyIntegrationHealth,
    emptyPracticeHealth,
    emptyPvWaterfall,
    emptyQuestionnaireOptimisation,
    emptyScenarioPlanning,
    emptyWellbeingAnalytics,
    advisorPortfolioDeferredProps,
    advisorSignalDeferredProps,
} from './dashboard/defaults';
import {
    ProposalStatusPanel,
    PaymentStatusPanel,
    NpoPendingConversions,
    NpoFundingPanel,
    PracticeHealth,
    QuestionnaireOptimisation,
    WellbeingAnalytics,
    CoachSignals,
} from './dashboard/FinancialWorkflowPanels';
import { formatCurrency } from './dashboard/formatters';
import {
    FunnelAnalytics,
    ScenarioPlanning,
    PvWaterfallPanel,
    RedFlagPanel,
} from './dashboard/PlanningPanels';
import type { DashboardProps } from './dashboard/props';
import {
    EntrepreneurReviewPanel,
    ProspectInbox,
    EconomicIndicators,
    ReferenceDataTasksPanel,
    IntegrationHealth,
    PanelOperations,
} from './dashboard/SignalPanels';
import type { DashboardTab } from './dashboard/types';

export default function AdvisorDashboard({
    clientsHealth,
    cashFlowStatus,
    redFlags,
    documentVerificationFlags,
    messagesPending,
    clientTransferQueue,
    entrepreneurReviews,
    strategicPlanDeployments,
    pendingTermsReacceptance,
    prospectInbox,
    operationalHealth,
    aiOperationalAlert,
    integrationHealth,
    economicIndicators,
    pvWaterfall,
    practiceHealth,
    proposalStatus,
    paymentStatus,
    feeStatus,
    questionnaireOptimisation,
    wellbeingAnalytics,
    coachSignals,
    npoPendingConversions,
    npoFunding,
    referenceDataTasks,
    scenarioPlanning,
    funnelAnalytics,
    panelOperations,
}: DashboardProps) {
    const [activeTab, setActiveTab] =
        useState<DashboardTab>(initialDashboardTab);
    const loadedIntegrationHealth = integrationHealth ?? emptyIntegrationHealth;
    const loadedEconomicIndicators =
        economicIndicators ?? emptyEconomicIndicators;
    const loadedQuestionnaireOptimisation =
        questionnaireOptimisation ?? emptyQuestionnaireOptimisation;
    const loadedWellbeingAnalytics =
        wellbeingAnalytics ?? emptyWellbeingAnalytics;
    const loadedCoachSignals = coachSignals ?? emptyCoachSignals;
    const loadedFunnelAnalytics = funnelAnalytics ?? emptyFunnelAnalytics;
    const loadedPvWaterfall = pvWaterfall ?? emptyPvWaterfall;
    const loadedPracticeHealth = practiceHealth ?? emptyPracticeHealth;
    const loadedScenarioPlanning = scenarioPlanning ?? emptyScenarioPlanning;
    const actionItems = buildActionSummaryItems({
        cashFlowStatus,
        redFlags,
        documentVerificationFlags,
        clientTransferQueue,
        entrepreneurReviews,
        strategicPlanDeployments,
        pendingTermsReacceptance,
        proposalStatus,
        paymentStatus,
        feeStatus,
        operationalHealth,
        aiOperationalAlert,
        pvWaterfall: loadedPvWaterfall,
        practiceHealth: loadedPracticeHealth,
        npoPendingConversions,
        npoFunding,
        referenceDataTasks,
        scenarioPlanning: loadedScenarioPlanning,
        panelOperations,
    });
    const actionQueueCount = actionItems.filter(
        (item) => item.value > 0 && item.priority !== 'neutral',
    ).length;
    const signalQueueCount =
        loadedIntegrationHealth.summary.amber +
        loadedIntegrationHealth.summary.red +
        loadedEconomicIndicators.summary.change_alerts +
        referenceDataTasks.summary.missing +
        referenceDataTasks.summary.overdue +
        referenceDataTasks.summary.due_soon +
        loadedFunnelAnalytics.summary.abandoned +
        loadedQuestionnaireOptimisation.summary.detected_candidates;

    return (
        <>
            <Head title="Advisor dashboard" />
            <div className="space-y-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Activity className="size-4" aria-hidden="true" />
                            Live workspace
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            Advisor dashboard
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Start with action queues, then move into portfolio
                            decisions and operating signals.
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-6">
                        <Metric
                            label="Clients"
                            value={clientsHealth.summary.advisory_clients}
                            explanation="Clients counts advisory client records visible to your current advisor role."
                            href="/advisor/clients"
                        />
                        <Metric
                            label="Entrepreneurs"
                            value={clientsHealth.summary.entrepreneurs}
                            explanation="Entrepreneurs counts founder workspaces visible to your current advisor role."
                            href="/advisor/entrepreneurs"
                        />
                        <Metric
                            label="Action queues"
                            value={actionQueueCount}
                            explanation="Action queues count dashboard areas with open priority work for the advisor team."
                            href="#advisor-command-centre"
                        />
                        <Metric
                            label="Needs attention"
                            value={clientsHealth.summary.needs_attention}
                            explanation="Needs attention counts client records with low engagement signals or document verification attention."
                            href="#advisor-clients-health"
                        />
                        <Metric
                            label="Messages pending"
                            value={messagesPending.total}
                            explanation="Messages pending counts client and entrepreneur conversations where the latest message is inbound and awaiting advisor attention."
                            href={messagesPending.index_url}
                        />
                        <Metric
                            label={loadedPvWaterfall.summary.target_pv_label}
                            value={formatCurrency(
                                loadedPvWaterfall.summary.target_pv,
                            )}
                            explanation={`Modelled upside assumes surfaced improvements and risk mitigations are fully captured. Planning range ${formatCurrency(loadedPvWaterfall.summary.target_pv_range.low)} - ${formatCurrency(loadedPvWaterfall.summary.target_pv_range.high)}.`}
                            href="#advisor-pv-waterfall"
                        />
                    </div>
                </header>
                <div
                    className="inline-flex flex-wrap gap-1 rounded-md border bg-muted/30 p-1"
                    role="tablist"
                    aria-label="Advisor dashboard sections"
                >
                    <DashboardTabButton
                        active={activeTab === 'priorities'}
                        onClick={() => setActiveTab('priorities')}
                        label="Priorities"
                        count={actionQueueCount}
                        controls="advisor-dashboard-priorities"
                    />
                    <DashboardTabButton
                        active={activeTab === 'signals'}
                        onClick={() => setActiveTab('signals')}
                        label="Signals"
                        count={signalQueueCount}
                        controls="advisor-dashboard-signals"
                    />
                </div>
                {activeTab === 'priorities' && (
                    <div
                        id="advisor-dashboard-priorities"
                        role="tabpanel"
                        className="space-y-6"
                    >
                        <ActionCommandCentre
                            items={actionItems}
                            activeTab={activeTab}
                            onSelectTab={setActiveTab}
                        />
                        <DashboardSection
                            title="Action panel"
                            description="Work the live queues before moving into planning and portfolio decisions."
                        >
                            <div className="grid items-start gap-4 xl:grid-cols-2">
                                <EntrepreneurReviewPanel
                                    payload={entrepreneurReviews}
                                />
                                <CashFlowRiskPanel payload={cashFlowStatus} />
                                <StrategicPlanDeploymentPanel
                                    payload={strategicPlanDeployments}
                                />
                                <div id="advisor-documents">
                                    <DocumentVerificationFlagPanel
                                        flags={documentVerificationFlags}
                                    />
                                </div>
                                <PendingTermsReacceptance
                                    payload={pendingTermsReacceptance}
                                />
                            </div>
                            <div className="grid items-start gap-4 xl:grid-cols-2">
                                <ProposalStatusPanel payload={proposalStatus} />
                                <PaymentStatusPanel payload={paymentStatus} />
                            </div>
                        </DashboardSection>
                        <DashboardSection
                            title="Portfolio decisions"
                            description="Review client health, PV opportunity, practice position, and scenario options."
                        >
                            <Deferred
                                data={advisorPortfolioDeferredProps}
                                fallback={<AdvisorPortfolioFallback />}
                            >
                                <>
                                    <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(340px,0.95fr)]">
                                        <MyClientsHealth
                                            payload={clientsHealth}
                                        />
                                        <PvWaterfallPanel
                                            payload={loadedPvWaterfall}
                                        />
                                    </div>
                                    <div className="grid items-start gap-4 xl:grid-cols-2">
                                        <PracticeHealth
                                            payload={loadedPracticeHealth}
                                        />
                                        <ScenarioPlanning
                                            payload={loadedScenarioPlanning}
                                        />
                                    </div>
                                </>
                            </Deferred>
                        </DashboardSection>
                        <DashboardSection
                            title="Risk review"
                            description="AI red flags sit here for advisor review after the live action queues are clear."
                        >
                            <RedFlagPanel payload={redFlags} />
                        </DashboardSection>
                    </div>
                )}
                {activeTab === 'signals' && (
                    <div
                        id="advisor-dashboard-signals"
                        role="tabpanel"
                        className="space-y-6"
                    >
                        <Deferred
                            data={advisorSignalDeferredProps}
                            fallback={<AdvisorSignalsFallback />}
                        >
                            <>
                                <DashboardSection
                                    title="Panel operations"
                                    description="Track partner hand-offs and governed learning work that supports the advisory team."
                                >
                                    <PanelOperations
                                        payload={panelOperations}
                                    />
                                </DashboardSection>
                                <DashboardSection
                                    title="Specialist workflows"
                                    description="Monitor NPO conversion, funding, wellbeing, coaching, and prospect signals."
                                >
                                    <div className="grid gap-4 xl:grid-cols-3">
                                        <NpoPendingConversions
                                            payload={npoPendingConversions}
                                        />
                                        <NpoFundingPanel payload={npoFunding} />
                                        <ProspectInbox
                                            payload={prospectInbox}
                                        />
                                    </div>
                                    <div className="grid gap-4 xl:grid-cols-3">
                                        <WellbeingAnalytics
                                            payload={loadedWellbeingAnalytics}
                                        />
                                        <CoachSignals
                                            payload={loadedCoachSignals}
                                        />
                                    </div>
                                </DashboardSection>
                                <DashboardSection
                                    title="Operating signals"
                                    description="Use these lower-urgency indicators to spot systemic issues and improvement opportunities."
                                >
                                    <div className="grid gap-4 xl:grid-cols-3">
                                        <EconomicIndicators
                                            payload={loadedEconomicIndicators}
                                        />
                                        <ReferenceDataTasksPanel
                                            payload={referenceDataTasks}
                                        />
                                        <IntegrationHealth
                                            payload={loadedIntegrationHealth}
                                        />
                                        <FunnelAnalytics
                                            payload={loadedFunnelAnalytics}
                                        />
                                        <QuestionnaireOptimisation
                                            payload={
                                                loadedQuestionnaireOptimisation
                                            }
                                        />
                                    </div>
                                </DashboardSection>
                            </>
                        </Deferred>
                    </div>
                )}
            </div>
        </>
    );
}

AdvisorDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
