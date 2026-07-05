import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\ServiceRateController::index
 * @see app/Http/Controllers/Admin/ServiceRateController.php:26
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
 * @see app/Http/Controllers/Admin/ServiceRateController.php:26
 * @route '/admin/service-rates'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::index
 * @see app/Http/Controllers/Admin/ServiceRateController.php:26
 * @route '/admin/service-rates'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\ServiceRateController::index
 * @see app/Http/Controllers/Admin/ServiceRateController.php:26
 * @route '/admin/service-rates'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\ServiceRateController::index
 * @see app/Http/Controllers/Admin/ServiceRateController.php:26
 * @route '/admin/service-rates'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\ServiceRateController::index
 * @see app/Http/Controllers/Admin/ServiceRateController.php:26
 * @route '/admin/service-rates'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\ServiceRateController::index
 * @see app/Http/Controllers/Admin/ServiceRateController.php:26
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
 * @see app/Http/Controllers/Admin/ServiceRateController.php:57
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
 * @see app/Http/Controllers/Admin/ServiceRateController.php:57
 * @route '/admin/service-rates'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:57
 * @route '/admin/service-rates'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:57
 * @route '/admin/service-rates'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:57
 * @route '/admin/service-rates'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\ServiceRateController::storePackage
 * @see app/Http/Controllers/Admin/ServiceRateController.php:80
 * @route '/admin/service-rates/packages'
 */
export const storePackage = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storePackage.url(options),
    method: 'post',
})

storePackage.definition = {
    methods: ["post"],
    url: '/admin/service-rates/packages',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::storePackage
 * @see app/Http/Controllers/Admin/ServiceRateController.php:80
 * @route '/admin/service-rates/packages'
 */
storePackage.url = (options?: RouteQueryOptions) => {
    return storePackage.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::storePackage
 * @see app/Http/Controllers/Admin/ServiceRateController.php:80
 * @route '/admin/service-rates/packages'
 */
storePackage.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storePackage.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\ServiceRateController::storePackage
 * @see app/Http/Controllers/Admin/ServiceRateController.php:80
 * @route '/admin/service-rates/packages'
 */
    const storePackageForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: storePackage.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ServiceRateController::storePackage
 * @see app/Http/Controllers/Admin/ServiceRateController.php:80
 * @route '/admin/service-rates/packages'
 */
        storePackageForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: storePackage.url(options),
            method: 'post',
        })
    
    storePackage.form = storePackageForm
/**
* @see \App\Http\Controllers\Admin\ServiceRateController::togglePackage
 * @see app/Http/Controllers/Admin/ServiceRateController.php:128
 * @route '/admin/service-rates/packages/{serviceRatePackage}'
 */
export const togglePackage = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: togglePackage.url(args, options),
    method: 'patch',
})

togglePackage.definition = {
    methods: ["patch"],
    url: '/admin/service-rates/packages/{serviceRatePackage}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::togglePackage
 * @see app/Http/Controllers/Admin/ServiceRateController.php:128
 * @route '/admin/service-rates/packages/{serviceRatePackage}'
 */
togglePackage.url = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return togglePackage.definition.url
            .replace('{serviceRatePackage}', parsedArgs.serviceRatePackage.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::togglePackage
 * @see app/Http/Controllers/Admin/ServiceRateController.php:128
 * @route '/admin/service-rates/packages/{serviceRatePackage}'
 */
togglePackage.patch = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: togglePackage.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\ServiceRateController::togglePackage
 * @see app/Http/Controllers/Admin/ServiceRateController.php:128
 * @route '/admin/service-rates/packages/{serviceRatePackage}'
 */
    const togglePackageForm = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: togglePackage.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ServiceRateController::togglePackage
 * @see app/Http/Controllers/Admin/ServiceRateController.php:128
 * @route '/admin/service-rates/packages/{serviceRatePackage}'
 */
        togglePackageForm.patch = (args: { serviceRatePackage: string | { id: string } } | [serviceRatePackage: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: togglePackage.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    togglePackage.form = togglePackageForm
const ServiceRateController = { index, store, storePackage, togglePackage }

export default ServiceRateController