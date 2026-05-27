import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\NpoImpactMetricController::__invoke
 * @see app/Http/Controllers/Portal/NpoImpactMetricController.php:26
 * @route '/portal/npo-impact-metrics'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/npo-impact-metrics',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\NpoImpactMetricController::__invoke
 * @see app/Http/Controllers/Portal/NpoImpactMetricController.php:26
 * @route '/portal/npo-impact-metrics'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\NpoImpactMetricController::__invoke
 * @see app/Http/Controllers/Portal/NpoImpactMetricController.php:26
 * @route '/portal/npo-impact-metrics'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\NpoImpactMetricController::__invoke
 * @see app/Http/Controllers/Portal/NpoImpactMetricController.php:26
 * @route '/portal/npo-impact-metrics'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\NpoImpactMetricController::__invoke
 * @see app/Http/Controllers/Portal/NpoImpactMetricController.php:26
 * @route '/portal/npo-impact-metrics'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const npoImpactMetrics = {
    store: Object.assign(store, store),
}

export default npoImpactMetrics