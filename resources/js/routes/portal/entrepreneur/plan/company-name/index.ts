import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::update
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:324
* @route '/portal/entrepreneur/plan/company-name'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(options),
    method: 'post',
})

update.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/company-name',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::update
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:324
* @route '/portal/entrepreneur/plan/company-name'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::update
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:324
* @route '/portal/entrepreneur/plan/company-name'
*/
update.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::update
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:324
* @route '/portal/entrepreneur/plan/company-name'
*/
const updateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::update
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:324
* @route '/portal/entrepreneur/plan/company-name'
*/
updateForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(options),
    method: 'post',
})

update.form = updateForm

const companyName = {
    update: Object.assign(update, update),
}

export default companyName