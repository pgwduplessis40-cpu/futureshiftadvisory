import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::index
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:37
 * @route '/admin/reference-data'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/reference-data',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::index
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:37
 * @route '/admin/reference-data'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::index
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:37
 * @route '/admin/reference-data'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::index
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:37
 * @route '/admin/reference-data'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\ReferenceDataController::index
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:37
 * @route '/admin/reference-data'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\ReferenceDataController::index
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:37
 * @route '/admin/reference-data'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\ReferenceDataController::index
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:37
 * @route '/admin/reference-data'
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
* @see \App\Http\Controllers\Admin\ReferenceDataController::store
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:47
 * @route '/admin/reference-data'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/reference-data',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::store
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:47
 * @route '/admin/reference-data'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::store
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:47
 * @route '/admin/reference-data'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\ReferenceDataController::store
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:47
 * @route '/admin/reference-data'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ReferenceDataController::store
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:47
 * @route '/admin/reference-data'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const referenceData = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
}

export default referenceData