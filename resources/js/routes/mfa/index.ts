import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import challengeF9272e from './challenge'
/**
* @see \App\Http\Controllers\Auth\MfaSetupController::setup
 * @see app/Http/Controllers/Auth/MfaSetupController.php:19
 * @route '/mfa/setup'
 */
export const setup = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: setup.url(options),
    method: 'get',
})

setup.definition = {
    methods: ["get","head"],
    url: '/mfa/setup',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\MfaSetupController::setup
 * @see app/Http/Controllers/Auth/MfaSetupController.php:19
 * @route '/mfa/setup'
 */
setup.url = (options?: RouteQueryOptions) => {
    return setup.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\MfaSetupController::setup
 * @see app/Http/Controllers/Auth/MfaSetupController.php:19
 * @route '/mfa/setup'
 */
setup.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: setup.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Auth\MfaSetupController::setup
 * @see app/Http/Controllers/Auth/MfaSetupController.php:19
 * @route '/mfa/setup'
 */
setup.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: setup.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Auth\MfaSetupController::setup
 * @see app/Http/Controllers/Auth/MfaSetupController.php:19
 * @route '/mfa/setup'
 */
    const setupForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: setup.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Auth\MfaSetupController::setup
 * @see app/Http/Controllers/Auth/MfaSetupController.php:19
 * @route '/mfa/setup'
 */
        setupForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: setup.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Auth\MfaSetupController::setup
 * @see app/Http/Controllers/Auth/MfaSetupController.php:19
 * @route '/mfa/setup'
 */
        setupForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: setup.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    setup.form = setupForm
/**
* @see \App\Http\Controllers\Auth\MfaChallengeController::challenge
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:26
 * @route '/mfa/challenge'
 */
export const challenge = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: challenge.url(options),
    method: 'get',
})

challenge.definition = {
    methods: ["get","head"],
    url: '/mfa/challenge',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\MfaChallengeController::challenge
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:26
 * @route '/mfa/challenge'
 */
challenge.url = (options?: RouteQueryOptions) => {
    return challenge.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\MfaChallengeController::challenge
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:26
 * @route '/mfa/challenge'
 */
challenge.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: challenge.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Auth\MfaChallengeController::challenge
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:26
 * @route '/mfa/challenge'
 */
challenge.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: challenge.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Auth\MfaChallengeController::challenge
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:26
 * @route '/mfa/challenge'
 */
    const challengeForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: challenge.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Auth\MfaChallengeController::challenge
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:26
 * @route '/mfa/challenge'
 */
        challengeForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: challenge.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Auth\MfaChallengeController::challenge
 * @see app/Http/Controllers/Auth/MfaChallengeController.php:26
 * @route '/mfa/challenge'
 */
        challengeForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: challenge.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    challenge.form = challengeForm
const mfa = {
    setup: Object.assign(setup, setup),
challenge: Object.assign(challenge, challengeF9272e),
}

export default mfa