import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:32
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages'
 */
export const index = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/messages',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:32
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages'
 */
index.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { entrepreneurProfile: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { entrepreneurProfile: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                }

    return index.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:32
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages'
 */
index.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:32
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages'
 */
index.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:32
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages'
 */
    const indexForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:32
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages'
 */
        indexForm.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:32
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages'
 */
        indexForm.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:69
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages'
 */
export const store = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/messages',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:69
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages'
 */
store.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { entrepreneurProfile: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { entrepreneurProfile: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                }

    return store.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:69
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages'
 */
store.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:69
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages'
 */
    const storeForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:69
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages'
 */
        storeForm.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:50
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}'
 */
export const show = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:50
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}'
 */
show.url = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                    messageThread: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                                messageThread: typeof args.messageThread === 'object'
                ? args.messageThread.id
                : args.messageThread,
                }

    return show.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{messageThread}', parsedArgs.messageThread.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:50
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}'
 */
show.get = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:50
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}'
 */
show.head = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:50
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}'
 */
    const showForm = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:50
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}'
 */
        showForm.get = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:50
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}'
 */
        showForm.head = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::reply
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:88
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}'
 */
export const reply = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reply.url(args, options),
    method: 'post',
})

reply.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::reply
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:88
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}'
 */
reply.url = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                    messageThread: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                                messageThread: typeof args.messageThread === 'object'
                ? args.messageThread.id
                : args.messageThread,
                }

    return reply.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{messageThread}', parsedArgs.messageThread.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::reply
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:88
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}'
 */
reply.post = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reply.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::reply
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:88
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}'
 */
    const replyForm = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reply.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::reply
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:88
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}'
 */
        replyForm.post = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reply.url(args, options),
            method: 'post',
        })

    reply.form = replyForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::disableGamification
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:107
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}/gamification/disable'
 */
export const disableGamification = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: disableGamification.url(args, options),
    method: 'patch',
})

disableGamification.definition = {
    methods: ["patch"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}/gamification/disable',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::disableGamification
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:107
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}/gamification/disable'
 */
disableGamification.url = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                    messageThread: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                                messageThread: typeof args.messageThread === 'object'
                ? args.messageThread.id
                : args.messageThread,
                }

    return disableGamification.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{messageThread}', parsedArgs.messageThread.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::disableGamification
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:107
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}/gamification/disable'
 */
disableGamification.patch = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: disableGamification.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::disableGamification
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:107
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}/gamification/disable'
 */
    const disableGamificationForm = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: disableGamification.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::disableGamification
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:107
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}/gamification/disable'
 */
        disableGamificationForm.patch = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: disableGamification.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    disableGamification.form = disableGamificationForm
const EntrepreneurMessageController = { index, store, show, reply, disableGamification }

export default EntrepreneurMessageController
