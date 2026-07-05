import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:218
 * @route '/portal/acquisition-plan/business-advice'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/acquisition-plan/business-advice',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:218
 * @route '/portal/acquisition-plan/business-advice'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:218
 * @route '/portal/acquisition-plan/business-advice'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:218
 * @route '/portal/acquisition-plan/business-advice'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
 * @see app/Http/Controllers/Portal/DdBusinessPlanController.php:218
 * @route '/portal/acquisition-plan/business-advice'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const businessAdvice = {
    store: Object.assign(store, store),
}

export default businessAdvice