import ClientController from './ClientController'
import OffboardingController from './OffboardingController'
import ClientLifecycleController from './ClientLifecycleController'
import KnowledgeAssessmentController from './KnowledgeAssessmentController'
import ProposalController from './ProposalController'
import ReportController from './ReportController'
import MeetingController from './MeetingController'
import ClientEmailController from './ClientEmailController'
import ClientMessageController from './ClientMessageController'
import AccountingConnectionController from './AccountingConnectionController'
import BriefingController from './BriefingController'
import EntrepreneurController from './EntrepreneurController'
import KnowledgeController from './KnowledgeController'
import ProspectInboxController from './ProspectInboxController'
import DocumentVerificationController from './DocumentVerificationController'
import RedFlagController from './RedFlagController'
import AnalysisFeedbackController from './AnalysisFeedbackController'
const Advisor = {
    ClientController: Object.assign(ClientController, ClientController),
OffboardingController: Object.assign(OffboardingController, OffboardingController),
ClientLifecycleController: Object.assign(ClientLifecycleController, ClientLifecycleController),
KnowledgeAssessmentController: Object.assign(KnowledgeAssessmentController, KnowledgeAssessmentController),
ProposalController: Object.assign(ProposalController, ProposalController),
ReportController: Object.assign(ReportController, ReportController),
MeetingController: Object.assign(MeetingController, MeetingController),
ClientEmailController: Object.assign(ClientEmailController, ClientEmailController),
ClientMessageController: Object.assign(ClientMessageController, ClientMessageController),
AccountingConnectionController: Object.assign(AccountingConnectionController, AccountingConnectionController),
BriefingController: Object.assign(BriefingController, BriefingController),
EntrepreneurController: Object.assign(EntrepreneurController, EntrepreneurController),
KnowledgeController: Object.assign(KnowledgeController, KnowledgeController),
ProspectInboxController: Object.assign(ProspectInboxController, ProspectInboxController),
DocumentVerificationController: Object.assign(DocumentVerificationController, DocumentVerificationController),
RedFlagController: Object.assign(RedFlagController, RedFlagController),
AnalysisFeedbackController: Object.assign(AnalysisFeedbackController, AnalysisFeedbackController),
}

export default Advisor