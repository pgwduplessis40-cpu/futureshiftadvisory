import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Webhook\PaymentWebhookController::stripe
 * @see app/Http/Controllers/Webhook/PaymentWebhookController.php:20
 * @route '/api/webhooks/payments/stripe'
 */
export const stripe = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: stripe.url(options),
    method: 'post',
})

stripe.definition = {
    methods: ["post"],
    url: '/api/webhooks/payments/stripe',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Webhook\PaymentWebhookController::stripe
 * @see app/Http/Controllers/Webhook/PaymentWebhookController.php:20
 * @route '/api/webhooks/payments/stripe'
 */
stripe.url = (options?: RouteQueryOptions) => {
    return stripe.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Webhook\PaymentWebhookController::stripe
 * @see app/Http/Controllers/Webhook/PaymentWebhookController.php:20
 * @route '/api/webhooks/payments/stripe'
 */
stripe.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: stripe.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Webhook\PaymentWebhookController::stripe
 * @see app/Http/Controllers/Webhook/PaymentWebhookController.php:20
 * @route '/api/webhooks/payments/stripe'
 */
    const stripeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: stripe.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Webhook\PaymentWebhookController::stripe
 * @see app/Http/Controllers/Webhook/PaymentWebhookController.php:20
 * @route '/api/webhooks/payments/stripe'
 */
        stripeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: stripe.url(options),
            method: 'post',
        })

    stripe.form = stripeForm
/**
* @see \App\Http\Controllers\Webhook\PaymentWebhookController::windcave
 * @see app/Http/Controllers/Webhook/PaymentWebhookController.php:29
 * @route '/api/webhooks/payments/windcave'
 */
export const windcave = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: windcave.url(options),
    method: 'post',
})

windcave.definition = {
    methods: ["post"],
    url: '/api/webhooks/payments/windcave',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Webhook\PaymentWebhookController::windcave
 * @see app/Http/Controllers/Webhook/PaymentWebhookController.php:29
 * @route '/api/webhooks/payments/windcave'
 */
windcave.url = (options?: RouteQueryOptions) => {
    return windcave.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Webhook\PaymentWebhookController::windcave
 * @see app/Http/Controllers/Webhook/PaymentWebhookController.php:29
 * @route '/api/webhooks/payments/windcave'
 */
windcave.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: windcave.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Webhook\PaymentWebhookController::windcave
 * @see app/Http/Controllers/Webhook/PaymentWebhookController.php:29
 * @route '/api/webhooks/payments/windcave'
 */
    const windcaveForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: windcave.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Webhook\PaymentWebhookController::windcave
 * @see app/Http/Controllers/Webhook/PaymentWebhookController.php:29
 * @route '/api/webhooks/payments/windcave'
 */
        windcaveForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: windcave.url(options),
            method: 'post',
        })

    windcave.form = windcaveForm
const payments = {
    stripe: Object.assign(stripe, stripe),
windcave: Object.assign(windcave, windcave),
}

export default payments
