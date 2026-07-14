import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::index
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:33
 * @route '/advisor/integration-scopes'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/integration-scopes',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::index
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:33
 * @route '/advisor/integration-scopes'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::index
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:33
 * @route '/advisor/integration-scopes'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::index
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:33
 * @route '/advisor/integration-scopes'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::index
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:33
 * @route '/advisor/integration-scopes'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::index
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:33
 * @route '/advisor/integration-scopes'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::index
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:33
 * @route '/advisor/integration-scopes'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    index.form = indexForm
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:83
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
export const show = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/integration-scopes/{integrationScope}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:83
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
show.url = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{integrationScope}', parsedArgs.integrationScope.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:83
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
show.get = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:83
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
show.head = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:83
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
    const showForm = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:83
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
        showForm.get = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:83
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
        showForm.head = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    show.form = showForm
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::store
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:106
 * @route '/advisor/clients/{client}/integration-scopes'
 */
export const store = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/integration-scopes',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::store
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:106
 * @route '/advisor/clients/{client}/integration-scopes'
 */
store.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::store
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:106
 * @route '/advisor/clients/{client}/integration-scopes'
 */
store.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::store
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:106
 * @route '/advisor/clients/{client}/integration-scopes'
 */
    const storeForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::store
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:106
 * @route '/advisor/clients/{client}/integration-scopes'
 */
        storeForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::update
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:118
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
export const update = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/advisor/integration-scopes/{integrationScope}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::update
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:118
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
update.url = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{integrationScope}', parsedArgs.integrationScope.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::update
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:118
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
update.patch = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::update
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:118
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
    const updateForm = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::update
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:118
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
        updateForm.patch = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    update.form = updateForm
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::recalculate
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:130
 * @route '/advisor/integration-scopes/{integrationScope}/recalculate'
 */
export const recalculate = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: recalculate.url(args, options),
    method: 'post',
})

recalculate.definition = {
    methods: ["post"],
    url: '/advisor/integration-scopes/{integrationScope}/recalculate',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::recalculate
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:130
 * @route '/advisor/integration-scopes/{integrationScope}/recalculate'
 */
recalculate.url = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return recalculate.definition.url
            .replace('{integrationScope}', parsedArgs.integrationScope.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::recalculate
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:130
 * @route '/advisor/integration-scopes/{integrationScope}/recalculate'
 */
recalculate.post = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: recalculate.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::recalculate
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:130
 * @route '/advisor/integration-scopes/{integrationScope}/recalculate'
 */
    const recalculateForm = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: recalculate.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::recalculate
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:130
 * @route '/advisor/integration-scopes/{integrationScope}/recalculate'
 */
        recalculateForm.post = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: recalculate.url(args, options),
            method: 'post',
        })

    recalculate.form = recalculateForm
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::createFeeCalculation
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:142
 * @route '/advisor/integration-scopes/{integrationScope}/fee-calculations'
 */
export const createFeeCalculation = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: createFeeCalculation.url(args, options),
    method: 'post',
})

createFeeCalculation.definition = {
    methods: ["post"],
    url: '/advisor/integration-scopes/{integrationScope}/fee-calculations',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::createFeeCalculation
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:142
 * @route '/advisor/integration-scopes/{integrationScope}/fee-calculations'
 */
createFeeCalculation.url = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return createFeeCalculation.definition.url
            .replace('{integrationScope}', parsedArgs.integrationScope.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::createFeeCalculation
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:142
 * @route '/advisor/integration-scopes/{integrationScope}/fee-calculations'
 */
createFeeCalculation.post = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: createFeeCalculation.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::createFeeCalculation
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:142
 * @route '/advisor/integration-scopes/{integrationScope}/fee-calculations'
 */
    const createFeeCalculationForm = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: createFeeCalculation.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::createFeeCalculation
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:142
 * @route '/advisor/integration-scopes/{integrationScope}/fee-calculations'
 */
        createFeeCalculationForm.post = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: createFeeCalculation.url(args, options),
            method: 'post',
        })

    createFeeCalculation.form = createFeeCalculationForm
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::extractQuoteSources
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:162
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions'
 */
export const extractQuoteSources = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: extractQuoteSources.url(args, options),
    method: 'post',
})

extractQuoteSources.definition = {
    methods: ["post"],
    url: '/advisor/integration-scopes/{integrationScope}/quote-source-extractions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::extractQuoteSources
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:162
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions'
 */
extractQuoteSources.url = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return extractQuoteSources.definition.url
            .replace('{integrationScope}', parsedArgs.integrationScope.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::extractQuoteSources
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:162
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions'
 */
extractQuoteSources.post = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: extractQuoteSources.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::extractQuoteSources
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:162
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions'
 */
    const extractQuoteSourcesForm = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: extractQuoteSources.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::extractQuoteSources
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:162
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions'
 */
        extractQuoteSourcesForm.post = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: extractQuoteSources.url(args, options),
            method: 'post',
        })

    extractQuoteSources.form = extractQuoteSourcesForm
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::retryQuoteSourceExtraction
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:204
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/retry'
 */
export const retryQuoteSourceExtraction = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retryQuoteSourceExtraction.url(args, options),
    method: 'post',
})

