import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\MobileApi\ClientController::index
 * @see app/Http/Controllers/MobileApi/ClientController.php:16
 * @route '/api/mobile/v1/clients'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/mobile/v1/clients',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MobileApi\ClientController::index
 * @see app/Http/Controllers/MobileApi/ClientController.php:16
 * @route '/api/mobile/v1/clients'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MobileApi\ClientController::index
 * @see app/Http/Controllers/MobileApi/ClientController.php:16
 * @route '/api/mobile/v1/clients'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\MobileApi\ClientController::index
 * @see app/Http/Controllers/MobileApi/ClientController.php:16
 * @route '/api/mobile/v1/clients'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\MobileApi\ClientController::index
 * @see app/Http/Controllers/MobileApi/ClientController.php:16
 * @route '/api/mobile/v1/clients'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\MobileApi\ClientController::index
 * @see app/Http/Controllers/MobileApi/ClientController.php:16
 * @route '/api/mobile/v1/clients'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\MobileApi\ClientController::index
 * @see app/Http/Controllers/MobileApi/ClientController.php:16
 * @route '/api/mobile/v1/clients'
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
* @see \App\Http\Controllers\MobileApi\ClientController::show
 * @see app/Http/Controllers/MobileApi/ClientController.php:31
 * @route '/api/mobile/v1/clients/{client}'
 */
export const show = (args: { client: string | number } | [client: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/mobile/v1/clients/{client}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MobileApi\ClientController::show
 * @see app/Http/Controllers/MobileApi/ClientController.php:31
 * @route '/api/mobile/v1/clients/{client}'
 */
show.url = (args: { client: string | number } | [client: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }


    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: args.client,
                }

    return show.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\MobileApi\ClientController::show
 * @see app/Http/Controllers/MobileApi/ClientController.php:31
 * @route '/api/mobile/v1/clients/{client}'
 */
show.get = (args: { client: string | number } | [client: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\MobileApi\ClientController::show
 * @see app/Http/Controllers/MobileApi/ClientController.php:31
 * @route '/api/mobile/v1/clients/{client}'
 */
show.head = (args: { client: string | number } | [client: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\MobileApi\ClientController::show
 * @see app/Http/Controllers/MobileApi/ClientController.php:31
 * @route '/api/mobile/v1/clients/{client}'
 */
    const showForm = (args: { client: string | number } | [client: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\MobileApi\ClientController::show
 * @see app/Http/Controllers/MobileApi/ClientController.php:31
 * @route '/api/mobile/v1/clients/{client}'
 */
        showForm.get = (args: { client: string | number } | [client: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\MobileApi\ClientController::show
 * @see app/Http/Controllers/MobileApi/ClientController.php:31
 * @route '/api/mobile/v1/clients/{client}'
 */
        showForm.head = (args: { client: string | number } | [client: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    show.form = showForm
const clients = {
    index: Object.assign(index, index),
show: Object.assign(show, show),
}

export default clients