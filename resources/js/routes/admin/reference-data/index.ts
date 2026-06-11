import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::index
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:40
 * @route '/admin/reference-data'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/reference-data',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::index
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:40
 * @route '/admin/reference-data'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::index
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:40
 * @route '/admin/reference-data'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::index
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:40
 * @route '/admin/reference-data'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\ReferenceDataController::index
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:40
 * @route '/admin/reference-data'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\ReferenceDataController::index
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:40
 * @route '/admin/reference-data'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\ReferenceDataController::index
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:40
 * @route '/admin/reference-data'
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
* @see \App\Http\Controllers\Admin\ReferenceDataController::store
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:50
 * @route '/admin/reference-data'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/reference-data',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::store
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:50
 * @route '/admin/reference-data'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::store
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:50
 * @route '/admin/reference-data'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\ReferenceDataController::store
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:50
 * @route '/admin/reference-data'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ReferenceDataController::store
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:50
 * @route '/admin/reference-data'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::evidence
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:98
 * @route '/admin/reference-data/evidence/{document}'
 */
export const evidence = (args: { document: string | { id: string } } | [document: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: evidence.url(args, options),
    method: 'get',
})

evidence.definition = {
    methods: ["get","head"],
    url: '/admin/reference-data/evidence/{document}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::evidence
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:98
 * @route '/admin/reference-data/evidence/{document}'
 */
evidence.url = (args: { document: string | { id: string } } | [document: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return evidence.definition.url
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::evidence
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:98
 * @route '/admin/reference-data/evidence/{document}'
 */
evidence.get = (args: { document: string | { id: string } } | [document: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: evidence.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\ReferenceDataController::evidence
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:98
 * @route '/admin/reference-data/evidence/{document}'
 */
evidence.head = (args: { document: string | { id: string } } | [document: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: evidence.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\ReferenceDataController::evidence
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:98
 * @route '/admin/reference-data/evidence/{document}'
 */
    const evidenceForm = (args: { document: string | { id: string } } | [document: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: evidence.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\ReferenceDataController::evidence
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:98
 * @route '/admin/reference-data/evidence/{document}'
 */
        evidenceForm.get = (args: { document: string | { id: string } } | [document: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: evidence.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\ReferenceDataController::evidence
 * @see app/Http/Controllers/Admin/ReferenceDataController.php:98
 * @route '/admin/reference-data/evidence/{document}'
 */
        evidenceForm.head = (args: { document: string | { id: string } } | [document: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: evidence.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    evidence.form = evidenceForm
const referenceData = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
evidence: Object.assign(evidence, evidence),
}

export default referenceData