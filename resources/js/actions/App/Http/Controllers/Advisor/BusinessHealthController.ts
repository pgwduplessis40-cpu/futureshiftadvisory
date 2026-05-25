import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\BusinessHealthController::recompute
 * @see app/Http/Controllers/Advisor/BusinessHealthController.php:18
 * @route '/advisor/clients/{client}/health-radar/recompute'
 */
export const recompute = (args: { client: string | number } | [client: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: recompute.url(args, options),
    method: 'post',
})

recompute.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/health-radar/recompute',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\BusinessHealthController::recompute
 * @see app/Http/Controllers/Advisor/BusinessHealthController.php:18
 * @route '/advisor/clients/{client}/health-radar/recompute'
 */
recompute.url = (args: { client: string | number } | [client: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }

    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: args.client,
                }

    return recompute.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\BusinessHealthController::recompute
 * @see app/Http/Controllers/Advisor/BusinessHealthController.php:18
 * @route '/advisor/clients/{client}/health-radar/recompute'
 */
recompute.post = (args: { client: string | number } | [client: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: recompute.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\BusinessHealthController::recompute
 * @see app/Http/Controllers/Advisor/BusinessHealthController.php:18
 * @route '/advisor/clients/{client}/health-radar/recompute'
 */
    const recomputeForm = (args: { client: string | number } | [client: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: recompute.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\BusinessHealthController::recompute
 * @see app/Http/Controllers/Advisor/BusinessHealthController.php:18
 * @route '/advisor/clients/{client}/health-radar/recompute'
 */
        recomputeForm.post = (args: { client: string | number } | [client: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: recompute.url(args, options),
            method: 'post',
        })

    recompute.form = recomputeForm
const BusinessHealthController = { recompute }

export default BusinessHealthController
