import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import businessPlanBudget from './business-plan-budget'
import strategicPlan from './strategic-plan'
import serviceActivations from './service-activations'
import npoBoard from './npo-board'
import calendar from './calendar'
import ddPlan from './dd-plan'
import entrepreneur from './entrepreneur'
import documents from './documents'
import reports from './reports'
import npoImpactMetrics from './npo-impact-metrics'
import inspirationBoard from './inspiration-board'
import messages from './messages'
import proposals from './proposals'
import wellbeing from './wellbeing'
import surveys from './surveys'
import onboarding from './onboarding'
/**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:76
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
 * @see app/Http/Controllers/Portal/DashboardController.php:76
 * @route '/portal'
 */
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:76
 * @route '/portal'
 */
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:76
 * @route '/portal'
 */
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:76
 * @route '/portal'
 */
    const dashboardForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: dashboard.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:76
 * @route '/portal'
 */
        dashboardForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: dashboard.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\DashboardController::__invoke
 * @see app/Http/Controllers/Portal/DashboardController.php:76
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
businessPlanBudget: Object.assign(businessPlanBudget, businessPlanBudget),
strategicPlan: Object.assign(strategicPlan, strategicPlan),
serviceActivations: Object.assign(serviceActivations, serviceActivations),
npoBoard: Object.assign(npoBoard, npoBoard),
calendar: Object.assign(calendar, calendar),
ddPlan: Object.assign(ddPlan, ddPlan),
entrepreneur: Object.assign(entrepreneur, entrepreneur),
documents: Object.assign(documents, documents),
reports: Object.assign(reports, reports),
npoImpactMetrics: Object.assign(npoImpactMetrics, npoImpactMetrics),
inspirationBoard: Object.assign(inspirationBoard, inspirationBoard),
messages: Object.assign(messages, messages),
proposals: Object.assign(proposals, proposals),
wellbeing: Object.assign(wellbeing, wellbeing),
surveys: Object.assign(surveys, surveys),
onboarding: Object.assign(onboarding, onboarding),
}

export default portal