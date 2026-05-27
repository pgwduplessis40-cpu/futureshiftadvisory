import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::review
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:124
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/review'
 */
export const review = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: review.url(args, options),
    method: 'get',
})

review.definition = {
    methods: ["get","head"],
    url: '/advisor/knowledge-drafts/{knowledgeEntryDraft}/review',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::review
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:124
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/review'
 */
review.url = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return review.definition.url
            .replace('{knowledgeEntryDraft}', parsedArgs.knowledgeEntryDraft.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::review
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:124
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/review'
 */
review.get = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: review.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::review
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:124
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/review'
 */
review.head = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: review.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::review
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:124
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/review'
 */
    const reviewForm = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: review.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::review
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:124
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/review'
 */
        reviewForm.get = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: review.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::review
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:124
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/review'
 */
        reviewForm.head = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: review.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    review.form = reviewForm
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::accept
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:144
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/accept'
 */
export const accept = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: accept.url(args, options),
    method: 'patch',
})

accept.definition = {
    methods: ["patch"],
    url: '/advisor/knowledge-drafts/{knowledgeEntryDraft}/accept',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::accept
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:144
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/accept'
 */
accept.url = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return accept.definition.url
            .replace('{knowledgeEntryDraft}', parsedArgs.knowledgeEntryDraft.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::accept
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:144
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/accept'
 */
accept.patch = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: accept.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::accept
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:144
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/accept'
 */
    const acceptForm = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: accept.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::accept
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:144
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/accept'
 */
        acceptForm.patch = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: accept.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    accept.form = acceptForm
/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::discard
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:153
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/discard'
 */
export const discard = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: discard.url(args, options),
    method: 'patch',
})

discard.definition = {
    methods: ["patch"],
    url: '/advisor/knowledge-drafts/{knowledgeEntryDraft}/discard',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::discard
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:153
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/discard'
 */
discard.url = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return discard.definition.url
            .replace('{knowledgeEntryDraft}', parsedArgs.knowledgeEntryDraft.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\KnowledgeController::discard
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:153
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/discard'
 */
discard.patch = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: discard.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::discard
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:153
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/discard'
 */
    const discardForm = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: discard.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\KnowledgeController::discard
 * @see app/Http/Controllers/Advisor/KnowledgeController.php:153
 * @route '/advisor/knowledge-drafts/{knowledgeEntryDraft}/discard'
 */
        discardForm.patch = (args: { knowledgeEntryDraft: string | { id: string } } | [knowledgeEntryDraft: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: discard.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    discard.form = discardForm
const knowledgeDrafts = {
    review: Object.assign(review, review),
accept: Object.assign(accept, accept),
discard: Object.assign(discard, discard),
}

export default knowledgeDrafts