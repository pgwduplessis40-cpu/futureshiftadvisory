import type { DocumentVerificationFlag } from '@/components/verification/DocumentVerificationFlagPanel';
import type {
    AiOperationalAlertPayload,
    CashFlowStatusPayload,
    ClientsHealthPayload,
    ClientTransferQueuePayload,
    CoachSignalsPayload,
    EconomicIndicatorsPayload,
    EntrepreneurReviewsPayload,
    FeeStatusPayload,
    FunnelAnalyticsPayload,
    IntegrationHealthPayload,
    MessagesPendingPayload,
    NpoFundingPayload,
    NpoPendingConversionsPayload,
    OperationalHealthPayload,
    PanelOperationsPayload,
    PaymentStatusPayload,
    PracticeHealthPayload,
    ProposalStatusPayload,
    ProspectInboxPayload,
    PvWaterfallPayload,
    QuestionnaireOptimisationPayload,
    RedFlagsPayload,
    ReferenceDataTasksPayload,
    ScenarioPlanningPayload,
    ServiceActivationRequestsPayload,
    StrategicPlanDeploymentsPayload,
    PendingTermsPayload,
    WellbeingAnalyticsPayload,
} from './types';

export type DashboardProps = {
    clientsHealth: ClientsHealthPayload;
    cashFlowStatus: CashFlowStatusPayload;
    redFlags: RedFlagsPayload;
    documentVerificationFlags: DocumentVerificationFlag[];
    messagesPending: MessagesPendingPayload;
    clientTransferQueue: ClientTransferQueuePayload;
    serviceActivationRequests: ServiceActivationRequestsPayload;
    entrepreneurReviews: EntrepreneurReviewsPayload;
    strategicPlanDeployments: StrategicPlanDeploymentsPayload;
    pendingTermsReacceptance: PendingTermsPayload;
    prospectInbox: ProspectInboxPayload;
    operationalHealth: OperationalHealthPayload;
    aiOperationalAlert: AiOperationalAlertPayload;
    integrationHealth?: IntegrationHealthPayload;
    economicIndicators?: EconomicIndicatorsPayload;
    pvWaterfall?: PvWaterfallPayload;
    practiceHealth?: PracticeHealthPayload;
    proposalStatus: ProposalStatusPayload;
    paymentStatus: PaymentStatusPayload;
    feeStatus: FeeStatusPayload;
    questionnaireOptimisation?: QuestionnaireOptimisationPayload;
    wellbeingAnalytics?: WellbeingAnalyticsPayload;
    coachSignals?: CoachSignalsPayload;
    npoPendingConversions: NpoPendingConversionsPayload;
    npoFunding: NpoFundingPayload;
    referenceDataTasks: ReferenceDataTasksPayload;
    scenarioPlanning?: ScenarioPlanningPayload;
    funnelAnalytics?: FunnelAnalyticsPayload;
    panelOperations: PanelOperationsPayload;
};
