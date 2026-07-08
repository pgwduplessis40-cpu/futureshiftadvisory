import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::index
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:31
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
* @see \App\Http\Controllers\Admin\IntegrationHealthController::index
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:31
 * @route '/admin/integration-health'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::index
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:31
 * @route '/admin/integration-health'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::index
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:31
 * @route '/admin/integration-health'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::index
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:31
 * @route '/admin/integration-health'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::index
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:31
 * @route '/admin/integration-health'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::index
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:31
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
/**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::refresh
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:63
 * @route '/admin/integration-health/refresh'
 */
export const refresh = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refresh.url(options),
    method: 'post',
})

refresh.definition = {
    methods: ["post"],
    url: '/admin/integration-health/refresh',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::refresh
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:63
 * @route '/admin/integration-health/refresh'
 */
refresh.url = (options?: RouteQueryOptions) => {
    return refresh.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::refresh
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:63
 * @route '/admin/integration-health/refresh'
 */
refresh.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refresh.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::refresh
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:63
 * @route '/admin/integration-health/refresh'
 */
    const refreshForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: refresh.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\IntegrationHealthController::refresh
 * @see app/Http/Controllers/Admin/IntegrationHealthController.php:63
 * @route '/admin/integration-health/refresh'
 */
        refreshForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: refresh.url(options),
            method: 'post',
        })

    refresh.form = refreshForm
const IntegrationHealthController = { index, refresh }

export default IntegrationHealthController