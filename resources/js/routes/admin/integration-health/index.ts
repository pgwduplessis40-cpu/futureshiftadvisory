import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::__invoke
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:22
 * @route '/admin/integration-health'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/integration-health',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::__invoke
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:22
 * @route '/admin/integration-health'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::__invoke
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:22
 * @route '/admin/integration-health'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::__invoke
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:22
 * @route '/admin/integration-health'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::__invoke
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:22
 * @route '/admin/integration-health'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::__invoke
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:22
 * @route '/admin/integration-health'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::__invoke
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:22
 * @route '/admin/integration-health'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
const integrationHealth = {
    index: Object.assign(index, index),
}

export default integrationHealth