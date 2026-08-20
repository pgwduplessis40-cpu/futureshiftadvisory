import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Public\LlmsTxtController::__invoke
* @see app/Http/Controllers/Public/LlmsTxtController.php:19
* @route '/llms.txt'
*/
const LlmsTxtController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: LlmsTxtController.url(options),
    method: 'get',
})

LlmsTxtController.definition = {
    methods: ["get","head"],
    url: '/llms.txt',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Public\LlmsTxtController::__invoke
* @see app/Http/Controllers/Public/LlmsTxtController.php:19
* @route '/llms.txt'
*/
LlmsTxtController.url = (options?: RouteQueryOptions) => {
    return LlmsTxtController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Public\LlmsTxtController::__invoke
* @see app/Http/Controllers/Public/LlmsTxtController.php:19
* @route '/llms.txt'
*/
LlmsTxtController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: LlmsTxtController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Public\LlmsTxtController::__invoke
* @see app/Http/Controllers/Public/LlmsTxtController.php:19
* @route '/llms.txt'
*/
LlmsTxtController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: LlmsTxtController.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Public\LlmsTxtController::__invoke
* @see app/Http/Controllers/Public/LlmsTxtController.php:19
* @route '/llms.txt'
*/
const LlmsTxtControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: LlmsTxtController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Public\LlmsTxtController::__invoke
* @see app/Http/Controllers/Public/LlmsTxtController.php:19
* @route '/llms.txt'
*/
LlmsTxtControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: LlmsTxtController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Public\LlmsTxtController::__invoke
* @see app/Http/Controllers/Public/LlmsTxtController.php:19
* @route '/llms.txt'
*/
LlmsTxtControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: LlmsTxtController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

LlmsTxtController.form = LlmsTxtControllerForm
export default LlmsTxtController