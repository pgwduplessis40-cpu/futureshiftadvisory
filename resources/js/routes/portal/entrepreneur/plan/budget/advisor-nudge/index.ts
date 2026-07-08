import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::dismiss
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:433
 * @route '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss'
 */
export const dismiss = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: dismiss.url(options),
    method: 'post',
})

dismiss.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::dismiss
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:433
 * @route '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss'
 */
dismiss.url = (options?: RouteQueryOptions) => {
    return dismiss.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::dismiss
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:433
 * @route '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss'
 */
dismiss.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: dismiss.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::dismiss
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:433
 * @route '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss'
 */
    const dismissForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: dismiss.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::dismiss
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:433
 * @route '/portal/entrepreneur/plan/budget/advisor-nudge/dismiss'
 */
        dismissForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: dismiss.url(options),
            method: 'post',
        })
    
    dismiss.form = dismissForm
const advisorNudge = {
    dismiss: Object.assign(dismiss, dismiss),
}

export default advisorNudge