import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::response
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:63
 * @route '/portal/co-browse-sessions/{session}/response'
 */
export const response = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: response.url(args, options),
    method: 'post',
})

response.definition = {
    methods: ["post"],
    url: '/portal/co-browse-sessions/{session}/response',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::response
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:63
 * @route '/portal/co-browse-sessions/{session}/response'
 */
response.url = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { session: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { session: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    session: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        session: typeof args.session === 'object'
                ? args.session.id
                : args.session,
                }

    return response.definition.url
            .replace('{session}', parsedArgs.session.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::response
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:63
 * @route '/portal/co-browse-sessions/{session}/response'
 */
response.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: response.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::response
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:63
 * @route '/portal/co-browse-sessions/{session}/response'
 */
    const responseForm = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: response.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\CoBrowse\CoBrowseSessionController::response
 * @see app/Http/Controllers/CoBrowse/CoBrowseSessionController.php:63
 * @route '/portal/co-browse-sessions/{session}/response'
 */
        responseForm.post = (args: { session: string | number | { id: string | number } } | [session: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: response.url(args, options),
            method: 'post',
        })

    response.form = responseForm
const sessions = {
    response: Object.assign(response, response),
}

export default sessions