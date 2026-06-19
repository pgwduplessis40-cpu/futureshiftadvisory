import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\MfaSetupController::show
 * @see app/Http/Controllers/Auth/MfaSetupController.php:19
 * @route '/mfa/setup'
 */
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/mfa/setup',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\MfaSetupController::show
 * @see app/Http/Controllers/Auth/MfaSetupController.php:19
 * @route '/mfa/setup'
 */
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\MfaSetupController::show
 * @see app/Http/Controllers/Auth/MfaSetupController.php:19
 * @route '/mfa/setup'
 */
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Auth\MfaSetupController::show
 * @see app/Http/Controllers/Auth/MfaSetupController.php:19
 * @route '/mfa/setup'
 */
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Auth\MfaSetupController::show
 * @see app/Http/Controllers/Auth/MfaSetupController.php:19
 * @route '/mfa/setup'
 */
    const showForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Auth\MfaSetupController::show
 * @see app/Http/Controllers/Auth/MfaSetupController.php:19
 * @route '/mfa/setup'
 */
        showForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Auth\MfaSetupController::show
 * @see app/Http/Controllers/Auth/MfaSetupController.php:19
 * @route '/mfa/setup'
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
const MfaSetupController = { show }

export default MfaSetupController