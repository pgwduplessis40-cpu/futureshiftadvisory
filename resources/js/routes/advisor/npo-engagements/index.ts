import conversion from './conversion'
import configuration from './configuration'
import governanceReview from './governance-review'
const npoEngagements = {
    conversion: Object.assign(conversion, conversion),
configuration: Object.assign(configuration, configuration),
governanceReview: Object.assign(governanceReview, governanceReview),
}

export default npoEngagements