import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:165
* @route '/portal/acquisition-plan/questionnaire'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/acquisition-plan/questionnaire',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:165
* @route '/portal/acquisition-plan/questionnaire'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:165
* @route '/portal/acquisition-plan/questionnaire'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:165
* @route '/portal/acquisition-plan/questionnaire'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\DdBusinessPlanController::store
* @see app/Http/Controllers/Portal/DdBusinessPlanController.php:165
* @route '/portal/acquisition-plan/questionnaire'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

const questionnaire = {
    store: Object.assign(store, store),
}

export default questionnaire