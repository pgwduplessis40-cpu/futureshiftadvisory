import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import feeCalculations from './fee-calculations'
import quoteSourceExtractions from './quote-source-extractions'
import quoteSourceDocuments from './quote-source-documents'
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::index
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:34
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
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:34
 * @route '/advisor/integration-scopes'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::index
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:34
 * @route '/advisor/integration-scopes'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::index
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:34
 * @route '/advisor/integration-scopes'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::index
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:34
 * @route '/advisor/integration-scopes'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::index
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:34
 * @route '/advisor/integration-scopes'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::index
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:34
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
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:84
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
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:84
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
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:84
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
show.get = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:84
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
show.head = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:84
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
    const showForm = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:84
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
        showForm.get = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:84
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
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::update
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:120
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
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:120
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
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:120
 * @route '/advisor/integration-scopes/{integrationScope}'
 */
update.patch = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::update
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:120
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
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:120
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
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:132
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
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:132
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
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:132
 * @route '/advisor/integration-scopes/{integrationScope}/recalculate'
 */
recalculate.post = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: recalculate.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::recalculate
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:132
 * @route '/advisor/integration-scopes/{integrationScope}/recalculate'
 */
    const recalculateForm = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: recalculate.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::recalculate
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:132
 * @route '/advisor/integration-scopes/{integrationScope}/recalculate'
 */
        recalculateForm.post = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: recalculate.url(args, options),
            method: 'post',
        })

    recalculate.form = recalculateForm
const integrationScopes = {
    index: Object.assign(index, index),
show: Object.assign(show, show),
update: Object.assign(update, update),
recalculate: Object.assign(recalculate, recalculate),
feeCalculations: Object.assign(feeCalculations, feeCalculations),
quoteSourceExtractions: Object.assign(quoteSourceExtractions, quoteSourceExtractions),
quoteSourceDocuments: Object.assign(quoteSourceDocuments, quoteSourceDocuments),
}

export default integrationScopes