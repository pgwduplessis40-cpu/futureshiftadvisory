import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ProposalController::release
 * @see app/Http/Controllers/Advisor/ProposalController.php:62
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
 * @see app/Http/Controllers/Advisor/ProposalController.php:62
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
 * @see app/Http/Controllers/Advisor/ProposalController.php:62
 * @route '/advisor/proposals/{proposal}/release'
 */
release.patch = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: release.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\ProposalController::release
 * @see app/Http/Controllers/Advisor/ProposalController.php:62
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
 * @see app/Http/Controllers/Advisor/ProposalController.php:62
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
 * @see app/Http/Controllers/Advisor/ProposalController.php:81
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
 * @see app/Http/Controllers/Advisor/ProposalController.php:81
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
 * @see app/Http/Controllers/Advisor/ProposalController.php:81
 * @route '/advisor/proposals/{proposal}/recall'
 */
recall.patch = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: recall.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\ProposalController::recall
 * @see app/Http/Controllers/Advisor/ProposalController.php:81
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
 * @see app/Http/Controllers/Advisor/ProposalController.php:81
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
 * @see app/Http/Controllers/Advisor/ProposalController.php:94
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
 * @see app/Http/Controllers/Advisor/ProposalController.php:94
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
 * @see app/Http/Controllers/Advisor/ProposalController.php:94
 * @route '/advisor/proposals/{proposal}/renew'
 */
renew.patch = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: renew.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\ProposalController::renew
 * @see app/Http/Controllers/Advisor/ProposalController.php:94
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
 * @see app/Http/Controllers/Advisor/ProposalController.php:94
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
const proposals = {
    release: Object.assign(release, release),
recall: Object.assign(recall, recall),
renew: Object.assign(renew, renew),
}

export default proposals