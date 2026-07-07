import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\GoalController::store
 * @see app/Http/Controllers/Advisor/GoalController.php:22
 * @route '/advisor/clients/{client}/goals'
 */
export const store = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/goals',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\GoalController::store
 * @see app/Http/Controllers/Advisor/GoalController.php:22
 * @route '/advisor/clients/{client}/goals'
 */
store.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\GoalController::store
 * @see app/Http/Controllers/Advisor/GoalController.php:22
 * @route '/advisor/clients/{client}/goals'
 */
store.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\GoalController::store
 * @see app/Http/Controllers/Advisor/GoalController.php:22
 * @route '/advisor/clients/{client}/goals'
 */
    const storeForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\GoalController::store
 * @see app/Http/Controllers/Advisor/GoalController.php:22
 * @route '/advisor/clients/{client}/goals'
 */
        storeForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Advisor\GoalController::milestone
 * @see app/Http/Controllers/Advisor/GoalController.php:44
 * @route '/advisor/goals/{goal}/milestones'
 */
export const milestone = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: milestone.url(args, options),
    method: 'post',
})

milestone.definition = {
    methods: ["post"],
    url: '/advisor/goals/{goal}/milestones',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\GoalController::milestone
 * @see app/Http/Controllers/Advisor/GoalController.php:44
 * @route '/advisor/goals/{goal}/milestones'
 */
milestone.url = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return milestone.definition.url
            .replace('{goal}', parsedArgs.goal.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\GoalController::milestone
 * @see app/Http/Controllers/Advisor/GoalController.php:44
 * @route '/advisor/goals/{goal}/milestones'
 */
milestone.post = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: milestone.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\GoalController::milestone
 * @see app/Http/Controllers/Advisor/GoalController.php:44
 * @route '/advisor/goals/{goal}/milestones'
 */
    const milestoneForm = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: milestone.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\GoalController::milestone
 * @see app/Http/Controllers/Advisor/GoalController.php:44
 * @route '/advisor/goals/{goal}/milestones'
 */
        milestoneForm.post = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: milestone.url(args, options),
            method: 'post',
        })

    milestone.form = milestoneForm
/**
* @see \App\Http\Controllers\Advisor\GoalController::remeasure
 * @see app/Http/Controllers/Advisor/GoalController.php:66
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
 * @see app/Http/Controllers/Advisor/GoalController.php:66
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
 * @see app/Http/Controllers/Advisor/GoalController.php:66
 * @route '/advisor/goals/{goal}/remeasure'
 */
remeasure.post = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: remeasure.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\GoalController::remeasure
 * @see app/Http/Controllers/Advisor/GoalController.php:66
 * @route '/advisor/goals/{goal}/remeasure'
 */
    const remeasureForm = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: remeasure.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\GoalController::remeasure
 * @see app/Http/Controllers/Advisor/GoalController.php:66
 * @route '/advisor/goals/{goal}/remeasure'
 */
        remeasureForm.post = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: remeasure.url(args, options),
            method: 'post',
        })

    remeasure.form = remeasureForm
/**
* @see \App\Http\Controllers\Advisor\GoalController::achieve
 * @see app/Http/Controllers/Advisor/GoalController.php:90
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
 * @see app/Http/Controllers/Advisor/GoalController.php:90
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
 * @see app/Http/Controllers/Advisor/GoalController.php:90
 * @route '/advisor/goals/{goal}/achieve'
 */
achieve.patch = (args: { goal: string | { id: string } } | [goal: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: achieve.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\GoalController::achieve
 * @see app/Http/Controllers/Advisor/GoalController.php:90
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
 * @see app/Http/Controllers/Advisor/GoalController.php:90
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
/**
* @see \App\Http\Controllers\Advisor\GoalController::action
 * @see app/Http/Controllers/Advisor/GoalController.php:107
 * @route '/advisor/milestones/{milestone}/actions'
 */
export const action = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: action.url(args, options),
    method: 'post',
})

action.definition = {
    methods: ["post"],
    url: '/advisor/milestones/{milestone}/actions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\GoalController::action
 * @see app/Http/Controllers/Advisor/GoalController.php:107
 * @route '/advisor/milestones/{milestone}/actions'
 */
action.url = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return action.definition.url
            .replace('{milestone}', parsedArgs.milestone.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\GoalController::action
 * @see app/Http/Controllers/Advisor/GoalController.php:107
 * @route '/advisor/milestones/{milestone}/actions'
 */
action.post = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: action.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\GoalController::action
 * @see app/Http/Controllers/Advisor/GoalController.php:107
 * @route '/advisor/milestones/{milestone}/actions'
 */
    const actionForm = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: action.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\GoalController::action
 * @see app/Http/Controllers/Advisor/GoalController.php:107
 * @route '/advisor/milestones/{milestone}/actions'
 */
        actionForm.post = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: action.url(args, options),
            method: 'post',
        })

    action.form = actionForm
/**
* @see \App\Http\Controllers\Advisor\GoalController::proof
 * @see app/Http/Controllers/Advisor/GoalController.php:127
 * @route '/advisor/milestones/{milestone}/proof'
 */
export const proof = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: proof.url(args, options),
    method: 'post',
})

proof.definition = {
    methods: ["post"],
    url: '/advisor/milestones/{milestone}/proof',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\GoalController::proof
 * @see app/Http/Controllers/Advisor/GoalController.php:127
 * @route '/advisor/milestones/{milestone}/proof'
 */
proof.url = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return proof.definition.url
            .replace('{milestone}', parsedArgs.milestone.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\GoalController::proof
 * @see app/Http/Controllers/Advisor/GoalController.php:127
 * @route '/advisor/milestones/{milestone}/proof'
 */
proof.post = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: proof.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\GoalController::proof
 * @see app/Http/Controllers/Advisor/GoalController.php:127
 * @route '/advisor/milestones/{milestone}/proof'
 */
    const proofForm = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: proof.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\GoalController::proof
 * @see app/Http/Controllers/Advisor/GoalController.php:127
 * @route '/advisor/milestones/{milestone}/proof'
 */
        proofForm.post = (args: { milestone: string | { id: string } } | [milestone: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: proof.url(args, options),
            method: 'post',
        })

    proof.form = proofForm
const GoalController = { store, milestone, remeasure, achieve, action, proof }

export default GoalController