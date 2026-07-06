import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::generate
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:30
 * @route '/advisor/proposals/{proposal}/strategic-plan'
 */
export const generate = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generate.url(args, options),
    method: 'post',
})

generate.definition = {
    methods: ["post"],
    url: '/advisor/proposals/{proposal}/strategic-plan',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::generate
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:30
 * @route '/advisor/proposals/{proposal}/strategic-plan'
 */
generate.url = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { proposal: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { proposal: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    proposal: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        proposal: typeof args.proposal === 'object'
                ? args.proposal.id
                : args.proposal,
                }

    return generate.definition.url
            .replace('{proposal}', parsedArgs.proposal.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::generate
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:30
 * @route '/advisor/proposals/{proposal}/strategic-plan'
 */
generate.post = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generate.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::generate
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:30
 * @route '/advisor/proposals/{proposal}/strategic-plan'
 */
    const generateForm = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: generate.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\StrategicPlanController::generate
 * @see app/Http/Controllers/Advisor/StrategicPlanController.php:30
 * @route '/advisor/proposals/{proposal}/strategic-plan'
 */
        generateForm.post = (args: { proposal: string | { id: string } } | [proposal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: generate.url(args, options),
            method: 'post',
        })

    generate.form = generateForm
const strategicPlan = {
    generate: Object.assign(generate, generate),
}

export default strategicPlan