import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import roadmaps from './roadmaps'
/**
* @see \App\Http\Controllers\Advisor\FoundingAdvisoryController::replan
* @see app/Http/Controllers/Advisor/FoundingAdvisoryController.php:19
* @route '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/replan'
*/
export const replan = (args: { foundingAdvisoryEngagement: string | number | { id: string | number } } | [foundingAdvisoryEngagement: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: replan.url(args, options),
    method: 'post',
})

replan.definition = {
    methods: ["post"],
    url: '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/replan',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\FoundingAdvisoryController::replan
* @see app/Http/Controllers/Advisor/FoundingAdvisoryController.php:19
* @route '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/replan'
*/
replan.url = (args: { foundingAdvisoryEngagement: string | number | { id: string | number } } | [foundingAdvisoryEngagement: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { foundingAdvisoryEngagement: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { foundingAdvisoryEngagement: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            foundingAdvisoryEngagement: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        foundingAdvisoryEngagement: typeof args.foundingAdvisoryEngagement === 'object'
        ? args.foundingAdvisoryEngagement.id
        : args.foundingAdvisoryEngagement,
    }

    return replan.definition.url
            .replace('{foundingAdvisoryEngagement}', parsedArgs.foundingAdvisoryEngagement.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\FoundingAdvisoryController::replan
* @see app/Http/Controllers/Advisor/FoundingAdvisoryController.php:19
* @route '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/replan'
*/
replan.post = (args: { foundingAdvisoryEngagement: string | number | { id: string | number } } | [foundingAdvisoryEngagement: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: replan.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Advisor\FoundingAdvisoryController::replan
* @see app/Http/Controllers/Advisor/FoundingAdvisoryController.php:19
* @route '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/replan'
*/
const replanForm = (args: { foundingAdvisoryEngagement: string | number | { id: string | number } } | [foundingAdvisoryEngagement: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: replan.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Advisor\FoundingAdvisoryController::replan
* @see app/Http/Controllers/Advisor/FoundingAdvisoryController.php:19
* @route '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/replan'
*/
replanForm.post = (args: { foundingAdvisoryEngagement: string | number | { id: string | number } } | [foundingAdvisoryEngagement: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: replan.url(args, options),
    method: 'post',
})

replan.form = replanForm
const foundingAdvisory = {
    replan: Object.assign(replan, replan),
    roadmaps: Object.assign(roadmaps, roadmaps),
}

export default foundingAdvisory