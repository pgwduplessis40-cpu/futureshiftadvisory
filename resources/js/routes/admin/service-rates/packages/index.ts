import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:80
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
 * @see app/Http/Controllers/Admin/ServiceRateController.php:80
 * @route '/admin/service-rates/packages'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:80
 * @route '/admin/service-rates/packages'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:80
 * @route '/admin/service-rates/packages'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:80
 * @route '/admin/service-rates/packages'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\ServiceRateController::toggle
 * @see app/Http/Controllers/Admin/ServiceRateController.php:128
 * @route '/admin/service-rates/packages/{serviceRatePackage}'
 */
export const toggle = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggle.url(args, options),
    method: 'patch',
})

toggle.definition = {
    methods: ["patch"],
    url: '/admin/service-rates/packages/{serviceRatePackage}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::toggle
 * @see app/Http/Controllers/Admin/ServiceRateController.php:128
 * @route '/admin/service-rates/packages/{serviceRatePackage}'
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
 * @see app/Http/Controllers/Admin/ServiceRateController.php:128
 * @route '/admin/service-rates/packages/{serviceRatePackage}'
 */
toggle.patch = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggle.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\ServiceRateController::toggle
 * @see app/Http/Controllers/Admin/ServiceRateController.php:128
 * @route '/admin/service-rates/packages/{serviceRatePackage}'
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
 * @see app/Http/Controllers/Admin/ServiceRateController.php:128
 * @route '/admin/service-rates/packages/{serviceRatePackage}'
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
toggle: Object.assign(toggle, toggle),
}

export default packages