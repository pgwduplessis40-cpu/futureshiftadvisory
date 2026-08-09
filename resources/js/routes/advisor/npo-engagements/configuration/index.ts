import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\NpoConfigurationController::update
 * @see app/Http/Controllers/Advisor/NpoConfigurationController.php:24
 * @route '/advisor/npo-engagements/{npoEngagement}/configuration'
 */
export const update = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/advisor/npo-engagements/{npoEngagement}/configuration',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\NpoConfigurationController::update
 * @see app/Http/Controllers/Advisor/NpoConfigurationController.php:24
 * @route '/advisor/npo-engagements/{npoEngagement}/configuration'
 */
update.url = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { npoEngagement: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { npoEngagement: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    npoEngagement: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        npoEngagement: typeof args.npoEngagement === 'object'
                ? args.npoEngagement.id
                : args.npoEngagement,
                }

    return update.definition.url
            .replace('{npoEngagement}', parsedArgs.npoEngagement.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\NpoConfigurationController::update
 * @see app/Http/Controllers/Advisor/NpoConfigurationController.php:24
 * @route '/advisor/npo-engagements/{npoEngagement}/configuration'
 */
update.patch = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\NpoConfigurationController::update
 * @see app/Http/Controllers/Advisor/NpoConfigurationController.php:24
 * @route '/advisor/npo-engagements/{npoEngagement}/configuration'
 */
    const updateForm = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\NpoConfigurationController::update
 * @see app/Http/Controllers/Advisor/NpoConfigurationController.php:24
 * @route '/advisor/npo-engagements/{npoEngagement}/configuration'
 */
        updateForm.patch = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    update.form = updateForm
const configuration = {
    update: Object.assign(update, update),
}

export default configuration