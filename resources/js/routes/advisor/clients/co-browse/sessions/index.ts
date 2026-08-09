import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::store
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:25
 * @route '/advisor/clients/{client}/co-browse-sessions'
 */
export const store = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/co-browse-sessions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::store
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:25
 * @route '/advisor/clients/{client}/co-browse-sessions'
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
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::store
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:25
 * @route '/advisor/clients/{client}/co-browse-sessions'
 */
store.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::store
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:25
 * @route '/advisor/clients/{client}/co-browse-sessions'
 */
    const storeForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::store
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:25
 * @route '/advisor/clients/{client}/co-browse-sessions'
 */
        storeForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })

    store.form = storeForm
const sessions = {
    store: Object.assign(store, store),
}

export default sessions