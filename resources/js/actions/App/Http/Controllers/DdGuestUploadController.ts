import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\DdGuestUploadController::__invoke
 * @see app/Http/Controllers/DdGuestUploadController.php:21
 * @route '/api/dd/guest-uploads/{token}'
 */
const DdGuestUploadController = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: DdGuestUploadController.url(args, options),
    method: 'post',
})

DdGuestUploadController.definition = {
    methods: ["post"],
    url: '/api/dd/guest-uploads/{token}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\DdGuestUploadController::__invoke
 * @see app/Http/Controllers/DdGuestUploadController.php:21
 * @route '/api/dd/guest-uploads/{token}'
 */
DdGuestUploadController.url = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return DdGuestUploadController.definition.url
            .replace('{token}', parsedArgs.token.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\DdGuestUploadController::__invoke
 * @see app/Http/Controllers/DdGuestUploadController.php:21
 * @route '/api/dd/guest-uploads/{token}'
 */
DdGuestUploadController.post = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: DdGuestUploadController.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\DdGuestUploadController::__invoke
 * @see app/Http/Controllers/DdGuestUploadController.php:21
 * @route '/api/dd/guest-uploads/{token}'
 */
    const DdGuestUploadControllerForm = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: DdGuestUploadController.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\DdGuestUploadController::__invoke
 * @see app/Http/Controllers/DdGuestUploadController.php:21
 * @route '/api/dd/guest-uploads/{token}'
 */
        DdGuestUploadControllerForm.post = (args: { token: string | number } | [token: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: DdGuestUploadController.url(args, options),
            method: 'post',
        })

    DdGuestUploadController.form = DdGuestUploadControllerForm
export default DdGuestUploadController