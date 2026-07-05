import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\RatingFrameworkController::store
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:40
 * @route '/admin/rating-frameworks/drafts'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/rating-frameworks/drafts',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\RatingFrameworkController::store
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:40
 * @route '/admin/rating-frameworks/drafts'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\RatingFrameworkController::store
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:40
 * @route '/admin/rating-frameworks/drafts'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\RatingFrameworkController::store
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:40
 * @route '/admin/rating-frameworks/drafts'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\RatingFrameworkController::store
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:40
 * @route '/admin/rating-frameworks/drafts'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const drafts = {
    store: Object.assign(store, store),
}

export default drafts