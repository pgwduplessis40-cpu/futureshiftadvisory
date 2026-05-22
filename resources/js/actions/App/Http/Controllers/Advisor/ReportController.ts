import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ReportController::store
 * @see app/Http/Controllers/Advisor/ReportController.php:20
 * @route '/advisor/clients/{client}/reports'
 */
export const store = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/reports',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ReportController::store
 * @see app/Http/Controllers/Advisor/ReportController.php:20
 * @route '/advisor/clients/{client}/reports'
 */
store.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ReportController::store
 * @see app/Http/Controllers/Advisor/ReportController.php:20
 * @route '/advisor/clients/{client}/reports'
 */
store.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ReportController::store
 * @see app/Http/Controllers/Advisor/ReportController.php:20
 * @route '/advisor/clients/{client}/reports'
 */
    const storeForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ReportController::store
 * @see app/Http/Controllers/Advisor/ReportController.php:20
 * @route '/advisor/clients/{client}/reports'
 */
        storeForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Advisor\ReportController::review
 * @see app/Http/Controllers/Advisor/ReportController.php:36
 * @route '/advisor/reports/{report}/review'
 */
export const review = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: review.url(args, options),
    method: 'patch',
})

review.definition = {
    methods: ["patch"],
    url: '/advisor/reports/{report}/review',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\ReportController::review
 * @see app/Http/Controllers/Advisor/ReportController.php:36
 * @route '/advisor/reports/{report}/review'
 */
review.url = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { report: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { report: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    report: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        report: typeof args.report === 'object'
                ? args.report.id
                : args.report,
                }

    return review.definition.url
            .replace('{report}', parsedArgs.report.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ReportController::review
 * @see app/Http/Controllers/Advisor/ReportController.php:36
 * @route '/advisor/reports/{report}/review'
 */
review.patch = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: review.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\ReportController::review
 * @see app/Http/Controllers/Advisor/ReportController.php:36
 * @route '/advisor/reports/{report}/review'
 */
    const reviewForm = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: review.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ReportController::review
 * @see app/Http/Controllers/Advisor/ReportController.php:36
 * @route '/advisor/reports/{report}/review'
 */
        reviewForm.patch = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: review.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    review.form = reviewForm
const ReportController = { store, review }

export default ReportController