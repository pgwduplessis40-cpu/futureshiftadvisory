import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::pendingPrompt
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:52
 * @route '/co-browse/connections/{connection}/pending-prompt'
 */
export const pendingPrompt = (args: { connection: string | number | { id: string | number } } | [connection: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pendingPrompt.url(args, options),
    method: 'post',
})

pendingPrompt.definition = {
    methods: ["post"],
    url: '/co-browse/connections/{connection}/pending-prompt',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::pendingPrompt
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:52
 * @route '/co-browse/connections/{connection}/pending-prompt'
 */
pendingPrompt.url = (args: { connection: string | number | { id: string | number } } | [connection: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { connection: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { connection: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    connection: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        connection: typeof args.connection === 'object'
                ? args.connection.id
                : args.connection,
                }

    return pendingPrompt.definition.url
            .replace('{connection}', parsedArgs.connection.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::pendingPrompt
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:52
 * @route '/co-browse/connections/{connection}/pending-prompt'
 */
pendingPrompt.post = (args: { connection: string | number | { id: string | number } } | [connection: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pendingPrompt.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::pendingPrompt
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:52
 * @route '/co-browse/connections/{connection}/pending-prompt'
 */
    const pendingPromptForm = (args: { connection: string | number | { id: string | number } } | [connection: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: pendingPrompt.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::pendingPrompt
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:52
 * @route '/co-browse/connections/{connection}/pending-prompt'
 */
        pendingPromptForm.post = (args: { connection: string | number | { id: string | number } } | [connection: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: pendingPrompt.url(args, options),
            method: 'post',
        })

    pendingPrompt.form = pendingPromptForm
/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::heartbeat
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:42
 * @route '/co-browse/connections/{connection}/heartbeat'
 */
export const heartbeat = (args: { connection: string | number | { id: string | number } } | [connection: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: heartbeat.url(args, options),
    method: 'post',
})

heartbeat.definition = {
    methods: ["post"],
    url: '/co-browse/connections/{connection}/heartbeat',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::heartbeat
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:42
 * @route '/co-browse/connections/{connection}/heartbeat'
 */
heartbeat.url = (args: { connection: string | number | { id: string | number } } | [connection: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { connection: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { connection: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    connection: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        connection: typeof args.connection === 'object'
                ? args.connection.id
                : args.connection,
                }

    return heartbeat.definition.url
            .replace('{connection}', parsedArgs.connection.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::heartbeat
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:42
 * @route '/co-browse/connections/{connection}/heartbeat'
 */
heartbeat.post = (args: { connection: string | number | { id: string | number } } | [connection: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: heartbeat.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::heartbeat
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:42
 * @route '/co-browse/connections/{connection}/heartbeat'
 */
    const heartbeatForm = (args: { connection: string | number | { id: string | number } } | [connection: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: heartbeat.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::heartbeat
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:42
 * @route '/co-browse/connections/{connection}/heartbeat'
 */
        heartbeatForm.post = (args: { connection: string | number | { id: string | number } } | [connection: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: heartbeat.url(args, options),
            method: 'post',
        })

    heartbeat.form = heartbeatForm
const connections = {
    pendingPrompt: Object.assign(pendingPrompt, pendingPrompt),
heartbeat: Object.assign(heartbeat, heartbeat),
}

export default connections