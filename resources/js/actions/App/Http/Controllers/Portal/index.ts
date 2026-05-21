import DashboardController from './DashboardController'
import EntrepreneurDashboardController from './EntrepreneurDashboardController'
import MessageController from './MessageController'
import WellbeingController from './WellbeingController'
import OnboardingController from './OnboardingController'
const Portal = {
    DashboardController: Object.assign(DashboardController, DashboardController),
EntrepreneurDashboardController: Object.assign(EntrepreneurDashboardController, EntrepreneurDashboardController),
MessageController: Object.assign(MessageController, MessageController),
WellbeingController: Object.assign(WellbeingController, WellbeingController),
OnboardingController: Object.assign(OnboardingController, OnboardingController),
}

export default Portal