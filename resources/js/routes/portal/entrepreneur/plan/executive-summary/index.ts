import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:605
* @route '/portal/entrepreneur/plan/executive-summary'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/plan/executive-summary',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:605
* @route '/portal/entrepreneur/plan/executive-summary'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:605
* @route '/portal/entrepreneur/plan/executive-summary'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:605
* @route '/portal/entrepreneur/plan/executive-summary'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanController::store
* @see app/Http/Controllers/Portal/EntrepreneurPlanController.php:605
* @route '/portal/entrepreneur/plan/executive-summary'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

const executiveSummary = {
    store: Object.assign(store, store),
}

export default executiveSummary
