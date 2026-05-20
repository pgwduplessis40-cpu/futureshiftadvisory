import InvitationController from './InvitationController';
import TermsController from './TermsController';
const Admin = {
    InvitationController: Object.assign(
        InvitationController,
        InvitationController,
    ),
    TermsController: Object.assign(TermsController, TermsController),
};

export default Admin;
