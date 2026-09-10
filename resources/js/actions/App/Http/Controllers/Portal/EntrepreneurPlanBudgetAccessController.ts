import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetAccessController::__invoke
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetAccessController.php:15
* @route '/portal/entrepreneur/plan-budget'
*/
const EntrepreneurPlanBudgetAccessController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EntrepreneurPlanBudgetAccessController.url(options),
    method: 'get',
})

EntrepreneurPlanBudgetAccessController.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur/plan-budget',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetAccessController::__invoke
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetAccessController.php:15
* @route '/portal/entrepreneur/plan-budget'
*/
EntrepreneurPlanBudgetAccessController.url = (options?: RouteQueryOptions) => {
    return EntrepreneurPlanBudgetAccessController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetAccessController::__invoke
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetAccessController.php:15
* @route '/portal/entrepreneur/plan-budget'
*/
EntrepreneurPlanBudgetAccessController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EntrepreneurPlanBudgetAccessController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetAccessController::__invoke
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetAccessController.php:15
* @route '/portal/entrepreneur/plan-budget'
*/
EntrepreneurPlanBudgetAccessController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: EntrepreneurPlanBudgetAccessController.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetAccessController::__invoke
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetAccessController.php:15
* @route '/portal/entrepreneur/plan-budget'
*/
const EntrepreneurPlanBudgetAccessControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: EntrepreneurPlanBudgetAccessController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetAccessController::__invoke
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetAccessController.php:15
* @route '/portal/entrepreneur/plan-budget'
*/
EntrepreneurPlanBudgetAccessControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: EntrepreneurPlanBudgetAccessController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanBudgetAccessController::__invoke
* @see app/Http/Controllers/Portal/EntrepreneurPlanBudgetAccessController.php:15
* @route '/portal/entrepreneur/plan-budget'
*/
EntrepreneurPlanBudgetAccessControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: EntrepreneurPlanBudgetAccessController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

EntrepreneurPlanBudgetAccessController.form = EntrepreneurPlanBudgetAccessControllerForm

export default EntrepreneurPlanBudgetAccessController
