import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:67
 * @route '/portal'
 */
const DashboardController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: DashboardController.url(options),
    method: 'get',
})

DashboardController.definition = {
    methods: ["get","head"],
    url: '/portal',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:67
 * @route '/portal'
 */
DashboardController.url = (options?: RouteQueryOptions) => {
    return DashboardController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:67
 * @route '/portal'
 */
DashboardController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: DashboardController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:67
 * @route '/portal'
 */
DashboardController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: DashboardController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:67
 * @route '/portal'
 */
    const DashboardControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: DashboardController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:67
 * @route '/portal'
 */
        DashboardControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: DashboardController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:67
 * @route '/portal'
 */
        DashboardControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: DashboardController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    DashboardController.form = DashboardControllerForm
export default DashboardController