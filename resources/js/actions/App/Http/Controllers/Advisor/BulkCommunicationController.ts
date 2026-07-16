import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\BulkCommunicationController::index
 * @see app/Http/Controllers/Advisor/BulkCommunicationController.php:22
 * @route '/advisor/bulk-communications'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/bulk-communications',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\BulkCommunicationController::index
 * @see app/Http/Controllers/Advisor/BulkCommunicationController.php:22
 * @route '/advisor/bulk-communications'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\BulkCommunicationController::index
 * @see app/Http/Controllers/Advisor/BulkCommunicationController.php:22
 * @route '/advisor/bulk-communications'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\BulkCommunicationController::index
 * @see app/Http/Controllers/Advisor/BulkCommunicationController.php:22
 * @route '/advisor/bulk-communications'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\BulkCommunicationController::index
 * @see app/Http/Controllers/Advisor/BulkCommunicationController.php:22
 * @route '/advisor/bulk-communications'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\BulkCommunicationController::index
 * @see app/Http/Controllers/Advisor/BulkCommunicationController.php:22
 * @route '/advisor/bulk-communications'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\BulkCommunicationController::index
 * @see app/Http/Controllers/Advisor/BulkCommunicationController.php:22
 * @route '/advisor/bulk-communications'
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
* @see \App\Http\Controllers\Advisor\BulkCommunicationController::store
 * @see app/Http/Controllers/Advisor/BulkCommunicationController.php:60
 * @route '/advisor/bulk-communications'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/bulk-communications',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\BulkCommunicationController::store
 * @see app/Http/Controllers/Advisor/BulkCommunicationController.php:60
 * @route '/advisor/bulk-communications'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\BulkCommunicationController::store
 * @see app/Http/Controllers/Advisor/BulkCommunicationController.php:60
 * @route '/advisor/bulk-communications'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\BulkCommunicationController::store
 * @see app/Http/Controllers/Advisor/BulkCommunicationController.php:60
 * @route '/advisor/bulk-communications'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\BulkCommunicationController::store
 * @see app/Http/Controllers/Advisor/BulkCommunicationController.php:60
 * @route '/advisor/bulk-communications'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const BulkCommunicationController = { index, store }

export default BulkCommunicationController