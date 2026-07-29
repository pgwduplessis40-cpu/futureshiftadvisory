import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\WelcomeMessageController::index
 * @see app/Http/Controllers/Admin/WelcomeMessageController.php:24
 * @route '/admin/welcome-message'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/welcome-message',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\WelcomeMessageController::index
 * @see app/Http/Controllers/Admin/WelcomeMessageController.php:24
 * @route '/admin/welcome-message'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\WelcomeMessageController::index
 * @see app/Http/Controllers/Admin/WelcomeMessageController.php:24
 * @route '/admin/welcome-message'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\WelcomeMessageController::index
 * @see app/Http/Controllers/Admin/WelcomeMessageController.php:24
 * @route '/admin/welcome-message'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\WelcomeMessageController::index
 * @see app/Http/Controllers/Admin/WelcomeMessageController.php:24
 * @route '/admin/welcome-message'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\WelcomeMessageController::index
 * @see app/Http/Controllers/Admin/WelcomeMessageController.php:24
 * @route '/admin/welcome-message'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\WelcomeMessageController::index
 * @see app/Http/Controllers/Admin/WelcomeMessageController.php:24
 * @route '/admin/welcome-message'
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
* @see \App\Http\Controllers\Admin\WelcomeMessageController::store
 * @see app/Http/Controllers/Admin/WelcomeMessageController.php:50
 * @route '/admin/welcome-message'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/welcome-message',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\WelcomeMessageController::store
 * @see app/Http/Controllers/Admin/WelcomeMessageController.php:50
 * @route '/admin/welcome-message'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\WelcomeMessageController::store
 * @see app/Http/Controllers/Admin/WelcomeMessageController.php:50
 * @route '/admin/welcome-message'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\WelcomeMessageController::store
 * @see app/Http/Controllers/Admin/WelcomeMessageController.php:50
 * @route '/admin/welcome-message'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\WelcomeMessageController::store
 * @see app/Http/Controllers/Admin/WelcomeMessageController.php:50
 * @route '/admin/welcome-message'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
const WelcomeMessageController = { index, store }

export default WelcomeMessageController