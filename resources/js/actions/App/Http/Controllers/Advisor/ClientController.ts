import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ClientController::index
 * @see app/Http/Controllers/Advisor/ClientController.php:50
 * @route '/advisor/clients'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/clients',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ClientController::index
 * @see app/Http/Controllers/Advisor/ClientController.php:50
 * @route '/advisor/clients'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientController::index
 * @see app/Http/Controllers/Advisor/ClientController.php:50
 * @route '/advisor/clients'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ClientController::index
 * @see app/Http/Controllers/Advisor/ClientController.php:50
 * @route '/advisor/clients'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientController::index
 * @see app/Http/Controllers/Advisor/ClientController.php:50
 * @route '/advisor/clients'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientController::index
 * @see app/Http/Controllers/Advisor/ClientController.php:50
 * @route '/advisor/clients'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ClientController::index
 * @see app/Http/Controllers/Advisor/ClientController.php:50
 * @route '/advisor/clients'
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
* @see \App\Http\Controllers\Advisor\ClientController::create
 * @see app/Http/Controllers/Advisor/ClientController.php:64
 * @route '/advisor/clients/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/advisor/clients/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ClientController::create
 * @see app/Http/Controllers/Advisor/ClientController.php:64
 * @route '/advisor/clients/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientController::create
 * @see app/Http/Controllers/Advisor/ClientController.php:64
 * @route '/advisor/clients/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ClientController::create
 * @see app/Http/Controllers/Advisor/ClientController.php:64
 * @route '/advisor/clients/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientController::create
 * @see app/Http/Controllers/Advisor/ClientController.php:64
 * @route '/advisor/clients/create'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientController::create
 * @see app/Http/Controllers/Advisor/ClientController.php:64
 * @route '/advisor/clients/create'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ClientController::create
 * @see app/Http/Controllers/Advisor/ClientController.php:64
 * @route '/advisor/clients/create'
 */
        createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    create.form = createForm
/**
* @see \App\Http\Controllers\Advisor\ClientController::lookupNzbn
 * @see app/Http/Controllers/Advisor/ClientController.php:71
 * @route '/advisor/clients/lookup-nzbn'
 */
export const lookupNzbn = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: lookupNzbn.url(options),
    method: 'post',
})

lookupNzbn.definition = {
    methods: ["post"],
    url: '/advisor/clients/lookup-nzbn',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ClientController::lookupNzbn
 * @see app/Http/Controllers/Advisor/ClientController.php:71
 * @route '/advisor/clients/lookup-nzbn'
 */
lookupNzbn.url = (options?: RouteQueryOptions) => {
    return lookupNzbn.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientController::lookupNzbn
 * @see app/Http/Controllers/Advisor/ClientController.php:71
 * @route '/advisor/clients/lookup-nzbn'
 */
lookupNzbn.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: lookupNzbn.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientController::lookupNzbn
 * @see app/Http/Controllers/Advisor/ClientController.php:71
 * @route '/advisor/clients/lookup-nzbn'
 */
    const lookupNzbnForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: lookupNzbn.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientController::lookupNzbn
 * @see app/Http/Controllers/Advisor/ClientController.php:71
 * @route '/advisor/clients/lookup-nzbn'
 */
        lookupNzbnForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: lookupNzbn.url(options),
            method: 'post',
        })
    
    lookupNzbn.form = lookupNzbnForm
/**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:85
 * @route '/advisor/clients'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/clients',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:85
 * @route '/advisor/clients'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:85
 * @route '/advisor/clients'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:85
 * @route '/advisor/clients'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientController::store
 * @see app/Http/Controllers/Advisor/ClientController.php:85
 * @route '/advisor/clients'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Advisor\ClientController::show
 * @see app/Http/Controllers/Advisor/ClientController.php:153
 * @route '/advisor/clients/{client}'
 */
export const show = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/clients/{client}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ClientController::show
 * @see app/Http/Controllers/Advisor/ClientController.php:153
 * @route '/advisor/clients/{client}'
 */
show.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientController::show
 * @see app/Http/Controllers/Advisor/ClientController.php:153
 * @route '/advisor/clients/{client}'
 */
show.get = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ClientController::show
 * @see app/Http/Controllers/Advisor/ClientController.php:153
 * @route '/advisor/clients/{client}'
 */
show.head = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientController::show
 * @see app/Http/Controllers/Advisor/ClientController.php:153
 * @route '/advisor/clients/{client}'
 */
    const showForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientController::show
 * @see app/Http/Controllers/Advisor/ClientController.php:153
 * @route '/advisor/clients/{client}'
 */
        showForm.get = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ClientController::show
 * @see app/Http/Controllers/Advisor/ClientController.php:153
 * @route '/advisor/clients/{client}'
 */
        showForm.head = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
const ClientController = { index, create, lookupNzbn, store, show }

export default ClientController