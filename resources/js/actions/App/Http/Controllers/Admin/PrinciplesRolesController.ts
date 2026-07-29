import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\PrinciplesRolesController::index
 * @see app/Http/Controllers/Admin/PrinciplesRolesController.php:24
 * @route '/admin/principles-roles'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/principles-roles',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\PrinciplesRolesController::index
 * @see app/Http/Controllers/Admin/PrinciplesRolesController.php:24
 * @route '/admin/principles-roles'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PrinciplesRolesController::index
 * @see app/Http/Controllers/Admin/PrinciplesRolesController.php:24
 * @route '/admin/principles-roles'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\PrinciplesRolesController::index
 * @see app/Http/Controllers/Admin/PrinciplesRolesController.php:24
 * @route '/admin/principles-roles'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\PrinciplesRolesController::index
 * @see app/Http/Controllers/Admin/PrinciplesRolesController.php:24
 * @route '/admin/principles-roles'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\PrinciplesRolesController::index
 * @see app/Http/Controllers/Admin/PrinciplesRolesController.php:24
 * @route '/admin/principles-roles'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\PrinciplesRolesController::index
 * @see app/Http/Controllers/Admin/PrinciplesRolesController.php:24
 * @route '/admin/principles-roles'
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
* @see \App\Http\Controllers\Admin\PrinciplesRolesController::store
 * @see app/Http/Controllers/Admin/PrinciplesRolesController.php:54
 * @route '/admin/principles-roles'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/principles-roles',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\PrinciplesRolesController::store
 * @see app/Http/Controllers/Admin/PrinciplesRolesController.php:54
 * @route '/admin/principles-roles'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PrinciplesRolesController::store
 * @see app/Http/Controllers/Admin/PrinciplesRolesController.php:54
 * @route '/admin/principles-roles'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\PrinciplesRolesController::store
 * @see app/Http/Controllers/Admin/PrinciplesRolesController.php:54
 * @route '/admin/principles-roles'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\PrinciplesRolesController::store
 * @see app/Http/Controllers/Admin/PrinciplesRolesController.php:54
 * @route '/admin/principles-roles'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
const PrinciplesRolesController = { index, store }

export default PrinciplesRolesController