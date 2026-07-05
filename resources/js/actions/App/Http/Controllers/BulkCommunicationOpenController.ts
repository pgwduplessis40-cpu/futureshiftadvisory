import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\BulkCommunicationOpenController::__invoke
 * @see app/Http/Controllers/BulkCommunicationOpenController.php:15
 * @route '/communications/open/{token}.gif'
 */
const BulkCommunicationOpenController = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BulkCommunicationOpenController.url(args, options),
    method: 'get',
})

BulkCommunicationOpenController.definition = {
    methods: ["get","head"],
    url: '/communications/open/{token}.gif',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\BulkCommunicationOpenController::__invoke
 * @see app/Http/Controllers/BulkCommunicationOpenController.php:15
 * @route '/communications/open/{token}.gif'
 */
BulkCommunicationOpenController.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return BulkCommunicationOpenController.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\BulkCommunicationOpenController::__invoke
 * @see app/Http/Controllers/BulkCommunicationOpenController.php:15
 * @route '/communications/open/{token}.gif'
 */
BulkCommunicationOpenController.get = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: BulkCommunicationOpenController.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\BulkCommunicationOpenController::__invoke
 * @see app/Http/Controllers/BulkCommunicationOpenController.php:15
 * @route '/communications/open/{token}.gif'
 */
BulkCommunicationOpenController.head = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: BulkCommunicationOpenController.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\BulkCommunicationOpenController::__invoke
 * @see app/Http/Controllers/BulkCommunicationOpenController.php:15
 * @route '/communications/open/{token}.gif'
 */
    const BulkCommunicationOpenControllerForm = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: BulkCommunicationOpenController.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\BulkCommunicationOpenController::__invoke
 * @see app/Http/Controllers/BulkCommunicationOpenController.php:15
 * @route '/communications/open/{token}.gif'
 */
        BulkCommunicationOpenControllerForm.get = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: BulkCommunicationOpenController.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\BulkCommunicationOpenController::__invoke
 * @see app/Http/Controllers/BulkCommunicationOpenController.php:15
 * @route '/communications/open/{token}.gif'
 */
        BulkCommunicationOpenControllerForm.head = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: BulkCommunicationOpenController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    BulkCommunicationOpenController.form = BulkCommunicationOpenControllerForm
export default BulkCommunicationOpenController