import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\TestimonialController::nps
 * @see app/Http/Controllers/Advisor/TestimonialController.php:30
 * @route '/advisor/clients/{client}/testimonials/nps'
 */
export const nps = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: nps.url(args, options),
    method: 'post',
})

nps.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/testimonials/nps',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\TestimonialController::nps
 * @see app/Http/Controllers/Advisor/TestimonialController.php:30
 * @route '/advisor/clients/{client}/testimonials/nps'
 */
nps.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return nps.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\TestimonialController::nps
 * @see app/Http/Controllers/Advisor/TestimonialController.php:30
 * @route '/advisor/clients/{client}/testimonials/nps'
 */
nps.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: nps.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\TestimonialController::nps
 * @see app/Http/Controllers/Advisor/TestimonialController.php:30
 * @route '/advisor/clients/{client}/testimonials/nps'
 */
    const npsForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: nps.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\TestimonialController::nps
 * @see app/Http/Controllers/Advisor/TestimonialController.php:30
 * @route '/advisor/clients/{client}/testimonials/nps'
 */
        npsForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: nps.url(args, options),
            method: 'post',
        })
    
    nps.form = npsForm
const testimonials = {
    nps: Object.assign(nps, nps),
}

export default testimonials