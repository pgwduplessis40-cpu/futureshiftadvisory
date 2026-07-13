import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:203
 * @route '/admin/service-rates/integration-fee-bands'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/service-rates/integration-fee-bands',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:203
 * @route '/admin/service-rates/integration-fee-bands'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:203
 * @route '/admin/service-rates/integration-fee-bands'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:203
 * @route '/admin/service-rates/integration-fee-bands'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ServiceRateController::store
 * @see app/Http/Controllers/Admin/ServiceRateController.php:203
 * @route '/admin/service-rates/integration-fee-bands'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\ServiceRateController::importMethod
 * @see app/Http/Controllers/Admin/ServiceRateController.php:214
 * @route '/admin/service-rates/integration-fee-bands/import'
 */
export const importMethod = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importMethod.url(options),
    method: 'post',
})

importMethod.definition = {
    methods: ["post"],
    url: '/admin/service-rates/integration-fee-bands/import',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::importMethod
 * @see app/Http/Controllers/Admin/ServiceRateController.php:214
 * @route '/admin/service-rates/integration-fee-bands/import'
 */
importMethod.url = (options?: RouteQueryOptions) => {
    return importMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ServiceRateController::importMethod
 * @see app/Http/Controllers/Admin/ServiceRateController.php:214
 * @route '/admin/service-rates/integration-fee-bands/import'
 */
importMethod.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importMethod.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\ServiceRateController::importMethod
 * @see app/Http/Controllers/Admin/ServiceRateController.php:214
 * @route '/admin/service-rates/integration-fee-bands/import'
 */
    const importMethodForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: importMethod.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ServiceRateController::importMethod
 * @see app/Http/Controllers/Admin/ServiceRateController.php:214
 * @route '/admin/service-rates/integration-fee-bands/import'
 */
        importMethodForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: importMethod.url(options),
            method: 'post',
        })
    
    importMethod.form = importMethodForm
const integrationFeeBands = {
    store: Object.assign(store, store),
import: Object.assign(importMethod, importMethod),
}

export default integrationFeeBands