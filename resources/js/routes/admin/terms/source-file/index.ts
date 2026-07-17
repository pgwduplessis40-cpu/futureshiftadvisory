import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\TermsController::store
 * @see app/Http/Controllers/Admin/TermsController.php:191
 * @route '/admin/terms/{termsVersion}/source-file'
 */
export const store = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/terms/{termsVersion}/source-file',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\TermsController::store
 * @see app/Http/Controllers/Admin/TermsController.php:191
 * @route '/admin/terms/{termsVersion}/source-file'
 */
store.url = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { termsVersion: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { termsVersion: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    termsVersion: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        termsVersion: typeof args.termsVersion === 'object'
                ? args.termsVersion.id
                : args.termsVersion,
                }

    return store.definition.url
            .replace('{termsVersion}', parsedArgs.termsVersion.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\TermsController::store
 * @see app/Http/Controllers/Admin/TermsController.php:191
 * @route '/admin/terms/{termsVersion}/source-file'
 */
store.post = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\TermsController::store
 * @see app/Http/Controllers/Admin/TermsController.php:191
 * @route '/admin/terms/{termsVersion}/source-file'
 */
    const storeForm = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\TermsController::store
 * @see app/Http/Controllers/Admin/TermsController.php:191
 * @route '/admin/terms/{termsVersion}/source-file'
 */
        storeForm.post = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\TermsController::download
 * @see app/Http/Controllers/Admin/TermsController.php:236
 * @route '/admin/terms/{termsVersion}/source-file/download'
 */
export const download = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/admin/terms/{termsVersion}/source-file/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\TermsController::download
 * @see app/Http/Controllers/Admin/TermsController.php:236
 * @route '/admin/terms/{termsVersion}/source-file/download'
 */
download.url = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { termsVersion: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { termsVersion: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    termsVersion: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        termsVersion: typeof args.termsVersion === 'object'
                ? args.termsVersion.id
                : args.termsVersion,
                }

    return download.definition.url
            .replace('{termsVersion}', parsedArgs.termsVersion.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\TermsController::download
 * @see app/Http/Controllers/Admin/TermsController.php:236
 * @route '/admin/terms/{termsVersion}/source-file/download'
 */
download.get = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\TermsController::download
 * @see app/Http/Controllers/Admin/TermsController.php:236
 * @route '/admin/terms/{termsVersion}/source-file/download'
 */
download.head = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\TermsController::download
 * @see app/Http/Controllers/Admin/TermsController.php:236
 * @route '/admin/terms/{termsVersion}/source-file/download'
 */
    const downloadForm = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: download.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\TermsController::download
 * @see app/Http/Controllers/Admin/TermsController.php:236
 * @route '/admin/terms/{termsVersion}/source-file/download'
 */
        downloadForm.get = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\TermsController::download
 * @see app/Http/Controllers/Admin/TermsController.php:236
 * @route '/admin/terms/{termsVersion}/source-file/download'
 */
        downloadForm.head = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    download.form = downloadForm
const sourceFile = {
    store: Object.assign(store, store),
download: Object.assign(download, download),
}

export default sourceFile