import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::store
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:197
* @route '/portal/business-plan-budget/quote-request'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/business-plan-budget/quote-request',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::store
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:197
* @route '/portal/business-plan-budget/quote-request'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::store
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:197
* @route '/portal/business-plan-budget/quote-request'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::store
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:197
* @route '/portal/business-plan-budget/quote-request'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\StrategicBudgetController::store
* @see app/Http/Controllers/Portal/StrategicBudgetController.php:197
* @route '/portal/business-plan-budget/quote-request'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

const quote = {
    store: Object.assign(store, store),
}

export default quote
