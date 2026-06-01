import InvitationController from './InvitationController'
import QuestionnaireController from './QuestionnaireController'
import TermsController from './TermsController'
import IntegrationHealthController from './IntegrationHealthController'
import IntegrationCredentialController from './IntegrationCredentialController'
import ReferenceDataController from './ReferenceDataController'
import LearningUpdateController from './LearningUpdateController'
import PanelMemberController from './PanelMemberController'
const Admin = {
    InvitationController: Object.assign(InvitationController, InvitationController),
QuestionnaireController: Object.assign(QuestionnaireController, QuestionnaireController),
TermsController: Object.assign(TermsController, TermsController),
IntegrationHealthController: Object.assign(IntegrationHealthController, IntegrationHealthController),
IntegrationCredentialController: Object.assign(IntegrationCredentialController, IntegrationCredentialController),
ReferenceDataController: Object.assign(ReferenceDataController, ReferenceDataController),
LearningUpdateController: Object.assign(LearningUpdateController, LearningUpdateController),
PanelMemberController: Object.assign(PanelMemberController, PanelMemberController),
}

export default Admin