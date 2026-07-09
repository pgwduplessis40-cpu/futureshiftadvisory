import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::update
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:249
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
 */
export const update = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/invite',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::update
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:249
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
 */
update.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { entrepreneurProfile: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { entrepreneurProfile: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                }

    return update.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::update
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:249
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
 */
update.patch = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::update
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:249
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
 */
    const updateForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::update
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:249
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
 */
        updateForm.patch = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    update.form = updateForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::resend
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:200
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend'
 */
export const resend = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(args, options),
    method: 'post',
})

resend.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::resend
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:200
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend'
 */
resend.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { entrepreneurProfile: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { entrepreneurProfile: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                }

    return resend.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::resend
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:200
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend'
 */
resend.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::resend
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:200
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend'
 */
    const resendForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resend.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::resend
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:200
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite/resend'
 */
        resendForm.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resend.url(args, options),
            method: 'post',
        })

    resend.form = resendForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::cancel
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:331
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
 */
export const cancel = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancel.url(args, options),
    method: 'delete',
})

cancel.definition = {
    methods: ["delete"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/invite',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::cancel
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:331
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
 */
cancel.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { entrepreneurProfile: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { entrepreneurProfile: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                }

    return cancel.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::cancel
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:331
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
 */
cancel.delete = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancel.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::cancel
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:331
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
 */
    const cancelForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: cancel.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurController::cancel
 * @see app/Http/Controllers/Advisor/EntrepreneurController.php:331
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/invite'
 */
        cancelForm.delete = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: cancel.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    cancel.form = cancelForm
const invite = {
    update: Object.assign(update, update),
resend: Object.assign(resend, resend),
cancel: Object.assign(cancel, cancel),
}

export default invite