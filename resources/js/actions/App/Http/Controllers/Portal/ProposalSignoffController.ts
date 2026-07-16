import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::viewProposal
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:147
 * @route '/portal/proposals/{proposal}'
 */
export const viewProposal = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: viewProposal.url(args, options),
    method: 'get',
})

viewProposal.definition = {
    methods: ["get","head"],
    url: '/portal/proposals/{proposal}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::viewProposal
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:147
 * @route '/portal/proposals/{proposal}'
 */
viewProposal.url = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return viewProposal.definition.url
            .replace('{proposal}', parsedArgs.proposal.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::viewProposal
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:147
 * @route '/portal/proposals/{proposal}'
 */
viewProposal.get = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: viewProposal.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::viewProposal
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:147
 * @route '/portal/proposals/{proposal}'
 */
viewProposal.head = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: viewProposal.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::viewProposal
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:147
 * @route '/portal/proposals/{proposal}'
 */
    const viewProposalForm = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: viewProposal.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::viewProposal
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:147
 * @route '/portal/proposals/{proposal}'
 */
        viewProposalForm.get = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: viewProposal.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::viewProposal
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:147
 * @route '/portal/proposals/{proposal}'
 */
        viewProposalForm.head = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: viewProposal.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    viewProposal.form = viewProposalForm
/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::download
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:178
 * @route '/portal/proposals/{proposal}/download'
 */
export const download = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/portal/proposals/{proposal}/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::download
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:178
 * @route '/portal/proposals/{proposal}/download'
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
* @see \App\Http\Controllers\Portal\ProposalSignoffController::download
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:178
 * @route '/portal/proposals/{proposal}/download'
 */
download.get = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::download
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:178
 * @route '/portal/proposals/{proposal}/download'
 */
download.head = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::download
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:178
 * @route '/portal/proposals/{proposal}/download'
 */
    const downloadForm = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: download.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::download
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:178
 * @route '/portal/proposals/{proposal}/download'
 */
        downloadForm.get = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::download
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:178
 * @route '/portal/proposals/{proposal}/download'
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
/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:41
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
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:41
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
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:41
 * @route '/portal/proposals/{proposal}/signoff'
 */
show.get = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:41
 * @route '/portal/proposals/{proposal}/signoff'
 */
show.head = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:41
 * @route '/portal/proposals/{proposal}/signoff'
 */
    const showForm = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:41
 * @route '/portal/proposals/{proposal}/signoff'
 */
        showForm.get = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:41
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
* @see \App\Http\Controllers\Portal\ProposalSignoffController::paymentSetup
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:93
 * @route '/portal/proposals/{proposal}/signoff/payment-setup'
 */
export const paymentSetup = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: paymentSetup.url(args, options),
    method: 'post',
})

paymentSetup.definition = {
    methods: ["post"],
    url: '/portal/proposals/{proposal}/signoff/payment-setup',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::paymentSetup
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:93
 * @route '/portal/proposals/{proposal}/signoff/payment-setup'
 */
paymentSetup.url = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return paymentSetup.definition.url
            .replace('{proposal}', parsedArgs.proposal.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::paymentSetup
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:93
 * @route '/portal/proposals/{proposal}/signoff/payment-setup'
 */
paymentSetup.post = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: paymentSetup.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::paymentSetup
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:93
 * @route '/portal/proposals/{proposal}/signoff/payment-setup'
 */
    const paymentSetupForm = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: paymentSetup.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::paymentSetup
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:93
 * @route '/portal/proposals/{proposal}/signoff/payment-setup'
 */
        paymentSetupForm.post = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: paymentSetup.url(args, options),
            method: 'post',
        })
    
    paymentSetup.form = paymentSetupForm
/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::step
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:64
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
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:64
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
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:64
 * @route '/portal/proposals/{proposal}/signoff/{step}'
 */
step.post = (args: { proposal: string | { id: string }, step: string | number } | [proposal: string | { id: string }, step: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: step.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::step
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:64
 * @route '/portal/proposals/{proposal}/signoff/{step}'
 */
    const stepForm = (args: { proposal: string | { id: string }, step: string | number } | [proposal: string | { id: string }, step: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: step.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::step
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:64
 * @route '/portal/proposals/{proposal}/signoff/{step}'
 */
        stepForm.post = (args: { proposal: string | { id: string }, step: string | number } | [proposal: string | { id: string }, step: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: step.url(args, options),
            method: 'post',
        })
    
    step.form = stepForm
const ProposalSignoffController = { viewProposal, download, show, paymentSetup, step }

export default ProposalSignoffController