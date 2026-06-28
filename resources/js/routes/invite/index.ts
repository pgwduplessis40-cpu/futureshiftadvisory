import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Auth\InviteAcceptController::accept
 * @see app/Http/Controllers/Auth/InviteAcceptController.php:36
 * @route '/invite/{token}'
 */
export const accept = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: accept.url(args, options),
    method: 'get',
})

accept.definition = {
    methods: ["get","head"],
    url: '/invite/{token}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Auth\InviteAcceptController::accept
 * @see app/Http/Controllers/Auth/InviteAcceptController.php:36
 * @route '/invite/{token}'
 */
accept.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { token: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    token: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        token: args.token,
                }

    return accept.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\InviteAcceptController::accept
 * @see app/Http/Controllers/Auth/InviteAcceptController.php:36
 * @route '/invite/{token}'
 */
accept.get = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: accept.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Auth\InviteAcceptController::accept
 * @see app/Http/Controllers/Auth/InviteAcceptController.php:36
 * @route '/invite/{token}'
 */
accept.head = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: accept.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Auth\InviteAcceptController::accept
 * @see app/Http/Controllers/Auth/InviteAcceptController.php:36
 * @route '/invite/{token}'
 */
    const acceptForm = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: accept.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Auth\InviteAcceptController::accept
 * @see app/Http/Controllers/Auth/InviteAcceptController.php:36
 * @route '/invite/{token}'
 */
        acceptForm.get = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: accept.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Auth\InviteAcceptController::accept
 * @see app/Http/Controllers/Auth/InviteAcceptController.php:36
 * @route '/invite/{token}'
 */
        acceptForm.head = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: accept.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    accept.form = acceptForm
/**
* @see \App\Http\Controllers\Auth\InviteAcceptController::store
 * @see app/Http/Controllers/Auth/InviteAcceptController.php:93
 * @route '/invite/{token}'
 */
export const store = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/invite/{token}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Auth\InviteAcceptController::store
 * @see app/Http/Controllers/Auth/InviteAcceptController.php:93
 * @route '/invite/{token}'
 */
store.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { token: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    token: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        token: args.token,
                }

    return store.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Auth\InviteAcceptController::store
 * @see app/Http/Controllers/Auth/InviteAcceptController.php:93
 * @route '/invite/{token}'
 */
store.post = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Auth\InviteAcceptController::store
 * @see app/Http/Controllers/Auth/InviteAcceptController.php:93
 * @route '/invite/{token}'
 */
    const storeForm = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Auth\InviteAcceptController::store
 * @see app/Http/Controllers/Auth/InviteAcceptController.php:93
 * @route '/invite/{token}'
 */
        storeForm.post = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
const invite = {
    accept: Object.assign(accept, accept),
store: Object.assign(store, store),
}

export default invite