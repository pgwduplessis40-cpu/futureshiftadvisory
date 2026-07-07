import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::assist
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:448
 * @route '/portal/entrepreneur/plan/requirements/assist'
 */
export const assist = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: assist.url(options),
    method: 'post',
})

assist.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/requirements/assist',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::assist
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:448
 * @route '/portal/entrepreneur/plan/requirements/assist'
 */
assist.url = (options?: RouteQueryOptions) => {
    return assist.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::assist
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:448
 * @route '/portal/entrepreneur/plan/requirements/assist'
 */
assist.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: assist.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::assist
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:448
 * @route '/portal/entrepreneur/plan/requirements/assist'
 */
    const assistForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: assist.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::assist
 * @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:448
 * @route '/portal/entrepreneur/plan/requirements/assist'
 */
        assistForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: assist.url(options),
            method: 'post',
        })
    
    assist.form = assistForm
const requirements = {
    assist: Object.assign(assist, assist),
}

export default requirements