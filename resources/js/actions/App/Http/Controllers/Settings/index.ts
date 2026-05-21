import ProfileController from './ProfileController'
import CommunicationController from './CommunicationController'
import SecurityController from './SecurityController'
const Settings = {
    ProfileController: Object.assign(ProfileController, ProfileController),
CommunicationController: Object.assign(CommunicationController, CommunicationController),
SecurityController: Object.assign(SecurityController, SecurityController),
}

export default Settings