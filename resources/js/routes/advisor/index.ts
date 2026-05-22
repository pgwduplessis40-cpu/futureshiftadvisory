import clients from './clients'
import proposals from './proposals'
import reports from './reports'
import industryBriefings from './industry-briefings'
import preMeetingBriefs from './pre-meeting-briefs'
import entrepreneurs from './entrepreneurs'
import knowledge from './knowledge'
import prospects from './prospects'
import documentVerifications from './document-verifications'
import redFlags from './red-flags'
import analysisFindings from './analysis-findings'
const advisor = {
    clients: Object.assign(clients, clients),
proposals: Object.assign(proposals, proposals),
reports: Object.assign(reports, reports),
industryBriefings: Object.assign(industryBriefings, industryBriefings),
preMeetingBriefs: Object.assign(preMeetingBriefs, preMeetingBriefs),
entrepreneurs: Object.assign(entrepreneurs, entrepreneurs),
knowledge: Object.assign(knowledge, knowledge),
prospects: Object.assign(prospects, prospects),
documentVerifications: Object.assign(documentVerifications, documentVerifications),
redFlags: Object.assign(redFlags, redFlags),
analysisFindings: Object.assign(analysisFindings, analysisFindings),
}

export default advisor