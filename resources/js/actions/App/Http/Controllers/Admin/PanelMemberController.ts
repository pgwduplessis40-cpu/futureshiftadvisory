import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\PanelMemberController::index
 * @see app/Http/Controllers/Admin/PanelMemberController.php:18
 * @route '/admin/panel-members'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/panel-members',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\PanelMemberController::index
 * @see app/Http/Controllers/Admin/PanelMemberController.php:18
 * @route '/admin/panel-members'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PanelMemberController::index
 * @see app/Http/Controllers/Admin/PanelMemberController.php:18
 * @route '/admin/panel-members'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\PanelMemberController::index
 * @see app/Http/Controllers/Admin/PanelMemberController.php:18
 * @route '/admin/panel-members'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\PanelMemberController::index
 * @see app/Http/Controllers/Admin/PanelMemberController.php:18
 * @route '/admin/panel-members'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\PanelMemberController::index
 * @see app/Http/Controllers/Admin/PanelMemberController.php:18
 * @route '/admin/panel-members'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\PanelMemberController::index
 * @see app/Http/Controllers/Admin/PanelMemberController.php:18
 * @route '/admin/panel-members'
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
* @see \App\Http\Controllers\Admin\PanelMemberController::approve
 * @see app/Http/Controllers/Admin/PanelMemberController.php:52
 * @route '/admin/panel-members/{panelMember}/approve'
 */
export const approve = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: approve.url(args, options),
    method: 'patch',
})

approve.definition = {
    methods: ["patch"],
    url: '/admin/panel-members/{panelMember}/approve',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\PanelMemberController::approve
 * @see app/Http/Controllers/Admin/PanelMemberController.php:52
 * @route '/admin/panel-members/{panelMember}/approve'
 */
approve.url = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { panelMember: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { panelMember: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    panelMember: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        panelMember: typeof args.panelMember === 'object'
                ? args.panelMember.id
                : args.panelMember,
                }

    return approve.definition.url
            .replace('{panelMember}', parsedArgs.panelMember.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PanelMemberController::approve
 * @see app/Http/Controllers/Admin/PanelMemberController.php:52
 * @route '/admin/panel-members/{panelMember}/approve'
 */
approve.patch = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: approve.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\PanelMemberController::approve
 * @see app/Http/Controllers/Admin/PanelMemberController.php:52
 * @route '/admin/panel-members/{panelMember}/approve'
 */
    const approveForm = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: approve.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\PanelMemberController::approve
 * @see app/Http/Controllers/Admin/PanelMemberController.php:52
 * @route '/admin/panel-members/{panelMember}/approve'
 */
        approveForm.patch = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: approve.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    approve.form = approveForm
/**
* @see \App\Http\Controllers\Admin\PanelMemberController::requestInfo
 * @see app/Http/Controllers/Admin/PanelMemberController.php:65
 * @route '/admin/panel-members/{panelMember}/request-info'
 */
export const requestInfo = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: requestInfo.url(args, options),
    method: 'patch',
})

requestInfo.definition = {
    methods: ["patch"],
    url: '/admin/panel-members/{panelMember}/request-info',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\PanelMemberController::requestInfo
 * @see app/Http/Controllers/Admin/PanelMemberController.php:65
 * @route '/admin/panel-members/{panelMember}/request-info'
 */
requestInfo.url = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { panelMember: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { panelMember: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    panelMember: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        panelMember: typeof args.panelMember === 'object'
                ? args.panelMember.id
                : args.panelMember,
                }

    return requestInfo.definition.url
            .replace('{panelMember}', parsedArgs.panelMember.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PanelMemberController::requestInfo
 * @see app/Http/Controllers/Admin/PanelMemberController.php:65
 * @route '/admin/panel-members/{panelMember}/request-info'
 */
requestInfo.patch = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: requestInfo.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\PanelMemberController::requestInfo
 * @see app/Http/Controllers/Admin/PanelMemberController.php:65
 * @route '/admin/panel-members/{panelMember}/request-info'
 */
    const requestInfoForm = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: requestInfo.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\PanelMemberController::requestInfo
 * @see app/Http/Controllers/Admin/PanelMemberController.php:65
 * @route '/admin/panel-members/{panelMember}/request-info'
 */
        requestInfoForm.patch = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: requestInfo.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    requestInfo.form = requestInfoForm
/**
* @see \App\Http\Controllers\Admin\PanelMemberController::decline
 * @see app/Http/Controllers/Admin/PanelMemberController.php:77
 * @route '/admin/panel-members/{panelMember}/decline'
 */
export const decline = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: decline.url(args, options),
    method: 'patch',
})

decline.definition = {
    methods: ["patch"],
    url: '/admin/panel-members/{panelMember}/decline',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\PanelMemberController::decline
 * @see app/Http/Controllers/Admin/PanelMemberController.php:77
 * @route '/admin/panel-members/{panelMember}/decline'
 */
decline.url = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { panelMember: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { panelMember: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    panelMember: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        panelMember: typeof args.panelMember === 'object'
                ? args.panelMember.id
                : args.panelMember,
                }

    return decline.definition.url
            .replace('{panelMember}', parsedArgs.panelMember.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PanelMemberController::decline
 * @see app/Http/Controllers/Admin/PanelMemberController.php:77
 * @route '/admin/panel-members/{panelMember}/decline'
 */
decline.patch = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: decline.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\PanelMemberController::decline
 * @see app/Http/Controllers/Admin/PanelMemberController.php:77
 * @route '/admin/panel-members/{panelMember}/decline'
 */
    const declineForm = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: decline.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\PanelMemberController::decline
 * @see app/Http/Controllers/Admin/PanelMemberController.php:77
 * @route '/admin/panel-members/{panelMember}/decline'
 */
        declineForm.patch = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: decline.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    decline.form = declineForm
const PanelMemberController = { index, approve, requestInfo, decline }

export default PanelMemberController