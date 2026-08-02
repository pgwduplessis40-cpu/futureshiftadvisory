import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
} from './../../../../wayfinder';
/**
 * @see \App\Http\Controllers\ServiceWorkerController::__invoke
 * @see app/Http/Controllers/ServiceWorkerController.php:14
 * @route '/sw.js'
 */
const ServiceWorkerController = (
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: ServiceWorkerController.url(options),
    method: 'get',
});

ServiceWorkerController.definition = {
    methods: ['get', 'head'],
    url: '/sw.js',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\ServiceWorkerController::__invoke
 * @see app/Http/Controllers/ServiceWorkerController.php:14
 * @route '/sw.js'
 */
ServiceWorkerController.url = (options?: RouteQueryOptions) => {
    return ServiceWorkerController.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\ServiceWorkerController::__invoke
 * @see app/Http/Controllers/ServiceWorkerController.php:14
 * @route '/sw.js'
 */
ServiceWorkerController.get = (
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: ServiceWorkerController.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\ServiceWorkerController::__invoke
 * @see app/Http/Controllers/ServiceWorkerController.php:14
 * @route '/sw.js'
 */
ServiceWorkerController.head = (
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: ServiceWorkerController.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\ServiceWorkerController::__invoke
 * @see app/Http/Controllers/ServiceWorkerController.php:14
 * @route '/sw.js'
 */
const ServiceWorkerControllerForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: ServiceWorkerController.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\ServiceWorkerController::__invoke
 * @see app/Http/Controllers/ServiceWorkerController.php:14
 * @route '/sw.js'
 */
ServiceWorkerControllerForm.get = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: ServiceWorkerController.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\ServiceWorkerController::__invoke
 * @see app/Http/Controllers/ServiceWorkerController.php:14
 * @route '/sw.js'
 */
ServiceWorkerControllerForm.head = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: ServiceWorkerController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

ServiceWorkerController.form = ServiceWorkerControllerForm;
export default ServiceWorkerController;
