import ScreenShareConnectionController from './ScreenShareConnectionController'
import ScreenShareSessionController from './ScreenShareSessionController'
import EntrepreneurScreenShareController from './EntrepreneurScreenShareController'
const ScreenShare = {
    ScreenShareConnectionController: Object.assign(ScreenShareConnectionController, ScreenShareConnectionController),
ScreenShareSessionController: Object.assign(ScreenShareSessionController, ScreenShareSessionController),
EntrepreneurScreenShareController: Object.assign(EntrepreneurScreenShareController, EntrepreneurScreenShareController),
}

export default ScreenShare