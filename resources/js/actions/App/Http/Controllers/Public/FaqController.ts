import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Public\FaqController::__invoke
 * @see app/Http/Controllers/Public/FaqController.php:14
 * @route '/faq'
 */
const FaqController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: FaqController.url(options),
    method: 'get',
})

FaqController.definition = {
    methods: ["get","head"],
    url: '/faq',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Public\FaqController::__invoke
 * @see app/Http/Controllers/Public/FaqController.php:14
 * @route '/faq'
 */
FaqController.url = (options?: RouteQueryOptions) => {
    return FaqController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Public\FaqController::__invoke
 * @see app/Http/Controllers/Public/FaqController.php:14
 * @route '/faq'
 */
FaqController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: FaqController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Public\FaqController::__invoke
 * @see app/Http/Controllers/Public/FaqController.php:14
 * @route '/faq'
 */
FaqController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: FaqController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Public\FaqController::__invoke
 * @see app/Http/Controllers/Public/FaqController.php:14
 * @route '/faq'
 */
    const FaqControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: FaqController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Public\FaqController::__invoke
 * @see app/Http/Controllers/Public/FaqController.php:14
 * @route '/faq'
 */
        FaqControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: FaqController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Public\FaqController::__invoke
 * @see app/Http/Controllers/Public/FaqController.php:14
 * @route '/faq'
 */
        FaqControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: FaqController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    FaqController.form = FaqControllerForm
export default FaqController