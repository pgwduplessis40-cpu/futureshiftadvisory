import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import milestones from './milestones'
/**
* @see \App\Http\Controllers\Advisor\GoalController::remeasure
 * @see app/Http/Controllers/Advisor/GoalController.php:68
 * @route '/advisor/goals/{goal}/remeasure'
 */
export const remeasure = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: remeasure.url(args, options),
    method: 'post',
})

remeasure.definition = {
    methods: ["post"],
    url: '/advisor/goals/{goal}/remeasure',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\GoalController::remeasure
 * @see app/Http/Controllers/Advisor/GoalController.php:68
 * @route '/advisor/goals/{goal}/remeasure'
 */
remeasure.url = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { goal: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { goal: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    goal: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        goal: typeof args.goal === 'object'
                ? args.goal.id
                : args.goal,
                }

    return remeasure.definition.url
            .replace('{goal}', parsedArgs.goal.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\GoalController::remeasure
 * @see app/Http/Controllers/Advisor/GoalController.php:68
 * @route '/advisor/goals/{goal}/remeasure'
 */
remeasure.post = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: remeasure.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\GoalController::remeasure
 * @see app/Http/Controllers/Advisor/GoalController.php:68
 * @route '/advisor/goals/{goal}/remeasure'
 */
    const remeasureForm = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: remeasure.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\GoalController::remeasure
 * @see app/Http/Controllers/Advisor/GoalController.php:68
 * @route '/advisor/goals/{goal}/remeasure'
 */
        remeasureForm.post = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: remeasure.url(args, options),
            method: 'post',
        })
    
    remeasure.form = remeasureForm
/**
* @see \App\Http\Controllers\Advisor\GoalController::achieve
 * @see app/Http/Controllers/Advisor/GoalController.php:92
 * @route '/advisor/goals/{goal}/achieve'
 */
export const achieve = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: achieve.url(args, options),
    method: 'patch',
})

achieve.definition = {
    methods: ["patch"],
    url: '/advisor/goals/{goal}/achieve',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\GoalController::achieve
 * @see app/Http/Controllers/Advisor/GoalController.php:92
 * @route '/advisor/goals/{goal}/achieve'
 */
achieve.url = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { goal: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { goal: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    goal: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        goal: typeof args.goal === 'object'
                ? args.goal.id
                : args.goal,
                }

    return achieve.definition.url
            .replace('{goal}', parsedArgs.goal.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\GoalController::achieve
 * @see app/Http/Controllers/Advisor/GoalController.php:92
 * @route '/advisor/goals/{goal}/achieve'
 */
achieve.patch = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: achieve.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\GoalController::achieve
 * @see app/Http/Controllers/Advisor/GoalController.php:92
 * @route '/advisor/goals/{goal}/achieve'
 */
    const achieveForm = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: achieve.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\GoalController::achieve
 * @see app/Http/Controllers/Advisor/GoalController.php:92
 * @route '/advisor/goals/{goal}/achieve'
 */
        achieveForm.patch = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: achieve.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    achieve.form = achieveForm
const goals = {
    milestones: Object.assign(milestones, milestones),
remeasure: Object.assign(remeasure, remeasure),
achieve: Object.assign(achieve, achieve),
}

export default goals