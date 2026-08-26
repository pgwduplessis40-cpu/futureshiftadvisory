import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Telemetry\ClientErrorTelemetryController::__invoke
* @see app/Http/Controllers/Telemetry/ClientErrorTelemetryController.php:16
* @route '/api/telemetry/client-errors'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/telemetry/client-errors',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Telemetry\ClientErrorTelemetryController::__invoke
* @see app/Http/Controllers/Telemetry/ClientErrorTelemetryController.php:16
* @route '/api/telemetry/client-errors'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Telemetry\ClientErrorTelemetryController::__invoke
* @see app/Http/Controllers/Telemetry/ClientErrorTelemetryController.php:16
* @route '/api/telemetry/client-errors'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Telemetry\ClientErrorTelemetryController::__invoke
* @see app/Http/Controllers/Telemetry/ClientErrorTelemetryController.php:16
* @route '/api/telemetry/client-errors'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Telemetry\ClientErrorTelemetryController::__invoke
* @see app/Http/Controllers/Telemetry/ClientErrorTelemetryController.php:16
* @route '/api/telemetry/client-errors'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

const clientErrors = {
    store: Object.assign(store, store),
}

export default clientErrors
