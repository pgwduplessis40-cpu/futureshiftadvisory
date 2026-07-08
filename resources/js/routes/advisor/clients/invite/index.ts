import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:193
 * @route '/advisor/clients/invite'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/clients/invite',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:193
 * @route '/advisor/clients/invite'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:193
 * @route '/advisor/clients/invite'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:193
 * @route '/advisor/clients/invite'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:193
 * @route '/advisor/clients/invite'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const invite = {
    store: Object.assign(store, store),
}

export default invite