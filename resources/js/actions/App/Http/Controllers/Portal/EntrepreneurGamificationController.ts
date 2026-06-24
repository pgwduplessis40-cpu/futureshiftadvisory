import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurGamificationController::requestDisable
 * @see app/Http/Controllers/Portal/EntrepreneurGamificationController.php:35
 * @route '/portal/entrepreneur/gamification/disable-request'
 */
export const requestDisable = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: requestDisable.url(options),
    method: 'post',
})

requestDisable.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/gamification/disable-request',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurGamificationController::requestDisable
 * @see app/Http/Controllers/Portal/EntrepreneurGamificationController.php:35
 * @route '/portal/entrepreneur/gamification/disable-request'
 */
requestDisable.url = (options?: RouteQueryOptions) => {
    return requestDisable.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurGamificationController::requestDisable
 * @see app/Http/Controllers/Portal/EntrepreneurGamificationController.php:35
 * @route '/portal/entrepreneur/gamification/disable-request'
 */
requestDisable.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: requestDisable.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurGamificationController::requestDisable
 * @see app/Http/Controllers/Portal/EntrepreneurGamificationController.php:35
 * @route '/portal/entrepreneur/gamification/disable-request'
 */
    const requestDisableForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: requestDisable.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurGamificationController::requestDisable
 * @see app/Http/Controllers/Portal/EntrepreneurGamificationController.php:35
 * @route '/portal/entrepreneur/gamification/disable-request'
 */
        requestDisableForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: requestDisable.url(options),
            method: 'post',
        })

    requestDisable.form = requestDisableForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurGamificationController::seen
 * @see app/Http/Controllers/Portal/EntrepreneurGamificationController.php:21
 * @route '/portal/entrepreneur/gamification/seen'
 */
export const seen = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: seen.url(options),
    method: 'post',
})

seen.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/gamification/seen',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurGamificationController::seen
 * @see app/Http/Controllers/Portal/EntrepreneurGamificationController.php:21
 * @route '/portal/entrepreneur/gamification/seen'
 */
seen.url = (options?: RouteQueryOptions) => {
    return seen.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurGamificationController::seen
 * @see app/Http/Controllers/Portal/EntrepreneurGamificationController.php:21
 * @route '/portal/entrepreneur/gamification/seen'
 */
seen.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: seen.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurGamificationController::seen
 * @see app/Http/Controllers/Portal/EntrepreneurGamificationController.php:21
 * @route '/portal/entrepreneur/gamification/seen'
 */
    const seenForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: seen.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurGamificationController::seen
 * @see app/Http/Controllers/Portal/EntrepreneurGamificationController.php:21
 * @route '/portal/entrepreneur/gamification/seen'
 */
        seenForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: seen.url(options),
            method: 'post',
        })

    seen.form = seenForm
const EntrepreneurGamificationController = { requestDisable, seen }

export default EntrepreneurGamificationController