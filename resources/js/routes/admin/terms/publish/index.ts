import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\TermsController::create
 * @see app/Http/Controllers/Admin/TermsController.php:167
 * @route '/admin/terms/{termsVersion}/publish'
 */
export const create = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/terms/{termsVersion}/publish',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\TermsController::create
 * @see app/Http/Controllers/Admin/TermsController.php:167
 * @route '/admin/terms/{termsVersion}/publish'
 */
create.url = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { termsVersion: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { termsVersion: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    termsVersion: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        termsVersion: typeof args.termsVersion === 'object'
                ? args.termsVersion.id
                : args.termsVersion,
                }

    return create.definition.url
            .replace('{termsVersion}', parsedArgs.termsVersion.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\TermsController::create
 * @see app/Http/Controllers/Admin/TermsController.php:167
 * @route '/admin/terms/{termsVersion}/publish'
 */
create.get = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\TermsController::create
 * @see app/Http/Controllers/Admin/TermsController.php:167
 * @route '/admin/terms/{termsVersion}/publish'
 */
create.head = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\TermsController::create
 * @see app/Http/Controllers/Admin/TermsController.php:167
 * @route '/admin/terms/{termsVersion}/publish'
 */
    const createForm = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\TermsController::create
 * @see app/Http/Controllers/Admin/TermsController.php:167
 * @route '/admin/terms/{termsVersion}/publish'
 */
        createForm.get = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\TermsController::create
 * @see app/Http/Controllers/Admin/TermsController.php:167
 * @route '/admin/terms/{termsVersion}/publish'
 */
        createForm.head = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    create.form = createForm