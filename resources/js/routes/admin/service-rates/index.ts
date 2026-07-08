import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import packages from './packages'
/**
* @see \App\Http\Controllers\Admin\ServiceRateController::index
 * @see app/Http/Controllers/Admin/ServiceRateController.php:27
 * @route '/admin/service-rates'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/service-rates',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::index
 * @see app/Http/Controllers/Admin/ServiceRateController.php:27
 * @route '/admin/service-rates'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::index
 * @see app/Http/Controllers/Admin/ServiceRateController.php:27
 * @route '/admin/service-rates'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\ServiceRateController::index
 * @see app/Http/Controllers/Admin/ServiceRateController.php:27
 * @route '/admin/service-rates'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\ServiceRateController::index
 * @see app/Http/Controllers/Admin/ServiceRateController.php:27
 * @route '/admin/service-rates'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\ServiceRateController::index
 * @see app/Http/Controllers/Admin/ServiceRateController.php:27
 * @route '/admin/service-rates'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\ServiceRateController::index
 * @see app/Http/Controllers/Admin/ServiceRateController.php:27
 * @route '/admin/service-rates'
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
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:60
 * @route '/admin/service-rates'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/service-rates',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:60
 * @route '/admin/service-rates'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:60
 * @route '/admin/service-rates'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:60
 * @route '/admin/service-rates'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:60
 * @route '/admin/service-rates'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\ServiceRateController::toggle
 * @see app/Http/Controllers/Admin/ServiceRateController.php:83
 * @route '/admin/service-rates/{serviceRateSetting}/status'
 */
export const toggle = (args: { serviceRateSetting: string | { id: string } } | [serviceRateSetting: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggle.url(args, options),
    method: 'patch',
})

toggle.definition = {
    methods: ["patch"],
    url: '/admin/service-rates/{serviceRateSetting}/status',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::toggle
 * @see app/Http/Controllers/Admin/ServiceRateController.php:83
 * @route '/admin/service-rates/{serviceRateSetting}/status'
 */
toggle.url = (args: { serviceRateSetting: string | { id: string } } | [serviceRateSetting: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { serviceRateSetting: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { serviceRateSetting: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    serviceRateSetting: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        serviceRateSetting: typeof args.serviceRateSetting === 'object'
                ? args.serviceRateSetting.id
                : args.serviceRateSetting,
                }

    return toggle.definition.url
            .replace('{serviceRateSetting}', parsedArgs.serviceRateSetting.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::toggle
 * @see app/Http/Controllers/Admin/ServiceRateController.php:83
 * @route '/admin/service-rates/{serviceRateSetting}/status'
 */
toggle.patch = (args: { serviceRateSetting: string | { id: string } } | [serviceRateSetting: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggle.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\ServiceRateController::toggle
 * @see app/Http/Controllers/Admin/ServiceRateController.php:83
 * @route '/admin/service-rates/{serviceRateSetting}/status'
 */
    const toggleForm = (args: { serviceRateSetting: string | { id: string } } | [serviceRateSetting: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
 * @see app/Http/Controllers/Admin/ServiceRateController.php:83
 * @route '/admin/service-rates/{serviceRateSetting}/status'
 */
        toggleForm.patch = (args: { serviceRateSetting: string | { id: string } } | [serviceRateSetting: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: toggle.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    toggle.form = toggleForm
const serviceRates = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
toggle: Object.assign(toggle, toggle),
packages: Object.assign(packages, packages),
}

export default serviceRates