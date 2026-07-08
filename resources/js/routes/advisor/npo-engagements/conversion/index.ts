import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\NpoConversionController::reportDelivered
 * @see app/Http/Controllers/Advisor/NpoConversionController.php:18
 * @route '/advisor/npo-engagements/{npoEngagement}/conversion/report-delivered'
 */
export const reportDelivered = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reportDelivered.url(args, options),
    method: 'patch',
})

reportDelivered.definition = {
    methods: ["patch"],
    url: '/advisor/npo-engagements/{npoEngagement}/conversion/report-delivered',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\NpoConversionController::reportDelivered
 * @see app/Http/Controllers/Advisor/NpoConversionController.php:18
 * @route '/advisor/npo-engagements/{npoEngagement}/conversion/report-delivered'
 */
reportDelivered.url = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { npoEngagement: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { npoEngagement: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    npoEngagement: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        npoEngagement: typeof args.npoEngagement === 'object'
                ? args.npoEngagement.id
                : args.npoEngagement,
                }

    return reportDelivered.definition.url
            .replace('{npoEngagement}', parsedArgs.npoEngagement.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\NpoConversionController::reportDelivered
 * @see app/Http/Controllers/Advisor/NpoConversionController.php:18
 * @route '/advisor/npo-engagements/{npoEngagement}/conversion/report-delivered'
 */
reportDelivered.patch = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reportDelivered.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\NpoConversionController::reportDelivered
 * @see app/Http/Controllers/Advisor/NpoConversionController.php:18
 * @route '/advisor/npo-engagements/{npoEngagement}/conversion/report-delivered'
 */
    const reportDeliveredForm = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reportDelivered.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\NpoConversionController::reportDelivered
 * @see app/Http/Controllers/Advisor/NpoConversionController.php:18
 * @route '/advisor/npo-engagements/{npoEngagement}/conversion/report-delivered'
 */
        reportDeliveredForm.patch = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reportDelivered.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    reportDelivered.form = reportDeliveredForm
/**
* @see \App\Http\Controllers\Advisor\NpoConversionController::decline
 * @see app/Http/Controllers/Advisor/NpoConversionController.php:36
 * @route '/advisor/npo-engagements/{npoEngagement}/conversion/decline'
 */
export const decline = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: decline.url(args, options),
    method: 'patch',
})

decline.definition = {
    methods: ["patch"],
    url: '/advisor/npo-engagements/{npoEngagement}/conversion/decline',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\NpoConversionController::decline
 * @see app/Http/Controllers/Advisor/NpoConversionController.php:36
 * @route '/advisor/npo-engagements/{npoEngagement}/conversion/decline'
 */
decline.url = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { npoEngagement: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { npoEngagement: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    npoEngagement: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        npoEngagement: typeof args.npoEngagement === 'object'
                ? args.npoEngagement.id
                : args.npoEngagement,
                }

    return decline.definition.url
            .replace('{npoEngagement}', parsedArgs.npoEngagement.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\NpoConversionController::decline
 * @see app/Http/Controllers/Advisor/NpoConversionController.php:36
 * @route '/advisor/npo-engagements/{npoEngagement}/conversion/decline'
 */
decline.patch = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: decline.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\NpoConversionController::decline
 * @see app/Http/Controllers/Advisor/NpoConversionController.php:36
 * @route '/advisor/npo-engagements/{npoEngagement}/conversion/decline'
 */
    const declineForm = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: decline.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\NpoConversionController::decline
 * @see app/Http/Controllers/Advisor/NpoConversionController.php:36
 * @route '/advisor/npo-engagements/{npoEngagement}/conversion/decline'
 */
        declineForm.patch = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: decline.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    decline.form = declineForm
/**
* @see \App\Http\Controllers\Advisor\NpoConversionController::convert
 * @see app/Http/Controllers/Advisor/NpoConversionController.php:50
 * @route '/advisor/npo-engagements/{npoEngagement}/conversion/convert'
 */
export const convert = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: convert.url(args, options),
    method: 'patch',
})

convert.definition = {
    methods: ["patch"],
    url: '/advisor/npo-engagements/{npoEngagement}/conversion/convert',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\NpoConversionController::convert
 * @see app/Http/Controllers/Advisor/NpoConversionController.php:50
 * @route '/advisor/npo-engagements/{npoEngagement}/conversion/convert'
 */
convert.url = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { npoEngagement: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { npoEngagement: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    npoEngagement: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        npoEngagement: typeof args.npoEngagement === 'object'
                ? args.npoEngagement.id
                : args.npoEngagement,
                }

    return convert.definition.url
            .replace('{npoEngagement}', parsedArgs.npoEngagement.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\NpoConversionController::convert
 * @see app/Http/Controllers/Advisor/NpoConversionController.php:50
 * @route '/advisor/npo-engagements/{npoEngagement}/conversion/convert'
 */
convert.patch = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: convert.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\NpoConversionController::convert
 * @see app/Http/Controllers/Advisor/NpoConversionController.php:50
 * @route '/advisor/npo-engagements/{npoEngagement}/conversion/convert'
 */
    const convertForm = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: convert.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\NpoConversionController::convert
 * @see app/Http/Controllers/Advisor/NpoConversionController.php:50
 * @route '/advisor/npo-engagements/{npoEngagement}/conversion/convert'
 */
        convertForm.patch = (args: { npoEngagement: string | { id: string } } | [npoEngagement: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: convert.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    convert.form = convertForm
const conversion = {
    reportDelivered: Object.assign(reportDelivered, reportDelivered),
decline: Object.assign(decline, decline),
convert: Object.assign(convert, convert),
}

export default conversion