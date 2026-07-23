import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::store
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:25
 * @route '/advisor/clients/{client}/co-browse-sessions'
 */
export const store = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/co-browse-sessions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::store
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:25
 * @route '/advisor/clients/{client}/co-browse-sessions'
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
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::store
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:25
 * @route '/advisor/clients/{client}/co-browse-sessions'
 */
store.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::store
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:25
 * @route '/advisor/clients/{client}/co-browse-sessions'
 */
    const storeForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::store
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:25
 * @route '/advisor/clients/{client}/co-browse-sessions'
 */
        storeForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::storeForEntrepreneur
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:44
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/co-browse-sessions'
 */
export const storeForEntrepreneur = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeForEntrepreneur.url(args, options),
    method: 'post',
})

storeForEntrepreneur.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/co-browse-sessions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::storeForEntrepreneur
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:44
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/co-browse-sessions'
 */
storeForEntrepreneur.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { entrepreneurProfile: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { entrepreneurProfile: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                }

    return storeForEntrepreneur.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::storeForEntrepreneur
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:44
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/co-browse-sessions'
 */
storeForEntrepreneur.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeForEntrepreneur.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::storeForEntrepreneur
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:44
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/co-browse-sessions'
 */
    const storeForEntrepreneurForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: storeForEntrepreneur.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::storeForEntrepreneur
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:44
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/co-browse-sessions'
 */
        storeForEntrepreneurForm.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: storeForEntrepreneur.url(args, options),
            method: 'post',
        })

    storeForEntrepreneur.form = storeForEntrepreneurForm
/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::respond
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:63
 * @route '/portal/co-browse-sessions/{session}/response'
 */
export const respond = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: respond.url(args, options),
    method: 'post',
})

respond.definition = {
    methods: ["post"],
    url: '/portal/co-browse-sessions/{session}/response',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::respond
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:63
 * @route '/portal/co-browse-sessions/{session}/response'
 */
respond.url = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return respond.definition.url
            .replace('{session}', parsedArgs.session.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::respond
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:63
 * @route '/portal/co-browse-sessions/{session}/response'
 */
respond.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: respond.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::respond
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:63
 * @route '/portal/co-browse-sessions/{session}/response'
 */
    const respondForm = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: respond.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::respond
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:63
 * @route '/portal/co-browse-sessions/{session}/response'
 */
        respondForm.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: respond.url(args, options),
            method: 'post',
        })

    respond.form = respondForm
/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::action
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:83
 * @route '/co-browse/sessions/{session}/actions'
 */
export const action = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: action.url(args, options),
    method: 'post',
})

action.definition = {
    methods: ["post"],
    url: '/co-browse/sessions/{session}/actions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::action
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:83
 * @route '/co-browse/sessions/{session}/actions'
 */
action.url = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return action.definition.url
            .replace('{session}', parsedArgs.session.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::action
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:83
 * @route '/co-browse/sessions/{session}/actions'
 */
action.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: action.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::action
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:83
 * @route '/co-browse/sessions/{session}/actions'
 */
    const actionForm = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: action.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::action
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:83
 * @route '/co-browse/sessions/{session}/actions'
 */
        actionForm.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: action.url(args, options),
            method: 'post',
        })

    action.form = actionForm
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
const CoBrowseSessionController = { store, storeForEntrepreneur, respond, action, pendingActions, status, heartbeat, end }

export default CoBrowseSessionController