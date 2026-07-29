import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::index
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:23
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
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:23
 * @route '/advisor/service-activations'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::index
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:23
 * @route '/advisor/service-activations'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::index
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:23
 * @route '/advisor/service-activations'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::index
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:23
 * @route '/advisor/service-activations'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::index
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:23
 * @route '/advisor/service-activations'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::index
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:23
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
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:48
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
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:48
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
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:48
 * @route '/advisor/service-activations/{serviceActivation}'
 */
show.get = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::show
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:48
 * @route '/advisor/service-activations/{serviceActivation}'
 */
show.head = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::show
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:48
 * @route '/advisor/service-activations/{serviceActivation}'
 */
    const showForm = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::show
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:48
 * @route '/advisor/service-activations/{serviceActivation}'
 */
        showForm.get = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::show
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:48
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
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:68
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
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:68
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
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:68
 * @route '/advisor/service-activations/{serviceActivation}/package'
 */
packageMethod.post = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: packageMethod.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::packageMethod
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:68
 * @route '/advisor/service-activations/{serviceActivation}/package'
 */
    const packageMethodForm = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: packageMethod.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::packageMethod
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:68
 * @route '/advisor/service-activations/{serviceActivation}/package'
 */
        packageMethodForm.post = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: packageMethod.url(args, options),
            method: 'post',
        })

    packageMethod.form = packageMethodForm
/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::balanceReceived
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:88
 * @route '/advisor/service-activations/{serviceActivation}/balance-received'
 */
export const balanceReceived = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: balanceReceived.url(args, options),
    method: 'post',
})

balanceReceived.definition = {
    methods: ["post"],
    url: '/advisor/service-activations/{serviceActivation}/balance-received',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::balanceReceived
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:88
 * @route '/advisor/service-activations/{serviceActivation}/balance-received'
 */
balanceReceived.url = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return balanceReceived.definition.url
            .replace('{serviceActivation}', parsedArgs.serviceActivation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::balanceReceived
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:88
 * @route '/advisor/service-activations/{serviceActivation}/balance-received'
 */
balanceReceived.post = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: balanceReceived.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::balanceReceived
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:88
 * @route '/advisor/service-activations/{serviceActivation}/balance-received'
 */
    const balanceReceivedForm = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: balanceReceived.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::balanceReceived
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:88
 * @route '/advisor/service-activations/{serviceActivation}/balance-received'
 */
        balanceReceivedForm.post = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: balanceReceived.url(args, options),
            method: 'post',
        })

    balanceReceived.form = balanceReceivedForm
/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::offerIntegrationScoping
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:100
 * @route '/advisor/clients/{client}/integration-scoping-offer'
 */
export const offerIntegrationScoping = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: offerIntegrationScoping.url(args, options),
    method: 'post',
})

offerIntegrationScoping.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/integration-scoping-offer',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::offerIntegrationScoping
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:100
 * @route '/advisor/clients/{client}/integration-scoping-offer'
 */
offerIntegrationScoping.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return offerIntegrationScoping.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::offerIntegrationScoping
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:100
 * @route '/advisor/clients/{client}/integration-scoping-offer'
 */
offerIntegrationScoping.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: offerIntegrationScoping.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::offerIntegrationScoping
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:100
 * @route '/advisor/clients/{client}/integration-scoping-offer'
 */
    const offerIntegrationScopingForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: offerIntegrationScoping.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::offerIntegrationScoping
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:100
 * @route '/advisor/clients/{client}/integration-scoping-offer'
 */
        offerIntegrationScopingForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: offerIntegrationScoping.url(args, options),
            method: 'post',
        })

    offerIntegrationScoping.form = offerIntegrationScopingForm
const ServiceActivationController = { index, show, packageMethod, balanceReceived, offerIntegrationScoping, package: packageMethod }

export default ServiceActivationController