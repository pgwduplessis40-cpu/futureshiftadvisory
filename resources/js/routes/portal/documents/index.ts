import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\DocumentController::__invoke
 * @see app/Http/Controllers/DocumentController.php:46
 * @route '/portal/documents'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/documents',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\DocumentController::__invoke
 * @see app/Http/Controllers/DocumentController.php:46
 * @route '/portal/documents'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DocumentController::__invoke
 * @see app/Http/Controllers/DocumentController.php:46
 * @route '/portal/documents'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\DocumentController::__invoke
 * @see app/Http/Controllers/DocumentController.php:46
 * @route '/portal/documents'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\DocumentController::__invoke
 * @see app/Http/Controllers/DocumentController.php:46
 * @route '/portal/documents'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\DocumentController::show
 * @see app/Http/Controllers/DocumentController.php:68
 * @route '/portal/documents/{document}'
 */
export const show = (args: { document: string | { id: string } } | [document: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/documents/{document}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DocumentController::show
 * @see app/Http/Controllers/DocumentController.php:68
 * @route '/portal/documents/{document}'
 */
show.url = (args: { document: string | { id: string } } | [document: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { document: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { document: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    document: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        document: typeof args.document === 'object'
                ? args.document.id
                : args.document,
                }

    return show.definition.url
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\DocumentController::show
 * @see app/Http/Controllers/DocumentController.php:68
 * @route '/portal/documents/{document}'
 */
show.get = (args: { document: string | { id: string } } | [document: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\DocumentController::show
 * @see app/Http/Controllers/DocumentController.php:68
 * @route '/portal/documents/{document}'
 */
show.head = (args: { document: string | { id: string } } | [document: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\DocumentController::show
 * @see app/Http/Controllers/DocumentController.php:68
 * @route '/portal/documents/{document}'
 */
    const showForm = (args: { document: string | { id: string } } | [document: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\DocumentController::show
 * @see app/Http/Controllers/DocumentController.php:68
 * @route '/portal/documents/{document}'
 */
        showForm.get = (args: { document: string | { id: string } } | [document: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\DocumentController::show
 * @see app/Http/Controllers/DocumentController.php:68
 * @route '/portal/documents/{document}'
 */
        showForm.head = (args: { document: string | { id: string } } | [document: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
    store: Object.assign(store, store),
show: Object.assign(show, show),
}

export default documents