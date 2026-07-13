import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::offer
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:100
 * @route '/advisor/clients/{client}/integration-scoping-offer'
 */
export const offer = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: offer.url(args, options),
    method: 'post',
})

offer.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/integration-scoping-offer',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::offer
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:100
 * @route '/advisor/clients/{client}/integration-scoping-offer'
 */
offer.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { client: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                }

    return offer.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::offer
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:100
 * @route '/advisor/clients/{client}/integration-scoping-offer'
 */
offer.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: offer.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::offer
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:100
 * @route '/advisor/clients/{client}/integration-scoping-offer'
 */
    const offerForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: offer.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ServiceActivationController::offer
 * @see app/Http/Controllers/Advisor/ServiceActivationController.php:100
 * @route '/advisor/clients/{client}/integration-scoping-offer'
 */
        offerForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: offer.url(args, options),
            method: 'post',
        })
    
    offer.form = offerForm
const integrationScoping = {
    offer: Object.assign(offer, offer),
}

export default integrationScoping