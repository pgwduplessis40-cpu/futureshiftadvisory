import Webhook from './Webhook'
import Public from './Public'
import Auth from './Auth'
import Admin from './Admin'
import Advisor from './Advisor'
import Portal from './Portal'
import DocumentController from './DocumentController'
import DashboardController from './DashboardController'
import NotificationController from './NotificationController'
import Settings from './Settings'
const Controllers = {
    Webhook: Object.assign(Webhook, Webhook),
Public: Object.assign(Public, Public),
Auth: Object.assign(Auth, Auth),
Admin: Object.assign(Admin, Admin),
Advisor: Object.assign(Advisor, Advisor),
Portal: Object.assign(Portal, Portal),
DocumentController: Object.assign(DocumentController, DocumentController),
DashboardController: Object.assign(DashboardController, DashboardController),
NotificationController: Object.assign(NotificationController, NotificationController),
Settings: Object.assign(Settings, Settings),
}

export default Controllers