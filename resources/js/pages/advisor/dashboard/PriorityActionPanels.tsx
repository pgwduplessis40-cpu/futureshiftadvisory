import { DocumentVerificationFlagPanel } from '@/components/verification/DocumentVerificationFlagPanel';
import {
    CashFlowRiskPanel,
    PendingTermsReacceptance,
    StrategicPlanDeploymentPanel,
} from './ClientHealthPanels';
import {
    PaymentStatusPanel,
    ProposalStatusPanel,
} from './FinancialWorkflowPanels';
import type { DashboardProps } from './props';
import {
    EntrepreneurReviewPanel,
    ServiceActivationRequestPanel,
} from './SignalPanels';

type PriorityActionPanelsProps = Pick<
    DashboardProps,
    | 'cashFlowStatus'
    | 'documentVerificationFlags'
    | 'entrepreneurReviews'
    | 'pendingTermsReacceptance'
    | 'proposalStatus'
    | 'paymentStatus'
    | 'serviceActivationRequests'
    | 'strategicPlanDeployments'
>;

export function PriorityActionPanels({
    cashFlowStatus,
    documentVerificationFlags,
    entrepreneurReviews,
    pendingTermsReacceptance,
    proposalStatus,
    paymentStatus,
    serviceActivationRequests,
    strategicPlanDeployments,
}: PriorityActionPanelsProps) {
    return (
        <>
            <div className="grid items-start gap-4 xl:grid-cols-2">
                {serviceActivationRequests.summary.total > 0 ? (
                    <ServiceActivationRequestPanel
                        payload={serviceActivationRequests}
                    />
                ) : null}
                <EntrepreneurReviewPanel payload={entrepreneurReviews} />
                <CashFlowRiskPanel payload={cashFlowStatus} />
                <StrategicPlanDeploymentPanel
                    payload={strategicPlanDeployments}
                />
                <div id="advisor-documents">
                    <DocumentVerificationFlagPanel
                        flags={documentVerificationFlags}
                    />
                </div>
                <PendingTermsReacceptance payload={pendingTermsReacceptance} />
            </div>
            <div className="grid items-start gap-4 xl:grid-cols-2">
                <ProposalStatusPanel payload={proposalStatus} />
                <PaymentStatusPanel payload={paymentStatus} />
            </div>
        </>
    );
}
