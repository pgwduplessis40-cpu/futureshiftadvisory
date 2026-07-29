import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::gate
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:31
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate'
 */
export const gate = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: gate.url(args, options),
    method: 'patch',
})

gate.definition = {
    methods: ["patch"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::gate
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:31
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate'
 */
gate.url = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                    ideaValidation: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                                ideaValidation: typeof args.ideaValidation === 'object'
                ? args.ideaValidation.id
                : args.ideaValidation,
                }

    return gate.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{ideaValidation}', parsedArgs.ideaValidation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::gate
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:31
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate'
 */
gate.patch = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: gate.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::gate
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:31
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate'
 */
    const gateForm = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: gate.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::gate
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:31
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/gate'
 */
        gateForm.patch = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: gate.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    gate.form = gateForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::requestChanges
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:65
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/request-changes'
 */
export const requestChanges = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: requestChanges.url(args, options),
    method: 'patch',
})

requestChanges.definition = {
    methods: ["patch"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/request-changes',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::requestChanges
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:65
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/request-changes'
 */
requestChanges.url = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                    ideaValidation: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                                ideaValidation: typeof args.ideaValidation === 'object'
                ? args.ideaValidation.id
                : args.ideaValidation,
                }

    return requestChanges.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{ideaValidation}', parsedArgs.ideaValidation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::requestChanges
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:65
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/request-changes'
 */
requestChanges.patch = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: requestChanges.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::requestChanges
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:65
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/request-changes'
 */
    const requestChangesForm = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: requestChanges.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::requestChanges
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:65
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/request-changes'
 */
        requestChangesForm.patch = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: requestChanges.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    requestChanges.form = requestChangesForm
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::refresh
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:49
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/refresh'
 */
export const refresh = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refresh.url(args, options),
    method: 'post',
})

refresh.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/refresh',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::refresh
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:49
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/refresh'
 */
refresh.url = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                    ideaValidation: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                                ideaValidation: typeof args.ideaValidation === 'object'
                ? args.ideaValidation.id
                : args.ideaValidation,
                }

    return refresh.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{ideaValidation}', parsedArgs.ideaValidation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::refresh
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:49
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/refresh'
 */
refresh.post = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: refresh.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::refresh
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:49
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/refresh'
 */
    const refreshForm = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: refresh.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::refresh
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:49
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/idea-validations/{ideaValidation}/refresh'
 */
        refreshForm.post = (args: { entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } } | [entrepreneurProfile: string | { id: string }, ideaValidation: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: refresh.url(args, options),
            method: 'post',
        })

    refresh.form = refreshForm
const ideaValidations = {
    gate: Object.assign(gate, gate),
requestChanges: Object.assign(requestChanges, requestChanges),
refresh: Object.assign(refresh, refresh),
}

export default ideaValidations