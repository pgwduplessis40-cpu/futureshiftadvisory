import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\PanelAgreementController::sign
 * @see app/Http/Controllers/PanelAgreementController.php:25
 * @route '/panel/agreements/{panelAgreement}/sign'
 */
export const sign = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sign.url(args, options),
    method: 'post',
})

sign.definition = {
    methods: ["post"],
    url: '/panel/agreements/{panelAgreement}/sign',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PanelAgreementController::sign
 * @see app/Http/Controllers/PanelAgreementController.php:25
 * @route '/panel/agreements/{panelAgreement}/sign'
 */
sign.url = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { panelAgreement: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { panelAgreement: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    panelAgreement: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        panelAgreement: typeof args.panelAgreement === 'object'
                ? args.panelAgreement.id
                : args.panelAgreement,
                }

    return sign.definition.url
            .replace('{panelAgreement}', parsedArgs.panelAgreement.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PanelAgreementController::sign
 * @see app/Http/Controllers/PanelAgreementController.php:25
 * @route '/panel/agreements/{panelAgreement}/sign'
 */
sign.post = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sign.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PanelAgreementController::sign
 * @see app/Http/Controllers/PanelAgreementController.php:25
 * @route '/panel/agreements/{panelAgreement}/sign'
 */
    const signForm = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: sign.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PanelAgreementController::sign
 * @see app/Http/Controllers/PanelAgreementController.php:25
 * @route '/panel/agreements/{panelAgreement}/sign'
 */
        signForm.post = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: sign.url(args, options),
            method: 'post',
        })
    
    sign.form = signForm
/**
* @see \App\Http\Controllers\PanelAgreementController::view
 * @see app/Http/Controllers/PanelAgreementController.php:70
 * @route '/panel/agreements/{panelAgreement}/view'
 */
export const view = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: view.url(args, options),
    method: 'get',
})

view.definition = {
    methods: ["get","head"],
    url: '/panel/agreements/{panelAgreement}/view',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PanelAgreementController::view
 * @see app/Http/Controllers/PanelAgreementController.php:70
 * @route '/panel/agreements/{panelAgreement}/view'
 */
view.url = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { panelAgreement: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { panelAgreement: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    panelAgreement: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        panelAgreement: typeof args.panelAgreement === 'object'
                ? args.panelAgreement.id
                : args.panelAgreement,
                }

    return view.definition.url
            .replace('{panelAgreement}', parsedArgs.panelAgreement.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PanelAgreementController::view
 * @see app/Http/Controllers/PanelAgreementController.php:70
 * @route '/panel/agreements/{panelAgreement}/view'
 */
view.get = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: view.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PanelAgreementController::view
 * @see app/Http/Controllers/PanelAgreementController.php:70
 * @route '/panel/agreements/{panelAgreement}/view'
 */
view.head = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: view.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PanelAgreementController::view
 * @see app/Http/Controllers/PanelAgreementController.php:70
 * @route '/panel/agreements/{panelAgreement}/view'
 */
    const viewForm = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: view.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PanelAgreementController::view
 * @see app/Http/Controllers/PanelAgreementController.php:70
 * @route '/panel/agreements/{panelAgreement}/view'
 */
        viewForm.get = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: view.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PanelAgreementController::view
 * @see app/Http/Controllers/PanelAgreementController.php:70
 * @route '/panel/agreements/{panelAgreement}/view'
 */
        viewForm.head = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: view.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    view.form = viewForm
/**
* @see \App\Http\Controllers\PanelAgreementController::download
 * @see app/Http/Controllers/PanelAgreementController.php:55
 * @route '/panel/agreements/{panelAgreement}/download'
 */
export const download = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/panel/agreements/{panelAgreement}/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PanelAgreementController::download
 * @see app/Http/Controllers/PanelAgreementController.php:55
 * @route '/panel/agreements/{panelAgreement}/download'
 */
download.url = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { panelAgreement: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { panelAgreement: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    panelAgreement: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        panelAgreement: typeof args.panelAgreement === 'object'
                ? args.panelAgreement.id
                : args.panelAgreement,
                }

    return download.definition.url
            .replace('{panelAgreement}', parsedArgs.panelAgreement.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PanelAgreementController::download
 * @see app/Http/Controllers/PanelAgreementController.php:55
 * @route '/panel/agreements/{panelAgreement}/download'
 */
download.get = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\PanelAgreementController::download
 * @see app/Http/Controllers/PanelAgreementController.php:55
 * @route '/panel/agreements/{panelAgreement}/download'
 */
download.head = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\PanelAgreementController::download
 * @see app/Http/Controllers/PanelAgreementController.php:55
 * @route '/panel/agreements/{panelAgreement}/download'
 */
    const downloadForm = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: download.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\PanelAgreementController::download
 * @see app/Http/Controllers/PanelAgreementController.php:55
 * @route '/panel/agreements/{panelAgreement}/download'
 */
        downloadForm.get = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\PanelAgreementController::download
 * @see app/Http/Controllers/PanelAgreementController.php:55
 * @route '/panel/agreements/{panelAgreement}/download'
 */
        downloadForm.head = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    download.form = downloadForm
const agreements = {
    sign: Object.assign(sign, sign),
view: Object.assign(view, view),
download: Object.assign(download, download),
}

export default agreements