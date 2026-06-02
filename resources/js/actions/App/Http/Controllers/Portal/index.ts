import DashboardController from './DashboardController'
import DdBusinessPlanController from './DdBusinessPlanController'
import EntrepreneurDashboardController from './EntrepreneurDashboardController'
import EntrepreneurPlanController from './EntrepreneurPlanController'
import EntrepreneurAssessmentController from './EntrepreneurAssessmentController'
import ReportController from './ReportController'
import NpoImpactMetricController from './NpoImpactMetricController'
import InspirationBoardController from './InspirationBoardController'
import MessageController from './MessageController'
import ProposalSignoffController from './ProposalSignoffController'
import WellbeingController from './WellbeingController'
import OnboardingController from './OnboardingController'
const Portal = {
    DashboardController: Object.assign(DashboardController, DashboardController),
DdBusinessPlanController: Object.assign(DdBusinessPlanController, DdBusinessPlanController),
EntrepreneurDashboardController: Object.assign(EntrepreneurDashboardController, EntrepreneurDashboardController),
EntrepreneurPlanController: Object.assign(EntrepreneurPlanController, EntrepreneurPlanController),
EntrepreneurAssessmentController: Object.assign(EntrepreneurAssessmentController, EntrepreneurAssessmentController),
ReportController: Object.assign(ReportController, ReportController),
NpoImpactMetricController: Object.assign(NpoImpactMetricController, NpoImpactMetricController),
InspirationBoardController: Object.assign(InspirationBoardController, InspirationBoardController),
MessageController: Object.assign(MessageController, MessageController),
ProposalSignoffController: Object.assign(ProposalSignoffController, ProposalSignoffController),
WellbeingController: Object.assign(WellbeingController, WellbeingController),
OnboardingController: Object.assign(OnboardingController, OnboardingController),
}

export default Portal