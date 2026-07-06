import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import brokers from './brokers'
import coaches from './coaches'
import invite from './invite'
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::show
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:174
 * @route '/advisor/partners/{panelMember}'
 */
export const show = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/partners/{panelMember}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::show
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:174
 * @route '/advisor/partners/{panelMember}'
 */
show.url = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{panelMember}', parsedArgs.panelMember.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::show
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:174
 * @route '/advisor/partners/{panelMember}'
 */
show.get = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::show
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:174
 * @route '/advisor/partners/{panelMember}'
 */
show.head = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::show
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:174
 * @route '/advisor/partners/{panelMember}'
 */
    const showForm = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::show
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:174
 * @route '/advisor/partners/{panelMember}'
 */
        showForm.get = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::show
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:174
 * @route '/advisor/partners/{panelMember}'
 */
        showForm.head = (args: { panelMember: string | { id: string } } | [panelMember: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    show.form = showForm
const partners = {
    brokers: Object.assign(brokers, brokers),
coaches: Object.assign(coaches, coaches),
invite: Object.assign(invite, invite),
show: Object.assign(show, show),
}

export default partners