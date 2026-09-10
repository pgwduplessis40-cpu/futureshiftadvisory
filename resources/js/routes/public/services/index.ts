import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Public\EntrepreneurServiceController::__invoke
* @see app/Http/Controllers/Public/EntrepreneurServiceController.php:21
* @route '/services/entrepreneur'
*/
export const entrepreneur = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: entrepreneur.url(options),
    method: 'get',
})

entrepreneur.definition = {
    methods: ["get","head"],
    url: '/services/entrepreneur',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Public\EntrepreneurServiceController::__invoke
* @see app/Http/Controllers/Public/EntrepreneurServiceController.php:21
* @route '/services/entrepreneur'
*/
entrepreneur.url = (options?: RouteQueryOptions) => {
    return entrepreneur.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Public\EntrepreneurServiceController::__invoke
* @see app/Http/Controllers/Public/EntrepreneurServiceController.php:21
* @route '/services/entrepreneur'
*/
entrepreneur.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: entrepreneur.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Public\EntrepreneurServiceController::__invoke
* @see app/Http/Controllers/Public/EntrepreneurServiceController.php:21
* @route '/services/entrepreneur'
*/
entrepreneur.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: entrepreneur.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Public\EntrepreneurServiceController::__invoke
* @see app/Http/Controllers/Public/EntrepreneurServiceController.php:21
* @route '/services/entrepreneur'
*/
const entrepreneurForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: entrepreneur.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Public\EntrepreneurServiceController::__invoke
* @see app/Http/Controllers/Public/EntrepreneurServiceController.php:21
* @route '/services/entrepreneur'
*/
entrepreneurForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: entrepreneur.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Public\EntrepreneurServiceController::__invoke
* @see app/Http/Controllers/Public/EntrepreneurServiceController.php:21
* @route '/services/entrepreneur'
*/
entrepreneurForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: entrepreneur.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

entrepreneur.form = entrepreneurForm

const services = {
    entrepreneur: Object.assign(entrepreneur, entrepreneur),
}

export default services
