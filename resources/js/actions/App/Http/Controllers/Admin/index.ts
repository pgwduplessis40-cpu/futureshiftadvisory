import InvitationController from './InvitationController'
import QuestionnaireController from './QuestionnaireController'
import TermsController from './TermsController'
import IntegrationHealthController from './IntegrationHealthController'
import LearningUpdateController from './LearningUpdateController'
const Admin = {
    InvitationController: Object.assign(InvitationController, InvitationController),
QuestionnaireController: Object.assign(QuestionnaireController, QuestionnaireController),
TermsController: Object.assign(TermsController, TermsController),
IntegrationHealthController: Object.assign(IntegrationHealthController, IntegrationHealthController),
LearningUpdateController: Object.assign(LearningUpdateController, LearningUpdateController),
}

export default Admin