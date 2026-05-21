import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::__invoke
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:16
 * @route '/admin/integration-health'
 */
const IntegrationHealthController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: IntegrationHealthController.url(options),
    method: 'get',
})

IntegrationHealthController.definition = {
    methods: ["get","head"],
    url: '/admin/integration-health',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::__invoke
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:16
 * @route '/admin/integration-health'
 */
IntegrationHealthController.url = (options?: RouteQueryOptions) => {
    return IntegrationHealthController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::__invoke
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:16
 * @route '/admin/integration-health'
 */
IntegrationHealthController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: IntegrationHealthController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::__invoke
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:16
 * @route '/admin/integration-health'
 */
IntegrationHealthController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: IntegrationHealthController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::__invoke
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:16
 * @route '/admin/integration-health'
 */
    const IntegrationHealthControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: IntegrationHealthController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::__invoke
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:16
 * @route '/admin/integration-health'
 */
        IntegrationHealthControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: IntegrationHealthController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::__invoke
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:16
 * @route '/admin/integration-health'
 */
        IntegrationHealthControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: IntegrationHealthController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    IntegrationHealthController.form = IntegrationHealthControllerForm
export default IntegrationHealthController