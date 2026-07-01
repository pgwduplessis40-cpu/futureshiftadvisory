import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ClientStrategicBudgetController::approve
 * @see app/Http/Controllers/Advisor/ClientStrategicBudgetController.php:21
 * @route '/advisor/clients/{client}/strategic-budget/approve'
 */
export const approve = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: approve.url(args, options),
    method: 'patch',
})

approve.definition = {
    methods: ["patch"],
    url: '/advisor/clients/{client}/strategic-budget/approve',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\ClientStrategicBudgetController::approve
 * @see app/Http/Controllers/Advisor/ClientStrategicBudgetController.php:21
 * @route '/advisor/clients/{client}/strategic-budget/approve'
 */
approve.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { client: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                }

    return approve.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientStrategicBudgetController::approve
 * @see app/Http/Controllers/Advisor/ClientStrategicBudgetController.php:21
 * @route '/advisor/clients/{client}/strategic-budget/approve'
 */
approve.patch = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: approve.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientStrategicBudgetController::approve
 * @see app/Http/Controllers/Advisor/ClientStrategicBudgetController.php:21
 * @route '/advisor/clients/{client}/strategic-budget/approve'
 */
    const approveForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: approve.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientStrategicBudgetController::approve
 * @see app/Http/Controllers/Advisor/ClientStrategicBudgetController.php:21
 * @route '/advisor/clients/{client}/strategic-budget/approve'
 */
        approveForm.patch = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: approve.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    approve.form = approveForm
/**
* @see \App\Http\Controllers\Advisor\ClientStrategicBudgetController::advisorGoals
 * @see app/Http/Controllers/Advisor/ClientStrategicBudgetController.php:43
 * @route '/advisor/clients/{client}/strategic-budget/advisor-goals'
 */
export const advisorGoals = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: advisorGoals.url(args, options),
    method: 'patch',
})

advisorGoals.definition = {
    methods: ["patch"],
    url: '/advisor/clients/{client}/strategic-budget/advisor-goals',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\ClientStrategicBudgetController::advisorGoals
 * @see app/Http/Controllers/Advisor/ClientStrategicBudgetController.php:43
 * @route '/advisor/clients/{client}/strategic-budget/advisor-goals'
 */
advisorGoals.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { client: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                }

    return advisorGoals.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientStrategicBudgetController::advisorGoals
 * @see app/Http/Controllers/Advisor/ClientStrategicBudgetController.php:43
 * @route '/advisor/clients/{client}/strategic-budget/advisor-goals'
 */
advisorGoals.patch = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: advisorGoals.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientStrategicBudgetController::advisorGoals
 * @see app/Http/Controllers/Advisor/ClientStrategicBudgetController.php:43
 * @route '/advisor/clients/{client}/strategic-budget/advisor-goals'
 */
    const advisorGoalsForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: advisorGoals.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientStrategicBudgetController::advisorGoals
 * @see app/Http/Controllers/Advisor/ClientStrategicBudgetController.php:43
 * @route '/advisor/clients/{client}/strategic-budget/advisor-goals'
 */
        advisorGoalsForm.patch = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: advisorGoals.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    advisorGoals.form = advisorGoalsForm
const strategicBudget = {
    approve: Object.assign(approve, approve),
advisorGoals: Object.assign(advisorGoals, advisorGoals),
}

export default strategicBudget