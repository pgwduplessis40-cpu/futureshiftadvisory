import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::__invoke
 * @see app/Http/Controllers/Auth/TermsPendingController.php:13
 * @route '/terms/pending'
 */
export const pending = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pending.url(options),
    method: 'get',
})

pending.definition = {
    methods: ["get","head"],
    url: '/terms/pending',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::__invoke
 * @see app/Http/Controllers/Auth/TermsPendingController.php:13
 * @route '/terms/pending'
 */
pending.url = (options?: RouteQueryOptions) => {
    return pending.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\TermsPendingController::__invoke
 * @see app/Http/Controllers/Auth/TermsPendingController.php:13
 * @route '/terms/pending'
 */
pending.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pending.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Auth\TermsPendingController::__invoke
 * @see app/Http/Controllers/Auth/TermsPendingController.php:13
 * @route '/terms/pending'
 */
pending.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pending.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Auth\TermsPendingController::__invoke
 * @see app/Http/Controllers/Auth/TermsPendingController.php:13
 * @route '/terms/pending'
 */
    const pendingForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: pending.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::__invoke
 * @see app/Http/Controllers/Auth/TermsPendingController.php:13
 * @route '/terms/pending'
 */
        pendingForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pending.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Auth\TermsPendingController::__invoke
 * @see app/Http/Controllers/Auth/TermsPendingController.php:13
 * @route '/terms/pending'
 */
        pendingForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pending.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    pending.form = pendingForm
const terms = {
    pending: Object.assign(pending, pending),
}

export default terms