import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\ClientLeavePeriodController::store
 * @see app/Http/Controllers/Portal/ClientLeavePeriodController.php:22
 * @route '/portal/calendar/leave-periods'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/calendar/leave-periods',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\ClientLeavePeriodController::store
 * @see app/Http/Controllers/Portal/ClientLeavePeriodController.php:22
 * @route '/portal/calendar/leave-periods'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\ClientLeavePeriodController::store
 * @see app/Http/Controllers/Portal/ClientLeavePeriodController.php:22
 * @route '/portal/calendar/leave-periods'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\ClientLeavePeriodController::store
 * @see app/Http/Controllers/Portal/ClientLeavePeriodController.php:22
 * @route '/portal/calendar/leave-periods'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\ClientLeavePeriodController::store
 * @see app/Http/Controllers/Portal/ClientLeavePeriodController.php:22
 * @route '/portal/calendar/leave-periods'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Portal\ClientLeavePeriodController::destroy
 * @see app/Http/Controllers/Portal/ClientLeavePeriodController.php:51
 * @route '/portal/calendar/leave-periods/{leavePeriod}'
 */
export const destroy = (args: { leavePeriod: string | { id: string } } | [leavePeriod: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/portal/calendar/leave-periods/{leavePeriod}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Portal\ClientLeavePeriodController::destroy
 * @see app/Http/Controllers/Portal/ClientLeavePeriodController.php:51
 * @route '/portal/calendar/leave-periods/{leavePeriod}'
 */
destroy.url = (args: { leavePeriod: string | { id: string } } | [leavePeriod: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { leavePeriod: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { leavePeriod: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    leavePeriod: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        leavePeriod: typeof args.leavePeriod === 'object'
                ? args.leavePeriod.id
                : args.leavePeriod,
                }

    return destroy.definition.url
            .replace('{leavePeriod}', parsedArgs.leavePeriod.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\ClientLeavePeriodController::destroy
 * @see app/Http/Controllers/Portal/ClientLeavePeriodController.php:51
 * @route '/portal/calendar/leave-periods/{leavePeriod}'
 */
destroy.delete = (args: { leavePeriod: string | { id: string } } | [leavePeriod: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Portal\ClientLeavePeriodController::destroy
 * @see app/Http/Controllers/Portal/ClientLeavePeriodController.php:51
 * @route '/portal/calendar/leave-periods/{leavePeriod}'
 */
    const destroyForm = (args: { leavePeriod: string | { id: string } } | [leavePeriod: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\ClientLeavePeriodController::destroy
 * @see app/Http/Controllers/Portal/ClientLeavePeriodController.php:51
 * @route '/portal/calendar/leave-periods/{leavePeriod}'
 */
        destroyForm.delete = (args: { leavePeriod: string | { id: string } } | [leavePeriod: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const ClientLeavePeriodController = { store, destroy }

export default ClientLeavePeriodController