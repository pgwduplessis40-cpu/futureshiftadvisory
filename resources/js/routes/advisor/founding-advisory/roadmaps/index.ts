import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\FoundingAdvisoryController::publish
* @see app/Http/Controllers/Advisor/FoundingAdvisoryController.php:50
* @route '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/roadmaps/{foundingRoadmapVersion}/publish'
*/
export const publish = (args: { foundingAdvisoryEngagement: string | number | { id: string | number }, foundingRoadmapVersion: string | number | { id: string | number } } | [foundingAdvisoryEngagement: string | number | { id: string | number }, foundingRoadmapVersion: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
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
publish.url = (args: { foundingAdvisoryEngagement: string | number | { id: string | number }, foundingRoadmapVersion: string | number | { id: string | number } } | [foundingAdvisoryEngagement: string | number | { id: string | number }, foundingRoadmapVersion: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
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
publish.patch = (args: { foundingAdvisoryEngagement: string | number | { id: string | number }, foundingRoadmapVersion: string | number | { id: string | number } } | [foundingAdvisoryEngagement: string | number | { id: string | number }, foundingRoadmapVersion: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: publish.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Advisor\FoundingAdvisoryController::publish
* @see app/Http/Controllers/Advisor/FoundingAdvisoryController.php:50
* @route '/advisor/founding-advisory-engagements/{foundingAdvisoryEngagement}/roadmaps/{foundingRoadmapVersion}/publish'
*/
const publishForm = (args: { foundingAdvisoryEngagement: string | number | { id: string | number }, foundingRoadmapVersion: string | number | { id: string | number } } | [foundingAdvisoryEngagement: string | number | { id: string | number }, foundingRoadmapVersion: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
publishForm.patch = (args: { foundingAdvisoryEngagement: string | number | { id: string | number }, foundingRoadmapVersion: string | number | { id: string | number } } | [foundingAdvisoryEngagement: string | number | { id: string | number }, foundingRoadmapVersion: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: publish.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

publish.form = publishForm

const roadmaps = {
    publish: Object.assign(publish, publish),
}

export default roadmaps