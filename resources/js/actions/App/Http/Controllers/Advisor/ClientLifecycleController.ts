import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ClientLifecycleController::update
 * @see app/Http/Controllers/Advisor/ClientLifecycleController.php:19
 * @route '/advisor/clients/{client}/lifecycle'
 */
export const update = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/advisor/clients/{client}/lifecycle',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\ClientLifecycleController::update
 * @see app/Http/Controllers/Advisor/ClientLifecycleController.php:19
 * @route '/advisor/clients/{client}/lifecycle'
 */
update.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { client: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                }

    return update.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientLifecycleController::update
 * @see app/Http/Controllers/Advisor/ClientLifecycleController.php:19
 * @route '/advisor/clients/{client}/lifecycle'
 */
update.patch = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientLifecycleController::update
 * @see app/Http/Controllers/Advisor/ClientLifecycleController.php:19
 * @route '/advisor/clients/{client}/lifecycle'
 */
    const updateForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientLifecycleController::update
 * @see app/Http/Controllers/Advisor/ClientLifecycleController.php:19
 * @route '/advisor/clients/{client}/lifecycle'
 */
        updateForm.patch = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const ClientLifecycleController = { update }

export default ClientLifecycleController