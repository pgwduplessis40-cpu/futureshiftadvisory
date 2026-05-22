import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\BriefingController::reviewIndustry
 * @see app/Http/Controllers/Advisor/BriefingController.php:19
 * @route '/advisor/industry-briefings/{industryBriefing}/review'
 */
export const reviewIndustry = (args: { industryBriefing: string | { id: string } } | [industryBriefing: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reviewIndustry.url(args, options),
    method: 'patch',
})

reviewIndustry.definition = {
    methods: ["patch"],
    url: '/advisor/industry-briefings/{industryBriefing}/review',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\BriefingController::reviewIndustry
 * @see app/Http/Controllers/Advisor/BriefingController.php:19
 * @route '/advisor/industry-briefings/{industryBriefing}/review'
 */
reviewIndustry.url = (args: { industryBriefing: string | { id: string } } | [industryBriefing: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return reviewIndustry.definition.url
            .replace('{industryBriefing}', parsedArgs.industryBriefing.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\BriefingController::reviewIndustry
 * @see app/Http/Controllers/Advisor/BriefingController.php:19
 * @route '/advisor/industry-briefings/{industryBriefing}/review'
 */
reviewIndustry.patch = (args: { industryBriefing: string | { id: string } } | [industryBriefing: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reviewIndustry.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\BriefingController::reviewIndustry
 * @see app/Http/Controllers/Advisor/BriefingController.php:19
 * @route '/advisor/industry-briefings/{industryBriefing}/review'
 */
    const reviewIndustryForm = (args: { industryBriefing: string | { id: string } } | [industryBriefing: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reviewIndustry.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\BriefingController::reviewIndustry
 * @see app/Http/Controllers/Advisor/BriefingController.php:19
 * @route '/advisor/industry-briefings/{industryBriefing}/review'
 */
        reviewIndustryForm.patch = (args: { industryBriefing: string | { id: string } } | [industryBriefing: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reviewIndustry.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    reviewIndustry.form = reviewIndustryForm
/**
* @see \App\Http\Controllers\Advisor\BriefingController::reviewPreMeeting
 * @see app/Http/Controllers/Advisor/BriefingController.php:32
 * @route '/advisor/pre-meeting-briefs/{preMeetingBrief}/review'
 */
export const reviewPreMeeting = (args: { preMeetingBrief: string | { id: string } } | [preMeetingBrief: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reviewPreMeeting.url(args, options),
    method: 'patch',
})

reviewPreMeeting.definition = {
    methods: ["patch"],
    url: '/advisor/pre-meeting-briefs/{preMeetingBrief}/review',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\BriefingController::reviewPreMeeting
 * @see app/Http/Controllers/Advisor/BriefingController.php:32
 * @route '/advisor/pre-meeting-briefs/{preMeetingBrief}/review'
 */
reviewPreMeeting.url = (args: { preMeetingBrief: string | { id: string } } | [preMeetingBrief: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return reviewPreMeeting.definition.url
            .replace('{preMeetingBrief}', parsedArgs.preMeetingBrief.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\BriefingController::reviewPreMeeting
 * @see app/Http/Controllers/Advisor/BriefingController.php:32
 * @route '/advisor/pre-meeting-briefs/{preMeetingBrief}/review'
 */
reviewPreMeeting.patch = (args: { preMeetingBrief: string | { id: string } } | [preMeetingBrief: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reviewPreMeeting.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\BriefingController::reviewPreMeeting
 * @see app/Http/Controllers/Advisor/BriefingController.php:32
 * @route '/advisor/pre-meeting-briefs/{preMeetingBrief}/review'
 */
    const reviewPreMeetingForm = (args: { preMeetingBrief: string | { id: string } } | [preMeetingBrief: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reviewPreMeeting.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\BriefingController::reviewPreMeeting
 * @see app/Http/Controllers/Advisor/BriefingController.php:32
 * @route '/advisor/pre-meeting-briefs/{preMeetingBrief}/review'
 */
        reviewPreMeetingForm.patch = (args: { preMeetingBrief: string | { id: string } } | [preMeetingBrief: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reviewPreMeeting.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    reviewPreMeeting.form = reviewPreMeetingForm
const BriefingController = { reviewIndustry, reviewPreMeeting }

export default BriefingController