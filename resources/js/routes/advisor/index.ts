import clients from './clients'
import entrepreneurs from './entrepreneurs'
import knowledge from './knowledge'
import prospects from './prospects'
import documentVerifications from './document-verifications'
const advisor = {
    clients: Object.assign(clients, clients),
entrepreneurs: Object.assign(entrepreneurs, entrepreneurs),
knowledge: Object.assign(knowledge, knowledge),
prospects: Object.assign(prospects, prospects),
documentVerifications: Object.assign(documentVerifications, documentVerifications),
}

export default advisor