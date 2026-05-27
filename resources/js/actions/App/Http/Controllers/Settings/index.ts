import ProfileController from './ProfileController'
import CommunicationController from './CommunicationController'
import CalendarController from './CalendarController'
import SecurityController from './SecurityController'
const Settings = {
    ProfileController: Object.assign(ProfileController, ProfileController),
CommunicationController: Object.assign(CommunicationController, CommunicationController),
CalendarController: Object.assign(CalendarController, CalendarController),
SecurityController: Object.assign(SecurityController, SecurityController),
}

export default Settings