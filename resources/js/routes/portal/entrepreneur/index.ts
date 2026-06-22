import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
import plan from './plan'
import readiness from './readiness'
import ideaValidation from './idea-validation'
import advisoryRequest from './advisory-request'
import assessments from './assessments'
import surveys from './surveys'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:32
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
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:32
 * @route '/portal/entrepreneur'
 */
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:32
 * @route '/portal/entrepreneur'
 */
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:32
 * @route '/portal/entrepreneur'
 */
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:32
 * @route '/portal/entrepreneur'
 */
    const dashboardForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: dashboard.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:32
 * @route '/portal/entrepreneur'
 */
        dashboardForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: dashboard.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:32
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
plan: Object.assign(plan, plan),
readiness: Object.assign(readiness, readiness),
ideaValidation: Object.assign(ideaValidation, ideaValidation),
advisoryRequest: Object.assign(advisoryRequest, advisoryRequest),
assessments: Object.assign(assessments, assessments),
surveys: Object.assign(surveys, surveys),
}

export default entrepreneur