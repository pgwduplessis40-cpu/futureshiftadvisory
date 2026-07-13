import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:130
 * @route '/admin/service-rates/packages'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/service-rates/packages',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:130
 * @route '/admin/service-rates/packages'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:130
 * @route '/admin/service-rates/packages'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:130
 * @route '/admin/service-rates/packages'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:130
 * @route '/admin/service-rates/packages'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\ServiceRateController::update
 * @see app/Http/Controllers/Admin/ServiceRateController.php:149
 * @route '/admin/service-rates/packages/{serviceRatePackage}'
 */
export const update = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/admin/service-rates/packages/{serviceRatePackage}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::update
 * @see app/Http/Controllers/Admin/ServiceRateController.php:149
 * @route '/admin/service-rates/packages/{serviceRatePackage}'
 */
update.url = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { serviceRatePackage: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { serviceRatePackage: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    serviceRatePackage: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        serviceRatePackage: typeof args.serviceRatePackage === 'object'
                ? args.serviceRatePackage.id
                : args.serviceRatePackage,
                }

    return update.definition.url
            .replace('{serviceRatePackage}', parsedArgs.serviceRatePackage.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::update
 * @see app/Http/Controllers/Admin/ServiceRateController.php:149
 * @route '/admin/service-rates/packages/{serviceRatePackage}'
 */
update.patch = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\ServiceRateController::update
 * @see app/Http/Controllers/Admin/ServiceRateController.php:149
 * @route '/admin/service-rates/packages/{serviceRatePackage}'
 */
    const updateForm = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ServiceRateController::update
 * @see app/Http/Controllers/Admin/ServiceRateController.php:149
 * @route '/admin/service-rates/packages/{serviceRatePackage}'
 */
        updateForm.patch = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\Admin\ServiceRateController::toggle
 * @see app/Http/Controllers/Admin/ServiceRateController.php:180
 * @route '/admin/service-rates/packages/{serviceRatePackage}/status'
 */
export const toggle = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggle.url(args, options),
    method: 'patch',
})

toggle.definition = {
    methods: ["patch"],
    url: '/admin/service-rates/packages/{serviceRatePackage}/status',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::toggle
 * @see app/Http/Controllers/Admin/ServiceRateController.php:180
 * @route '/admin/service-rates/packages/{serviceRatePackage}/status'
 */
toggle.url = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { serviceRatePackage: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { serviceRatePackage: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    serviceRatePackage: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        serviceRatePackage: typeof args.serviceRatePackage === 'object'
                ? args.serviceRatePackage.id
                : args.serviceRatePackage,
                }

    return toggle.definition.url
            .replace('{serviceRatePackage}', parsedArgs.serviceRatePackage.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::toggle
 * @see app/Http/Controllers/Admin/ServiceRateController.php:180
 * @route '/admin/service-rates/packages/{serviceRatePackage}/status'
 */
toggle.patch = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggle.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\ServiceRateController::toggle
 * @see app/Http/Controllers/Admin/ServiceRateController.php:180
 * @route '/admin/service-rates/packages/{serviceRatePackage}/status'
 */
    const toggleForm = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: toggle.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ServiceRateController::toggle
 * @see app/Http/Controllers/Admin/ServiceRateController.php:180
 * @route '/admin/service-rates/packages/{serviceRatePackage}/status'
 */
        toggleForm.patch = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: toggle.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    toggle.form = toggleForm
const packages = {
    store: Object.assign(store, store),
update: Object.assign(update, update),
toggle: Object.assign(toggle, toggle),
}

export default packages