retryQuoteSourceExtraction.definition = {
    methods: ["post"],
    url: '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/retry',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::retryQuoteSourceExtraction
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:204
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/retry'
 */
retryQuoteSourceExtraction.url = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions) => {
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

    return retryQuoteSourceExtraction.definition.url
            .replace('{integrationScope}', parsedArgs.integrationScope.toString())
            .replace('{quoteSourceExtraction}', parsedArgs.quoteSourceExtraction.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::retryQuoteSourceExtraction
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:204
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/retry'
 */
retryQuoteSourceExtraction.post = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: retryQuoteSourceExtraction.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::retryQuoteSourceExtraction
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:204
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/retry'
 */
    const retryQuoteSourceExtractionForm = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: retryQuoteSourceExtraction.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::retryQuoteSourceExtraction
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:204
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/retry'
 */
        retryQuoteSourceExtractionForm.post = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: retryQuoteSourceExtraction.url(args, options),
            method: 'post',
        })

    retryQuoteSourceExtraction.form = retryQuoteSourceExtractionForm
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::confirmQuoteSourceRows
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:223
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/confirm'
 */
export const confirmQuoteSourceRows = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirmQuoteSourceRows.url(args, options),
    method: 'post',
})

confirmQuoteSourceRows.definition = {
    methods: ["post"],
    url: '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/confirm',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::confirmQuoteSourceRows
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:223
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/confirm'
 */
confirmQuoteSourceRows.url = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions) => {
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

    return confirmQuoteSourceRows.definition.url
            .replace('{integrationScope}', parsedArgs.integrationScope.toString())
            .replace('{quoteSourceExtraction}', parsedArgs.quoteSourceExtraction.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::confirmQuoteSourceRows
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:223
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/confirm'
 */
confirmQuoteSourceRows.post = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: confirmQuoteSourceRows.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::confirmQuoteSourceRows
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:223
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/confirm'
 */
    const confirmQuoteSourceRowsForm = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: confirmQuoteSourceRows.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::confirmQuoteSourceRows
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:223
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/confirm'
 */
        confirmQuoteSourceRowsForm.post = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: confirmQuoteSourceRows.url(args, options),
            method: 'post',
        })

    confirmQuoteSourceRows.form = confirmQuoteSourceRowsForm
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::rejectQuoteSourceRows
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:243
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/reject'
 */
export const rejectQuoteSourceRows = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: rejectQuoteSourceRows.url(args, options),
    method: 'post',
})

rejectQuoteSourceRows.definition = {
    methods: ["post"],
    url: '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/reject',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::rejectQuoteSourceRows
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:243
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/reject'
 */
rejectQuoteSourceRows.url = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions) => {
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

    return rejectQuoteSourceRows.definition.url
            .replace('{integrationScope}', parsedArgs.integrationScope.toString())
            .replace('{quoteSourceExtraction}', parsedArgs.quoteSourceExtraction.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::rejectQuoteSourceRows
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:243
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/reject'
 */
rejectQuoteSourceRows.post = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: rejectQuoteSourceRows.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::rejectQuoteSourceRows
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:243
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/reject'
 */
    const rejectQuoteSourceRowsForm = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: rejectQuoteSourceRows.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::rejectQuoteSourceRows
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:243
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-extractions/{quoteSourceExtraction}/reject'
 */
        rejectQuoteSourceRowsForm.post = (args: { integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } } | [integrationScope: string | { id: string }, quoteSourceExtraction: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: rejectQuoteSourceRows.url(args, options),
            method: 'post',
        })

    rejectQuoteSourceRows.form = rejectQuoteSourceRowsForm
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::showQuoteSourceDocument
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:263
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-documents/{document}'
 */
export const showQuoteSourceDocument = (args: { integrationScope: string | { id: string }, document: string | number } | [integrationScope: string | { id: string }, document: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showQuoteSourceDocument.url(args, options),
    method: 'get',
})

showQuoteSourceDocument.definition = {
    methods: ["get","head"],
    url: '/advisor/integration-scopes/{integrationScope}/quote-source-documents/{document}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::showQuoteSourceDocument
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:263
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-documents/{document}'
 */
showQuoteSourceDocument.url = (args: { integrationScope: string | { id: string }, document: string | number } | [integrationScope: string | { id: string }, document: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    integrationScope: args[0],
                    document: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        integrationScope: typeof args.integrationScope === 'object'
                ? args.integrationScope.id
                : args.integrationScope,
                                document: args.document,
                }

    return showQuoteSourceDocument.definition.url
            .replace('{integrationScope}', parsedArgs.integrationScope.toString())
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::showQuoteSourceDocument
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:263
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-documents/{document}'
 */
showQuoteSourceDocument.get = (args: { integrationScope: string | { id: string }, document: string | number } | [integrationScope: string | { id: string }, document: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showQuoteSourceDocument.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::showQuoteSourceDocument
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:263
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-documents/{document}'
 */
showQuoteSourceDocument.head = (args: { integrationScope: string | { id: string }, document: string | number } | [integrationScope: string | { id: string }, document: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showQuoteSourceDocument.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::showQuoteSourceDocument
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:263
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-documents/{document}'
 */
    const showQuoteSourceDocumentForm = (args: { integrationScope: string | { id: string }, document: string | number } | [integrationScope: string | { id: string }, document: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: showQuoteSourceDocument.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::showQuoteSourceDocument
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:263
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-documents/{document}'
 */
        showQuoteSourceDocumentForm.get = (args: { integrationScope: string | { id: string }, document: string | number } | [integrationScope: string | { id: string }, document: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: showQuoteSourceDocument.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::showQuoteSourceDocument
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:263
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-documents/{document}'
 */
        showQuoteSourceDocumentForm.head = (args: { integrationScope: string | { id: string }, document: string | number } | [integrationScope: string | { id: string }, document: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: showQuoteSourceDocument.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    showQuoteSourceDocument.form = showQuoteSourceDocumentForm
const IntegrationScopeController = { index, show, store, update, recalculate, createFeeCalculation, extractQuoteSources, retryQuoteSourceExtraction, confirmQuoteSourceRows, rejectQuoteSourceRows, showQuoteSourceDocument }

export default IntegrationScopeController