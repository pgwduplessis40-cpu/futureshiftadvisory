import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ClientTransferRequestController::index
 * @see app/Http/Controllers/Advisor/ClientTransferRequestController.php:23
 * @route '/advisor/client-transfers'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/client-transfers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ClientTransferRequestController::index
 * @see app/Http/Controllers/Advisor/ClientTransferRequestController.php:23
 * @route '/advisor/client-transfers'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientTransferRequestController::index
 * @see app/Http/Controllers/Advisor/ClientTransferRequestController.php:23
 * @route '/advisor/client-transfers'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ClientTransferRequestController::index
 * @see app/Http/Controllers/Advisor/ClientTransferRequestController.php:23
 * @route '/advisor/client-transfers'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientTransferRequestController::index
 * @see app/Http/Controllers/Advisor/ClientTransferRequestController.php:23
 * @route '/advisor/client-transfers'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientTransferRequestController::index
 * @see app/Http/Controllers/Advisor/ClientTransferRequestController.php:23
 * @route '/advisor/client-transfers'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ClientTransferRequestController::index
 * @see app/Http/Controllers/Advisor/ClientTransferRequestController.php:23
 * @route '/advisor/client-transfers'
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
* @see \App\Http\Controllers\Advisor\ClientTransferRequestController::store
 * @see app/Http/Controllers/Advisor/ClientTransferRequestController.php:65
 * @route '/advisor/client-transfers'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/client-transfers',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ClientTransferRequestController::store
 * @see app/Http/Controllers/Advisor/ClientTransferRequestController.php:65
 * @route '/advisor/client-transfers'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientTransferRequestController::store
 * @see app/Http/Controllers/Advisor/ClientTransferRequestController.php:65
 * @route '/advisor/client-transfers'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientTransferRequestController::store
 * @see app/Http/Controllers/Advisor/ClientTransferRequestController.php:65
 * @route '/advisor/client-transfers'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientTransferRequestController::store
 * @see app/Http/Controllers/Advisor/ClientTransferRequestController.php:65
 * @route '/advisor/client-transfers'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
const clientTransfers = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
}

export default clientTransfers