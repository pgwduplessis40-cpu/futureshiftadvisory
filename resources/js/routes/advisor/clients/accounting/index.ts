import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::connect
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:21
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
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:21
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
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:21
 * @route '/advisor/clients/{client}/accounting/{provider}/connect'
 */
connect.get = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: connect.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::connect
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:21
 * @route '/advisor/clients/{client}/accounting/{provider}/connect'
 */
connect.head = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: connect.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::callback
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:34
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
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:34
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
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:34
 * @route '/advisor/clients/{client}/accounting/{provider}/callback'
 */
callback.get = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::callback
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:34
 * @route '/advisor/clients/{client}/accounting/{provider}/callback'
 */
callback.head = (args: { client: string | { id: string }, provider: string | number } | [client: string | { id: string }, provider: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: callback.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::pull
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:65
 * @route '/advisor/clients/{client}/accounting/{accountingConnection}/pull'
 */
export const pull = (args: { client: string | { id: string }, accountingConnection: string | number | { id: string | number } } | [client: string | { id: string }, accountingConnection: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pull.url(args, options),
    method: 'post',
})

pull.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/accounting/{accountingConnection}/pull',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::pull
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:65
 * @route '/advisor/clients/{client}/accounting/{accountingConnection}/pull'
 */
pull.url = (args: { client: string | { id: string }, accountingConnection: string | number | { id: string | number } } | [client: string | { id: string }, accountingConnection: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
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
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:65
 * @route '/advisor/clients/{client}/accounting/{accountingConnection}/pull'
 */
pull.post = (args: { client: string | { id: string }, accountingConnection: string | number | { id: string | number } } | [client: string | { id: string }, accountingConnection: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pull.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::revoke
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:85
 * @route '/advisor/clients/{client}/accounting/{accountingConnection}/revoke'
 */
export const revoke = (args: { client: string | { id: string }, accountingConnection: string | number | { id: string | number } } | [client: string | { id: string }, accountingConnection: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: revoke.url(args, options),
    method: 'patch',
})

revoke.definition = {
    methods: ["patch"],
    url: '/advisor/clients/{client}/accounting/{accountingConnection}/revoke',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\AccountingConnectionController::revoke
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:85
 * @route '/advisor/clients/{client}/accounting/{accountingConnection}/revoke'
 */
revoke.url = (args: { client: string | { id: string }, accountingConnection: string | number | { id: string | number } } | [client: string | { id: string }, accountingConnection: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
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
 * @see app/Http/Controllers/Advisor/AccountingConnectionController.php:85
 * @route '/advisor/clients/{client}/accounting/{accountingConnection}/revoke'
 */
revoke.patch = (args: { client: string | { id: string }, accountingConnection: string | number | { id: string | number } } | [client: string | { id: string }, accountingConnection: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: revoke.url(args, options),
    method: 'patch',
})
const accounting = {
    connect: Object.assign(connect, connect),
callback: Object.assign(callback, callback),
pull: Object.assign(pull, pull),
revoke: Object.assign(revoke, revoke),
}

export default accounting