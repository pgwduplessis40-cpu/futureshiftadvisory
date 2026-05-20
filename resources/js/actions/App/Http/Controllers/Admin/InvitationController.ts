import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\InvitationController::index
 * @see app/Http/Controllers/Admin/InvitationController.php:18
 * @route '/admin/invitations'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/invitations',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\InvitationController::index
 * @see app/Http/Controllers/Admin/InvitationController.php:18
 * @route '/admin/invitations'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\InvitationController::index
 * @see app/Http/Controllers/Admin/InvitationController.php:18
 * @route '/admin/invitations'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\InvitationController::index
 * @see app/Http/Controllers/Admin/InvitationController.php:18
 * @route '/admin/invitations'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\InvitationController::index
 * @see app/Http/Controllers/Admin/InvitationController.php:18
 * @route '/admin/invitations'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\InvitationController::index
 * @see app/Http/Controllers/Admin/InvitationController.php:18
 * @route '/admin/invitations'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\InvitationController::index
 * @see app/Http/Controllers/Admin/InvitationController.php:18
 * @route '/admin/invitations'
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
* @see \App\Http\Controllers\Admin\InvitationController::create
 * @see app/Http/Controllers/Admin/InvitationController.php:30
 * @route '/admin/invitations/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/invitations/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\InvitationController::create
 * @see app/Http/Controllers/Admin/InvitationController.php:30
 * @route '/admin/invitations/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\InvitationController::create
 * @see app/Http/Controllers/Admin/InvitationController.php:30
 * @route '/admin/invitations/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\InvitationController::create
 * @see app/Http/Controllers/Admin/InvitationController.php:30
 * @route '/admin/invitations/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\InvitationController::create
 * @see app/Http/Controllers/Admin/InvitationController.php:30
 * @route '/admin/invitations/create'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\InvitationController::create
 * @see app/Http/Controllers/Admin/InvitationController.php:30
 * @route '/admin/invitations/create'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\InvitationController::create
 * @see app/Http/Controllers/Admin/InvitationController.php:30
 * @route '/admin/invitations/create'
 */
        createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    create.form = createForm
/**
* @see \App\Http\Controllers\Admin\InvitationController::store
 * @see app/Http/Controllers/Admin/InvitationController.php:39
 * @route '/admin/invitations'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/invitations',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\InvitationController::store
 * @see app/Http/Controllers/Admin/InvitationController.php:39
 * @route '/admin/invitations'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\InvitationController::store
 * @see app/Http/Controllers/Admin/InvitationController.php:39
 * @route '/admin/invitations'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\InvitationController::store
 * @see app/Http/Controllers/Admin/InvitationController.php:39
 * @route '/admin/invitations'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\InvitationController::store
 * @see app/Http/Controllers/Admin/InvitationController.php:39
 * @route '/admin/invitations'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
const InvitationController = { index, create, store }

export default InvitationController