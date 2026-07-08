import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\DocumentVerificationController::update
 * @see app/Http/Controllers/Advisor/DocumentVerificationController.php:16
 * @route '/advisor/document-verifications/{documentVerification}'
 */
export const update = (args: { documentVerification: string | { id: string } } | [documentVerification: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/advisor/document-verifications/{documentVerification}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\DocumentVerificationController::update
 * @see app/Http/Controllers/Advisor/DocumentVerificationController.php:16
 * @route '/advisor/document-verifications/{documentVerification}'
 */
update.url = (args: { documentVerification: string | { id: string } } | [documentVerification: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { documentVerification: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { documentVerification: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    documentVerification: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        documentVerification: typeof args.documentVerification === 'object'
                ? args.documentVerification.id
                : args.documentVerification,
                }

    return update.definition.url
            .replace('{documentVerification}', parsedArgs.documentVerification.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\DocumentVerificationController::update
 * @see app/Http/Controllers/Advisor/DocumentVerificationController.php:16
 * @route '/advisor/document-verifications/{documentVerification}'
 */
update.patch = (args: { documentVerification: string | { id: string } } | [documentVerification: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\DocumentVerificationController::update
 * @see app/Http/Controllers/Advisor/DocumentVerificationController.php:16
 * @route '/advisor/document-verifications/{documentVerification}'
 */
    const updateForm = (args: { documentVerification: string | { id: string } } | [documentVerification: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\DocumentVerificationController::update
 * @see app/Http/Controllers/Advisor/DocumentVerificationController.php:16
 * @route '/advisor/document-verifications/{documentVerification}'
 */
        updateForm.patch = (args: { documentVerification: string | { id: string } } | [documentVerification: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const documentVerifications = {
    update: Object.assign(update, update),
}

export default documentVerifications