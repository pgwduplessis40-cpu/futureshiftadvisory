import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\NpoGovernanceReviewController::run
 * @see app/Http/Controllers/Advisor/NpoGovernanceReviewController.php:19
 * @route '/advisor/npo-engagements/{npoEngagement}/governance-review/analysis'
 */
export const run = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: run.url(args, options),
    method: 'post',
})

run.definition = {
    methods: ["post"],
    url: '/advisor/npo-engagements/{npoEngagement}/governance-review/analysis',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\NpoGovernanceReviewController::run
 * @see app/Http/Controllers/Advisor/NpoGovernanceReviewController.php:19
 * @route '/advisor/npo-engagements/{npoEngagement}/governance-review/analysis'
 */
run.url = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { npoEngagement: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { npoEngagement: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    npoEngagement: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        npoEngagement: typeof args.npoEngagement === 'object'
                ? args.npoEngagement.id
                : args.npoEngagement,
                }

    return run.definition.url
            .replace('{npoEngagement}', parsedArgs.npoEngagement.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\NpoGovernanceReviewController::run
 * @see app/Http/Controllers/Advisor/NpoGovernanceReviewController.php:19
 * @route '/advisor/npo-engagements/{npoEngagement}/governance-review/analysis'
 */
run.post = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: run.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\NpoGovernanceReviewController::run
 * @see app/Http/Controllers/Advisor/NpoGovernanceReviewController.php:19
 * @route '/advisor/npo-engagements/{npoEngagement}/governance-review/analysis'
 */
    const runForm = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: run.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\NpoGovernanceReviewController::run
 * @see app/Http/Controllers/Advisor/NpoGovernanceReviewController.php:19
 * @route '/advisor/npo-engagements/{npoEngagement}/governance-review/analysis'
 */
        runForm.post = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: run.url(args, options),
            method: 'post',
        })
    
    run.form = runForm
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
const NpoGovernanceReviewController = { run, review }

export default NpoGovernanceReviewController