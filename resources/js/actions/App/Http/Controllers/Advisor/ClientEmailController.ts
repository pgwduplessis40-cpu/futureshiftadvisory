import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ClientEmailController::create
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:22
 * @route '/advisor/clients/{client}/compose'
 */
export const create = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/advisor/clients/{client}/compose',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ClientEmailController::create
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:22
 * @route '/advisor/clients/{client}/compose'
 */
create.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return create.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientEmailController::create
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:22
 * @route '/advisor/clients/{client}/compose'
 */
create.get = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ClientEmailController::create
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:22
 * @route '/advisor/clients/{client}/compose'
 */
create.head = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientEmailController::create
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:22
 * @route '/advisor/clients/{client}/compose'
 */
    const createForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientEmailController::create
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:22
 * @route '/advisor/clients/{client}/compose'
 */
        createForm.get = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ClientEmailController::create
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:22
 * @route '/advisor/clients/{client}/compose'
 */
        createForm.head = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Advisor\ClientEmailController::store
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:39
 * @route '/advisor/clients/{client}/email'
 */
export const store = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/email',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ClientEmailController::store
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:39
 * @route '/advisor/clients/{client}/email'
 */
store.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientEmailController::store
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:39
 * @route '/advisor/clients/{client}/email'
 */
store.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientEmailController::store
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:39
 * @route '/advisor/clients/{client}/email'
 */
    const storeForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientEmailController::store
 * @see app/Http/Controllers/Advisor/ClientEmailController.php:39
 * @route '/advisor/clients/{client}/email'
 */
        storeForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
const ClientEmailController = { create, store }

export default ClientEmailController