import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import actions from './actions'
/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::pendingActions
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:103
 * @route '/co-browse/sessions/{session}/pending-actions'
 */
export const pendingActions = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pendingActions.url(args, options),
    method: 'post',
})

pendingActions.definition = {
    methods: ["post"],
    url: '/co-browse/sessions/{session}/pending-actions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::pendingActions
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:103
 * @route '/co-browse/sessions/{session}/pending-actions'
 */
pendingActions.url = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { session: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { session: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    session: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        session: typeof args.session === 'object'
                ? args.session.id
                : args.session,
                }

    return pendingActions.definition.url
            .replace('{session}', parsedArgs.session.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::pendingActions
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:103
 * @route '/co-browse/sessions/{session}/pending-actions'
 */
pendingActions.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pendingActions.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::pendingActions
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:103
 * @route '/co-browse/sessions/{session}/pending-actions'
 */
    const pendingActionsForm = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: pendingActions.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::pendingActions
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:103
 * @route '/co-browse/sessions/{session}/pending-actions'
 */
        pendingActionsForm.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: pendingActions.url(args, options),
            method: 'post',
        })

    pendingActions.form = pendingActionsForm
/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::status
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:122
 * @route '/co-browse/sessions/{session}/status'
 */
export const status = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: status.url(args, options),
    method: 'post',
})

status.definition = {
    methods: ["post"],
    url: '/co-browse/sessions/{session}/status',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::status
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:122
 * @route '/co-browse/sessions/{session}/status'
 */
status.url = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { session: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { session: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    session: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        session: typeof args.session === 'object'
                ? args.session.id
                : args.session,
                }

    return status.definition.url
            .replace('{session}', parsedArgs.session.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::status
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:122
 * @route '/co-browse/sessions/{session}/status'
 */
status.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: status.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::status
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:122
 * @route '/co-browse/sessions/{session}/status'
 */
    const statusForm = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: status.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::status
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:122
 * @route '/co-browse/sessions/{session}/status'
 */
        statusForm.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: status.url(args, options),
            method: 'post',
        })

    status.form = statusForm
/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::heartbeat
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:130
 * @route '/co-browse/sessions/{session}/heartbeat'
 */
export const heartbeat = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: heartbeat.url(args, options),
    method: 'post',
})

heartbeat.definition = {
    methods: ["post"],
    url: '/co-browse/sessions/{session}/heartbeat',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::heartbeat
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:130
 * @route '/co-browse/sessions/{session}/heartbeat'
 */
heartbeat.url = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { session: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { session: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    session: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        session: typeof args.session === 'object'
                ? args.session.id
                : args.session,
                }

    return heartbeat.definition.url
            .replace('{session}', parsedArgs.session.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::heartbeat
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:130
 * @route '/co-browse/sessions/{session}/heartbeat'
 */
heartbeat.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: heartbeat.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::heartbeat
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:130
 * @route '/co-browse/sessions/{session}/heartbeat'
 */
    const heartbeatForm = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: heartbeat.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::heartbeat
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:130
 * @route '/co-browse/sessions/{session}/heartbeat'
 */
        heartbeatForm.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: heartbeat.url(args, options),
            method: 'post',
        })

    heartbeat.form = heartbeatForm
/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::end
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:138
 * @route '/co-browse/sessions/{session}/end'
 */
export const end = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: end.url(args, options),
    method: 'post',
})

end.definition = {
    methods: ["post"],
    url: '/co-browse/sessions/{session}/end',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::end
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:138
 * @route '/co-browse/sessions/{session}/end'
 */
end.url = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { session: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { session: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    session: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        session: typeof args.session === 'object'
                ? args.session.id
                : args.session,
                }

    return end.definition.url
            .replace('{session}', parsedArgs.session.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::end
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:138
 * @route '/co-browse/sessions/{session}/end'
 */
end.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: end.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::end
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:138
 * @route '/co-browse/sessions/{session}/end'
 */
    const endForm = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: end.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::end
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:138
 * @route '/co-browse/sessions/{session}/end'
 */
        endForm.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: end.url(args, options),
            method: 'post',
        })

    end.form = endForm
const sessions = {
    actions: Object.assign(actions, actions),
pendingActions: Object.assign(pendingActions, pendingActions),
status: Object.assign(status, status),
heartbeat: Object.assign(heartbeat, heartbeat),
end: Object.assign(end, end),
}

export default sessions