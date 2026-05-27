import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\MobileApi\VoiceSessionController::store
 * @see app/Http/Controllers/MobileApi/VoiceSessionController.php:18
 * @route '/api/mobile/v1/voice-assistant/sessions'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/mobile/v1/voice-assistant/sessions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\MobileApi\VoiceSessionController::store
 * @see app/Http/Controllers/MobileApi/VoiceSessionController.php:18
 * @route '/api/mobile/v1/voice-assistant/sessions'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MobileApi\VoiceSessionController::store
 * @see app/Http/Controllers/MobileApi/VoiceSessionController.php:18
 * @route '/api/mobile/v1/voice-assistant/sessions'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\MobileApi\VoiceSessionController::store
 * @see app/Http/Controllers/MobileApi/VoiceSessionController.php:18
 * @route '/api/mobile/v1/voice-assistant/sessions'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\MobileApi\VoiceSessionController::store
 * @see app/Http/Controllers/MobileApi/VoiceSessionController.php:18
 * @route '/api/mobile/v1/voice-assistant/sessions'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
const sessions = {
    store: Object.assign(store, store),
}

export default sessions