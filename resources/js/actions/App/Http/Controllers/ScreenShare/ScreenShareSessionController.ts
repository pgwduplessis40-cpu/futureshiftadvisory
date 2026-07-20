import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::store
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:24
 * @route '/advisor/clients/{client}/screen-share-sessions'
 */
export const store = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/screen-share-sessions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::store
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:24
 * @route '/advisor/clients/{client}/screen-share-sessions'
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
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::store
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:24
 * @route '/advisor/clients/{client}/screen-share-sessions'
 */
store.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::store
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:24
 * @route '/advisor/clients/{client}/screen-share-sessions'
 */
    const storeForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::store
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:24
 * @route '/advisor/clients/{client}/screen-share-sessions'
 */
        storeForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::respond
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:53
 * @route '/portal/screen-share-sessions/{session}/response'
 */
export const respond = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: respond.url(args, options),
    method: 'post',
})

respond.definition = {
    methods: ["post"],
    url: '/portal/screen-share-sessions/{session}/response',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::respond
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:53
 * @route '/portal/screen-share-sessions/{session}/response'
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
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::respond
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:53
 * @route '/portal/screen-share-sessions/{session}/response'
 */
respond.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: respond.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::respond
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:53
 * @route '/portal/screen-share-sessions/{session}/response'
 */
    const respondForm = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: respond.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::respond
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:53
 * @route '/portal/screen-share-sessions/{session}/response'
 */
        respondForm.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: respond.url(args, options),
            method: 'post',
        })

    respond.form = respondForm
/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::browserPermission
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:66
 * @route '/portal/screen-share-sessions/{session}/browser-permission'
 */
export const browserPermission = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: browserPermission.url(args, options),
    method: 'post',
})

browserPermission.definition = {
    methods: ["post"],
    url: '/portal/screen-share-sessions/{session}/browser-permission',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::browserPermission
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:66
 * @route '/portal/screen-share-sessions/{session}/browser-permission'
 */
browserPermission.url = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return browserPermission.definition.url
            .replace('{session}', parsedArgs.session.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::browserPermission
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:66
 * @route '/portal/screen-share-sessions/{session}/browser-permission'
 */
browserPermission.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: browserPermission.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::browserPermission
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:66
 * @route '/portal/screen-share-sessions/{session}/browser-permission'
 */
    const browserPermissionForm = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: browserPermission.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::browserPermission
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:66
 * @route '/portal/screen-share-sessions/{session}/browser-permission'
 */
        browserPermissionForm.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: browserPermission.url(args, options),
            method: 'post',
        })

    browserPermission.form = browserPermissionForm
/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::active
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:79
 * @route '/screen-share/sessions/{session}/active'
 */
export const active = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: active.url(args, options),
    method: 'post',
})

active.definition = {
    methods: ["post"],
    url: '/screen-share/sessions/{session}/active',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::active
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:79
 * @route '/screen-share/sessions/{session}/active'
 */
active.url = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return active.definition.url
            .replace('{session}', parsedArgs.session.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::active
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:79
 * @route '/screen-share/sessions/{session}/active'
 */
active.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: active.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::active
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:79
 * @route '/screen-share/sessions/{session}/active'
 */
    const activeForm = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: active.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::active
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:79
 * @route '/screen-share/sessions/{session}/active'
 */
        activeForm.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: active.url(args, options),
            method: 'post',
        })

    active.form = activeForm
/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::signal
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:90
 * @route '/screen-share/sessions/{session}/signal'
 */
export const signal = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: signal.url(args, options),
    method: 'post',
})

signal.definition = {
    methods: ["post"],
    url: '/screen-share/sessions/{session}/signal',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::signal
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:90
 * @route '/screen-share/sessions/{session}/signal'
 */
signal.url = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return signal.definition.url
            .replace('{session}', parsedArgs.session.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::signal
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:90
 * @route '/screen-share/sessions/{session}/signal'
 */
signal.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: signal.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::signal
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:90
 * @route '/screen-share/sessions/{session}/signal'
 */
    const signalForm = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: signal.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::signal
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:90
 * @route '/screen-share/sessions/{session}/signal'
 */
        signalForm.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: signal.url(args, options),
            method: 'post',
        })

    signal.form = signalForm
/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::iceServers
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:104
 * @route '/screen-share/sessions/{session}/ice-servers'
 */
export const iceServers = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: iceServers.url(args, options),
    method: 'post',
})

iceServers.definition = {
    methods: ["post"],
    url: '/screen-share/sessions/{session}/ice-servers',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::iceServers
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:104
 * @route '/screen-share/sessions/{session}/ice-servers'
 */
iceServers.url = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
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

    return iceServers.definition.url
            .replace('{session}', parsedArgs.session.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::iceServers
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:104
 * @route '/screen-share/sessions/{session}/ice-servers'
 */
iceServers.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: iceServers.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::iceServers
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:104
 * @route '/screen-share/sessions/{session}/ice-servers'
 */
    const iceServersForm = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: iceServers.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::iceServers
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:104
 * @route '/screen-share/sessions/{session}/ice-servers'
 */
        iceServersForm.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: iceServers.url(args, options),
            method: 'post',
        })

    iceServers.form = iceServersForm
/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::heartbeat
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:119
 * @route '/screen-share/sessions/{session}/heartbeat'
 */
export const heartbeat = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: heartbeat.url(args, options),
    method: 'post',
})

heartbeat.definition = {
    methods: ["post"],
    url: '/screen-share/sessions/{session}/heartbeat',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::heartbeat
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:119
 * @route '/screen-share/sessions/{session}/heartbeat'
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
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::heartbeat
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:119
 * @route '/screen-share/sessions/{session}/heartbeat'
 */
heartbeat.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: heartbeat.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::heartbeat
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:119
 * @route '/screen-share/sessions/{session}/heartbeat'
 */
    const heartbeatForm = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: heartbeat.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::heartbeat
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:119
 * @route '/screen-share/sessions/{session}/heartbeat'
 */
        heartbeatForm.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: heartbeat.url(args, options),
            method: 'post',
        })

    heartbeat.form = heartbeatForm
/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::end
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:130
 * @route '/screen-share/sessions/{session}/end'
 */
export const end = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: end.url(args, options),
    method: 'post',
})

end.definition = {
    methods: ["post"],
    url: '/screen-share/sessions/{session}/end',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::end
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:130
 * @route '/screen-share/sessions/{session}/end'
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
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::end
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:130
 * @route '/screen-share/sessions/{session}/end'
 */
end.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: end.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::end
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:130
 * @route '/screen-share/sessions/{session}/end'
 */
    const endForm = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: end.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareSessionController::end
 * @see app/Http/Controllers/ScreenShare/ScreenShareSessionController.php:130
 * @route '/screen-share/sessions/{session}/end'
 */
        endForm.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: end.url(args, options),
            method: 'post',
        })

    end.form = endForm
const ScreenShareSessionController = { store, respond, browserPermission, active, signal, iceServers, heartbeat, end }

export default ScreenShareSessionController