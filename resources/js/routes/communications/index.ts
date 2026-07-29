import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\BulkCommunicationOpenController::__invoke
 * @see app/Http/Controllers/BulkCommunicationOpenController.php:15
 * @route '/communications/open/{token}.gif'
 */
export const open = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: open.url(args, options),
    method: 'get',
})

open.definition = {
    methods: ["get","head"],
    url: '/communications/open/{token}.gif',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\BulkCommunicationOpenController::__invoke
 * @see app/Http/Controllers/BulkCommunicationOpenController.php:15
 * @route '/communications/open/{token}.gif'
 */
open.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { token: args }
    }


    if (Array.isArray(args)) {
        args = {
                    token: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        token: args.token,
                }

    return open.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\BulkCommunicationOpenController::__invoke
 * @see app/Http/Controllers/BulkCommunicationOpenController.php:15
 * @route '/communications/open/{token}.gif'
 */
open.get = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: open.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\BulkCommunicationOpenController::__invoke
 * @see app/Http/Controllers/BulkCommunicationOpenController.php:15
 * @route '/communications/open/{token}.gif'
 */
open.head = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: open.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\BulkCommunicationOpenController::__invoke
 * @see app/Http/Controllers/BulkCommunicationOpenController.php:15
 * @route '/communications/open/{token}.gif'
 */
    const openForm = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: open.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\BulkCommunicationOpenController::__invoke
 * @see app/Http/Controllers/BulkCommunicationOpenController.php:15
 * @route '/communications/open/{token}.gif'
 */
        openForm.get = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: open.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\BulkCommunicationOpenController::__invoke
 * @see app/Http/Controllers/BulkCommunicationOpenController.php:15
 * @route '/communications/open/{token}.gif'
 */
        openForm.head = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: open.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    open.form = openForm
const communications = {
    open: Object.assign(open, open),
}

export default communications