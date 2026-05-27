import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\TestimonialController::requestFromNps
 * @see app/Http/Controllers/Advisor/TestimonialController.php:30
 * @route '/advisor/clients/{client}/testimonials/nps'
 */
export const requestFromNps = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: requestFromNps.url(args, options),
    method: 'post',
})

requestFromNps.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/testimonials/nps',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\TestimonialController::requestFromNps
 * @see app/Http/Controllers/Advisor/TestimonialController.php:30
 * @route '/advisor/clients/{client}/testimonials/nps'
 */
requestFromNps.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return requestFromNps.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\TestimonialController::requestFromNps
 * @see app/Http/Controllers/Advisor/TestimonialController.php:30
 * @route '/advisor/clients/{client}/testimonials/nps'
 */
requestFromNps.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: requestFromNps.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\TestimonialController::requestFromNps
 * @see app/Http/Controllers/Advisor/TestimonialController.php:30
 * @route '/advisor/clients/{client}/testimonials/nps'
 */
    const requestFromNpsForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: requestFromNps.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\TestimonialController::requestFromNps
 * @see app/Http/Controllers/Advisor/TestimonialController.php:30
 * @route '/advisor/clients/{client}/testimonials/nps'
 */
        requestFromNpsForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: requestFromNps.url(args, options),
            method: 'post',
        })
    
    requestFromNps.form = requestFromNpsForm
/**
* @see \App\Http\Controllers\Advisor\TestimonialController::index
 * @see app/Http/Controllers/Advisor/TestimonialController.php:21
 * @route '/advisor/testimonials'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/testimonials',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\TestimonialController::index
 * @see app/Http/Controllers/Advisor/TestimonialController.php:21
 * @route '/advisor/testimonials'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\TestimonialController::index
 * @see app/Http/Controllers/Advisor/TestimonialController.php:21
 * @route '/advisor/testimonials'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\TestimonialController::index
 * @see app/Http/Controllers/Advisor/TestimonialController.php:21
 * @route '/advisor/testimonials'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\TestimonialController::index
 * @see app/Http/Controllers/Advisor/TestimonialController.php:21
 * @route '/advisor/testimonials'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\TestimonialController::index
 * @see app/Http/Controllers/Advisor/TestimonialController.php:21
 * @route '/advisor/testimonials'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\TestimonialController::index
 * @see app/Http/Controllers/Advisor/TestimonialController.php:21
 * @route '/advisor/testimonials'
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
* @see \App\Http\Controllers\Advisor\TestimonialController::capture
 * @see app/Http/Controllers/Advisor/TestimonialController.php:41
 * @route '/advisor/testimonials/{testimonial}/consent'
 */
export const capture = (args: { testimonial: string | { id: string } } | [testimonial: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: capture.url(args, options),
    method: 'patch',
})

capture.definition = {
    methods: ["patch"],
    url: '/advisor/testimonials/{testimonial}/consent',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\TestimonialController::capture
 * @see app/Http/Controllers/Advisor/TestimonialController.php:41
 * @route '/advisor/testimonials/{testimonial}/consent'
 */
capture.url = (args: { testimonial: string | { id: string } } | [testimonial: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { testimonial: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { testimonial: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    testimonial: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        testimonial: typeof args.testimonial === 'object'
                ? args.testimonial.id
                : args.testimonial,
                }

    return capture.definition.url
            .replace('{testimonial}', parsedArgs.testimonial.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\TestimonialController::capture
 * @see app/Http/Controllers/Advisor/TestimonialController.php:41
 * @route '/advisor/testimonials/{testimonial}/consent'
 */
capture.patch = (args: { testimonial: string | { id: string } } | [testimonial: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: capture.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\TestimonialController::capture
 * @see app/Http/Controllers/Advisor/TestimonialController.php:41
 * @route '/advisor/testimonials/{testimonial}/consent'
 */
    const captureForm = (args: { testimonial: string | { id: string } } | [testimonial: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: capture.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\TestimonialController::capture
 * @see app/Http/Controllers/Advisor/TestimonialController.php:41
 * @route '/advisor/testimonials/{testimonial}/consent'
 */
        captureForm.patch = (args: { testimonial: string | { id: string } } | [testimonial: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: capture.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    capture.form = captureForm
const TestimonialController = { requestFromNps, index, capture }

export default TestimonialController