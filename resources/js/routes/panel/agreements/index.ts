import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\PanelAgreementController::sign
 * @see app/Http/Controllers/PanelAgreementController.php:15
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
 * @see app/Http/Controllers/PanelAgreementController.php:15
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
 * @see app/Http/Controllers/PanelAgreementController.php:15
 * @route '/panel/agreements/{panelAgreement}/sign'
 */
sign.post = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sign.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\PanelAgreementController::sign
 * @see app/Http/Controllers/PanelAgreementController.php:15
 * @route '/panel/agreements/{panelAgreement}/sign'
 */
    const signForm = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: sign.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\PanelAgreementController::sign
 * @see app/Http/Controllers/PanelAgreementController.php:15
 * @route '/panel/agreements/{panelAgreement}/sign'
 */
        signForm.post = (args: { panelAgreement: string | { id: string } } | [panelAgreement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: sign.url(args, options),
            method: 'post',
        })
    
    sign.form = signForm
const agreements = {
    sign: Object.assign(sign, sign),
}

export default agreements