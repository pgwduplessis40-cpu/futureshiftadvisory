import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::store
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:23
 * @route '/portal/entrepreneur-screen-share/connections'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur-screen-share/connections',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::store
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:23
 * @route '/portal/entrepreneur-screen-share/connections'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::store
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:23
 * @route '/portal/entrepreneur-screen-share/connections'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::store
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:23
 * @route '/portal/entrepreneur-screen-share/connections'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::store
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:23
 * @route '/portal/entrepreneur-screen-share/connections'
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