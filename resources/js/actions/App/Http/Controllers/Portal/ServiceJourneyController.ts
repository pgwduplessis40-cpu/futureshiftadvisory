import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\ServiceJourneyController::preference
* @see app/Http/Controllers/Portal/ServiceJourneyController.php:23
* @route '/portal/service-journey/preference'
*/
export const preference = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: preference.url(options),
    method: 'post',
})

preference.definition = {
    methods: ["post"],
    url: '/portal/service-journey/preference',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\ServiceJourneyController::preference
* @see app/Http/Controllers/Portal/ServiceJourneyController.php:23
* @route '/portal/service-journey/preference'
*/
preference.url = (options?: RouteQueryOptions) => {
    return preference.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\ServiceJourneyController::preference
* @see app/Http/Controllers/Portal/ServiceJourneyController.php:23
* @route '/portal/service-journey/preference'
*/
preference.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: preference.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\ServiceJourneyController::preference
* @see app/Http/Controllers/Portal/ServiceJourneyController.php:23
* @route '/portal/service-journey/preference'
*/
const preferenceForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: preference.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\ServiceJourneyController::preference
* @see app/Http/Controllers/Portal/ServiceJourneyController.php:23
* @route '/portal/service-journey/preference'
*/
preferenceForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: preference.url(options),
    method: 'post',
})

preference.form = preferenceForm

/**
* @see \App\Http\Controllers\Portal\ServiceJourneyController::seen
* @see app/Http/Controllers/Portal/ServiceJourneyController.php:48
* @route '/portal/service-journey/seen'
*/
export const seen = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: seen.url(options),
    method: 'post',
})

seen.definition = {
    methods: ["post"],
    url: '/portal/service-journey/seen',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\ServiceJourneyController::seen
* @see app/Http/Controllers/Portal/ServiceJourneyController.php:48
* @route '/portal/service-journey/seen'
*/
seen.url = (options?: RouteQueryOptions) => {
    return seen.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\ServiceJourneyController::seen
* @see app/Http/Controllers/Portal/ServiceJourneyController.php:48
* @route '/portal/service-journey/seen'
*/
seen.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: seen.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\ServiceJourneyController::seen
* @see app/Http/Controllers/Portal/ServiceJourneyController.php:48
* @route '/portal/service-journey/seen'
*/
const seenForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: seen.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\ServiceJourneyController::seen
* @see app/Http/Controllers/Portal/ServiceJourneyController.php:48
* @route '/portal/service-journey/seen'
*/
seenForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: seen.url(options),
    method: 'post',
})

seen.form = seenForm

const ServiceJourneyController = { preference, seen }

export default ServiceJourneyController
