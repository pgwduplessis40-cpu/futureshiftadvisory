import ClientController from './ClientController'
import OffboardingController from './OffboardingController'
import ClientLifecycleController from './ClientLifecycleController'
import ClientEmailController from './ClientEmailController'
import ClientMessageController from './ClientMessageController'
import EntrepreneurController from './EntrepreneurController'
import KnowledgeController from './KnowledgeController'
import ProspectInboxController from './ProspectInboxController'
import DocumentVerificationController from './DocumentVerificationController'
import AnalysisFeedbackController from './AnalysisFeedbackController'
const Advisor = {
    ClientController: Object.assign(ClientController, ClientController),
OffboardingController: Object.assign(OffboardingController, OffboardingController),
ClientLifecycleController: Object.assign(ClientLifecycleController, ClientLifecycleController),
ClientEmailController: Object.assign(ClientEmailController, ClientEmailController),
ClientMessageController: Object.assign(ClientMessageController, ClientMessageController),
EntrepreneurController: Object.assign(EntrepreneurController, EntrepreneurController),
KnowledgeController: Object.assign(KnowledgeController, KnowledgeController),
ProspectInboxController: Object.assign(ProspectInboxController, ProspectInboxController),
DocumentVerificationController: Object.assign(DocumentVerificationController, DocumentVerificationController),
AnalysisFeedbackController: Object.assign(AnalysisFeedbackController, AnalysisFeedbackController),
}

export default Advisor