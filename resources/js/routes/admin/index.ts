import invitations from './invitations'
import questionnaires from './questionnaires'
import terms from './terms'
import integrationHealth from './integration-health'
import learningUpdates from './learning-updates'
import learningUpdateImplementations from './learning-update-implementations'
import panelMembers from './panel-members'
const admin = {
    invitations: Object.assign(invitations, invitations),
questionnaires: Object.assign(questionnaires, questionnaires),
terms: Object.assign(terms, terms),
integrationHealth: Object.assign(integrationHealth, integrationHealth),
learningUpdates: Object.assign(learningUpdates, learningUpdates),
learningUpdateImplementations: Object.assign(learningUpdateImplementations, learningUpdateImplementations),
panelMembers: Object.assign(panelMembers, panelMembers),
}

export default admin