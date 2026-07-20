import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareConnectionController::heartbeat
 * @see app/Http/Controllers/ScreenShare/ScreenShareConnectionController.php:36
 * @route '/screen-share/connections/{connection}/heartbeat'
 */
export const heartbeat = (args: { connection: string | number | { id: string | number } } | [connection: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: heartbeat.url(args, options),
    method: 'post',
})

heartbeat.definition = {
    methods: ["post"],
    url: '/screen-share/connections/{connection}/heartbeat',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareConnectionController::heartbeat
 * @see app/Http/Controllers/ScreenShare/ScreenShareConnectionController.php:36
 * @route '/screen-share/connections/{connection}/heartbeat'
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
* @see \App\Http\Controllers\ScreenShare\ScreenShareConnectionController::heartbeat
 * @see app/Http/Controllers/ScreenShare/ScreenShareConnectionController.php:36
 * @route '/screen-share/connections/{connection}/heartbeat'
 */
heartbeat.post = (args: { connection: string | number | { id: string | number } } | [connection: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: heartbeat.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareConnectionController::heartbeat
 * @see app/Http/Controllers/ScreenShare/ScreenShareConnectionController.php:36
 * @route '/screen-share/connections/{connection}/heartbeat'
 */
    const heartbeatForm = (args: { connection: string | number | { id: string | number } } | [connection: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: heartbeat.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareConnectionController::heartbeat
 * @see app/Http/Controllers/ScreenShare/ScreenShareConnectionController.php:36
 * @route '/screen-share/connections/{connection}/heartbeat'
 */
        heartbeatForm.post = (args: { connection: string | number | { id: string | number } } | [connection: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: heartbeat.url(args, options),
            method: 'post',
        })

    heartbeat.form = heartbeatForm
const connections = {
    heartbeat: Object.assign(heartbeat, heartbeat),
}

export default connections