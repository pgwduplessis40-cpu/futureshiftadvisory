import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanWorkspaceController::show
* @see app/Http/Controllers/Portal/EntrepreneurPlanWorkspaceController.php:19
* @route '/portal/entrepreneur/plan'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur/plan',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanWorkspaceController::show
* @see app/Http/Controllers/Portal/EntrepreneurPlanWorkspaceController.php:19
* @route '/portal/entrepreneur/plan'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanWorkspaceController::show
* @see app/Http/Controllers/Portal/EntrepreneurPlanWorkspaceController.php:19
* @route '/portal/entrepreneur/plan'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanWorkspaceController::show
* @see app/Http/Controllers/Portal/EntrepreneurPlanWorkspaceController.php:19
* @route '/portal/entrepreneur/plan'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanWorkspaceController::show
* @see app/Http/Controllers/Portal/EntrepreneurPlanWorkspaceController.php:19
* @route '/portal/entrepreneur/plan'
*/
const showForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanWorkspaceController::show
* @see app/Http/Controllers/Portal/EntrepreneurPlanWorkspaceController.php:19
* @route '/portal/entrepreneur/plan'
*/
showForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurPlanWorkspaceController::show
* @see app/Http/Controllers/Portal/EntrepreneurPlanWorkspaceController.php:19
* @route '/portal/entrepreneur/plan'
*/
showForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

const EntrepreneurPlanWorkspaceController = { show }

export default EntrepreneurPlanWorkspaceController
