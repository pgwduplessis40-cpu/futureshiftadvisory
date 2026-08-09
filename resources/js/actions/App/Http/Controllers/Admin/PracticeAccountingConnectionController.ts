import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::connect
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:20
 * @route '/admin/practice-accounting/{provider}/connect'
 */
export const connect = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: connect.url(args, options),
    method: 'get',
})

connect.definition = {
    methods: ["get","head"],
    url: '/admin/practice-accounting/{provider}/connect',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::connect
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:20
 * @route '/admin/practice-accounting/{provider}/connect'
 */
connect.url = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { provider: args }
    }


    if (Array.isArray(args)) {
        args = {
                    provider: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        provider: args.provider,
                }

    return connect.definition.url
            .replace('{provider}', parsedArgs.provider.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::connect
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:20
 * @route '/admin/practice-accounting/{provider}/connect'
 */
connect.get = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: connect.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::connect
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:20
 * @route '/admin/practice-accounting/{provider}/connect'
 */
connect.head = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: connect.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::connect
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:20
 * @route '/admin/practice-accounting/{provider}/connect'
 */
    const connectForm = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: connect.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::connect
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:20
 * @route '/admin/practice-accounting/{provider}/connect'
 */
        connectForm.get = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: connect.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::connect
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:20
 * @route '/admin/practice-accounting/{provider}/connect'
 */
        connectForm.head = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: connect.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    connect.form = connectForm
/**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::callback
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:35
 * @route '/admin/practice-accounting/{provider}/callback'
 */
export const callback = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(args, options),
    method: 'get',
})

callback.definition = {
    methods: ["get","head"],
    url: '/admin/practice-accounting/{provider}/callback',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::callback
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:35
 * @route '/admin/practice-accounting/{provider}/callback'
 */
callback.url = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { provider: args }
    }


    if (Array.isArray(args)) {
        args = {
                    provider: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        provider: args.provider,
                }

    return callback.definition.url
            .replace('{provider}', parsedArgs.provider.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::callback
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:35
 * @route '/admin/practice-accounting/{provider}/callback'
 */
callback.get = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::callback
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:35
 * @route '/admin/practice-accounting/{provider}/callback'
 */
callback.head = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: callback.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::callback
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:35
 * @route '/admin/practice-accounting/{provider}/callback'
 */
    const callbackForm = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: callback.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::callback
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:35
 * @route '/admin/practice-accounting/{provider}/callback'
 */
        callbackForm.get = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: callback.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::callback
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:35
 * @route '/admin/practice-accounting/{provider}/callback'
 */
        callbackForm.head = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: callback.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    callback.form = callbackForm
/**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::revoke
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:73
 * @route '/admin/practice-accounting/{practiceAccountingConnection}/revoke'
 */
export const revoke = (args: { practiceAccountingConnection: string | { id: string } } | [practiceAccountingConnection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: revoke.url(args, options),
    method: 'patch',
})

revoke.definition = {
    methods: ["patch"],
    url: '/admin/practice-accounting/{practiceAccountingConnection}/revoke',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::revoke
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:73
 * @route '/admin/practice-accounting/{practiceAccountingConnection}/revoke'
 */
revoke.url = (args: { practiceAccountingConnection: string | { id: string } } | [practiceAccountingConnection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { practiceAccountingConnection: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { practiceAccountingConnection: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    practiceAccountingConnection: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        practiceAccountingConnection: typeof args.practiceAccountingConnection === 'object'
                ? args.practiceAccountingConnection.id
                : args.practiceAccountingConnection,
                }

    return revoke.definition.url
            .replace('{practiceAccountingConnection}', parsedArgs.practiceAccountingConnection.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::revoke
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:73
 * @route '/admin/practice-accounting/{practiceAccountingConnection}/revoke'
 */
revoke.patch = (args: { practiceAccountingConnection: string | { id: string } } | [practiceAccountingConnection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: revoke.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::revoke
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:73
 * @route '/admin/practice-accounting/{practiceAccountingConnection}/revoke'
 */
    const revokeForm = (args: { practiceAccountingConnection: string | { id: string } } | [practiceAccountingConnection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: revoke.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\PracticeAccountingConnectionController::revoke
 * @see app/Http/Controllers/Admin/PracticeAccountingConnectionController.php:73
 * @route '/admin/practice-accounting/{practiceAccountingConnection}/revoke'
 */
        revokeForm.patch = (args: { practiceAccountingConnection: string | { id: string } } | [practiceAccountingConnection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: revoke.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    revoke.form = revokeForm
const PracticeAccountingConnectionController = { connect, callback, revoke }

export default PracticeAccountingConnectionController