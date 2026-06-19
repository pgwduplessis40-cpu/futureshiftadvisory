import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\SurveyResultController::client
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:18
 * @route '/advisor/clients/{client}/surveys'
 */
export const client = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: client.url(args, options),
    method: 'get',
})

client.definition = {
    methods: ["get","head"],
    url: '/advisor/clients/{client}/surveys',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\SurveyResultController::client
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:18
 * @route '/advisor/clients/{client}/surveys'
 */
client.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { client: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                }

    return client.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\SurveyResultController::client
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:18
 * @route '/advisor/clients/{client}/surveys'
 */
client.get = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: client.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\SurveyResultController::client
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:18
 * @route '/advisor/clients/{client}/surveys'
 */
client.head = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: client.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\SurveyResultController::client
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:18
 * @route '/advisor/clients/{client}/surveys'
 */
    const clientForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: client.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\SurveyResultController::client
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:18
 * @route '/advisor/clients/{client}/surveys'
 */
        clientForm.get = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: client.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\SurveyResultController::client
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:18
 * @route '/advisor/clients/{client}/surveys'
 */
        clientForm.head = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: client.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    client.form = clientForm
/**
* @see \App\Http\Controllers\Advisor\SurveyResultController::entrepreneur
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:39
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/surveys'
 */
export const entrepreneur = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: entrepreneur.url(args, options),
    method: 'get',
})

entrepreneur.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/surveys',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\SurveyResultController::entrepreneur
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:39
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/surveys'
 */
entrepreneur.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return entrepreneur.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\SurveyResultController::entrepreneur
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:39
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/surveys'
 */
entrepreneur.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: entrepreneur.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\SurveyResultController::entrepreneur
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:39
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/surveys'
 */
entrepreneur.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: entrepreneur.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\SurveyResultController::entrepreneur
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:39
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/surveys'
 */
    const entrepreneurForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: entrepreneur.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\SurveyResultController::entrepreneur
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:39
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/surveys'
 */
        entrepreneurForm.get = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: entrepreneur.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\SurveyResultController::entrepreneur
 * @see app/Http/Controllers/Advisor/SurveyResultController.php:39
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/surveys'
 */
        entrepreneurForm.head = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: entrepreneur.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    entrepreneur.form = entrepreneurForm
const SurveyResultController = { client, entrepreneur }

export default SurveyResultController