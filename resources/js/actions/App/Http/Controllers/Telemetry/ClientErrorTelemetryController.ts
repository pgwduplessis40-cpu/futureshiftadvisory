import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Telemetry\ClientErrorTelemetryController::__invoke
* @see app/Http/Controllers/Telemetry/ClientErrorTelemetryController.php:16
* @route '/api/telemetry/client-errors'
*/
const ClientErrorTelemetryController = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ClientErrorTelemetryController.url(options),
    method: 'post',
})

ClientErrorTelemetryController.definition = {
    methods: ["post"],
    url: '/api/telemetry/client-errors',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Telemetry\ClientErrorTelemetryController::__invoke
* @see app/Http/Controllers/Telemetry/ClientErrorTelemetryController.php:16
* @route '/api/telemetry/client-errors'
*/
ClientErrorTelemetryController.url = (options?: RouteQueryOptions) => {
    return ClientErrorTelemetryController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Telemetry\ClientErrorTelemetryController::__invoke
* @see app/Http/Controllers/Telemetry/ClientErrorTelemetryController.php:16
* @route '/api/telemetry/client-errors'
*/
ClientErrorTelemetryController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: ClientErrorTelemetryController.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Telemetry\ClientErrorTelemetryController::__invoke
* @see app/Http/Controllers/Telemetry/ClientErrorTelemetryController.php:16
* @route '/api/telemetry/client-errors'
*/
const ClientErrorTelemetryControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: ClientErrorTelemetryController.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Telemetry\ClientErrorTelemetryController::__invoke
* @see app/Http/Controllers/Telemetry/ClientErrorTelemetryController.php:16
* @route '/api/telemetry/client-errors'
*/
ClientErrorTelemetryControllerForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: ClientErrorTelemetryController.url(options),
    method: 'post',
})

ClientErrorTelemetryController.form = ClientErrorTelemetryControllerForm

export default ClientErrorTelemetryController
