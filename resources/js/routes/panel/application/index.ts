import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\PanelApplicationController::store
 * @see app/Http/Controllers/PanelApplicationController.php:16
 * @route '/panel/application'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/panel/application',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PanelApplicationController::store
 * @see app/Http/Controllers/PanelApplicationController.php:16
 * @route '/panel/application'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PanelApplicationController::store
 * @see app/Http/Controllers/PanelApplicationController.php:16
 * @route '/panel/application'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PanelApplicationController::store
 * @see app/Http/Controllers/PanelApplicationController.php:16
 * @route '/panel/application'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PanelApplicationController::store
 * @see app/Http/Controllers/PanelApplicationController.php:16
 * @route '/panel/application'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const application = {
    store: Object.assign(store, store),
}

export default application