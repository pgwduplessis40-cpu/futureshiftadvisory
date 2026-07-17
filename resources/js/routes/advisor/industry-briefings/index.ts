import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\BriefingController::review
 * @see app/Http/Controllers/Advisor/BriefingController.php:19
 * @route '/advisor/industry-briefings/{industryBriefing}/review'
 */
export const review = (args: { industryBriefing: string | { id: string } } | [industryBriefing: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: review.url(args, options),
    method: 'patch',
})

review.definition = {
    methods: ["patch"],
    url: '/advisor/industry-briefings/{industryBriefing}/review',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\BriefingController::review
 * @see app/Http/Controllers/Advisor/BriefingController.php:19
 * @route '/advisor/industry-briefings/{industryBriefing}/review'
 */
review.url = (args: { industryBriefing: string | { id: string } } | [industryBriefing: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { industryBriefing: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { industryBriefing: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    industryBriefing: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        industryBriefing: typeof args.industryBriefing === 'object'
                ? args.industryBriefing.id
                : args.industryBriefing,
                }

    return review.definition.url
            .replace('{industryBriefing}', parsedArgs.industryBriefing.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\BriefingController::review
 * @see app/Http/Controllers/Advisor/BriefingController.php:19
 * @route '/advisor/industry-briefings/{industryBriefing}/review'
 */
review.patch = (args: { industryBriefing: string | { id: string } } | [industryBriefing: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: review.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\BriefingController::review
 * @see app/Http/Controllers/Advisor/BriefingController.php:19
 * @route '/advisor/industry-briefings/{industryBriefing}/review'
 */
    const reviewForm = (args: { industryBriefing: string | { id: string } } | [industryBriefing: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: review.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\BriefingController::review
 * @see app/Http/Controllers/Advisor/BriefingController.php:19
 * @route '/advisor/industry-briefings/{industryBriefing}/review'
 */
        reviewForm.patch = (args: { industryBriefing: string | { id: string } } | [industryBriefing: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: review.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    review.form = reviewForm
const industryBriefings = {
    review: Object.assign(review, review),
}

export default industryBriefings