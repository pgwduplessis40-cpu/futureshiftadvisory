import Public from './Public'
import Auth from './Auth'
import Admin from './Admin'
import Settings from './Settings'
const Controllers = {
    Public: Object.assign(Public, Public),
Auth: Object.assign(Auth, Auth),
Admin: Object.assign(Admin, Admin),
Settings: Object.assign(Settings, Settings),
}

export default Controllers