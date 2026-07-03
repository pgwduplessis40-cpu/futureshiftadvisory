import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\DdGuestUploadController::__invoke
 * @see app/Http/Controllers/DdGuestUploadController.php:20
 * @route '/api/dd/guest-uploads/{token}'
 */
export const store = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/dd/guest-uploads/{token}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\DdGuestUploadController::__invoke
 * @see app/Http/Controllers/DdGuestUploadController.php:20
 * @route '/api/dd/guest-uploads/{token}'
 */
store.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { token: args }
    }


    if (Array.isArray(args)) {
        args = {
                    token: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        token: args.token,
                }

    return store.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\DdGuestUploadController::__invoke
 * @see app/Http/Controllers/DdGuestUploadController.php:20
 * @route '/api/dd/guest-uploads/{token}'
 */
store.post = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\DdGuestUploadController::__invoke
 * @see app/Http/Controllers/DdGuestUploadController.php:20
 * @route '/api/dd/guest-uploads/{token}'
 */
    const storeForm = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\DdGuestUploadController::__invoke
 * @see app/Http/Controllers/DdGuestUploadController.php:20
 * @route '/api/dd/guest-uploads/{token}'
 */
        storeForm.post = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
const guestUploads = {
    store: Object.assign(store, store),
}

export default guestUploads