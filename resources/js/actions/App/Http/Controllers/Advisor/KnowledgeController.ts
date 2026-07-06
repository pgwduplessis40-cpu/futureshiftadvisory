import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::draftFromClient
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:106
 * @route '/advisor/clients/{client}/knowledge-drafts'
 */
export const draftFromClient = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: draftFromClient.url(args, options),
    method: 'post',
})

draftFromClient.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/knowledge-drafts',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::draftFromClient
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:106
 * @route '/advisor/clients/{client}/knowledge-drafts'
 */
draftFromClient.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return draftFromClient.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::draftFromClient
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:106
 * @route '/advisor/clients/{client}/knowledge-drafts'
 */
draftFromClient.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: draftFromClient.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::draftFromClient
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:106
 * @route '/advisor/clients/{client}/knowledge-drafts'
 */
    const draftFromClientForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: draftFromClient.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::draftFromClient
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:106
 * @route '/advisor/clients/{client}/knowledge-drafts'
 */
        draftFromClientForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: draftFromClient.url(args, options),
            method: 'post',
        })
    
    draftFromClient.form = draftFromClientForm
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::index
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:32
 * @route '/advisor/knowledge'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/knowledge',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::index
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:32
 * @route '/advisor/knowledge'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::index
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:32
 * @route '/advisor/knowledge'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::index
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:32
 * @route '/advisor/knowledge'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::index
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:32
 * @route '/advisor/knowledge'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::index
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:32
 * @route '/advisor/knowledge'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::index
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:32
 * @route '/advisor/knowledge'
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
* @see \App\Http\Controllers\Advisor\KnowledgeController::create
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:73
 * @route '/advisor/knowledge/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/advisor/knowledge/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::create
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:73
 * @route '/advisor/knowledge/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::create
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:73
 * @route '/advisor/knowledge/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::create
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:73
 * @route '/advisor/knowledge/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::create
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:73
 * @route '/advisor/knowledge/create'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::create
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:73
 * @route '/advisor/knowledge/create'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::create
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:73
 * @route '/advisor/knowledge/create'
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
* @see \App\Http\Controllers\Advisor\KnowledgeController::store
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:86
 * @route '/advisor/knowledge'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/knowledge',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::store
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:86
 * @route '/advisor/knowledge'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::store
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:86
 * @route '/advisor/knowledge'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::store
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:86
 * @route '/advisor/knowledge'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::store
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:86
 * @route '/advisor/knowledge'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::reviewDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:124
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/review'
 */
export const reviewDraft = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: reviewDraft.url(args, options),
    method: 'get',
})

