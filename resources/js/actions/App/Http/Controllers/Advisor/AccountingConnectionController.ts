import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::connect
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:23
 * @route '/advisor/clients/{client}/accounting/{provider}/connect'
 */
export const connect = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: connect.url(args, options),
    method: 'get',
})

connect.definition = {
    methods: ["get","head"],
    url: '/advisor/clients/{client}/accounting/{provider}/connect',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::connect
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:23
 * @route '/advisor/clients/{client}/accounting/{provider}/connect'
 */
connect.url = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                    provider: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                                provider: args.provider,
                }

    return connect.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace('{provider}', parsedArgs.provider.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::connect
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:23
 * @route '/advisor/clients/{client}/accounting/{provider}/connect'
 */
connect.get = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: connect.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::connect
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:23
 * @route '/advisor/clients/{client}/accounting/{provider}/connect'
 */
connect.head = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: connect.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::connect
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:23
 * @route '/advisor/clients/{client}/accounting/{provider}/connect'
 */
    const connectForm = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: connect.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::connect
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:23
 * @route '/advisor/clients/{client}/accounting/{provider}/connect'
 */
        connectForm.get = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: connect.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::connect
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:23
 * @route '/advisor/clients/{client}/accounting/{provider}/connect'
 */
        connectForm.head = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::callbackFromState
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:83
 * @route '/advisor/accounting/{provider}/callback'
 */
export const callbackFromState = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callbackFromState.url(args, options),
    method: 'get',
})

callbackFromState.definition = {
    methods: ["get","head"],
    url: '/advisor/accounting/{provider}/callback',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::callbackFromState
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:83
 * @route '/advisor/accounting/{provider}/callback'
 */
callbackFromState.url = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return callbackFromState.definition.url
            .replace('{provider}', parsedArgs.provider.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::callbackFromState
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:83
 * @route '/advisor/accounting/{provider}/callback'
 */
callbackFromState.get = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callbackFromState.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::callbackFromState
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:83
 * @route '/advisor/accounting/{provider}/callback'
 */
callbackFromState.head = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: callbackFromState.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::callbackFromState
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:83
 * @route '/advisor/accounting/{provider}/callback'
 */
    const callbackFromStateForm = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: callbackFromState.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::callbackFromState
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:83
 * @route '/advisor/accounting/{provider}/callback'
 */
        callbackFromStateForm.get = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: callbackFromState.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::callbackFromState
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:83
 * @route '/advisor/accounting/{provider}/callback'
 */
        callbackFromStateForm.head = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: callbackFromState.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    callbackFromState.form = callbackFromStateForm
/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::callback
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:41
 * @route '/advisor/clients/{client}/accounting/{provider}/callback'
 */
export const callback = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(args, options),
    method: 'get',
})

callback.definition = {
    methods: ["get","head"],
    url: '/advisor/clients/{client}/accounting/{provider}/callback',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::callback
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:41
 * @route '/advisor/clients/{client}/accounting/{provider}/callback'
 */
callback.url = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                    provider: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                                provider: args.provider,
                }

    return callback.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace('{provider}', parsedArgs.provider.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::callback
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:41
 * @route '/advisor/clients/{client}/accounting/{provider}/callback'
 */
callback.get = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::callback
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:41
 * @route '/advisor/clients/{client}/accounting/{provider}/callback'
 */
callback.head = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: callback.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::callback
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:41
 * @route '/advisor/clients/{client}/accounting/{provider}/callback'
 */
    const callbackForm = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: callback.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::callback
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:41
 * @route '/advisor/clients/{client}/accounting/{provider}/callback'
 */
        callbackForm.get = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: callback.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::callback
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:41
 * @route '/advisor/clients/{client}/accounting/{provider}/callback'
 */
        callbackForm.head = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::pull
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:131
 * @route '/advisor/clients/{client}/accounting/{accountingConnection}/pull'
 */
export const pull = (args: { client: string | { id: string }, accountingConnection: string | { id: string } } | [client: string | { id: string }, accountingConnection: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pull.url(args, options),
    method: 'post',
})

pull.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/accounting/{accountingConnection}/pull',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::pull
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:131
 * @route '/advisor/clients/{client}/accounting/{accountingConnection}/pull'
 */
pull.url = (args: { client: string | { id: string }, accountingConnection: string | { id: string } } | [client: string | { id: string }, accountingConnection: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                    accountingConnection: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                                accountingConnection: typeof args.accountingConnection === 'object'
                ? args.accountingConnection.id
                : args.accountingConnection,
                }

    return pull.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace('{accountingConnection}', parsedArgs.accountingConnection.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::pull
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:131
 * @route '/advisor/clients/{client}/accounting/{accountingConnection}/pull'
 */
pull.post = (args: { client: string | { id: string }, accountingConnection: string | { id: string } } | [client: string | { id: string }, accountingConnection: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pull.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::pull
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:131
 * @route '/advisor/clients/{client}/accounting/{accountingConnection}/pull'
 */
    const pullForm = (args: { client: string | { id: string }, accountingConnection: string | { id: string } } | [client: string | { id: string }, accountingConnection: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: pull.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::pull
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:131
 * @route '/advisor/clients/{client}/accounting/{accountingConnection}/pull'
 */
        pullForm.post = (args: { client: string | { id: string }, accountingConnection: string | { id: string } } | [client: string | { id: string }, accountingConnection: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: pull.url(args, options),
            method: 'post',
        })
    
    pull.form = pullForm
/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::revoke
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:169
 * @route '/advisor/clients/{client}/accounting/{accountingConnection}/revoke'
 */
export const revoke = (args: { client: string | { id: string }, accountingConnection: string | { id: string } } | [client: string | { id: string }, accountingConnection: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: revoke.url(args, options),
    method: 'patch',
})

revoke.definition = {
    methods: ["patch"],
    url: '/advisor/clients/{client}/accounting/{accountingConnection}/revoke',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::revoke
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:169
 * @route '/advisor/clients/{client}/accounting/{accountingConnection}/revoke'
 */
revoke.url = (args: { client: string | { id: string }, accountingConnection: string | { id: string } } | [client: string | { id: string }, accountingConnection: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                    accountingConnection: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                                accountingConnection: typeof args.accountingConnection === 'object'
                ? args.accountingConnection.id
                : args.accountingConnection,
                }

    return revoke.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace('{accountingConnection}', parsedArgs.accountingConnection.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::revoke
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:169
 * @route '/advisor/clients/{client}/accounting/{accountingConnection}/revoke'
 */
revoke.patch = (args: { client: string | { id: string }, accountingConnection: string | { id: string } } | [client: string | { id: string }, accountingConnection: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: revoke.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::revoke
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:169
 * @route '/advisor/clients/{client}/accounting/{accountingConnection}/revoke'
 */
    const revokeForm = (args: { client: string | { id: string }, accountingConnection: string | { id: string } } | [client: string | { id: string }, accountingConnection: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: revoke.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::revoke
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:169
 * @route '/advisor/clients/{client}/accounting/{accountingConnection}/revoke'
 */
        revokeForm.patch = (args: { client: string | { id: string }, accountingConnection: string | { id: string } } | [client: string | { id: string }, accountingConnection: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: revoke.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    revoke.form = revokeForm
const AccountingConnectionController = { connect, callbackFromState, callback, pull, revoke }

export default AccountingConnectionController