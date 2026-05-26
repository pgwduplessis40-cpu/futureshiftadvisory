import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Broker\ReferralStageController::__invoke
 * @see app/Http/Controllers/Broker/ReferralStageController.php:22
 * @route '/broker/referrals/{referral}/stage'
 */
const ReferralStageController = (args: { referral: string | { id: string } } | [referral: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: ReferralStageController.url(args, options),
    method: 'patch',
})

ReferralStageController.definition = {
    methods: ["patch"],
    url: '/broker/referrals/{referral}/stage',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Broker\ReferralStageController::__invoke
 * @see app/Http/Controllers/Broker/ReferralStageController.php:22
 * @route '/broker/referrals/{referral}/stage'
 */
ReferralStageController.url = (args: { referral: string | { id: string } } | [referral: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { referral: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { referral: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    referral: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        referral: typeof args.referral === 'object'
                ? args.referral.id
                : args.referral,
                }

    return ReferralStageController.definition.url
            .replace('{referral}', parsedArgs.referral.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Broker\ReferralStageController::__invoke
 * @see app/Http/Controllers/Broker/ReferralStageController.php:22
 * @route '/broker/referrals/{referral}/stage'
 */
ReferralStageController.patch = (args: { referral: string | { id: string } } | [referral: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: ReferralStageController.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Broker\ReferralStageController::__invoke
 * @see app/Http/Controllers/Broker/ReferralStageController.php:22
 * @route '/broker/referrals/{referral}/stage'
 */
    const ReferralStageControllerForm = (args: { referral: string | { id: string } } | [referral: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: ReferralStageController.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Broker\ReferralStageController::__invoke
 * @see app/Http/Controllers/Broker/ReferralStageController.php:22
 * @route '/broker/referrals/{referral}/stage'
 */
        ReferralStageControllerForm.patch = (args: { referral: string | { id: string } } | [referral: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: ReferralStageController.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    ReferralStageController.form = ReferralStageControllerForm
export default ReferralStageController
