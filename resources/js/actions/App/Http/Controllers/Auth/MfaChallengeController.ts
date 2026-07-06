import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\MfaChallengeController::show
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:26
 * @route '/mfa/challenge'
 */
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/mfa/challenge',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\MfaChallengeController::show
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:26
 * @route '/mfa/challenge'
 */
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\MfaChallengeController::show
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:26
 * @route '/mfa/challenge'
 */
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Auth\MfaChallengeController::show
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:26
 * @route '/mfa/challenge'
 */
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Auth\MfaChallengeController::show
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:26
 * @route '/mfa/challenge'
 */
    const showForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Auth\MfaChallengeController::show
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:26
 * @route '/mfa/challenge'
 */
        showForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Auth\MfaChallengeController::show
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:26
 * @route '/mfa/challenge'
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
/**
* @see \App\Http\Controllers\Auth\MfaChallengeController::store
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:44
 * @route '/mfa/challenge'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/mfa/challenge',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\MfaChallengeController::store
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:44
 * @route '/mfa/challenge'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\MfaChallengeController::store
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:44
 * @route '/mfa/challenge'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Auth\MfaChallengeController::store
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:44
 * @route '/mfa/challenge'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Auth\MfaChallengeController::store
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:44
 * @route '/mfa/challenge'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const MfaChallengeController = { show, store }

export default MfaChallengeController