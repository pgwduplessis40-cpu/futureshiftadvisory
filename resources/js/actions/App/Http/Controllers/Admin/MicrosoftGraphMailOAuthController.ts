import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::connect
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:18
 * @route '/admin/project-settings/mail/graph/connect'
 */
export const connect = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: connect.url(options),
    method: 'get',
})

connect.definition = {
    methods: ["get","head"],
    url: '/admin/project-settings/mail/graph/connect',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::connect
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:18
 * @route '/admin/project-settings/mail/graph/connect'
 */
connect.url = (options?: RouteQueryOptions) => {
    return connect.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::connect
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:18
 * @route '/admin/project-settings/mail/graph/connect'
 */
connect.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: connect.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::connect
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:18
 * @route '/admin/project-settings/mail/graph/connect'
 */
connect.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: connect.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::connect
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:18
 * @route '/admin/project-settings/mail/graph/connect'
 */
    const connectForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: connect.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::connect
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:18
 * @route '/admin/project-settings/mail/graph/connect'
 */
        connectForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: connect.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::connect
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:18
 * @route '/admin/project-settings/mail/graph/connect'
 */
        connectForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: connect.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    connect.form = connectForm
/**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::callback
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:39
 * @route '/admin/project-settings/mail/graph/callback'
 */
export const callback = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(options),
    method: 'get',
})

callback.definition = {
    methods: ["get","head"],
    url: '/admin/project-settings/mail/graph/callback',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::callback
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:39
 * @route '/admin/project-settings/mail/graph/callback'
 */
callback.url = (options?: RouteQueryOptions) => {
    return callback.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::callback
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:39
 * @route '/admin/project-settings/mail/graph/callback'
 */
callback.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::callback
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:39
 * @route '/admin/project-settings/mail/graph/callback'
 */
callback.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: callback.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::callback
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:39
 * @route '/admin/project-settings/mail/graph/callback'
 */
    const callbackForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: callback.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::callback
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:39
 * @route '/admin/project-settings/mail/graph/callback'
 */
        callbackForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: callback.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::callback
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:39
 * @route '/admin/project-settings/mail/graph/callback'
 */
        callbackForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: callback.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    callback.form = callbackForm
/**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::disconnect
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:72
 * @route '/admin/project-settings/mail/graph/disconnect'
 */
export const disconnect = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: disconnect.url(options),
    method: 'patch',
})

disconnect.definition = {
    methods: ["patch"],
    url: '/admin/project-settings/mail/graph/disconnect',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::disconnect
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:72
 * @route '/admin/project-settings/mail/graph/disconnect'
 */
disconnect.url = (options?: RouteQueryOptions) => {
    return disconnect.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::disconnect
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:72
 * @route '/admin/project-settings/mail/graph/disconnect'
 */
disconnect.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: disconnect.url(options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::disconnect
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:72
 * @route '/admin/project-settings/mail/graph/disconnect'
 */
    const disconnectForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: disconnect.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\MicrosoftGraphMailOAuthController::disconnect
 * @see app/Http/Controllers/Admin/MicrosoftGraphMailOAuthController.php:72
 * @route '/admin/project-settings/mail/graph/disconnect'
 */
        disconnectForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: disconnect.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    disconnect.form = disconnectForm
const MicrosoftGraphMailOAuthController = { connect, callback, disconnect }

export default MicrosoftGraphMailOAuthController