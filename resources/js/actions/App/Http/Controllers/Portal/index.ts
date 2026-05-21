import DashboardController from './DashboardController'
import EntrepreneurDashboardController from './EntrepreneurDashboardController'
import OnboardingController from './OnboardingController'
const Portal = {
    DashboardController: Object.assign(DashboardController, DashboardController),
EntrepreneurDashboardController: Object.assign(EntrepreneurDashboardController, EntrepreneurDashboardController),
OnboardingController: Object.assign(OnboardingController, OnboardingController),
}

export default Portal