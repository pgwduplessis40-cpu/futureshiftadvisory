import ClientController from './ClientController'
import EntrepreneurController from './EntrepreneurController'
const Advisor = {
    ClientController: Object.assign(ClientController, ClientController),
EntrepreneurController: Object.assign(EntrepreneurController, EntrepreneurController),
}

export default Advisor