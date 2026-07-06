import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import signoff from './signoff'
/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:138
 * @route '/portal/proposals/{proposal}'
 */
export const show = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/proposals/{proposal}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:138
 * @route '/portal/proposals/{proposal}'
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
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:138
 * @route '/portal/proposals/{proposal}'
 */
show.get = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:138
 * @route '/portal/proposals/{proposal}'
 */
show.head = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:138
 * @route '/portal/proposals/{proposal}'
 */
    const showForm = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:138
 * @route '/portal/proposals/{proposal}'
 */
        showForm.get = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::show
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:138
 * @route '/portal/proposals/{proposal}'
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
* @see \App\Http\Controllers\Portal\ProposalSignoffController::download
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:169
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
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:169
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
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:169
 * @route '/portal/proposals/{proposal}/download'
 */
download.get = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::download
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:169
 * @route '/portal/proposals/{proposal}/download'
 */
download.head = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::download
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:169
 * @route '/portal/proposals/{proposal}/download'
 */
    const downloadForm = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: download.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::download
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:169
 * @route '/portal/proposals/{proposal}/download'
 */
        downloadForm.get = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\ProposalSignoffController::download
 * @see app/Http/Controllers/Portal/ProposalSignoffController.php:169
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
const proposals = {
    show: Object.assign(show, show),
download: Object.assign(download, download),
signoff: Object.assign(signoff, signoff),
}

export default proposals