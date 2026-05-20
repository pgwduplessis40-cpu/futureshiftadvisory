import InviteAcceptController from './InviteAcceptController'
import MfaSetupController from './MfaSetupController'
import MfaChallengeController from './MfaChallengeController'
import TermsPendingController from './TermsPendingController'
const Auth = {
    InviteAcceptController: Object.assign(InviteAcceptController, InviteAcceptController),
MfaSetupController: Object.assign(MfaSetupController, MfaSetupController),
MfaChallengeController: Object.assign(MfaChallengeController, MfaChallengeController),
TermsPendingController: Object.assign(TermsPendingController, TermsPendingController),
}

export default Auth