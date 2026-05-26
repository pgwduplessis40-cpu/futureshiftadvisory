import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:22
 * @route '/portal/entrepreneur'
 */
export const dashboard = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:22
 * @route '/portal/entrepreneur'
 */
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:22
 * @route '/portal/entrepreneur'
 */
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:22
 * @route '/portal/entrepreneur'
 */
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:22
 * @route '/portal/entrepreneur'
 */
    const dashboardForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: dashboard.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:22
 * @route '/portal/entrepreneur'
 */
        dashboardForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: dashboard.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:22
 * @route '/portal/entrepreneur'
 */
        dashboardForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: dashboard.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    dashboard.form = dashboardForm
const entrepreneur = {
    dashboard: Object.assign(dashboard, dashboard),
}

export default entrepreneur