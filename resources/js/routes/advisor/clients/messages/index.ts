import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ClientMessageController::index
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:28
 * @route '/advisor/clients/{client}/messages'
 */
export const index = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/clients/{client}/messages',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ClientMessageController::index
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:28
 * @route '/advisor/clients/{client}/messages'
 */
index.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return index.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientMessageController::index
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:28
 * @route '/advisor/clients/{client}/messages'
 */
index.get = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ClientMessageController::index
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:28
 * @route '/advisor/clients/{client}/messages'
 */
index.head = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientMessageController::index
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:28
 * @route '/advisor/clients/{client}/messages'
 */
    const indexForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientMessageController::index
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:28
 * @route '/advisor/clients/{client}/messages'
 */
        indexForm.get = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ClientMessageController::index
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:28
 * @route '/advisor/clients/{client}/messages'
 */
        indexForm.head = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    index.form = indexForm
/**
* @see \App\Http\Controllers\Advisor\ClientMessageController::store
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:57
 * @route '/advisor/clients/{client}/messages'
 */
export const store = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/messages',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ClientMessageController::store
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:57
 * @route '/advisor/clients/{client}/messages'
 */
store.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientMessageController::store
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:57
 * @route '/advisor/clients/{client}/messages'
 */
store.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientMessageController::store
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:57
 * @route '/advisor/clients/{client}/messages'
 */
    const storeForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientMessageController::store
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:57
 * @route '/advisor/clients/{client}/messages'
 */
        storeForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Advisor\ClientMessageController::show
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:42
 * @route '/advisor/clients/{client}/messages/{messageThread}'
 */
export const show = (args: { client: string | { id: string }, messageThread: string | { id: string } } | [client: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/clients/{client}/messages/{messageThread}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ClientMessageController::show
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:42
 * @route '/advisor/clients/{client}/messages/{messageThread}'
 */
show.url = (args: { client: string | { id: string }, messageThread: string | { id: string } } | [client: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                    messageThread: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                                messageThread: typeof args.messageThread === 'object'
                ? args.messageThread.id
                : args.messageThread,
                }

    return show.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace('{messageThread}', parsedArgs.messageThread.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientMessageController::show
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:42
 * @route '/advisor/clients/{client}/messages/{messageThread}'
 */
show.get = (args: { client: string | { id: string }, messageThread: string | { id: string } } | [client: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ClientMessageController::show
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:42
 * @route '/advisor/clients/{client}/messages/{messageThread}'
 */
show.head = (args: { client: string | { id: string }, messageThread: string | { id: string } } | [client: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientMessageController::show
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:42
 * @route '/advisor/clients/{client}/messages/{messageThread}'
 */
    const showForm = (args: { client: string | { id: string }, messageThread: string | { id: string } } | [client: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientMessageController::show
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:42
 * @route '/advisor/clients/{client}/messages/{messageThread}'
 */
        showForm.get = (args: { client: string | { id: string }, messageThread: string | { id: string } } | [client: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ClientMessageController::show
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:42
 * @route '/advisor/clients/{client}/messages/{messageThread}'
 */
        showForm.head = (args: { client: string | { id: string }, messageThread: string | { id: string } } | [client: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    show.form = showForm
/**
* @see \App\Http\Controllers\Advisor\ClientMessageController::reply
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:75
 * @route '/advisor/clients/{client}/messages/{messageThread}'
 */
export const reply = (args: { client: string | { id: string }, messageThread: string | { id: string } } | [client: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reply.url(args, options),
    method: 'post',
})

reply.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/messages/{messageThread}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ClientMessageController::reply
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:75
 * @route '/advisor/clients/{client}/messages/{messageThread}'
 */
reply.url = (args: { client: string | { id: string }, messageThread: string | { id: string } } | [client: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                    messageThread: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                                messageThread: typeof args.messageThread === 'object'
                ? args.messageThread.id
                : args.messageThread,
                }

    return reply.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace('{messageThread}', parsedArgs.messageThread.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientMessageController::reply
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:75
 * @route '/advisor/clients/{client}/messages/{messageThread}'
 */
reply.post = (args: { client: string | { id: string }, messageThread: string | { id: string } } | [client: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reply.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientMessageController::reply
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:75
 * @route '/advisor/clients/{client}/messages/{messageThread}'
 */
    const replyForm = (args: { client: string | { id: string }, messageThread: string | { id: string } } | [client: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reply.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientMessageController::reply
 * @see app/Http/Controllers/Advisor/ClientMessageController.php:75
 * @route '/advisor/clients/{client}/messages/{messageThread}'
 */
        replyForm.post = (args: { client: string | { id: string }, messageThread: string | { id: string } } | [client: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reply.url(args, options),
            method: 'post',
        })

    reply.form = replyForm
const messages = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
show: Object.assign(show, show),
reply: Object.assign(reply, reply),
}

export default messages