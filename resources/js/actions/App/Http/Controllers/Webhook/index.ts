import ProspectIntakeController from './ProspectIntakeController'
import PaymentWebhookController from './PaymentWebhookController'
const Webhook = {
    ProspectIntakeController: Object.assign(ProspectIntakeController, ProspectIntakeController),
PaymentWebhookController: Object.assign(PaymentWebhookController, PaymentWebhookController),
}

export default Webhook