import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::store
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:144
 * @route '/advisor/integration-scopes/{integrationScope}/fee-calculations'
 */
export const store = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/integration-scopes/{integrationScope}/fee-calculations',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::store
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:144
 * @route '/advisor/integration-scopes/{integrationScope}/fee-calculations'
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
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:144
 * @route '/advisor/integration-scopes/{integrationScope}/fee-calculations'
 */
store.post = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::store
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:144
 * @route '/advisor/integration-scopes/{integrationScope}/fee-calculations'
 */
    const storeForm = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\IntegrationScopeController::store
 * @see app/Http/Controllers/Advisor/IntegrationScopeController.php:144
 * @route '/advisor/integration-scopes/{integrationScope}/fee-calculations'
 */
        storeForm.post = (args: { integrationScope: string | { id: string } } | [integrationScope: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
const feeCalculations = {
    store: Object.assign(store, store),
}

export default feeCalculations