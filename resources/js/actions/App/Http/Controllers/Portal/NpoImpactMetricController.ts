import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\NpoImpactMetricController::__invoke
 * @see app/Http/Controllers/Portal/NpoImpactMetricController.php:26
 * @route '/portal/npo-impact-metrics'
 */
const NpoImpactMetricController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: NpoImpactMetricController.url(options),
    method: 'post',
})

NpoImpactMetricController.definition = {
    methods: ["post"],
    url: '/portal/npo-impact-metrics',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\NpoImpactMetricController::__invoke
 * @see app/Http/Controllers/Portal/NpoImpactMetricController.php:26
 * @route '/portal/npo-impact-metrics'
 */
NpoImpactMetricController.url = (options?: RouteQueryOptions) => {
    return NpoImpactMetricController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\NpoImpactMetricController::__invoke
 * @see app/Http/Controllers/Portal/NpoImpactMetricController.php:26
 * @route '/portal/npo-impact-metrics'
 */
NpoImpactMetricController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: NpoImpactMetricController.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\NpoImpactMetricController::__invoke
 * @see app/Http/Controllers/Portal/NpoImpactMetricController.php:26
 * @route '/portal/npo-impact-metrics'
 */
    const NpoImpactMetricControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: NpoImpactMetricController.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\NpoImpactMetricController::__invoke
 * @see app/Http/Controllers/Portal/NpoImpactMetricController.php:26
 * @route '/portal/npo-impact-metrics'
 */
        NpoImpactMetricControllerForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: NpoImpactMetricController.url(options),
            method: 'post',
        })

    NpoImpactMetricController.form = NpoImpactMetricControllerForm
export default NpoImpactMetricController
