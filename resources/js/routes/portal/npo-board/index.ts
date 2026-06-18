import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\NpoBoardDashboardController::__invoke
 * @see app/Http/Controllers/Portal/NpoBoardDashboardController.php:21
 * @route '/portal/npo-board'
 */
export const dashboard = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/portal/npo-board',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\NpoBoardDashboardController::__invoke
 * @see app/Http/Controllers/Portal/NpoBoardDashboardController.php:21
 * @route '/portal/npo-board'
 */
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\NpoBoardDashboardController::__invoke
 * @see app/Http/Controllers/Portal/NpoBoardDashboardController.php:21
 * @route '/portal/npo-board'
 */
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\NpoBoardDashboardController::__invoke
 * @see app/Http/Controllers/Portal/NpoBoardDashboardController.php:21
 * @route '/portal/npo-board'
 */
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\NpoBoardDashboardController::__invoke
 * @see app/Http/Controllers/Portal/NpoBoardDashboardController.php:21
 * @route '/portal/npo-board'
 */
    const dashboardForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: dashboard.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\NpoBoardDashboardController::__invoke
 * @see app/Http/Controllers/Portal/NpoBoardDashboardController.php:21
 * @route '/portal/npo-board'
 */
        dashboardForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: dashboard.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\NpoBoardDashboardController::__invoke
 * @see app/Http/Controllers/Portal/NpoBoardDashboardController.php:21
 * @route '/portal/npo-board'
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
const npoBoard = {
    dashboard: Object.assign(dashboard, dashboard),
}

export default npoBoard