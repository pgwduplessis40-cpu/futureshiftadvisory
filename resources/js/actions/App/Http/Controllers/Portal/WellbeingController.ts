import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\WellbeingController::show
 * @see app/Http/Controllers/Portal/WellbeingController.php:26
 * @route '/portal/wellbeing'
 */
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/wellbeing',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\WellbeingController::show
 * @see app/Http/Controllers/Portal/WellbeingController.php:26
 * @route '/portal/wellbeing'
 */
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\WellbeingController::show
 * @see app/Http/Controllers/Portal/WellbeingController.php:26
 * @route '/portal/wellbeing'
 */
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\WellbeingController::show
 * @see app/Http/Controllers/Portal/WellbeingController.php:26
 * @route '/portal/wellbeing'
 */
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\WellbeingController::show
 * @see app/Http/Controllers/Portal/WellbeingController.php:26
 * @route '/portal/wellbeing'
 */
    const showForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\WellbeingController::show
 * @see app/Http/Controllers/Portal/WellbeingController.php:26
 * @route '/portal/wellbeing'
 */
        showForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\WellbeingController::show
 * @see app/Http/Controllers/Portal/WellbeingController.php:26
 * @route '/portal/wellbeing'
 */
        showForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\Portal\WellbeingController::store
 * @see app/Http/Controllers/Portal/WellbeingController.php:35
 * @route '/portal/wellbeing'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/wellbeing',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\WellbeingController::store
 * @see app/Http/Controllers/Portal/WellbeingController.php:35
 * @route '/portal/wellbeing'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\WellbeingController::store
 * @see app/Http/Controllers/Portal/WellbeingController.php:35
 * @route '/portal/wellbeing'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\WellbeingController::store
 * @see app/Http/Controllers/Portal/WellbeingController.php:35
 * @route '/portal/wellbeing'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\WellbeingController::store
 * @see app/Http/Controllers/Portal/WellbeingController.php:35
 * @route '/portal/wellbeing'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Portal\WellbeingController::destroy
 * @see app/Http/Controllers/Portal/WellbeingController.php:56
 * @route '/portal/wellbeing/{wellbeingCheckin}'
 */
export const destroy = (args: { wellbeingCheckin: string | { id: string } } | [wellbeingCheckin: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/portal/wellbeing/{wellbeingCheckin}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Portal\WellbeingController::destroy
 * @see app/Http/Controllers/Portal/WellbeingController.php:56
 * @route '/portal/wellbeing/{wellbeingCheckin}'
 */
destroy.url = (args: { wellbeingCheckin: string | { id: string } } | [wellbeingCheckin: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { wellbeingCheckin: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { wellbeingCheckin: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    wellbeingCheckin: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        wellbeingCheckin: typeof args.wellbeingCheckin === 'object'
                ? args.wellbeingCheckin.id
                : args.wellbeingCheckin,
                }

    return destroy.definition.url
            .replace('{wellbeingCheckin}', parsedArgs.wellbeingCheckin.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\WellbeingController::destroy
 * @see app/Http/Controllers/Portal/WellbeingController.php:56
 * @route '/portal/wellbeing/{wellbeingCheckin}'
 */
destroy.delete = (args: { wellbeingCheckin: string | { id: string } } | [wellbeingCheckin: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Portal\WellbeingController::destroy
 * @see app/Http/Controllers/Portal/WellbeingController.php:56
 * @route '/portal/wellbeing/{wellbeingCheckin}'
 */
    const destroyForm = (args: { wellbeingCheckin: string | { id: string } } | [wellbeingCheckin: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\WellbeingController::destroy
 * @see app/Http/Controllers/Portal/WellbeingController.php:56
 * @route '/portal/wellbeing/{wellbeingCheckin}'
 */
        destroyForm.delete = (args: { wellbeingCheckin: string | { id: string } } | [wellbeingCheckin: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const WellbeingController = { show, store, destroy }

export default WellbeingController