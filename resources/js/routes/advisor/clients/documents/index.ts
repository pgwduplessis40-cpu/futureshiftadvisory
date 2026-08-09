import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ClientDocumentController::show
 * @see app/Http/Controllers/Advisor/ClientDocumentController.php:18
 * @route '/advisor/clients/{client}/documents/{document}'
 */
export const show = (args: { client: string | { id: string }, document: string | { id: string } } | [client: string | { id: string }, document: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/clients/{client}/documents/{document}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ClientDocumentController::show
 * @see app/Http/Controllers/Advisor/ClientDocumentController.php:18
 * @route '/advisor/clients/{client}/documents/{document}'
 */
show.url = (args: { client: string | { id: string }, document: string | { id: string } } | [client: string | { id: string }, document: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                    document: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                                document: typeof args.document === 'object'
                ? args.document.id
                : args.document,
                }

    return show.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ClientDocumentController::show
 * @see app/Http/Controllers/Advisor/ClientDocumentController.php:18
 * @route '/advisor/clients/{client}/documents/{document}'
 */
show.get = (args: { client: string | { id: string }, document: string | { id: string } } | [client: string | { id: string }, document: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ClientDocumentController::show
 * @see app/Http/Controllers/Advisor/ClientDocumentController.php:18
 * @route '/advisor/clients/{client}/documents/{document}'
 */
show.head = (args: { client: string | { id: string }, document: string | { id: string } } | [client: string | { id: string }, document: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ClientDocumentController::show
 * @see app/Http/Controllers/Advisor/ClientDocumentController.php:18
 * @route '/advisor/clients/{client}/documents/{document}'
 */
    const showForm = (args: { client: string | { id: string }, document: string | { id: string } } | [client: string | { id: string }, document: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ClientDocumentController::show
 * @see app/Http/Controllers/Advisor/ClientDocumentController.php:18
 * @route '/advisor/clients/{client}/documents/{document}'
 */
        showForm.get = (args: { client: string | { id: string }, document: string | { id: string } } | [client: string | { id: string }, document: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ClientDocumentController::show
 * @see app/Http/Controllers/Advisor/ClientDocumentController.php:18
 * @route '/advisor/clients/{client}/documents/{document}'
 */
        showForm.head = (args: { client: string | { id: string }, document: string | { id: string } } | [client: string | { id: string }, document: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    show.form = showForm
const documents = {
    show: Object.assign(show, show),
}

export default documents