import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\MessageController::index
 * @see app/Http/Controllers/Portal/MessageController.php:31
 * @route '/portal/messages'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/portal/messages',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\MessageController::index
 * @see app/Http/Controllers/Portal/MessageController.php:31
 * @route '/portal/messages'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\MessageController::index
 * @see app/Http/Controllers/Portal/MessageController.php:31
 * @route '/portal/messages'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\MessageController::index
 * @see app/Http/Controllers/Portal/MessageController.php:31
 * @route '/portal/messages'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\MessageController::index
 * @see app/Http/Controllers/Portal/MessageController.php:31
 * @route '/portal/messages'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\MessageController::index
 * @see app/Http/Controllers/Portal/MessageController.php:31
 * @route '/portal/messages'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\MessageController::index
 * @see app/Http/Controllers/Portal/MessageController.php:31
 * @route '/portal/messages'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\Portal\MessageController::store
 * @see app/Http/Controllers/Portal/MessageController.php:85
 * @route '/portal/messages'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/messages',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\MessageController::store
 * @see app/Http/Controllers/Portal/MessageController.php:85
 * @route '/portal/messages'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\MessageController::store
 * @see app/Http/Controllers/Portal/MessageController.php:85
 * @route '/portal/messages'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\MessageController::store
 * @see app/Http/Controllers/Portal/MessageController.php:85
 * @route '/portal/messages'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\MessageController::store
 * @see app/Http/Controllers/Portal/MessageController.php:85
 * @route '/portal/messages'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Portal\MessageController::show
 * @see app/Http/Controllers/Portal/MessageController.php:57
 * @route '/portal/messages/{messageThread}'
 */
export const show = (args: { messageThread: string | { id: string } } | [messageThread: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/messages/{messageThread}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\MessageController::show
 * @see app/Http/Controllers/Portal/MessageController.php:57
 * @route '/portal/messages/{messageThread}'
 */
show.url = (args: { messageThread: string | { id: string } } | [messageThread: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { messageThread: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { messageThread: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    messageThread: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        messageThread: typeof args.messageThread === 'object'
                ? args.messageThread.id
                : args.messageThread,
                }

    return show.definition.url
            .replace('{messageThread}', parsedArgs.messageThread.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\MessageController::show
 * @see app/Http/Controllers/Portal/MessageController.php:57
 * @route '/portal/messages/{messageThread}'
 */
show.get = (args: { messageThread: string | { id: string } } | [messageThread: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\MessageController::show
 * @see app/Http/Controllers/Portal/MessageController.php:57
 * @route '/portal/messages/{messageThread}'
 */
show.head = (args: { messageThread: string | { id: string } } | [messageThread: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\MessageController::show
 * @see app/Http/Controllers/Portal/MessageController.php:57
 * @route '/portal/messages/{messageThread}'
 */
    const showForm = (args: { messageThread: string | { id: string } } | [messageThread: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\MessageController::show
 * @see app/Http/Controllers/Portal/MessageController.php:57
 * @route '/portal/messages/{messageThread}'
 */
        showForm.get = (args: { messageThread: string | { id: string } } | [messageThread: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\MessageController::show
 * @see app/Http/Controllers/Portal/MessageController.php:57
 * @route '/portal/messages/{messageThread}'
 */
        showForm.head = (args: { messageThread: string | { id: string } } | [messageThread: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Portal\MessageController::reply
 * @see app/Http/Controllers/Portal/MessageController.php:119
 * @route '/portal/messages/{messageThread}'
 */
export const reply = (args: { messageThread: string | { id: string } } | [messageThread: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reply.url(args, options),
    method: 'post',
})

reply.definition = {
    methods: ["post"],
    url: '/portal/messages/{messageThread}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\MessageController::reply
 * @see app/Http/Controllers/Portal/MessageController.php:119
 * @route '/portal/messages/{messageThread}'
 */
reply.url = (args: { messageThread: string | { id: string } } | [messageThread: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { messageThread: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { messageThread: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    messageThread: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        messageThread: typeof args.messageThread === 'object'
                ? args.messageThread.id
                : args.messageThread,
                }

    return reply.definition.url
            .replace('{messageThread}', parsedArgs.messageThread.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\MessageController::reply
 * @see app/Http/Controllers/Portal/MessageController.php:119
 * @route '/portal/messages/{messageThread}'
 */
reply.post = (args: { messageThread: string | { id: string } } | [messageThread: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reply.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\MessageController::reply
 * @see app/Http/Controllers/Portal/MessageController.php:119
 * @route '/portal/messages/{messageThread}'
 */
    const replyForm = (args: { messageThread: string | { id: string } } | [messageThread: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reply.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\MessageController::reply
 * @see app/Http/Controllers/Portal/MessageController.php:119
 * @route '/portal/messages/{messageThread}'
 */
        replyForm.post = (args: { messageThread: string | { id: string } } | [messageThread: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reply.url(args, options),
            method: 'post',
        })
    
    reply.form = replyForm
const MessageController = { index, store, show, reply }

export default MessageController