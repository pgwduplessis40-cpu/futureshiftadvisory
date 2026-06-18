import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/calendar'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/calendar',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/calendar'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/calendar'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/calendar'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/calendar'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/calendar'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/calendar'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\Settings\CalendarController::edit
 * @see app/Http/Controllers/Settings/CalendarController.php:29
 * @route '/settings/calendar'
 */
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/calendar',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\CalendarController::edit
 * @see app/Http/Controllers/Settings/CalendarController.php:29
 * @route '/settings/calendar'
 */
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\CalendarController::edit
 * @see app/Http/Controllers/Settings/CalendarController.php:29
 * @route '/settings/calendar'
 */
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Settings\CalendarController::edit
 * @see app/Http/Controllers/Settings/CalendarController.php:29
 * @route '/settings/calendar'
 */
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Settings\CalendarController::edit
 * @see app/Http/Controllers/Settings/CalendarController.php:29
 * @route '/settings/calendar'
 */
    const editForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: edit.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Settings\CalendarController::edit
 * @see app/Http/Controllers/Settings/CalendarController.php:29
 * @route '/settings/calendar'
 */
        editForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Settings\CalendarController::edit
 * @see app/Http/Controllers/Settings/CalendarController.php:29
 * @route '/settings/calendar'
 */
        editForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    edit.form = editForm
/**
* @see \App\Http\Controllers\Settings\CalendarController::connect
 * @see app/Http/Controllers/Settings/CalendarController.php:74
 * @route '/settings/calendar/{provider}/connect'
 */
export const connect = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: connect.url(args, options),
    method: 'get',
})

connect.definition = {
    methods: ["get","head"],
    url: '/settings/calendar/{provider}/connect',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\CalendarController::connect
 * @see app/Http/Controllers/Settings/CalendarController.php:74
 * @route '/settings/calendar/{provider}/connect'
 */
connect.url = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { provider: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    provider: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        provider: args.provider,
                }

    return connect.definition.url
            .replace('{provider}', parsedArgs.provider.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\CalendarController::connect
 * @see app/Http/Controllers/Settings/CalendarController.php:74
 * @route '/settings/calendar/{provider}/connect'
 */
connect.get = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: connect.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Settings\CalendarController::connect
 * @see app/Http/Controllers/Settings/CalendarController.php:74
 * @route '/settings/calendar/{provider}/connect'
 */
connect.head = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: connect.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Settings\CalendarController::connect
 * @see app/Http/Controllers/Settings/CalendarController.php:74
 * @route '/settings/calendar/{provider}/connect'
 */
    const connectForm = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: connect.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Settings\CalendarController::connect
 * @see app/Http/Controllers/Settings/CalendarController.php:74
 * @route '/settings/calendar/{provider}/connect'
 */
        connectForm.get = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: connect.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Settings\CalendarController::connect
 * @see app/Http/Controllers/Settings/CalendarController.php:74
 * @route '/settings/calendar/{provider}/connect'
 */
        connectForm.head = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: connect.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    connect.form = connectForm
/**
* @see \App\Http\Controllers\Settings\CalendarController::callback
 * @see app/Http/Controllers/Settings/CalendarController.php:82
 * @route '/settings/calendar/{provider}/callback'
 */
export const callback = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(args, options),
    method: 'get',
})

