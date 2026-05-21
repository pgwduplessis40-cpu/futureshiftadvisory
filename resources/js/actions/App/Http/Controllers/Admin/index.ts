import InvitationController from './InvitationController'
import QuestionnaireController from './QuestionnaireController'
import TermsController from './TermsController'
import IntegrationHealthController from './IntegrationHealthController'
const Admin = {
    InvitationController: Object.assign(InvitationController, InvitationController),
QuestionnaireController: Object.assign(QuestionnaireController, QuestionnaireController),
TermsController: Object.assign(TermsController, TermsController),
IntegrationHealthController: Object.assign(IntegrationHealthController, IntegrationHealthController),
}

export default Admin