import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Public\EntrepreneurServiceController::__invoke
* @see app/Http/Controllers/Public/EntrepreneurServiceController.php:21
* @route '/services/entrepreneur'
*/
const EntrepreneurServiceController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EntrepreneurServiceController.url(options),
    method: 'get',
})

EntrepreneurServiceController.definition = {
    methods: ["get","head"],
    url: '/services/entrepreneur',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Public\EntrepreneurServiceController::__invoke
* @see app/Http/Controllers/Public/EntrepreneurServiceController.php:21
* @route '/services/entrepreneur'
*/
EntrepreneurServiceController.url = (options?: RouteQueryOptions) => {
    return EntrepreneurServiceController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Public\EntrepreneurServiceController::__invoke
* @see app/Http/Controllers/Public/EntrepreneurServiceController.php:21
* @route '/services/entrepreneur'
*/
EntrepreneurServiceController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EntrepreneurServiceController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Public\EntrepreneurServiceController::__invoke
* @see app/Http/Controllers/Public/EntrepreneurServiceController.php:21
* @route '/services/entrepreneur'
*/
EntrepreneurServiceController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: EntrepreneurServiceController.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Public\EntrepreneurServiceController::__invoke
* @see app/Http/Controllers/Public/EntrepreneurServiceController.php:21
* @route '/services/entrepreneur'
*/
const EntrepreneurServiceControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: EntrepreneurServiceController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Public\EntrepreneurServiceController::__invoke
* @see app/Http/Controllers/Public/EntrepreneurServiceController.php:21
* @route '/services/entrepreneur'
*/
EntrepreneurServiceControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: EntrepreneurServiceController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Public\EntrepreneurServiceController::__invoke
* @see app/Http/Controllers/Public/EntrepreneurServiceController.php:21
* @route '/services/entrepreneur'
*/
EntrepreneurServiceControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: EntrepreneurServiceController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

EntrepreneurServiceController.form = EntrepreneurServiceControllerForm

export default EntrepreneurServiceController
