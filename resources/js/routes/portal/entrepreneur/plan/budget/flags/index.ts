import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::acknowledge
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:382
 * @route '/portal/entrepreneur/plan/budget/flags/acknowledge'
 */
export const acknowledge = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: acknowledge.url(options),
    method: 'post',
})

acknowledge.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/budget/flags/acknowledge',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::acknowledge
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:382
 * @route '/portal/entrepreneur/plan/budget/flags/acknowledge'
 */
acknowledge.url = (options?: RouteQueryOptions) => {
    return acknowledge.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::acknowledge
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:382
 * @route '/portal/entrepreneur/plan/budget/flags/acknowledge'
 */
acknowledge.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: acknowledge.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::acknowledge
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:382
 * @route '/portal/entrepreneur/plan/budget/flags/acknowledge'
 */
    const acknowledgeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: acknowledge.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::acknowledge
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:382
 * @route '/portal/entrepreneur/plan/budget/flags/acknowledge'
 */
        acknowledgeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: acknowledge.url(options),
            method: 'post',
        })

    acknowledge.form = acknowledgeForm
const flags = {
    acknowledge: Object.assign(acknowledge, acknowledge),
}

export default flags