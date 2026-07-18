import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
import program from './program'
import clients from './clients'
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
const pilotFeeWaivers = {
    index: Object.assign(index, index),
program: Object.assign(program, program),
clients: Object.assign(clients, clients),
}

export default pilotFeeWaivers