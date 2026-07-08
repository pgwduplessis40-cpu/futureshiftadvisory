import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:528
 * @route '/portal/entrepreneur/advisory-request'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/advisory-request',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:528
 * @route '/portal/entrepreneur/advisory-request'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:528
 * @route '/portal/entrepreneur/advisory-request'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:528
 * @route '/portal/entrepreneur/advisory-request'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:528
 * @route '/portal/entrepreneur/advisory-request'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const advisoryRequest = {
    store: Object.assign(store, store),
}

export default advisoryRequest