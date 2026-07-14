import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\ServiceActivationController::create
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:28
 * @route '/portal/service-activations/new/{serviceType}'
 */
export const create = (args: { serviceType: string | number } | [serviceType: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/portal/service-activations/new/{serviceType}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\ServiceActivationController::create
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:28
 * @route '/portal/service-activations/new/{serviceType}'
 */
create.url = (args: { serviceType: string | number } | [serviceType: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { serviceType: args }
    }


    if (Array.isArray(args)) {
        args = {
                    serviceType: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        serviceType: args.serviceType,
                }

    return create.definition.url
            .replace('{serviceType}', parsedArgs.serviceType.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\ServiceActivationController::create
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:28
 * @route '/portal/service-activations/new/{serviceType}'
 */
create.get = (args: { serviceType: string | number } | [serviceType: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\ServiceActivationController::create
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:28
 * @route '/portal/service-activations/new/{serviceType}'
 */
create.head = (args: { serviceType: string | number } | [serviceType: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\ServiceActivationController::create
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:28
 * @route '/portal/service-activations/new/{serviceType}'
 */
    const createForm = (args: { serviceType: string | number } | [serviceType: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\ServiceActivationController::create
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:28
 * @route '/portal/service-activations/new/{serviceType}'
 */
        createForm.get = (args: { serviceType: string | number } | [serviceType: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\ServiceActivationController::create
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:28
 * @route '/portal/service-activations/new/{serviceType}'
 */
        createForm.head = (args: { serviceType: string | number } | [serviceType: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    create.form = createForm
/**
* @see \App\Http\Controllers\Portal\ServiceActivationController::store
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:67
 * @route '/portal/service-activations'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/service-activations',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\ServiceActivationController::store
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:67
 * @route '/portal/service-activations'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\ServiceActivationController::store
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:67
 * @route '/portal/service-activations'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\ServiceActivationController::store
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:67
 * @route '/portal/service-activations'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\ServiceActivationController::store
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:67
 * @route '/portal/service-activations'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Portal\ServiceActivationController::show
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:112
 * @route '/portal/service-activations/{serviceActivation}'
 */
export const show = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/service-activations/{serviceActivation}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\ServiceActivationController::show
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:112
 * @route '/portal/service-activations/{serviceActivation}'
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
* @see \App\Http\Controllers\Portal\ServiceActivationController::show
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:112
 * @route '/portal/service-activations/{serviceActivation}'
 */
show.get = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\ServiceActivationController::show
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:112
 * @route '/portal/service-activations/{serviceActivation}'
 */
show.head = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\ServiceActivationController::show
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:112
 * @route '/portal/service-activations/{serviceActivation}'
 */
    const showForm = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\ServiceActivationController::show
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:112
 * @route '/portal/service-activations/{serviceActivation}'
 */
        showForm.get = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\ServiceActivationController::show
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:112
 * @route '/portal/service-activations/{serviceActivation}'
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
* @see \App\Http\Controllers\Portal\ServiceActivationController::paymentComplete
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:129
 * @route '/portal/service-activations/{serviceActivation}/payment-complete'
 */
export const paymentComplete = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: paymentComplete.url(args, options),
    method: 'post',
})

paymentComplete.definition = {
    methods: ["post"],
    url: '/portal/service-activations/{serviceActivation}/payment-complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\ServiceActivationController::paymentComplete
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:129
 * @route '/portal/service-activations/{serviceActivation}/payment-complete'
 */
paymentComplete.url = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return paymentComplete.definition.url
            .replace('{serviceActivation}', parsedArgs.serviceActivation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\ServiceActivationController::paymentComplete
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:129
 * @route '/portal/service-activations/{serviceActivation}/payment-complete'
 */
paymentComplete.post = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: paymentComplete.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\ServiceActivationController::paymentComplete
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:129
 * @route '/portal/service-activations/{serviceActivation}/payment-complete'
 */
    const paymentCompleteForm = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: paymentComplete.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\ServiceActivationController::paymentComplete
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:129
 * @route '/portal/service-activations/{serviceActivation}/payment-complete'
 */
        paymentCompleteForm.post = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: paymentComplete.url(args, options),
            method: 'post',
        })

    paymentComplete.form = paymentCompleteForm
/**
* @see \App\Http\Controllers\Portal\ServiceActivationController::accept
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:142
 * @route '/portal/service-activations/{serviceActivation}/accept'
 */
export const accept = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: accept.url(args, options),
    method: 'post',
})

accept.definition = {
    methods: ["post"],
    url: '/portal/service-activations/{serviceActivation}/accept',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\ServiceActivationController::accept
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:142
 * @route '/portal/service-activations/{serviceActivation}/accept'
 */
accept.url = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return accept.definition.url
            .replace('{serviceActivation}', parsedArgs.serviceActivation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\ServiceActivationController::accept
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:142
 * @route '/portal/service-activations/{serviceActivation}/accept'
 */
accept.post = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: accept.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\ServiceActivationController::accept
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:142
 * @route '/portal/service-activations/{serviceActivation}/accept'
 */
    const acceptForm = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: accept.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\ServiceActivationController::accept
 * @see app/Http/Controllers/Portal/ServiceActivationController.php:142
 * @route '/portal/service-activations/{serviceActivation}/accept'
 */
        acceptForm.post = (args: { serviceActivation: string | { id: string } } | [serviceActivation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: accept.url(args, options),
            method: 'post',
        })

    accept.form = acceptForm
const ServiceActivationController = { create, store, show, paymentComplete, accept }

export default ServiceActivationController