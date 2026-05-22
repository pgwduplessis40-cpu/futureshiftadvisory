import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import entrepreneur from './entrepreneur'
import documents from './documents'
import messages from './messages'
import wellbeing from './wellbeing'
import onboarding from './onboarding'
/**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:33
 * @route '/portal'
 */
export const dashboard = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/portal',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:33
 * @route '/portal'
 */
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:33
 * @route '/portal'
 */
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:33
 * @route '/portal'
 */
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:33
 * @route '/portal'
 */
    const dashboardForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: dashboard.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:33
 * @route '/portal'
 */
        dashboardForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: dashboard.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:33
 * @route '/portal'
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
const portal = {
    dashboard: Object.assign(dashboard, dashboard),
entrepreneur: Object.assign(entrepreneur, entrepreneur),
documents: Object.assign(documents, documents),
messages: Object.assign(messages, messages),
wellbeing: Object.assign(wellbeing, wellbeing),
onboarding: Object.assign(onboarding, onboarding),
}

export default portal