import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:263
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-documents/{document}'
 */
export const show = (args: { integrationScope: string | { id: string }, document: string | number } | [integrationScope: string | { id: string }, document: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/integration-scopes/{integrationScope}/quote-source-documents/{document}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:263
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-documents/{document}'
 */
show.url = (args: { integrationScope: string | { id: string }, document: string | number } | [integrationScope: string | { id: string }, document: string | number ], options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{integrationScope}', parsedArgs.integrationScope.toString())
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:263
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-documents/{document}'
 */
show.get = (args: { integrationScope: string | { id: string }, document: string | number } | [integrationScope: string | { id: string }, document: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:263
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-documents/{document}'
 */
show.head = (args: { integrationScope: string | { id: string }, document: string | number } | [integrationScope: string | { id: string }, document: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:263
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-documents/{document}'
 */
    const showForm = (args: { integrationScope: string | { id: string }, document: string | number } | [integrationScope: string | { id: string }, document: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:263
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-documents/{document}'
 */
        showForm.get = (args: { integrationScope: string | { id: string }, document: string | number } | [integrationScope: string | { id: string }, document: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::show
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:263
 * @route '/advisor/integration-scopes/{integrationScope}/quote-source-documents/{document}'
 */
        showForm.head = (args: { integrationScope: string | { id: string }, document: string | number } | [integrationScope: string | { id: string }, document: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    show.form = showForm
const quoteSourceDocuments = {
    show: Object.assign(show, show),
}

export default quoteSourceDocuments