callback.definition = {
    methods: ["get","head"],
    url: '/settings/calendar/{provider}/callback',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\CalendarController::callback
 * @see app/Http/Controllers/Settings/CalendarController.php:82
 * @route '/settings/calendar/{provider}/callback'
 */
callback.url = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { provider: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    provider: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        provider: args.provider,
                }

    return callback.definition.url
            .replace('{provider}', parsedArgs.provider.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\CalendarController::callback
 * @see app/Http/Controllers/Settings/CalendarController.php:82
 * @route '/settings/calendar/{provider}/callback'
 */
callback.get = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: callback.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Settings\CalendarController::callback
 * @see app/Http/Controllers/Settings/CalendarController.php:82
 * @route '/settings/calendar/{provider}/callback'
 */
callback.head = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: callback.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Settings\CalendarController::callback
 * @see app/Http/Controllers/Settings/CalendarController.php:82
 * @route '/settings/calendar/{provider}/callback'
 */
    const callbackForm = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: callback.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Settings\CalendarController::callback
 * @see app/Http/Controllers/Settings/CalendarController.php:82
 * @route '/settings/calendar/{provider}/callback'
 */
        callbackForm.get = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: callback.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Settings\CalendarController::callback
 * @see app/Http/Controllers/Settings/CalendarController.php:82
 * @route '/settings/calendar/{provider}/callback'
 */
        callbackForm.head = (args: { provider: string | number } | [provider: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: callback.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    callback.form = callbackForm
/**
* @see \App\Http\Controllers\Settings\CalendarController::sync
 * @see app/Http/Controllers/Settings/CalendarController.php:117
 * @route '/settings/calendar/{calendarConnection}/sync'
 */
export const sync = (args: { calendarConnection: string | { id: string } } | [calendarConnection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sync.url(args, options),
    method: 'post',
})

sync.definition = {
    methods: ["post"],
    url: '/settings/calendar/{calendarConnection}/sync',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\CalendarController::sync
 * @see app/Http/Controllers/Settings/CalendarController.php:117
 * @route '/settings/calendar/{calendarConnection}/sync'
 */
sync.url = (args: { calendarConnection: string | { id: string } } | [calendarConnection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { calendarConnection: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { calendarConnection: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    calendarConnection: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        calendarConnection: typeof args.calendarConnection === 'object'
                ? args.calendarConnection.id
                : args.calendarConnection,
                }

    return sync.definition.url
            .replace('{calendarConnection}', parsedArgs.calendarConnection.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\CalendarController::sync
 * @see app/Http/Controllers/Settings/CalendarController.php:117
 * @route '/settings/calendar/{calendarConnection}/sync'
 */
sync.post = (args: { calendarConnection: string | { id: string } } | [calendarConnection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sync.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Settings\CalendarController::sync
 * @see app/Http/Controllers/Settings/CalendarController.php:117
 * @route '/settings/calendar/{calendarConnection}/sync'
 */
    const syncForm = (args: { calendarConnection: string | { id: string } } | [calendarConnection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: sync.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Settings\CalendarController::sync
 * @see app/Http/Controllers/Settings/CalendarController.php:117
 * @route '/settings/calendar/{calendarConnection}/sync'
 */
        syncForm.post = (args: { calendarConnection: string | { id: string } } | [calendarConnection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: sync.url(args, options),
            method: 'post',
        })
    
    sync.form = syncForm
/**
* @see \App\Http\Controllers\Settings\CalendarController::revoke
 * @see app/Http/Controllers/Settings/CalendarController.php:150
 * @route '/settings/calendar/{calendarConnection}/revoke'
 */
export const revoke = (args: { calendarConnection: string | { id: string } } | [calendarConnection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: revoke.url(args, options),
    method: 'patch',
})

revoke.definition = {
    methods: ["patch"],
    url: '/settings/calendar/{calendarConnection}/revoke',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Settings\CalendarController::revoke
 * @see app/Http/Controllers/Settings/CalendarController.php:150
 * @route '/settings/calendar/{calendarConnection}/revoke'
 */
revoke.url = (args: { calendarConnection: string | { id: string } } | [calendarConnection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { calendarConnection: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { calendarConnection: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    calendarConnection: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        calendarConnection: typeof args.calendarConnection === 'object'
                ? args.calendarConnection.id
                : args.calendarConnection,
                }

    return revoke.definition.url
            .replace('{calendarConnection}', parsedArgs.calendarConnection.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\CalendarController::revoke
 * @see app/Http/Controllers/Settings/CalendarController.php:150
 * @route '/settings/calendar/{calendarConnection}/revoke'
 */
revoke.patch = (args: { calendarConnection: string | { id: string } } | [calendarConnection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: revoke.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Settings\CalendarController::revoke
 * @see app/Http/Controllers/Settings/CalendarController.php:150
 * @route '/settings/calendar/{calendarConnection}/revoke'
 */
    const revokeForm = (args: { calendarConnection: string | { id: string } } | [calendarConnection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: revoke.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Settings\CalendarController::revoke
 * @see app/Http/Controllers/Settings/CalendarController.php:150
 * @route '/settings/calendar/{calendarConnection}/revoke'
 */
        revokeForm.patch = (args: { calendarConnection: string | { id: string } } | [calendarConnection: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: revoke.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    revoke.form = revokeForm
const calendar = {
    index: Object.assign(index, index),
edit: Object.assign(edit, edit),
connect: Object.assign(connect, connect),
callback: Object.assign(callback, callback),
sync: Object.assign(sync, sync),
revoke: Object.assign(revoke, revoke),
}

export default calendar