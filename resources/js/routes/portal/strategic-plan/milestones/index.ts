import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\StrategicPlanMilestoneController::update
 * @see app/Http/Controllers/Portal/StrategicPlanMilestoneController.php:24
 * @route '/portal/strategic-plan/milestones/{milestone}'
 */
export const update = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/portal/strategic-plan/milestones/{milestone}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Portal\StrategicPlanMilestoneController::update
 * @see app/Http/Controllers/Portal/StrategicPlanMilestoneController.php:24
 * @route '/portal/strategic-plan/milestones/{milestone}'
 */
update.url = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { milestone: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { milestone: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    milestone: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        milestone: typeof args.milestone === 'object'
                ? args.milestone.id
                : args.milestone,
                }

    return update.definition.url
            .replace('{milestone}', parsedArgs.milestone.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\StrategicPlanMilestoneController::update
 * @see app/Http/Controllers/Portal/StrategicPlanMilestoneController.php:24
 * @route '/portal/strategic-plan/milestones/{milestone}'
 */
update.patch = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Portal\StrategicPlanMilestoneController::update
 * @see app/Http/Controllers/Portal/StrategicPlanMilestoneController.php:24
 * @route '/portal/strategic-plan/milestones/{milestone}'
 */
    const updateForm = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\StrategicPlanMilestoneController::update
 * @see app/Http/Controllers/Portal/StrategicPlanMilestoneController.php:24
 * @route '/portal/strategic-plan/milestones/{milestone}'
 */
        updateForm.patch = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const milestones = {
    update: Object.assign(update, update),
}

export default milestones