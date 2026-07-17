import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\ClientAllocationController::index
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:25
 * @route '/admin/client-allocations'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/client-allocations',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\ClientAllocationController::index
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:25
 * @route '/admin/client-allocations'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ClientAllocationController::index
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:25
 * @route '/admin/client-allocations'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\ClientAllocationController::index
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:25
 * @route '/admin/client-allocations'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\ClientAllocationController::index
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:25
 * @route '/admin/client-allocations'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\ClientAllocationController::index
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:25
 * @route '/admin/client-allocations'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\ClientAllocationController::index
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:25
 * @route '/admin/client-allocations'
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
* @see \App\Http\Controllers\Admin\ClientAllocationController::reassign
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:74
 * @route '/admin/client-allocations/{client}'
 */
export const reassign = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reassign.url(args, options),
    method: 'patch',
})

reassign.definition = {
    methods: ["patch"],
    url: '/admin/client-allocations/{client}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\ClientAllocationController::reassign
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:74
 * @route '/admin/client-allocations/{client}'
 */
reassign.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return reassign.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ClientAllocationController::reassign
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:74
 * @route '/admin/client-allocations/{client}'
 */
reassign.patch = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reassign.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\ClientAllocationController::reassign
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:74
 * @route '/admin/client-allocations/{client}'
 */
    const reassignForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reassign.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ClientAllocationController::reassign
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:74
 * @route '/admin/client-allocations/{client}'
 */
        reassignForm.patch = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reassign.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    reassign.form = reassignForm
const clientAllocations = {
    index: Object.assign(index, index),
reassign: Object.assign(reassign, reassign),
}

export default clientAllocations