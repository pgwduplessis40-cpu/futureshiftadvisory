import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\RedFlagController::acknowledge
 * @see app/Http/Controllers/Advisor/RedFlagController.php:17
 * @route '/advisor/red-flags/{redFlag}/acknowledge'
 */
export const acknowledge = (args: { redFlag: string | number | { id: string | number } } | [redFlag: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: acknowledge.url(args, options),
    method: 'patch',
})

acknowledge.definition = {
    methods: ["patch"],
    url: '/advisor/red-flags/{redFlag}/acknowledge',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\RedFlagController::acknowledge
 * @see app/Http/Controllers/Advisor/RedFlagController.php:17
 * @route '/advisor/red-flags/{redFlag}/acknowledge'
 */
acknowledge.url = (args: { redFlag: string | number | { id: string | number } } | [redFlag: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { redFlag: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { redFlag: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    redFlag: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        redFlag: typeof args.redFlag === 'object'
                ? args.redFlag.id
                : args.redFlag,
                }

    return acknowledge.definition.url
            .replace('{redFlag}', parsedArgs.redFlag.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\RedFlagController::acknowledge
 * @see app/Http/Controllers/Advisor/RedFlagController.php:17
 * @route '/advisor/red-flags/{redFlag}/acknowledge'
 */
acknowledge.patch = (args: { redFlag: string | number | { id: string | number } } | [redFlag: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: acknowledge.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\RedFlagController::acknowledge
 * @see app/Http/Controllers/Advisor/RedFlagController.php:17
 * @route '/advisor/red-flags/{redFlag}/acknowledge'
 */
    const acknowledgeForm = (args: { redFlag: string | number | { id: string | number } } | [redFlag: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: acknowledge.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\RedFlagController::acknowledge
 * @see app/Http/Controllers/Advisor/RedFlagController.php:17
 * @route '/advisor/red-flags/{redFlag}/acknowledge'
 */
        acknowledgeForm.patch = (args: { redFlag: string | number | { id: string | number } } | [redFlag: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: acknowledge.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    acknowledge.form = acknowledgeForm
/**
* @see \App\Http\Controllers\Advisor\RedFlagController::resolve
 * @see app/Http/Controllers/Advisor/RedFlagController.php:42
 * @route '/advisor/red-flags/{redFlag}/resolve'
 */
export const resolve = (args: { redFlag: string | number | { id: string | number } } | [redFlag: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: resolve.url(args, options),
    method: 'patch',
})

resolve.definition = {
    methods: ["patch"],
    url: '/advisor/red-flags/{redFlag}/resolve',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\RedFlagController::resolve
 * @see app/Http/Controllers/Advisor/RedFlagController.php:42
 * @route '/advisor/red-flags/{redFlag}/resolve'
 */
resolve.url = (args: { redFlag: string | number | { id: string | number } } | [redFlag: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { redFlag: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { redFlag: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    redFlag: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        redFlag: typeof args.redFlag === 'object'
                ? args.redFlag.id
                : args.redFlag,
                }

    return resolve.definition.url
            .replace('{redFlag}', parsedArgs.redFlag.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\RedFlagController::resolve
 * @see app/Http/Controllers/Advisor/RedFlagController.php:42
 * @route '/advisor/red-flags/{redFlag}/resolve'
 */
resolve.patch = (args: { redFlag: string | number | { id: string | number } } | [redFlag: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: resolve.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\RedFlagController::resolve
 * @see app/Http/Controllers/Advisor/RedFlagController.php:42
 * @route '/advisor/red-flags/{redFlag}/resolve'
 */
    const resolveForm = (args: { redFlag: string | number | { id: string | number } } | [redFlag: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resolve.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\RedFlagController::resolve
 * @see app/Http/Controllers/Advisor/RedFlagController.php:42
 * @route '/advisor/red-flags/{redFlag}/resolve'
 */
        resolveForm.patch = (args: { redFlag: string | number | { id: string | number } } | [redFlag: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resolve.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    resolve.form = resolveForm
const RedFlagController = { acknowledge, resolve }

export default RedFlagController
