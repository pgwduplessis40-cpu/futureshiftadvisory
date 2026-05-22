import clients from './clients'
import entrepreneurs from './entrepreneurs'
import knowledge from './knowledge'
import prospects from './prospects'
import documentVerifications from './document-verifications'
import analysisFindings from './analysis-findings'
const advisor = {
    clients: Object.assign(clients, clients),
entrepreneurs: Object.assign(entrepreneurs, entrepreneurs),
knowledge: Object.assign(knowledge, knowledge),
prospects: Object.assign(prospects, prospects),
documentVerifications: Object.assign(documentVerifications, documentVerifications),
analysisFindings: Object.assign(analysisFindings, analysisFindings),
}

export default advisor