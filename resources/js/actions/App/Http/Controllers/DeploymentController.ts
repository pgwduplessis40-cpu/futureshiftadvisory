import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
} from './../../../../wayfinder';
/**
 * @see \App\Http\Controllers\DeploymentController::__invoke
 * @see app/Http/Controllers/DeploymentController.php:12
 * @route '/api/deployment'
 */
const DeploymentController = (
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: DeploymentController.url(options),
    method: 'get',
});

DeploymentController.definition = {
    methods: ['get', 'head'],
    url: '/api/deployment',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\DeploymentController::__invoke
 * @see app/Http/Controllers/DeploymentController.php:12
 * @route '/api/deployment'
 */
DeploymentController.url = (options?: RouteQueryOptions) => {
    return DeploymentController.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\DeploymentController::__invoke
 * @see app/Http/Controllers/DeploymentController.php:12
 * @route '/api/deployment'
 */
DeploymentController.get = (
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: DeploymentController.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\DeploymentController::__invoke
 * @see app/Http/Controllers/DeploymentController.php:12
 * @route '/api/deployment'
 */
DeploymentController.head = (
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: DeploymentController.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\DeploymentController::__invoke
 * @see app/Http/Controllers/DeploymentController.php:12
 * @route '/api/deployment'
 */
const DeploymentControllerForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: DeploymentController.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\DeploymentController::__invoke
 * @see app/Http/Controllers/DeploymentController.php:12
 * @route '/api/deployment'
 */
DeploymentControllerForm.get = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: DeploymentController.url(options),
    method: 'get',
});
/**
 * @see \App\Http\Controllers\DeploymentController::__invoke
 * @see app/Http/Controllers/DeploymentController.php:12
 * @route '/api/deployment'
 */
DeploymentControllerForm.head = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: DeploymentController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

DeploymentController.form = DeploymentControllerForm;
export default DeploymentController;
