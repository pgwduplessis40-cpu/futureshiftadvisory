import ClientController from './ClientController'
import WriteController from './WriteController'
const AdvisorApi = {
    ClientController: Object.assign(ClientController, ClientController),
WriteController: Object.assign(WriteController, WriteController),
}

export default AdvisorApi