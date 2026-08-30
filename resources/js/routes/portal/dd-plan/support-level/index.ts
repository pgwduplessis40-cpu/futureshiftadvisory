import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:143
* @route '/portal/acquisition-plan/support-level'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/acquisition-plan/support-level',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:143
* @route '/portal/acquisition-plan/support-level'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:143
* @route '/portal/acquisition-plan/support-level'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:143
* @route '/portal/acquisition-plan/support-level'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:143
* @route '/portal/acquisition-plan/support-level'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

const supportLevel = {
    store: Object.assign(store, store),
}

export default supportLevel
