import MeController from './MeController'
import ClientController from './ClientController'
import VoiceSessionController from './VoiceSessionController'
const MobileApi = {
    MeController: Object.assign(MeController, MeController),
ClientController: Object.assign(ClientController, ClientController),
VoiceSessionController: Object.assign(VoiceSessionController, VoiceSessionController),
}

export default MobileApi