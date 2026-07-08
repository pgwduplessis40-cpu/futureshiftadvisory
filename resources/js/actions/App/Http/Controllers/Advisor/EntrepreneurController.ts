import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:54
 * @route '/advisor/entrepreneurs'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:54
 * @route '/advisor/entrepreneurs'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:54
 * @route '/advisor/entrepreneurs'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:54
 * @route '/advisor/entrepreneurs'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:54
 * @route '/advisor/entrepreneurs'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:54
 * @route '/advisor/entrepreneurs'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::index
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:54
 * @route '/advisor/entrepreneurs'
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
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:71
 * @route '/advisor/entrepreneurs/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:71
 * @route '/advisor/entrepreneurs/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:71
 * @route '/advisor/entrepreneurs/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:71
 * @route '/advisor/entrepreneurs/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:71
 * @route '/advisor/entrepreneurs/create'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:71
 * @route '/advisor/entrepreneurs/create'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::create
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:71
 * @route '/advisor/entrepreneurs/create'
 */
        createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    create.form = createForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:81
 * @route '/advisor/entrepreneurs'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:81
 * @route '/advisor/entrepreneurs'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:81
 * @route '/advisor/entrepreneurs'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:81
 * @route '/advisor/entrepreneurs'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::store
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:81
 * @route '/advisor/entrepreneurs'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::resendInvite
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:147
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend'
 */
export const resendInvite = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resendInvite.url(args, options),
    method: 'post',
})

resendInvite.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::resendInvite
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:147
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend'
 */
resendInvite.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return resendInvite.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::resendInvite
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:147
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend'
 */
resendInvite.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resendInvite.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::resendInvite
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:147
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend'
 */
    const resendInviteForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resendInvite.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::resendInvite
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:147
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend'
 */
        resendInviteForm.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resendInvite.url(args, options),
            method: 'post',
        })

    resendInvite.form = resendInviteForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::cancelInvite
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:194
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
 */
export const cancelInvite = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancelInvite.url(args, options),
    method: 'delete',
})

cancelInvite.definition = {
    methods: ["delete"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/invite',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::cancelInvite
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:194
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
 */
cancelInvite.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return cancelInvite.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::cancelInvite
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:194
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
 */
cancelInvite.delete = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancelInvite.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::cancelInvite
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:194
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
 */
    const cancelInviteForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: cancelInvite.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::cancelInvite
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:194
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
 */
        cancelInviteForm.delete = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: cancelInvite.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    cancelInvite.form = cancelInviteForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:229
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}'
 */
export const show = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:229
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}'
 */
show.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:229
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}'
 */
show.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:229
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}'
 */
show.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:229
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}'
 */
    const showForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:229
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}'
 */
        showForm.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:229
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}'
 */
        showForm.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    show.form = showForm
const EntrepreneurController = { index, create, store, resendInvite, cancelInvite, show }

export default EntrepreneurController