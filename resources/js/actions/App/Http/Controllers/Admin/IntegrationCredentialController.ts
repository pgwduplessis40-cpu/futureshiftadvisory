import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::index
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:29
 * @route '/admin/integration-credentials'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/integration-credentials',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::index
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:29
 * @route '/admin/integration-credentials'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::index
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:29
 * @route '/admin/integration-credentials'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::index
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:29
 * @route '/admin/integration-credentials'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::index
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:29
 * @route '/admin/integration-credentials'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::index
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:29
 * @route '/admin/integration-credentials'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::index
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:29
 * @route '/admin/integration-credentials'
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
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::store
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:49
 * @route '/admin/integration-credentials'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/integration-credentials',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::store
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:49
 * @route '/admin/integration-credentials'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::store
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:49
 * @route '/admin/integration-credentials'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::store
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:49
 * @route '/admin/integration-credentials'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::store
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:49
 * @route '/admin/integration-credentials'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::revoke
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:70
 * @route '/admin/integration-credentials/revoke'
 */
export const revoke = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: revoke.url(options),
    method: 'patch',
})

revoke.definition = {
    methods: ["patch"],
    url: '/admin/integration-credentials/revoke',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::revoke
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:70
 * @route '/admin/integration-credentials/revoke'
 */
revoke.url = (options?: RouteQueryOptions) => {
    return revoke.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::revoke
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:70
 * @route '/admin/integration-credentials/revoke'
 */
revoke.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: revoke.url(options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::revoke
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:70
 * @route '/admin/integration-credentials/revoke'
 */
    const revokeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: revoke.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::revoke
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:70
 * @route '/admin/integration-credentials/revoke'
 */
        revokeForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: revoke.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    revoke.form = revokeForm
/**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::activate
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:90
 * @route '/admin/integration-credentials/activate'
 */
export const activate = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: activate.url(options),
    method: 'patch',
})

activate.definition = {
    methods: ["patch"],
    url: '/admin/integration-credentials/activate',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::activate
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:90
 * @route '/admin/integration-credentials/activate'
 */
activate.url = (options?: RouteQueryOptions) => {
    return activate.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::activate
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:90
 * @route '/admin/integration-credentials/activate'
 */
activate.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: activate.url(options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::activate
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:90
 * @route '/admin/integration-credentials/activate'
 */
    const activateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: activate.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::activate
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:90
 * @route '/admin/integration-credentials/activate'
 */
        activateForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: activate.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    activate.form = activateForm
/**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::deactivate
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:104
 * @route '/admin/integration-credentials/deactivate'
 */
export const deactivate = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: deactivate.url(options),
    method: 'patch',
})

deactivate.definition = {
    methods: ["patch"],
    url: '/admin/integration-credentials/deactivate',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::deactivate
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:104
 * @route '/admin/integration-credentials/deactivate'
 */
deactivate.url = (options?: RouteQueryOptions) => {
    return deactivate.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::deactivate
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:104
 * @route '/admin/integration-credentials/deactivate'
 */
deactivate.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: deactivate.url(options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::deactivate
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:104
 * @route '/admin/integration-credentials/deactivate'
 */
    const deactivateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: deactivate.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\IntegrationCredentialController::deactivate
 * @see app/Http/Controllers/Admin/IntegrationCredentialController.php:104
 * @route '/admin/integration-credentials/deactivate'
 */
        deactivateForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: deactivate.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    deactivate.form = deactivateForm
const IntegrationCredentialController = { index, store, revoke, activate, deactivate }

export default IntegrationCredentialController