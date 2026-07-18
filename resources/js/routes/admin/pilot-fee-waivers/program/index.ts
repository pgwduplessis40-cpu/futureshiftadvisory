import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::update
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:49
 * @route '/admin/pilot-fee-waivers/program'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/admin/pilot-fee-waivers/program',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::update
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:49
 * @route '/admin/pilot-fee-waivers/program'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::update
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:49
 * @route '/admin/pilot-fee-waivers/program'
 */
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::update
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:49
 * @route '/admin/pilot-fee-waivers/program'
 */
    const updateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\PilotFeeWaiverController::update
 * @see app/Http/Controllers/Admin/PilotFeeWaiverController.php:49
 * @route '/admin/pilot-fee-waivers/program'
 */
        updateForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const program = {
    update: Object.assign(update, update),
}

export default program