reviewDraft.definition = {
    methods: ["get","head"],
    url: '/advisor/knowledge-drafts/{knowledgeEntryDraft}/review',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::reviewDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:124
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/review'
 */
reviewDraft.url = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { knowledgeEntryDraft: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { knowledgeEntryDraft: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    knowledgeEntryDraft: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        knowledgeEntryDraft: typeof args.knowledgeEntryDraft === 'object'
                ? args.knowledgeEntryDraft.id
                : args.knowledgeEntryDraft,
                }

    return reviewDraft.definition.url
            .replace('{knowledgeEntryDraft}', parsedArgs.knowledgeEntryDraft.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::reviewDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:124
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/review'
 */
reviewDraft.get = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: reviewDraft.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::reviewDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:124
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/review'
 */
reviewDraft.head = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: reviewDraft.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::reviewDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:124
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/review'
 */
    const reviewDraftForm = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: reviewDraft.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::reviewDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:124
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/review'
 */
        reviewDraftForm.get = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: reviewDraft.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::reviewDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:124
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/review'
 */
        reviewDraftForm.head = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: reviewDraft.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    reviewDraft.form = reviewDraftForm
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::acceptDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:144
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/accept'
 */
export const acceptDraft = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: acceptDraft.url(args, options),
    method: 'patch',
})

acceptDraft.definition = {
    methods: ["patch"],
    url: '/advisor/knowledge-drafts/{knowledgeEntryDraft}/accept',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::acceptDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:144
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/accept'
 */
acceptDraft.url = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { knowledgeEntryDraft: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { knowledgeEntryDraft: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    knowledgeEntryDraft: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        knowledgeEntryDraft: typeof args.knowledgeEntryDraft === 'object'
                ? args.knowledgeEntryDraft.id
                : args.knowledgeEntryDraft,
                }

    return acceptDraft.definition.url
            .replace('{knowledgeEntryDraft}', parsedArgs.knowledgeEntryDraft.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::acceptDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:144
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/accept'
 */
acceptDraft.patch = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: acceptDraft.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::acceptDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:144
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/accept'
 */
    const acceptDraftForm = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: acceptDraft.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::acceptDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:144
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/accept'
 */
        acceptDraftForm.patch = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: acceptDraft.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    acceptDraft.form = acceptDraftForm
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::discardDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:153
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/discard'
 */
export const discardDraft = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: discardDraft.url(args, options),
    method: 'patch',
})

discardDraft.definition = {
    methods: ["patch"],
    url: '/advisor/knowledge-drafts/{knowledgeEntryDraft}/discard',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::discardDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:153
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/discard'
 */
discardDraft.url = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { knowledgeEntryDraft: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { knowledgeEntryDraft: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    knowledgeEntryDraft: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        knowledgeEntryDraft: typeof args.knowledgeEntryDraft === 'object'
                ? args.knowledgeEntryDraft.id
                : args.knowledgeEntryDraft,
                }

    return discardDraft.definition.url
            .replace('{knowledgeEntryDraft}', parsedArgs.knowledgeEntryDraft.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::discardDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:153
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/discard'
 */
discardDraft.patch = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: discardDraft.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::discardDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:153
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/discard'
 */
    const discardDraftForm = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: discardDraft.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::discardDraft
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:153
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/discard'
 */
        discardDraftForm.patch = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: discardDraft.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    discardDraft.form = discardDraftForm
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::show
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:161
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
export const show = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/knowledge/{knowledgeEntry}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::show
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:161
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
show.url = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { knowledgeEntry: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { knowledgeEntry: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    knowledgeEntry: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        knowledgeEntry: typeof args.knowledgeEntry === 'object'
                ? args.knowledgeEntry.id
                : args.knowledgeEntry,
                }

    return show.definition.url
            .replace('{knowledgeEntry}', parsedArgs.knowledgeEntry.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::show
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:161
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
show.get = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::show
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:161
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
show.head = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::show
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:161
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
    const showForm = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::show
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:161
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
        showForm.get = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::show
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:161
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
        showForm.head = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Advisor\KnowledgeController::edit
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:172
 * @route '/advisor/knowledge/{knowledgeEntry}/edit'
 */
export const edit = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/advisor/knowledge/{knowledgeEntry}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::edit
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:172
 * @route '/advisor/knowledge/{knowledgeEntry}/edit'
 */
edit.url = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { knowledgeEntry: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { knowledgeEntry: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    knowledgeEntry: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        knowledgeEntry: typeof args.knowledgeEntry === 'object'
                ? args.knowledgeEntry.id
                : args.knowledgeEntry,
                }

    return edit.definition.url
            .replace('{knowledgeEntry}', parsedArgs.knowledgeEntry.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::edit
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:172
 * @route '/advisor/knowledge/{knowledgeEntry}/edit'
 */
edit.get = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::edit
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:172
 * @route '/advisor/knowledge/{knowledgeEntry}/edit'
 */
edit.head = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::edit
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:172
 * @route '/advisor/knowledge/{knowledgeEntry}/edit'
 */
    const editForm = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: edit.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::edit
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:172
 * @route '/advisor/knowledge/{knowledgeEntry}/edit'
 */
        editForm.get = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::edit
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:172
 * @route '/advisor/knowledge/{knowledgeEntry}/edit'
 */
        editForm.head = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    edit.form = editForm
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::update
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:189
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
export const update = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/advisor/knowledge/{knowledgeEntry}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::update
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:189
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
update.url = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { knowledgeEntry: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { knowledgeEntry: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    knowledgeEntry: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        knowledgeEntry: typeof args.knowledgeEntry === 'object'
                ? args.knowledgeEntry.id
                : args.knowledgeEntry,
                }

    return update.definition.url
            .replace('{knowledgeEntry}', parsedArgs.knowledgeEntry.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::update
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:189
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
update.patch = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::update
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:189
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
    const updateForm = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::update
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:189
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
        updateForm.patch = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::destroy
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:208
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
export const destroy = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/advisor/knowledge/{knowledgeEntry}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::destroy
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:208
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
destroy.url = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { knowledgeEntry: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { knowledgeEntry: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    knowledgeEntry: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        knowledgeEntry: typeof args.knowledgeEntry === 'object'
                ? args.knowledgeEntry.id
                : args.knowledgeEntry,
                }

    return destroy.definition.url
            .replace('{knowledgeEntry}', parsedArgs.knowledgeEntry.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::destroy
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:208
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
destroy.delete = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::destroy
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:208
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
    const destroyForm = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::destroy
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:208
 * @route '/advisor/knowledge/{knowledgeEntry}'
 */
        destroyForm.delete = (args: { knowledgeEntry: string | { id: string } } | [knowledgeEntry: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const KnowledgeController = { draftFromClient, index, create, store, reviewDraft, acceptDraft, discardDraft, show, edit, update, destroy }

export default KnowledgeController