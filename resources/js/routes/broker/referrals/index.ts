import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Broker\ReferralStageController::__invoke
 * @see app/Http/Controllers/Broker/ReferralStageController.php:22
 * @route '/broker/referrals/{referral}/stage'
 */
export const stage = (args: { referral: string | { id: string } } | [referral: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: stage.url(args, options),
    method: 'patch',
})

stage.definition = {
    methods: ["patch"],
    url: '/broker/referrals/{referral}/stage',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Broker\ReferralStageController::__invoke
 * @see app/Http/Controllers/Broker/ReferralStageController.php:22
 * @route '/broker/referrals/{referral}/stage'
 */
stage.url = (args: { referral: string | { id: string } } | [referral: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return stage.definition.url
            .replace('{referral}', parsedArgs.referral.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Broker\ReferralStageController::__invoke
 * @see app/Http/Controllers/Broker/ReferralStageController.php:22
 * @route '/broker/referrals/{referral}/stage'
 */
stage.patch = (args: { referral: string | { id: string } } | [referral: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: stage.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Broker\ReferralStageController::__invoke
 * @see app/Http/Controllers/Broker/ReferralStageController.php:22
 * @route '/broker/referrals/{referral}/stage'
 */
    const stageForm = (args: { referral: string | { id: string } } | [referral: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: stage.url(args, {
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
        stageForm.patch = (args: { referral: string | { id: string } } | [referral: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: stage.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    stage.form = stageForm
const referrals = {
    stage: Object.assign(stage, stage),
}

export default referrals