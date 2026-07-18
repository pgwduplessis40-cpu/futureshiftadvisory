import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::index
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:27
 * @route '/admin/pilot-fee-waivers'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/pilot-fee-waivers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::index
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:27
 * @route '/admin/pilot-fee-waivers'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::index
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:27
 * @route '/admin/pilot-fee-waivers'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::index
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:27
 * @route '/admin/pilot-fee-waivers'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::index
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:27
 * @route '/admin/pilot-fee-waivers'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::index
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:27
 * @route '/admin/pilot-fee-waivers'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::index
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:27
 * @route '/admin/pilot-fee-waivers'
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
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::updateProgram
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:49
 * @route '/admin/pilot-fee-waivers/program'
 */
export const updateProgram = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateProgram.url(options),
    method: 'patch',
})

updateProgram.definition = {
    methods: ["patch"],
    url: '/admin/pilot-fee-waivers/program',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::updateProgram
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:49
 * @route '/admin/pilot-fee-waivers/program'
 */
updateProgram.url = (options?: RouteQueryOptions) => {
    return updateProgram.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::updateProgram
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:49
 * @route '/admin/pilot-fee-waivers/program'
 */
updateProgram.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateProgram.url(options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::updateProgram
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:49
 * @route '/admin/pilot-fee-waivers/program'
 */
    const updateProgramForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateProgram.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::updateProgram
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:49
 * @route '/admin/pilot-fee-waivers/program'
 */
        updateProgramForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateProgram.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateProgram.form = updateProgramForm
/**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::updateClient
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:63
 * @route '/admin/pilot-fee-waivers/clients/{client}'
 */
export const updateClient = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateClient.url(args, options),
    method: 'patch',
})

updateClient.definition = {
    methods: ["patch"],
    url: '/admin/pilot-fee-waivers/clients/{client}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::updateClient
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:63
 * @route '/admin/pilot-fee-waivers/clients/{client}'
 */
updateClient.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return updateClient.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::updateClient
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:63
 * @route '/admin/pilot-fee-waivers/clients/{client}'
 */
updateClient.patch = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateClient.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::updateClient
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:63
 * @route '/admin/pilot-fee-waivers/clients/{client}'
 */
    const updateClientForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateClient.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::updateClient
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:63
 * @route '/admin/pilot-fee-waivers/clients/{client}'
 */
        updateClientForm.patch = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateClient.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateClient.form = updateClientForm
const PilotFeeWaiverController = { index, updateProgram, updateClient }

export default PilotFeeWaiverController