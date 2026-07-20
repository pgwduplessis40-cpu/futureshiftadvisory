import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::response
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:53
 * @route '/portal/screen-share-sessions/{session}/response'
 */
export const response = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: response.url(args, options),
    method: 'post',
})

response.definition = {
    methods: ["post"],
    url: '/portal/screen-share-sessions/{session}/response',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::response
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:53
 * @route '/portal/screen-share-sessions/{session}/response'
 */
response.url = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { session: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { session: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    session: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        session: typeof args.session === 'object'
                ? args.session.id
                : args.session,
                }

    return response.definition.url
            .replace('{session}', parsedArgs.session.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::response
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:53
 * @route '/portal/screen-share-sessions/{session}/response'
 */
response.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: response.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::response
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:53
 * @route '/portal/screen-share-sessions/{session}/response'
 */
    const responseForm = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: response.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::response
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:53
 * @route '/portal/screen-share-sessions/{session}/response'
 */
        responseForm.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: response.url(args, options),
            method: 'post',
        })

    response.form = responseForm
/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::browserPermission
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:66
 * @route '/portal/screen-share-sessions/{session}/browser-permission'
 */
export const browserPermission = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: browserPermission.url(args, options),
    method: 'post',
})

browserPermission.definition = {
    methods: ["post"],
    url: '/portal/screen-share-sessions/{session}/browser-permission',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::browserPermission
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:66
 * @route '/portal/screen-share-sessions/{session}/browser-permission'
 */
browserPermission.url = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { session: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { session: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    session: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        session: typeof args.session === 'object'
                ? args.session.id
                : args.session,
                }

    return browserPermission.definition.url
            .replace('{session}', parsedArgs.session.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::browserPermission
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:66
 * @route '/portal/screen-share-sessions/{session}/browser-permission'
 */
browserPermission.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: browserPermission.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::browserPermission
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:66
 * @route '/portal/screen-share-sessions/{session}/browser-permission'
 */
    const browserPermissionForm = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: browserPermission.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::browserPermission
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:66
 * @route '/portal/screen-share-sessions/{session}/browser-permission'
 */
        browserPermissionForm.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: browserPermission.url(args, options),
            method: 'post',
        })

    browserPermission.form = browserPermissionForm
const sessions = {
    response: Object.assign(response, response),
browserPermission: Object.assign(browserPermission, browserPermission),
}

export default sessions