import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:26
 * @route '/portal/proposals/{proposal}/signoff'
 */
export const show = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/proposals/{proposal}/signoff',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:26
 * @route '/portal/proposals/{proposal}/signoff'
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
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:26
 * @route '/portal/proposals/{proposal}/signoff'
 */
show.get = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:26
 * @route '/portal/proposals/{proposal}/signoff'
 */
show.head = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:26
 * @route '/portal/proposals/{proposal}/signoff'
 */
    const showForm = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:26
 * @route '/portal/proposals/{proposal}/signoff'
 */
        showForm.get = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:26
 * @route '/portal/proposals/{proposal}/signoff'
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
* @see \App\Http\Controllers\Portal\ProposalSignoffController::step
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:39
 * @route '/portal/proposals/{proposal}/signoff/{step}'
 */
export const step = (args: { proposal: string | { id: string }, step: string | number } | [proposal: string | { id: string }, step: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: step.url(args, options),
    method: 'post',
})

step.definition = {
    methods: ["post"],
    url: '/portal/proposals/{proposal}/signoff/{step}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::step
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:39
 * @route '/portal/proposals/{proposal}/signoff/{step}'
 */
step.url = (args: { proposal: string | { id: string }, step: string | number } | [proposal: string | { id: string }, step: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    proposal: args[0],
                    step: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        proposal: typeof args.proposal === 'object'
                ? args.proposal.id
                : args.proposal,
                                step: args.step,
                }

    return step.definition.url
            .replace('{proposal}', parsedArgs.proposal.toString())
            .replace('{step}', parsedArgs.step.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::step
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:39
 * @route '/portal/proposals/{proposal}/signoff/{step}'
 */
step.post = (args: { proposal: string | { id: string }, step: string | number } | [proposal: string | { id: string }, step: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: step.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::step
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:39
 * @route '/portal/proposals/{proposal}/signoff/{step}'
 */
    const stepForm = (args: { proposal: string | { id: string }, step: string | number } | [proposal: string | { id: string }, step: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: step.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::step
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:39
 * @route '/portal/proposals/{proposal}/signoff/{step}'
 */
        stepForm.post = (args: { proposal: string | { id: string }, step: string | number } | [proposal: string | { id: string }, step: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: step.url(args, options),
            method: 'post',
        })
    
    step.form = stepForm
const signoff = {
    show: Object.assign(show, show),
step: Object.assign(step, step),
}

export default signoff