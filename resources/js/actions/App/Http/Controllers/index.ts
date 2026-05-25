import DdGuestUploadController from './DdGuestUploadController'
import Webhook from './Webhook'
import AdvisorApi from './AdvisorApi'
import MobileApi from './MobileApi'
import Public from './Public'
import Auth from './Auth'
import Admin from './Admin'
import Advisor from './Advisor'
import Portal from './Portal'
import DocumentController from './DocumentController'
import BulkCommunicationOpenController from './BulkCommunicationOpenController'
import DashboardController from './DashboardController'
import NotificationController from './NotificationController'
import Settings from './Settings'
const Controllers = {
    DdGuestUploadController: Object.assign(DdGuestUploadController, DdGuestUploadController),
Webhook: Object.assign(Webhook, Webhook),
AdvisorApi: Object.assign(AdvisorApi, AdvisorApi),
MobileApi: Object.assign(MobileApi, MobileApi),
Public: Object.assign(Public, Public),
Auth: Object.assign(Auth, Auth),
Admin: Object.assign(Admin, Admin),
Advisor: Object.assign(Advisor, Advisor),
Portal: Object.assign(Portal, Portal),
DocumentController: Object.assign(DocumentController, DocumentController),
BulkCommunicationOpenController: Object.assign(BulkCommunicationOpenController, BulkCommunicationOpenController),
DashboardController: Object.assign(DashboardController, DashboardController),
NotificationController: Object.assign(NotificationController, NotificationController),
Settings: Object.assign(Settings, Settings),
}

export default Controllers