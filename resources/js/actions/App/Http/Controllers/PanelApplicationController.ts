import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PanelApplicationController::store
 * @see app/Http/Controllers/PanelApplicationController.php:19
 * @route '/panel/application'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/panel/application',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PanelApplicationController::store
 * @see app/Http/Controllers/PanelApplicationController.php:19
 * @route '/panel/application'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PanelApplicationController::store
 * @see app/Http/Controllers/PanelApplicationController.php:19
 * @route '/panel/application'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PanelApplicationController::store
 * @see app/Http/Controllers/PanelApplicationController.php:19
 * @route '/panel/application'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PanelApplicationController::store
 * @see app/Http/Controllers/PanelApplicationController.php:19
 * @route '/panel/application'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\PanelApplicationController::update
 * @see app/Http/Controllers/PanelApplicationController.php:35
 * @route '/panel/application'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/panel/application',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\PanelApplicationController::update
 * @see app/Http/Controllers/PanelApplicationController.php:35
 * @route '/panel/application'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PanelApplicationController::update
 * @see app/Http/Controllers/PanelApplicationController.php:35
 * @route '/panel/application'
 */
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\PanelApplicationController::update
 * @see app/Http/Controllers/PanelApplicationController.php:35
 * @route '/panel/application'
 */
    const updateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PanelApplicationController::update
 * @see app/Http/Controllers/PanelApplicationController.php:35
 * @route '/panel/application'
 */
        updateForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    update.form = updateForm
const PanelApplicationController = { store, update }

export default PanelApplicationController
