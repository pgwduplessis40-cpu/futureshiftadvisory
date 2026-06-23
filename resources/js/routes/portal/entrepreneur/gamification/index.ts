import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurGamificationController::disableRequest
 * @see app/Http/Controllers/Portal/EntrepreneurGamificationController.php:35
 * @route '/portal/entrepreneur/gamification/disable-request'
 */
export const disableRequest = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: disableRequest.url(options),
    method: 'post',
})

disableRequest.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/gamification/disable-request',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurGamificationController::disableRequest
 * @see app/Http/Controllers/Portal/EntrepreneurGamificationController.php:35
 * @route '/portal/entrepreneur/gamification/disable-request'
 */
disableRequest.url = (options?: RouteQueryOptions) => {
    return disableRequest.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurGamificationController::disableRequest
 * @see app/Http/Controllers/Portal/EntrepreneurGamificationController.php:35
 * @route '/portal/entrepreneur/gamification/disable-request'
 */
disableRequest.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: disableRequest.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurGamificationController::disableRequest
 * @see app/Http/Controllers/Portal/EntrepreneurGamificationController.php:35
 * @route '/portal/entrepreneur/gamification/disable-request'
 */
    const disableRequestForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: disableRequest.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurGamificationController::disableRequest
 * @see app/Http/Controllers/Portal/EntrepreneurGamificationController.php:35
 * @route '/portal/entrepreneur/gamification/disable-request'
 */
        disableRequestForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: disableRequest.url(options),
            method: 'post',
        })

    disableRequest.form = disableRequestForm
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
const gamification = {
    disableRequest: Object.assign(disableRequest, disableRequest),
seen: Object.assign(seen, seen),
}

export default gamification
