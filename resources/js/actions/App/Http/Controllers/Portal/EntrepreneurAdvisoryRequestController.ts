import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurAdvisoryRequestController::requestAdvisory
* @see app/Http/Controllers/Portal/EntrepreneurAdvisoryRequestController.php:26
* @route '/portal/entrepreneur/advisory-request'
*/
export const requestAdvisory = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: requestAdvisory.url(options),
    method: 'post',
})

requestAdvisory.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/advisory-request',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurAdvisoryRequestController::requestAdvisory
* @see app/Http/Controllers/Portal/EntrepreneurAdvisoryRequestController.php:26
* @route '/portal/entrepreneur/advisory-request'
*/
requestAdvisory.url = (options?: RouteQueryOptions) => {
    return requestAdvisory.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurAdvisoryRequestController::requestAdvisory
* @see app/Http/Controllers/Portal/EntrepreneurAdvisoryRequestController.php:26
* @route '/portal/entrepreneur/advisory-request'
*/
requestAdvisory.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: requestAdvisory.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurAdvisoryRequestController::requestAdvisory
* @see app/Http/Controllers/Portal/EntrepreneurAdvisoryRequestController.php:26
* @route '/portal/entrepreneur/advisory-request'
*/
const requestAdvisoryForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: requestAdvisory.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurAdvisoryRequestController::requestAdvisory
* @see app/Http/Controllers/Portal/EntrepreneurAdvisoryRequestController.php:26
* @route '/portal/entrepreneur/advisory-request'
*/
requestAdvisoryForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: requestAdvisory.url(options),
    method: 'post',
})

requestAdvisory.form = requestAdvisoryForm

const EntrepreneurAdvisoryRequestController = { requestAdvisory }

export default EntrepreneurAdvisoryRequestController
