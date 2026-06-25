import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:39
 * @route '/portal/entrepreneur'
 */
const EntrepreneurDashboardController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EntrepreneurDashboardController.url(options),
    method: 'get',
})

EntrepreneurDashboardController.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:39
 * @route '/portal/entrepreneur'
 */
EntrepreneurDashboardController.url = (options?: RouteQueryOptions) => {
    return EntrepreneurDashboardController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:39
 * @route '/portal/entrepreneur'
 */
EntrepreneurDashboardController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: EntrepreneurDashboardController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:39
 * @route '/portal/entrepreneur'
 */
EntrepreneurDashboardController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: EntrepreneurDashboardController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:39
 * @route '/portal/entrepreneur'
 */
    const EntrepreneurDashboardControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: EntrepreneurDashboardController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:39
 * @route '/portal/entrepreneur'
 */
        EntrepreneurDashboardControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: EntrepreneurDashboardController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\EntrepreneurDashboardController::__invoke
 * @see app/Http/Controllers/Portal/EntrepreneurDashboardController.php:39
 * @route '/portal/entrepreneur'
 */
        EntrepreneurDashboardControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: EntrepreneurDashboardController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    EntrepreneurDashboardController.form = EntrepreneurDashboardControllerForm
export default EntrepreneurDashboardController