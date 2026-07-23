import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::registerAdvisor
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:32
 * @route '/advisor/clients/{client}/co-browse/connections'
 */
export const registerAdvisor = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registerAdvisor.url(args, options),
    method: 'post',
})

registerAdvisor.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/co-browse/connections',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::registerAdvisor
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:32
 * @route '/advisor/clients/{client}/co-browse/connections'
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
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::registerAdvisor
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:32
 * @route '/advisor/clients/{client}/co-browse/connections'
 */
registerAdvisor.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registerAdvisor.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::registerAdvisor
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:32
 * @route '/advisor/clients/{client}/co-browse/connections'
 */
    const registerAdvisorForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: registerAdvisor.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::registerAdvisor
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:32
 * @route '/advisor/clients/{client}/co-browse/connections'
 */
        registerAdvisorForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: registerAdvisor.url(args, options),
            method: 'post',
        })

    registerAdvisor.form = registerAdvisorForm
/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::registerAdvisorForEntrepreneur
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:37
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/co-browse/connections'
 */
export const registerAdvisorForEntrepreneur = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registerAdvisorForEntrepreneur.url(args, options),
    method: 'post',
})

registerAdvisorForEntrepreneur.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/co-browse/connections',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::registerAdvisorForEntrepreneur
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:37
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/co-browse/connections'
 */
registerAdvisorForEntrepreneur.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return registerAdvisorForEntrepreneur.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::registerAdvisorForEntrepreneur
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:37
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/co-browse/connections'
 */
registerAdvisorForEntrepreneur.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registerAdvisorForEntrepreneur.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::registerAdvisorForEntrepreneur
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:37
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/co-browse/connections'
 */
    const registerAdvisorForEntrepreneurForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: registerAdvisorForEntrepreneur.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::registerAdvisorForEntrepreneur
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:37
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/co-browse/connections'
 */
        registerAdvisorForEntrepreneurForm.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: registerAdvisorForEntrepreneur.url(args, options),
            method: 'post',
        })

    registerAdvisorForEntrepreneur.form = registerAdvisorForEntrepreneurForm
/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::registerClient
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:23
 * @route '/portal/co-browse/connections'
 */
export const registerClient = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registerClient.url(options),
    method: 'post',
})

registerClient.definition = {
    methods: ["post"],
    url: '/portal/co-browse/connections',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::registerClient
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:23
 * @route '/portal/co-browse/connections'
 */
registerClient.url = (options?: RouteQueryOptions) => {
    return registerClient.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::registerClient
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:23
 * @route '/portal/co-browse/connections'
 */
registerClient.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registerClient.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::registerClient
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:23
 * @route '/portal/co-browse/connections'
 */
    const registerClientForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: registerClient.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseConnectionController::registerClient
 * @see app/Http/Controllers/CoBrowse/CoBrowseConnectionController.php:23
 * @route '/portal/co-browse/connections'
 */
        registerClientForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: registerClient.url(options),
            method: 'post',
        })

    registerClient.form = registerClientForm
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
const CoBrowseConnectionController = { registerAdvisor, registerAdvisorForEntrepreneur, registerClient, pendingPrompt, heartbeat }

export default CoBrowseConnectionController