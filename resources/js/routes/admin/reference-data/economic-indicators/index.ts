import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::refresh
* @see app/Http/Controllers/Admin/ReferenceDataController.php:53
* @route '/admin/reference-data/economic-indicators/refresh'
*/
export const refresh = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refresh.url(options),
    method: 'post',
})

refresh.definition = {
    methods: ["post"],
    url: '/admin/reference-data/economic-indicators/refresh',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::refresh
* @see app/Http/Controllers/Admin/ReferenceDataController.php:53
* @route '/admin/reference-data/economic-indicators/refresh'
*/
refresh.url = (options?: RouteQueryOptions) => {
    return refresh.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::refresh
* @see app/Http/Controllers/Admin/ReferenceDataController.php:53
* @route '/admin/reference-data/economic-indicators/refresh'
*/
refresh.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refresh.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::refresh
* @see app/Http/Controllers/Admin/ReferenceDataController.php:53
* @route '/admin/reference-data/economic-indicators/refresh'
*/
const refreshForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: refresh.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::refresh
* @see app/Http/Controllers/Admin/ReferenceDataController.php:53
* @route '/admin/reference-data/economic-indicators/refresh'
*/
refreshForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: refresh.url(options),
    method: 'post',
})

refresh.form = refreshForm

const economicIndicators = {
    refresh: Object.assign(refresh, refresh),
}

export default economicIndicators
