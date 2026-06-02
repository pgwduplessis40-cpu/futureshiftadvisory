import DdGuestUploadController from './DdGuestUploadController'
import Webhook from './Webhook'
import AdvisorApi from './AdvisorApi'
import MobileApi from './MobileApi'
import Public from './Public'
import Auth from './Auth'
import Admin from './Admin'
import Advisor from './Advisor'
import Portal from './Portal'
import CalendarController from './CalendarController'
import DocumentController from './DocumentController'
import BulkCommunicationOpenController from './BulkCommunicationOpenController'
import DashboardController from './DashboardController'
import NotificationController from './NotificationController'
import Broker from './Broker'
import Coach from './Coach'
import PanelApplicationController from './PanelApplicationController'
import PanelAgreementController from './PanelAgreementController'
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
CalendarController: Object.assign(CalendarController, CalendarController),
DocumentController: Object.assign(DocumentController, DocumentController),
BulkCommunicationOpenController: Object.assign(BulkCommunicationOpenController, BulkCommunicationOpenController),
DashboardController: Object.assign(DashboardController, DashboardController),
NotificationController: Object.assign(NotificationController, NotificationController),
Broker: Object.assign(Broker, Broker),
Coach: Object.assign(Coach, Coach),
PanelApplicationController: Object.assign(PanelApplicationController, PanelApplicationController),
PanelAgreementController: Object.assign(PanelAgreementController, PanelAgreementController),
Settings: Object.assign(Settings, Settings),
}

export default Controllers