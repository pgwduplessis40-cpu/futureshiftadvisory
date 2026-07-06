import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::disable
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:107
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}/gamification/disable'
 */
export const disable = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: disable.url(args, options),
    method: 'patch',
})

disable.definition = {
    methods: ["patch"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}/gamification/disable',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::disable
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:107
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}/gamification/disable'
 */
disable.url = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                    messageThread: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                                messageThread: typeof args.messageThread === 'object'
                ? args.messageThread.id
                : args.messageThread,
                }

    return disable.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{messageThread}', parsedArgs.messageThread.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::disable
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:107
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}/gamification/disable'
 */
disable.patch = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: disable.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::disable
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:107
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}/gamification/disable'
 */
    const disableForm = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: disable.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurMessageController::disable
 * @see app/Http/Controllers/Advisor/EntrepreneurMessageController.php:107
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/messages/{messageThread}/gamification/disable'
 */
        disableForm.patch = (args: { entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } } | [entrepreneurProfile: string | { id: string }, messageThread: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: disable.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    disable.form = disableForm
const gamification = {
    disable: Object.assign(disable, disable),
}

export default gamification