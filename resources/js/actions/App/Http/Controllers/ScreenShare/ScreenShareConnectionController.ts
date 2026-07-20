import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareConnectionController::registerAdvisor
 * @see app/Http/Controllers/ScreenShare/ScreenShareConnectionController.php:29
 * @route '/advisor/clients/{client}/screen-share/connections'
 */
export const registerAdvisor = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registerAdvisor.url(args, options),
    method: 'post',
})

registerAdvisor.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/screen-share/connections',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareConnectionController::registerAdvisor
 * @see app/Http/Controllers/ScreenShare/ScreenShareConnectionController.php:29
 * @route '/advisor/clients/{client}/screen-share/connections'
 */
registerAdvisor.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return registerAdvisor.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareConnectionController::registerAdvisor
 * @see app/Http/Controllers/ScreenShare/ScreenShareConnectionController.php:29
 * @route '/advisor/clients/{client}/screen-share/connections'
 */
registerAdvisor.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registerAdvisor.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareConnectionController::registerAdvisor
 * @see app/Http/Controllers/ScreenShare/ScreenShareConnectionController.php:29
 * @route '/advisor/clients/{client}/screen-share/connections'
 */
    const registerAdvisorForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: registerAdvisor.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareConnectionController::registerAdvisor
 * @see app/Http/Controllers/ScreenShare/ScreenShareConnectionController.php:29
 * @route '/advisor/clients/{client}/screen-share/connections'
 */
        registerAdvisorForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: registerAdvisor.url(args, options),
            method: 'post',
        })

    registerAdvisor.form = registerAdvisorForm
/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareConnectionController::registerClient
 * @see app/Http/Controllers/ScreenShare/ScreenShareConnectionController.php:18
 * @route '/portal/screen-share/connections'
 */
export const registerClient = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registerClient.url(options),
    method: 'post',
})

registerClient.definition = {
    methods: ["post"],
    url: '/portal/screen-share/connections',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareConnectionController::registerClient
 * @see app/Http/Controllers/ScreenShare/ScreenShareConnectionController.php:18
 * @route '/portal/screen-share/connections'
 */
registerClient.url = (options?: RouteQueryOptions) => {
    return registerClient.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ScreenShare\ScreenShareConnectionController::registerClient
 * @see app/Http/Controllers/ScreenShare/ScreenShareConnectionController.php:18
 * @route '/portal/screen-share/connections'
 */
registerClient.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registerClient.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareConnectionController::registerClient
 * @see app/Http/Controllers/ScreenShare/ScreenShareConnectionController.php:18
 * @route '/portal/screen-share/connections'
 */
    const registerClientForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: registerClient.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\ScreenShareConnectionController::registerClient
 * @see app/Http/Controllers/ScreenShare/ScreenShareConnectionController.php:18
 * @route '/portal/screen-share/connections'
 */
        registerClientForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: registerClient.url(options),
            method: 'post',
        })

    registerClient.form = registerClientForm
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
const ScreenShareConnectionController = { registerAdvisor, registerClient, heartbeat }

export default ScreenShareConnectionController