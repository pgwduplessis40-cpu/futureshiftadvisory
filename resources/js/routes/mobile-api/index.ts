import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import clients from './clients'
import voiceAssistant from './voice-assistant'
/**
* @see \App\Http\Controllers\MobileApi\MeController::me
 * @see app/Http/Controllers/MobileApi/MeController.php:14
 * @route '/api/mobile/v1/me'
 */
export const me = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: me.url(options),
    method: 'get',
})

me.definition = {
    methods: ["get","head"],
    url: '/api/mobile/v1/me',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MobileApi\MeController::me
 * @see app/Http/Controllers/MobileApi/MeController.php:14
 * @route '/api/mobile/v1/me'
 */
me.url = (options?: RouteQueryOptions) => {
    return me.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MobileApi\MeController::me
 * @see app/Http/Controllers/MobileApi/MeController.php:14
 * @route '/api/mobile/v1/me'
 */
me.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: me.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\MobileApi\MeController::me
 * @see app/Http/Controllers/MobileApi/MeController.php:14
 * @route '/api/mobile/v1/me'
 */
me.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: me.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\MobileApi\MeController::me
 * @see app/Http/Controllers/MobileApi/MeController.php:14
 * @route '/api/mobile/v1/me'
 */
    const meForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: me.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\MobileApi\MeController::me
 * @see app/Http/Controllers/MobileApi/MeController.php:14
 * @route '/api/mobile/v1/me'
 */
        meForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: me.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\MobileApi\MeController::me
 * @see app/Http/Controllers/MobileApi/MeController.php:14
 * @route '/api/mobile/v1/me'
 */
        meForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: me.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    me.form = meForm
const mobileApi = {
    me: Object.assign(me, me),
clients: Object.assign(clients, clients),
voiceAssistant: Object.assign(voiceAssistant, voiceAssistant),
}

export default mobileApi
