import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\ClientAllocationController::approve
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:96
 * @route '/admin/client-transfers/{transfer}/approve'
 */
export const approve = (args: { transfer: string | { id: string } } | [transfer: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: approve.url(args, options),
    method: 'patch',
})

approve.definition = {
    methods: ["patch"],
    url: '/admin/client-transfers/{transfer}/approve',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\ClientAllocationController::approve
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:96
 * @route '/admin/client-transfers/{transfer}/approve'
 */
approve.url = (args: { transfer: string | { id: string } } | [transfer: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { transfer: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { transfer: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    transfer: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        transfer: typeof args.transfer === 'object'
                ? args.transfer.id
                : args.transfer,
                }

    return approve.definition.url
            .replace('{transfer}', parsedArgs.transfer.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ClientAllocationController::approve
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:96
 * @route '/admin/client-transfers/{transfer}/approve'
 */
approve.patch = (args: { transfer: string | { id: string } } | [transfer: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: approve.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\ClientAllocationController::approve
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:96
 * @route '/admin/client-transfers/{transfer}/approve'
 */
    const approveForm = (args: { transfer: string | { id: string } } | [transfer: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: approve.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ClientAllocationController::approve
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:96
 * @route '/admin/client-transfers/{transfer}/approve'
 */
        approveForm.patch = (args: { transfer: string | { id: string } } | [transfer: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: approve.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    approve.form = approveForm
/**
* @see \App\Http\Controllers\Admin\ClientAllocationController::reject
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:134
 * @route '/admin/client-transfers/{transfer}/reject'
 */
export const reject = (args: { transfer: string | { id: string } } | [transfer: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reject.url(args, options),
    method: 'patch',
})

reject.definition = {
    methods: ["patch"],
    url: '/admin/client-transfers/{transfer}/reject',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\ClientAllocationController::reject
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:134
 * @route '/admin/client-transfers/{transfer}/reject'
 */
reject.url = (args: { transfer: string | { id: string } } | [transfer: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { transfer: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { transfer: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    transfer: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        transfer: typeof args.transfer === 'object'
                ? args.transfer.id
                : args.transfer,
                }

    return reject.definition.url
            .replace('{transfer}', parsedArgs.transfer.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ClientAllocationController::reject
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:134
 * @route '/admin/client-transfers/{transfer}/reject'
 */
reject.patch = (args: { transfer: string | { id: string } } | [transfer: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reject.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\ClientAllocationController::reject
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:134
 * @route '/admin/client-transfers/{transfer}/reject'
 */
    const rejectForm = (args: { transfer: string | { id: string } } | [transfer: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reject.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ClientAllocationController::reject
 * @see app/Http/Controllers/Admin/ClientAllocationController.php:134
 * @route '/admin/client-transfers/{transfer}/reject'
 */
        rejectForm.patch = (args: { transfer: string | { id: string } } | [transfer: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reject.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    reject.form = rejectForm
const clientTransfers = {
    approve: Object.assign(approve, approve),
reject: Object.assign(reject, reject),
}

export default clientTransfers