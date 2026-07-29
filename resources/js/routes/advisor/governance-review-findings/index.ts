import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\NpoGovernanceReviewController::review
 * @see app/Http/Controllers/Advisor/NpoGovernanceReviewController.php:33
 * @route '/advisor/governance-review-findings/{governanceReviewFinding}/review'
 */
export const review = (args: { governanceReviewFinding: string | { id: string } } | [governanceReviewFinding: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: review.url(args, options),
    method: 'patch',
})

review.definition = {
    methods: ["patch"],
    url: '/advisor/governance-review-findings/{governanceReviewFinding}/review',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\NpoGovernanceReviewController::review
 * @see app/Http/Controllers/Advisor/NpoGovernanceReviewController.php:33
 * @route '/advisor/governance-review-findings/{governanceReviewFinding}/review'
 */
review.url = (args: { governanceReviewFinding: string | { id: string } } | [governanceReviewFinding: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { governanceReviewFinding: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { governanceReviewFinding: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    governanceReviewFinding: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        governanceReviewFinding: typeof args.governanceReviewFinding === 'object'
                ? args.governanceReviewFinding.id
                : args.governanceReviewFinding,
                }

    return review.definition.url
            .replace('{governanceReviewFinding}', parsedArgs.governanceReviewFinding.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\NpoGovernanceReviewController::review
 * @see app/Http/Controllers/Advisor/NpoGovernanceReviewController.php:33
 * @route '/advisor/governance-review-findings/{governanceReviewFinding}/review'
 */
review.patch = (args: { governanceReviewFinding: string | { id: string } } | [governanceReviewFinding: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: review.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\NpoGovernanceReviewController::review
 * @see app/Http/Controllers/Advisor/NpoGovernanceReviewController.php:33
 * @route '/advisor/governance-review-findings/{governanceReviewFinding}/review'
 */
    const reviewForm = (args: { governanceReviewFinding: string | { id: string } } | [governanceReviewFinding: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: review.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\NpoGovernanceReviewController::review
 * @see app/Http/Controllers/Advisor/NpoGovernanceReviewController.php:33
 * @route '/advisor/governance-review-findings/{governanceReviewFinding}/review'
 */
        reviewForm.patch = (args: { governanceReviewFinding: string | { id: string } } | [governanceReviewFinding: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: review.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    review.form = reviewForm
const governanceReviewFindings = {
    review: Object.assign(review, review),
}

export default governanceReviewFindings