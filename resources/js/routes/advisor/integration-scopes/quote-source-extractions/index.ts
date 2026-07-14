import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::store
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:162
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions'
 */
export const store = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/integration-scopes/{integrationScope}/quote-source-extractions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::store
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:162
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions'
 */
store.url = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { integrationScope: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { integrationScope: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    integrationScope: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        integrationScope: typeof args.integrationScope === 'object'
                ? args.integrationScope.id
                : args.integrationScope,
                }

    return store.definition.url
            .replace('{integrationScope}', parsedArgs.integrationScope.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::store
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:162
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions'
 */
store.post = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::store
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:162
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions'
 */
    const storeForm = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::store
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:162
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions'
 */
        storeForm.post = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::retry
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:204
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/retry'
 */
export const retry = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retry.url(args, options),
    method: 'post',
})

retry.definition = {
    methods: ["post"],
    url: '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/retry',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::retry
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:204
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/retry'
 */
retry.url = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    integrationScope: args[0],
                    quoteSourceExtraction: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        integrationScope: typeof args.integrationScope === 'object'
                ? args.integrationScope.id
                : args.integrationScope,
                                quoteSourceExtraction: typeof args.quoteSourceExtraction === 'object'
                ? args.quoteSourceExtraction.id
                : args.quoteSourceExtraction,
                }

    return retry.definition.url
            .replace('{integrationScope}', parsedArgs.integrationScope.toString())
            .replace('{quoteSourceExtraction}', parsedArgs.quoteSourceExtraction.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::retry
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:204
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/retry'
 */
retry.post = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retry.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::retry
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:204
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/retry'
 */
    const retryForm = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: retry.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::retry
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:204
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/retry'
 */
        retryForm.post = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: retry.url(args, options),
            method: 'post',
        })

    retry.form = retryForm
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::confirm
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:223
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/confirm'
 */
export const confirm = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirm.url(args, options),
    method: 'post',
})

confirm.definition = {
    methods: ["post"],
    url: '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/confirm',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::confirm
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:223
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/confirm'
 */
confirm.url = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    integrationScope: args[0],
                    quoteSourceExtraction: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        integrationScope: typeof args.integrationScope === 'object'
                ? args.integrationScope.id
                : args.integrationScope,
                                quoteSourceExtraction: typeof args.quoteSourceExtraction === 'object'
                ? args.quoteSourceExtraction.id
                : args.quoteSourceExtraction,
                }

    return confirm.definition.url
            .replace('{integrationScope}', parsedArgs.integrationScope.toString())
            .replace('{quoteSourceExtraction}', parsedArgs.quoteSourceExtraction.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::confirm
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:223
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/confirm'
 */
confirm.post = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirm.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::confirm
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:223
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/confirm'
 */
    const confirmForm = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: confirm.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::confirm
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:223
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/confirm'
 */
        confirmForm.post = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: confirm.url(args, options),
            method: 'post',
        })

    confirm.form = confirmForm
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::reject
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:243
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/reject'
 */
export const reject = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reject.url(args, options),
    method: 'post',
})

reject.definition = {
    methods: ["post"],
    url: '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/reject',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::reject
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:243
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/reject'
 */
reject.url = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    integrationScope: args[0],
                    quoteSourceExtraction: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        integrationScope: typeof args.integrationScope === 'object'
                ? args.integrationScope.id
                : args.integrationScope,
                                quoteSourceExtraction: typeof args.quoteSourceExtraction === 'object'
                ? args.quoteSourceExtraction.id
                : args.quoteSourceExtraction,
                }

    return reject.definition.url
            .replace('{integrationScope}', parsedArgs.integrationScope.toString())
            .replace('{quoteSourceExtraction}', parsedArgs.quoteSourceExtraction.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::reject
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:243
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/reject'
 */
reject.post = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reject.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::reject
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:243
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/reject'
 */
    const rejectForm = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reject.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::reject
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:243
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/reject'
 */
        rejectForm.post = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reject.url(args, options),
            method: 'post',
        })

    reject.form = rejectForm
const quoteSourceExtractions = {
    store: Object.assign(store, store),
retry: Object.assign(retry, retry),
confirm: Object.assign(confirm, confirm),
reject: Object.assign(reject, reject),
}

export default quoteSourceExtractions