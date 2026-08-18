import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:266
 * @route '/advisor/clients/invite'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/clients/invite',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:266
 * @route '/advisor/clients/invite'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:266
 * @route '/advisor/clients/invite'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:266
 * @route '/advisor/clients/invite'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:266
 * @route '/advisor/clients/invite'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Advisor\ClientController::resend
 * @see app/Http/Controllers/Advisor/ClientController.php:322
 * @route '/advisor/clients/{client}/invite/resend'
 */
export const resend = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(args, options),
    method: 'post',
})

resend.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/invite/resend',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ClientController::resend
 * @see app/Http/Controllers/Advisor/ClientController.php:322
 * @route '/advisor/clients/{client}/invite/resend'
 */
resend.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { client: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                }

    return resend.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientController::resend
 * @see app/Http/Controllers/Advisor/ClientController.php:322
 * @route '/advisor/clients/{client}/invite/resend'
 */
resend.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientController::resend
 * @see app/Http/Controllers/Advisor/ClientController.php:322
 * @route '/advisor/clients/{client}/invite/resend'
 */
    const resendForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resend.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientController::resend
 * @see app/Http/Controllers/Advisor/ClientController.php:322
 * @route '/advisor/clients/{client}/invite/resend'
 */
        resendForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resend.url(args, options),
            method: 'post',
        })

    resend.form = resendForm
/**
* @see \App\Http\Controllers\Advisor\ClientController::cancel
 * @see app/Http/Controllers/Advisor/ClientController.php:384
 * @route '/advisor/clients/{client}/invite'
 */
export const cancel = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancel.url(args, options),
    method: 'delete',
})

cancel.definition = {
    methods: ["delete"],
    url: '/advisor/clients/{client}/invite',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Advisor\ClientController::cancel
 * @see app/Http/Controllers/Advisor/ClientController.php:384
 * @route '/advisor/clients/{client}/invite'
 */
cancel.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { client: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                }

    return cancel.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientController::cancel
 * @see app/Http/Controllers/Advisor/ClientController.php:384
 * @route '/advisor/clients/{client}/invite'
 */
cancel.delete = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancel.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientController::cancel
 * @see app/Http/Controllers/Advisor/ClientController.php:384
 * @route '/advisor/clients/{client}/invite'
 */
    const cancelForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: cancel.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientController::cancel
 * @see app/Http/Controllers/Advisor/ClientController.php:384
 * @route '/advisor/clients/{client}/invite'
 */
        cancelForm.delete = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: cancel.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    cancel.form = cancelForm
const invite = {
    store: Object.assign(store, store),
resend: Object.assign(resend, resend),
cancel: Object.assign(cancel, cancel),
}

export default invite