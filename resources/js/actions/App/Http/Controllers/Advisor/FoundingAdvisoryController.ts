import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\FoundingAdvisoryController::replan
* @see app/Http/Controllers/Advisor/FoundingAdvisoryController.php:19
* @route '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/replan'
*/
export const replan = (args: { foundingAdvisoryEngagement: string | { id: string } } | [foundingAdvisoryEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
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
replan.url = (args: { foundingAdvisoryEngagement: string | { id: string } } | [foundingAdvisoryEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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
replan.post = (args: { foundingAdvisoryEngagement: string | { id: string } } | [foundingAdvisoryEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: replan.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Advisor\FoundingAdvisoryController::replan
* @see app/Http/Controllers/Advisor/FoundingAdvisoryController.php:19
* @route '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/replan'
*/
const replanForm = (args: { foundingAdvisoryEngagement: string | { id: string } } | [foundingAdvisoryEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: replan.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Advisor\FoundingAdvisoryController::replan
* @see app/Http/Controllers/Advisor/FoundingAdvisoryController.php:19
* @route '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/replan'
*/
replanForm.post = (args: { foundingAdvisoryEngagement: string | { id: string } } | [foundingAdvisoryEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: replan.url(args, options),
    method: 'post',
})

replan.form = replanForm

/**
* @see \App\Http\Controllers\Advisor\FoundingAdvisoryController::publish
* @see app/Http/Controllers/Advisor/FoundingAdvisoryController.php:50
* @route '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/roadmaps/{foundingRoadmapVersion}/publish'
*/
export const publish = (args: { foundingAdvisoryEngagement: string | { id: string }, foundingRoadmapVersion: string | { id: string } } | [foundingAdvisoryEngagement: string | { id: string }, foundingRoadmapVersion: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: publish.url(args, options),
    method: 'patch',
})

publish.definition = {
    methods: ["patch"],
    url: '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/roadmaps/{foundingRoadmapVersion}/publish',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\FoundingAdvisoryController::publish
* @see app/Http/Controllers/Advisor/FoundingAdvisoryController.php:50
* @route '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/roadmaps/{foundingRoadmapVersion}/publish'
*/
publish.url = (args: { foundingAdvisoryEngagement: string | { id: string }, foundingRoadmapVersion: string | { id: string } } | [foundingAdvisoryEngagement: string | { id: string }, foundingRoadmapVersion: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            foundingAdvisoryEngagement: args[0],
            foundingRoadmapVersion: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        foundingAdvisoryEngagement: typeof args.foundingAdvisoryEngagement === 'object'
        ? args.foundingAdvisoryEngagement.id
        : args.foundingAdvisoryEngagement,
        foundingRoadmapVersion: typeof args.foundingRoadmapVersion === 'object'
        ? args.foundingRoadmapVersion.id
        : args.foundingRoadmapVersion,
    }

    return publish.definition.url
            .replace('{foundingAdvisoryEngagement}', parsedArgs.foundingAdvisoryEngagement.toString())
            .replace('{foundingRoadmapVersion}', parsedArgs.foundingRoadmapVersion.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\FoundingAdvisoryController::publish
* @see app/Http/Controllers/Advisor/FoundingAdvisoryController.php:50
* @route '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/roadmaps/{foundingRoadmapVersion}/publish'
*/
publish.patch = (args: { foundingAdvisoryEngagement: string | { id: string }, foundingRoadmapVersion: string | { id: string } } | [foundingAdvisoryEngagement: string | { id: string }, foundingRoadmapVersion: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: publish.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Advisor\FoundingAdvisoryController::publish
* @see app/Http/Controllers/Advisor/FoundingAdvisoryController.php:50
* @route '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/roadmaps/{foundingRoadmapVersion}/publish'
*/
const publishForm = (args: { foundingAdvisoryEngagement: string | { id: string }, foundingRoadmapVersion: string | { id: string } } | [foundingAdvisoryEngagement: string | { id: string }, foundingRoadmapVersion: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: publish.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Advisor\FoundingAdvisoryController::publish
* @see app/Http/Controllers/Advisor/FoundingAdvisoryController.php:50
* @route '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/roadmaps/{foundingRoadmapVersion}/publish'
*/
publishForm.patch = (args: { foundingAdvisoryEngagement: string | { id: string }, foundingRoadmapVersion: string | { id: string } } | [foundingAdvisoryEngagement: string | { id: string }, foundingRoadmapVersion: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: publish.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

publish.form = publishForm

const FoundingAdvisoryController = { replan, publish }

export default FoundingAdvisoryController
