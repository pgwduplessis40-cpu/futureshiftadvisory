import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:230
 * @route '/portal/entrepreneur/idea-validation'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/idea-validation',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:230
 * @route '/portal/entrepreneur/idea-validation'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:230
 * @route '/portal/entrepreneur/idea-validation'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:230
 * @route '/portal/entrepreneur/idea-validation'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:230
 * @route '/portal/entrepreneur/idea-validation'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
const ideaValidation = {
    store: Object.assign(store, store),
}

export default ideaValidation