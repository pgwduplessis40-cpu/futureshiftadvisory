import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\DeploymentController::__invoke
* @see app/Http/Controllers/DeploymentController.php:12
* @route '/api/deployment'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/deployment',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DeploymentController::__invoke
* @see app/Http/Controllers/DeploymentController.php:12
* @route '/api/deployment'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DeploymentController::__invoke
* @see app/Http/Controllers/DeploymentController.php:12
* @route '/api/deployment'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DeploymentController::__invoke
* @see app/Http/Controllers/DeploymentController.php:12
* @route '/api/deployment'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\DeploymentController::__invoke
* @see app/Http/Controllers/DeploymentController.php:12
* @route '/api/deployment'
*/
const showForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DeploymentController::__invoke
* @see app/Http/Controllers/DeploymentController.php:12
* @route '/api/deployment'
*/
showForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DeploymentController::__invoke
* @see app/Http/Controllers/DeploymentController.php:12
* @route '/api/deployment'
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

const deployment = {
    show: Object.assign(show, show),
}

export default deployment