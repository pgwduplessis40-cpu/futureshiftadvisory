import Public from './Public'
import Auth from './Auth'
import Admin from './Admin'
import Advisor from './Advisor'
import DashboardController from './DashboardController'
import Portal from './Portal'
import Settings from './Settings'
const Controllers = {
    Public: Object.assign(Public, Public),
Auth: Object.assign(Auth, Auth),
Admin: Object.assign(Admin, Admin),
Advisor: Object.assign(Advisor, Advisor),
DashboardController: Object.assign(DashboardController, DashboardController),
Portal: Object.assign(Portal, Portal),
Settings: Object.assign(Settings, Settings),
}

export default Controllers