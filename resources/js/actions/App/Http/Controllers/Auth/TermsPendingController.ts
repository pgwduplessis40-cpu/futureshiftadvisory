import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::__invoke
 * @see app/Http/Controllers/Auth/TermsPendingController.php:13
 * @route '/terms/pending'
 */
const TermsPendingController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: TermsPendingController.url(options),
    method: 'get',
})

TermsPendingController.definition = {
    methods: ["get","head"],
    url: '/terms/pending',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::__invoke
 * @see app/Http/Controllers/Auth/TermsPendingController.php:13
 * @route '/terms/pending'
 */
TermsPendingController.url = (options?: RouteQueryOptions) => {
    return TermsPendingController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::__invoke
 * @see app/Http/Controllers/Auth/TermsPendingController.php:13
 * @route '/terms/pending'
 */
TermsPendingController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: TermsPendingController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::__invoke
 * @see app/Http/Controllers/Auth/TermsPendingController.php:13
 * @route '/terms/pending'
 */
TermsPendingController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: TermsPendingController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Auth\TermsPendingController::__invoke
 * @see app/Http/Controllers/Auth/TermsPendingController.php:13
 * @route '/terms/pending'
 */
    const TermsPendingControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: TermsPendingController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::__invoke
 * @see app/Http/Controllers/Auth/TermsPendingController.php:13
 * @route '/terms/pending'
 */
        TermsPendingControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: TermsPendingController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::__invoke
 * @see app/Http/Controllers/Auth/TermsPendingController.php:13
 * @route '/terms/pending'
 */
        TermsPendingControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: TermsPendingController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    TermsPendingController.form = TermsPendingControllerForm
export default TermsPendingController