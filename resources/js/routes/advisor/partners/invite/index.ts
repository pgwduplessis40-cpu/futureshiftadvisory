import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::resend
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:75
 * @route '/advisor/partners/{panelMember}/invite/resend'
 */
export const resend = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(args, options),
    method: 'post',
})

resend.definition = {
    methods: ["post"],
    url: '/advisor/partners/{panelMember}/invite/resend',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::resend
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:75
 * @route '/advisor/partners/{panelMember}/invite/resend'
 */
resend.url = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return resend.definition.url
            .replace('{panelMember}', parsedArgs.panelMember.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::resend
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:75
 * @route '/advisor/partners/{panelMember}/invite/resend'
 */
resend.post = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: resend.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::resend
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:75
 * @route '/advisor/partners/{panelMember}/invite/resend'
 */
    const resendForm = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: resend.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::resend
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:75
 * @route '/advisor/partners/{panelMember}/invite/resend'
 */
        resendForm.post = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: resend.url(args, options),
            method: 'post',
        })
    
    resend.form = resendForm
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::cancel
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:129
 * @route '/advisor/partners/{panelMember}/invite'
 */
export const cancel = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancel.url(args, options),
    method: 'delete',
})

cancel.definition = {
    methods: ["delete"],
    url: '/advisor/partners/{panelMember}/invite',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::cancel
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:129
 * @route '/advisor/partners/{panelMember}/invite'
 */
cancel.url = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return cancel.definition.url
            .replace('{panelMember}', parsedArgs.panelMember.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::cancel
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:129
 * @route '/advisor/partners/{panelMember}/invite'
 */
cancel.delete = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancel.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::cancel
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:129
 * @route '/advisor/partners/{panelMember}/invite'
 */
    const cancelForm = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: cancel.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::cancel
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:129
 * @route '/advisor/partners/{panelMember}/invite'
 */
        cancelForm.delete = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: cancel.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    cancel.form = cancelForm
const invite = {
    resend: Object.assign(resend, resend),
cancel: Object.assign(cancel, cancel),
}

export default invite