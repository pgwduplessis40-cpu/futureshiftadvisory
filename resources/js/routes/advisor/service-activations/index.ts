import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::index
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:22
 * @route '/advisor/service-activations'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/service-activations',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::index
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:22
 * @route '/advisor/service-activations'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::index
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:22
 * @route '/advisor/service-activations'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::index
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:22
 * @route '/advisor/service-activations'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::index
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:22
 * @route '/advisor/service-activations'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::index
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:22
 * @route '/advisor/service-activations'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::index
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:22
 * @route '/advisor/service-activations'
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
* @see \App\Http\Controllers\Advisor\ServiceActivationController::show
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:47
 * @route '/advisor/service-activations/{serviceActivation}'
 */
export const show = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/service-activations/{serviceActivation}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::show
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:47
 * @route '/advisor/service-activations/{serviceActivation}'
 */
show.url = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { serviceActivation: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { serviceActivation: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    serviceActivation: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        serviceActivation: typeof args.serviceActivation === 'object'
                ? args.serviceActivation.id
                : args.serviceActivation,
                }

    return show.definition.url
            .replace('{serviceActivation}', parsedArgs.serviceActivation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::show
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:47
 * @route '/advisor/service-activations/{serviceActivation}'
 */
show.get = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::show
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:47
 * @route '/advisor/service-activations/{serviceActivation}'
 */
show.head = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::show
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:47
 * @route '/advisor/service-activations/{serviceActivation}'
 */
    const showForm = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::show
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:47
 * @route '/advisor/service-activations/{serviceActivation}'
 */
        showForm.get = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::show
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:47
 * @route '/advisor/service-activations/{serviceActivation}'
 */
        showForm.head = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    show.form = showForm
/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::packageMethod
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:66
 * @route '/advisor/service-activations/{serviceActivation}/package'
 */
export const packageMethod = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: packageMethod.url(args, options),
    method: 'post',
})

packageMethod.definition = {
    methods: ["post"],
    url: '/advisor/service-activations/{serviceActivation}/package',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::packageMethod
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:66
 * @route '/advisor/service-activations/{serviceActivation}/package'
 */
packageMethod.url = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { serviceActivation: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { serviceActivation: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    serviceActivation: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        serviceActivation: typeof args.serviceActivation === 'object'
                ? args.serviceActivation.id
                : args.serviceActivation,
                }

    return packageMethod.definition.url
            .replace('{serviceActivation}', parsedArgs.serviceActivation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::packageMethod
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:66
 * @route '/advisor/service-activations/{serviceActivation}/package'
 */
packageMethod.post = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: packageMethod.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::packageMethod
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:66
 * @route '/advisor/service-activations/{serviceActivation}/package'
 */
    const packageMethodForm = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: packageMethod.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::packageMethod
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:66
 * @route '/advisor/service-activations/{serviceActivation}/package'
 */
        packageMethodForm.post = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: packageMethod.url(args, options),
            method: 'post',
        })

    packageMethod.form = packageMethodForm
const serviceActivations = {
    index: Object.assign(index, index),
show: Object.assign(show, show),
package: Object.assign(packageMethod, packageMethod),
}

export default serviceActivations