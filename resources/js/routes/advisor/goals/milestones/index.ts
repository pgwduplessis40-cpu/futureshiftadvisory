import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\GoalController::store
 * @see app/Http/Controllers/Advisor/GoalController.php:46
 * @route '/advisor/goals/{goal}/milestones'
 */
export const store = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/goals/{goal}/milestones',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\GoalController::store
 * @see app/Http/Controllers/Advisor/GoalController.php:46
 * @route '/advisor/goals/{goal}/milestones'
 */
store.url = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{goal}', parsedArgs.goal.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\GoalController::store
 * @see app/Http/Controllers/Advisor/GoalController.php:46
 * @route '/advisor/goals/{goal}/milestones'
 */
store.post = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\GoalController::store
 * @see app/Http/Controllers/Advisor/GoalController.php:46
 * @route '/advisor/goals/{goal}/milestones'
 */
    const storeForm = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\GoalController::store
 * @see app/Http/Controllers/Advisor/GoalController.php:46
 * @route '/advisor/goals/{goal}/milestones'
 */
        storeForm.post = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
const milestones = {
    store: Object.assign(store, store),
}

export default milestones