import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::store
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:23
 * @route '/portal/co-browse/connections'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/co-browse/connections',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::store
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:23
 * @route '/portal/co-browse/connections'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::store
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:23
 * @route '/portal/co-browse/connections'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::store
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:23
 * @route '/portal/co-browse/connections'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::store
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:23
 * @route '/portal/co-browse/connections'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
const connections = {
    store: Object.assign(store, store),
}

export default connections