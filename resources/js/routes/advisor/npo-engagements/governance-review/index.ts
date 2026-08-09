import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\NpoGovernanceReviewController::analysis
 * @see app/Http/Controllers/Advisor/NpoGovernanceReviewController.php:19
 * @route '/advisor/npo-engagements/{npoEngagement}/governance-review/analysis'
 */
export const analysis = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: analysis.url(args, options),
    method: 'post',
})

analysis.definition = {
    methods: ["post"],
    url: '/advisor/npo-engagements/{npoEngagement}/governance-review/analysis',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\NpoGovernanceReviewController::analysis
 * @see app/Http/Controllers/Advisor/NpoGovernanceReviewController.php:19
 * @route '/advisor/npo-engagements/{npoEngagement}/governance-review/analysis'
 */
analysis.url = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return analysis.definition.url
            .replace('{npoEngagement}', parsedArgs.npoEngagement.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\NpoGovernanceReviewController::analysis
 * @see app/Http/Controllers/Advisor/NpoGovernanceReviewController.php:19
 * @route '/advisor/npo-engagements/{npoEngagement}/governance-review/analysis'
 */
analysis.post = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: analysis.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\NpoGovernanceReviewController::analysis
 * @see app/Http/Controllers/Advisor/NpoGovernanceReviewController.php:19
 * @route '/advisor/npo-engagements/{npoEngagement}/governance-review/analysis'
 */
    const analysisForm = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: analysis.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\NpoGovernanceReviewController::analysis
 * @see app/Http/Controllers/Advisor/NpoGovernanceReviewController.php:19
 * @route '/advisor/npo-engagements/{npoEngagement}/governance-review/analysis'
 */
        analysisForm.post = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: analysis.url(args, options),
            method: 'post',
        })

    analysis.form = analysisForm
const governanceReview = {
    analysis: Object.assign(analysis, analysis),
}

export default governanceReview