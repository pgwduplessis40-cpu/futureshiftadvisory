import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\MfaChallengeController::store
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:35
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
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:35
 * @route '/mfa/challenge'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\MfaChallengeController::store
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:35
 * @route '/mfa/challenge'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Auth\MfaChallengeController::store
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:35
 * @route '/mfa/challenge'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Auth\MfaChallengeController::store
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:35
 * @route '/mfa/challenge'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
const challenge = {
    store: Object.assign(store, store),
}

export default challenge