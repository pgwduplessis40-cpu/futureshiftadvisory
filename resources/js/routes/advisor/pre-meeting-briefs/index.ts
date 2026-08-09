import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\BriefingController::review
 * @see app/Http/Controllers/Advisor/BriefingController.php:32
 * @route '/advisor/pre-meeting-briefs/{preMeetingBrief}/review'
 */
export const review = (args: { preMeetingBrief: string | { id: string } } | [preMeetingBrief: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: review.url(args, options),
    method: 'patch',
})

review.definition = {
    methods: ["patch"],
    url: '/advisor/pre-meeting-briefs/{preMeetingBrief}/review',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\BriefingController::review
 * @see app/Http/Controllers/Advisor/BriefingController.php:32
 * @route '/advisor/pre-meeting-briefs/{preMeetingBrief}/review'
 */
review.url = (args: { preMeetingBrief: string | { id: string } } | [preMeetingBrief: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { preMeetingBrief: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { preMeetingBrief: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    preMeetingBrief: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        preMeetingBrief: typeof args.preMeetingBrief === 'object'
                ? args.preMeetingBrief.id
                : args.preMeetingBrief,
                }

    return review.definition.url
            .replace('{preMeetingBrief}', parsedArgs.preMeetingBrief.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\BriefingController::review
 * @see app/Http/Controllers/Advisor/BriefingController.php:32
 * @route '/advisor/pre-meeting-briefs/{preMeetingBrief}/review'
 */
review.patch = (args: { preMeetingBrief: string | { id: string } } | [preMeetingBrief: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: review.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\BriefingController::review
 * @see app/Http/Controllers/Advisor/BriefingController.php:32
 * @route '/advisor/pre-meeting-briefs/{preMeetingBrief}/review'
 */
    const reviewForm = (args: { preMeetingBrief: string | { id: string } } | [preMeetingBrief: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
 * @see app/Http/Controllers/Advisor/BriefingController.php:32
 * @route '/advisor/pre-meeting-briefs/{preMeetingBrief}/review'
 */
        reviewForm.patch = (args: { preMeetingBrief: string | { id: string } } | [preMeetingBrief: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: review.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    review.form = reviewForm
const preMeetingBriefs = {
    review: Object.assign(review, review),
}

export default preMeetingBriefs