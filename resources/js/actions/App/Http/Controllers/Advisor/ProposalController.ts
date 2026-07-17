import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ProposalController::store
 * @see app/Http/Controllers/Advisor/ProposalController.php:31
 * @route '/advisor/clients/{client}/proposals'
 */
export const store = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/proposals',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ProposalController::store
 * @see app/Http/Controllers/Advisor/ProposalController.php:31
 * @route '/advisor/clients/{client}/proposals'
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
* @see \App\Http\Controllers\Advisor\ProposalController::store
 * @see app/Http/Controllers/Advisor/ProposalController.php:31
 * @route '/advisor/clients/{client}/proposals'
 */
store.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ProposalController::store
 * @see app/Http/Controllers/Advisor/ProposalController.php:31
 * @route '/advisor/clients/{client}/proposals'
 */
    const storeForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ProposalController::store
 * @see app/Http/Controllers/Advisor/ProposalController.php:31
 * @route '/advisor/clients/{client}/proposals'
 */
        storeForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Advisor\ProposalController::release
 * @see app/Http/Controllers/Advisor/ProposalController.php:124
 * @route '/advisor/proposals/{proposal}/release'
 */
export const release = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: release.url(args, options),
    method: 'patch',
})

release.definition = {
    methods: ["patch"],
    url: '/advisor/proposals/{proposal}/release',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\ProposalController::release
 * @see app/Http/Controllers/Advisor/ProposalController.php:124
 * @route '/advisor/proposals/{proposal}/release'
 */
release.url = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { proposal: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { proposal: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    proposal: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        proposal: typeof args.proposal === 'object'
                ? args.proposal.id
                : args.proposal,
                }

    return release.definition.url
            .replace('{proposal}', parsedArgs.proposal.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ProposalController::release
 * @see app/Http/Controllers/Advisor/ProposalController.php:124
 * @route '/advisor/proposals/{proposal}/release'
 */
release.patch = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: release.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\ProposalController::release
 * @see app/Http/Controllers/Advisor/ProposalController.php:124
 * @route '/advisor/proposals/{proposal}/release'
 */
    const releaseForm = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: release.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ProposalController::release
 * @see app/Http/Controllers/Advisor/ProposalController.php:124
 * @route '/advisor/proposals/{proposal}/release'
 */
        releaseForm.patch = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: release.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    release.form = releaseForm
/**
* @see \App\Http\Controllers\Advisor\ProposalController::recall
 * @see app/Http/Controllers/Advisor/ProposalController.php:151
 * @route '/advisor/proposals/{proposal}/recall'
 */
export const recall = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: recall.url(args, options),
    method: 'patch',
})

recall.definition = {
    methods: ["patch"],
    url: '/advisor/proposals/{proposal}/recall',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\ProposalController::recall
 * @see app/Http/Controllers/Advisor/ProposalController.php:151
 * @route '/advisor/proposals/{proposal}/recall'
 */
recall.url = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { proposal: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { proposal: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    proposal: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        proposal: typeof args.proposal === 'object'
                ? args.proposal.id
                : args.proposal,
                }

    return recall.definition.url
            .replace('{proposal}', parsedArgs.proposal.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ProposalController::recall
 * @see app/Http/Controllers/Advisor/ProposalController.php:151
 * @route '/advisor/proposals/{proposal}/recall'
 */
recall.patch = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: recall.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\ProposalController::recall
 * @see app/Http/Controllers/Advisor/ProposalController.php:151
 * @route '/advisor/proposals/{proposal}/recall'
 */
    const recallForm = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: recall.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ProposalController::recall
 * @see app/Http/Controllers/Advisor/ProposalController.php:151
 * @route '/advisor/proposals/{proposal}/recall'
 */
        recallForm.patch = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: recall.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    recall.form = recallForm
/**
* @see \App\Http\Controllers\Advisor\ProposalController::renew
 * @see app/Http/Controllers/Advisor/ProposalController.php:164
 * @route '/advisor/proposals/{proposal}/renew'
 */
export const renew = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: renew.url(args, options),
    method: 'patch',
})

renew.definition = {
    methods: ["patch"],
    url: '/advisor/proposals/{proposal}/renew',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\ProposalController::renew
 * @see app/Http/Controllers/Advisor/ProposalController.php:164
 * @route '/advisor/proposals/{proposal}/renew'
 */
renew.url = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { proposal: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { proposal: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    proposal: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        proposal: typeof args.proposal === 'object'
                ? args.proposal.id
                : args.proposal,
                }

    return renew.definition.url
            .replace('{proposal}', parsedArgs.proposal.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ProposalController::renew
 * @see app/Http/Controllers/Advisor/ProposalController.php:164
 * @route '/advisor/proposals/{proposal}/renew'
 */
renew.patch = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: renew.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\ProposalController::renew
 * @see app/Http/Controllers/Advisor/ProposalController.php:164
 * @route '/advisor/proposals/{proposal}/renew'
 */
    const renewForm = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: renew.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ProposalController::renew
 * @see app/Http/Controllers/Advisor/ProposalController.php:164
 * @route '/advisor/proposals/{proposal}/renew'
 */
        renewForm.patch = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: renew.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    renew.form = renewForm
/**
* @see \App\Http\Controllers\Advisor\ProposalController::show
 * @see app/Http/Controllers/Advisor/ProposalController.php:177
 * @route '/advisor/proposals/{proposal}'
 */
export const show = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/proposals/{proposal}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ProposalController::show
 * @see app/Http/Controllers/Advisor/ProposalController.php:177
 * @route '/advisor/proposals/{proposal}'
 */
show.url = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { proposal: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { proposal: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    proposal: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        proposal: typeof args.proposal === 'object'
                ? args.proposal.id
                : args.proposal,
                }

    return show.definition.url
            .replace('{proposal}', parsedArgs.proposal.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ProposalController::show
 * @see app/Http/Controllers/Advisor/ProposalController.php:177
 * @route '/advisor/proposals/{proposal}'
 */
show.get = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ProposalController::show
 * @see app/Http/Controllers/Advisor/ProposalController.php:177
 * @route '/advisor/proposals/{proposal}'
 */
show.head = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ProposalController::show
 * @see app/Http/Controllers/Advisor/ProposalController.php:177
 * @route '/advisor/proposals/{proposal}'
 */
    const showForm = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ProposalController::show
 * @see app/Http/Controllers/Advisor/ProposalController.php:177
 * @route '/advisor/proposals/{proposal}'
 */
        showForm.get = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ProposalController::show
 * @see app/Http/Controllers/Advisor/ProposalController.php:177
 * @route '/advisor/proposals/{proposal}'
 */
        showForm.head = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Advisor\ProposalController::download
 * @see app/Http/Controllers/Advisor/ProposalController.php:200
 * @route '/advisor/proposals/{proposal}/download'
 */
export const download = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/advisor/proposals/{proposal}/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ProposalController::download
 * @see app/Http/Controllers/Advisor/ProposalController.php:200
 * @route '/advisor/proposals/{proposal}/download'
 */
download.url = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { proposal: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { proposal: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    proposal: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        proposal: typeof args.proposal === 'object'
                ? args.proposal.id
                : args.proposal,
                }

    return download.definition.url
            .replace('{proposal}', parsedArgs.proposal.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ProposalController::download
 * @see app/Http/Controllers/Advisor/ProposalController.php:200
 * @route '/advisor/proposals/{proposal}/download'
 */
download.get = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ProposalController::download
 * @see app/Http/Controllers/Advisor/ProposalController.php:200
 * @route '/advisor/proposals/{proposal}/download'
 */
download.head = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ProposalController::download
 * @see app/Http/Controllers/Advisor/ProposalController.php:200
 * @route '/advisor/proposals/{proposal}/download'
 */
    const downloadForm = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: download.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ProposalController::download
 * @see app/Http/Controllers/Advisor/ProposalController.php:200
 * @route '/advisor/proposals/{proposal}/download'
 */
        downloadForm.get = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ProposalController::download
 * @see app/Http/Controllers/Advisor/ProposalController.php:200
 * @route '/advisor/proposals/{proposal}/download'
 */
        downloadForm.head = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    download.form = downloadForm
const ProposalController = { store, release, recall, renew, show, download }

export default ProposalController