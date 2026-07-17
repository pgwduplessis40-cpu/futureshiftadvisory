import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\GoalController::store
 * @see app/Http/Controllers/Advisor/GoalController.php:109
 * @route '/advisor/milestones/{milestone}/actions'
 */
export const store = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/milestones/{milestone}/actions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\GoalController::store
 * @see app/Http/Controllers/Advisor/GoalController.php:109
 * @route '/advisor/milestones/{milestone}/actions'
 */
store.url = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{milestone}', parsedArgs.milestone.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\GoalController::store
 * @see app/Http/Controllers/Advisor/GoalController.php:109
 * @route '/advisor/milestones/{milestone}/actions'
 */
store.post = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\GoalController::store
 * @see app/Http/Controllers/Advisor/GoalController.php:109
 * @route '/advisor/milestones/{milestone}/actions'
 */
    const storeForm = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\GoalController::store
 * @see app/Http/Controllers/Advisor/GoalController.php:109
 * @route '/advisor/milestones/{milestone}/actions'
 */
        storeForm.post = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
const actions = {
    store: Object.assign(store, store),
}

export default actions