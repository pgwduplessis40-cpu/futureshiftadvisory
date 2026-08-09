import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Webhook\ProspectIntakeController::store
 * @see app/Http/Controllers/Webhook/ProspectIntakeController.php:23
 * @route '/api/webhooks/prospects'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/webhooks/prospects',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Webhook\ProspectIntakeController::store
 * @see app/Http/Controllers/Webhook/ProspectIntakeController.php:23
 * @route '/api/webhooks/prospects'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Webhook\ProspectIntakeController::store
 * @see app/Http/Controllers/Webhook/ProspectIntakeController.php:23
 * @route '/api/webhooks/prospects'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Webhook\ProspectIntakeController::store
 * @see app/Http/Controllers/Webhook/ProspectIntakeController.php:23
 * @route '/api/webhooks/prospects'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Webhook\ProspectIntakeController::store
 * @see app/Http/Controllers/Webhook/ProspectIntakeController.php:23
 * @route '/api/webhooks/prospects'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
const ProspectIntakeController = { store }

export default ProspectIntakeController