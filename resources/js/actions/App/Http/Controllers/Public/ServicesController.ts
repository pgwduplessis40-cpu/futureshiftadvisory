import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Public\ServicesController::__invoke
 * @see app/Http/Controllers/Public/ServicesController.php:14
 * @route '/services'
 */
const ServicesController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ServicesController.url(options),
    method: 'get',
})

ServicesController.definition = {
    methods: ["get","head"],
    url: '/services',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Public\ServicesController::__invoke
 * @see app/Http/Controllers/Public/ServicesController.php:14
 * @route '/services'
 */
ServicesController.url = (options?: RouteQueryOptions) => {
    return ServicesController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Public\ServicesController::__invoke
 * @see app/Http/Controllers/Public/ServicesController.php:14
 * @route '/services'
 */
ServicesController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: ServicesController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Public\ServicesController::__invoke
 * @see app/Http/Controllers/Public/ServicesController.php:14
 * @route '/services'
 */
ServicesController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: ServicesController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Public\ServicesController::__invoke
 * @see app/Http/Controllers/Public/ServicesController.php:14
 * @route '/services'
 */
    const ServicesControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: ServicesController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Public\ServicesController::__invoke
 * @see app/Http/Controllers/Public/ServicesController.php:14
 * @route '/services'
 */
        ServicesControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: ServicesController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Public\ServicesController::__invoke
 * @see app/Http/Controllers/Public/ServicesController.php:14
 * @route '/services'
 */
        ServicesControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: ServicesController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    ServicesController.form = ServicesControllerForm
export default ServicesController