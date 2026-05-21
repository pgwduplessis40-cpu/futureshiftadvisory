import InvitationController from './InvitationController'
import QuestionnaireController from './QuestionnaireController'
import TermsController from './TermsController'
const Admin = {
    InvitationController: Object.assign(InvitationController, InvitationController),
QuestionnaireController: Object.assign(QuestionnaireController, QuestionnaireController),
TermsController: Object.assign(TermsController, TermsController),
}

export default Admin