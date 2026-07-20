import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::registerAdvisor
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:34
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/screen-share/connections'
 */
export const registerAdvisor = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registerAdvisor.url(args, options),
    method: 'post',
})

registerAdvisor.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/screen-share/connections',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::registerAdvisor
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:34
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/screen-share/connections'
 */
registerAdvisor.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { entrepreneurProfile: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { entrepreneurProfile: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                }

    return registerAdvisor.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::registerAdvisor
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:34
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/screen-share/connections'
 */
registerAdvisor.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registerAdvisor.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::registerAdvisor
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:34
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/screen-share/connections'
 */
    const registerAdvisorForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: registerAdvisor.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::registerAdvisor
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:34
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/screen-share/connections'
 */
        registerAdvisorForm.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: registerAdvisor.url(args, options),
            method: 'post',
        })

    registerAdvisor.form = registerAdvisorForm
/**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::store
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:43
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/screen-share-sessions'
 */
export const store = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/screen-share-sessions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::store
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:43
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/screen-share-sessions'
 */
store.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { entrepreneurProfile: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { entrepreneurProfile: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                }

    return store.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::store
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:43
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/screen-share-sessions'
 */
store.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::store
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:43
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/screen-share-sessions'
 */
    const storeForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::store
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:43
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/screen-share-sessions'
 */
        storeForm.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::registerPortalParticipant
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:23
 * @route '/portal/entrepreneur-screen-share/connections'
 */
export const registerPortalParticipant = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registerPortalParticipant.url(options),
    method: 'post',
})

registerPortalParticipant.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur-screen-share/connections',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::registerPortalParticipant
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:23
 * @route '/portal/entrepreneur-screen-share/connections'
 */
registerPortalParticipant.url = (options?: RouteQueryOptions) => {
    return registerPortalParticipant.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::registerPortalParticipant
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:23
 * @route '/portal/entrepreneur-screen-share/connections'
 */
registerPortalParticipant.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: registerPortalParticipant.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::registerPortalParticipant
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:23
 * @route '/portal/entrepreneur-screen-share/connections'
 */
    const registerPortalParticipantForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: registerPortalParticipant.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ScreenShare\EntrepreneurScreenShareController::registerPortalParticipant
 * @see app/Http/Controllers/ScreenShare/EntrepreneurScreenShareController.php:23
 * @route '/portal/entrepreneur-screen-share/connections'
 */
        registerPortalParticipantForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: registerPortalParticipant.url(options),
            method: 'post',
        })

    registerPortalParticipant.form = registerPortalParticipantForm
const EntrepreneurScreenShareController = { registerAdvisor, store, registerPortalParticipant }

export default EntrepreneurScreenShareController