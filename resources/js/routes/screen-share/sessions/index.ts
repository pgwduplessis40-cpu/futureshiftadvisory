import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
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
const sessions = {
    active: Object.assign(active, active),
signal: Object.assign(signal, signal),
iceServers: Object.assign(iceServers, iceServers),
heartbeat: Object.assign(heartbeat, heartbeat),
end: Object.assign(end, end),
}

export default sessions