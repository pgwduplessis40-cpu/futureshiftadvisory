import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\NpoBoardDashboardController::__invoke
 * @see app/Http/Controllers/Portal/NpoBoardDashboardController.php:21
 * @route '/portal/npo-board'
 */
const NpoBoardDashboardController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: NpoBoardDashboardController.url(options),
    method: 'get',
})

NpoBoardDashboardController.definition = {
    methods: ["get","head"],
    url: '/portal/npo-board',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\NpoBoardDashboardController::__invoke
 * @see app/Http/Controllers/Portal/NpoBoardDashboardController.php:21
 * @route '/portal/npo-board'
 */
NpoBoardDashboardController.url = (options?: RouteQueryOptions) => {
    return NpoBoardDashboardController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\NpoBoardDashboardController::__invoke
 * @see app/Http/Controllers/Portal/NpoBoardDashboardController.php:21
 * @route '/portal/npo-board'
 */
NpoBoardDashboardController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: NpoBoardDashboardController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\NpoBoardDashboardController::__invoke
 * @see app/Http/Controllers/Portal/NpoBoardDashboardController.php:21
 * @route '/portal/npo-board'
 */
NpoBoardDashboardController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: NpoBoardDashboardController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\NpoBoardDashboardController::__invoke
 * @see app/Http/Controllers/Portal/NpoBoardDashboardController.php:21
 * @route '/portal/npo-board'
 */
    const NpoBoardDashboardControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: NpoBoardDashboardController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\NpoBoardDashboardController::__invoke
 * @see app/Http/Controllers/Portal/NpoBoardDashboardController.php:21
 * @route '/portal/npo-board'
 */
        NpoBoardDashboardControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: NpoBoardDashboardController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\NpoBoardDashboardController::__invoke
 * @see app/Http/Controllers/Portal/NpoBoardDashboardController.php:21
 * @route '/portal/npo-board'
 */
        NpoBoardDashboardControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: NpoBoardDashboardController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    NpoBoardDashboardController.form = NpoBoardDashboardControllerForm
export default NpoBoardDashboardController