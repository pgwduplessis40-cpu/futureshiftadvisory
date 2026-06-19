import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import sections from './sections'
/**
* @see \App\Http\Controllers\Advisor\ReportController::download
 * @see app/Http/Controllers/Advisor/ReportController.php:134
 * @route '/advisor/reports/{report}/download'
 */
export const download = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/advisor/reports/{report}/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ReportController::download
 * @see app/Http/Controllers/Advisor/ReportController.php:134
 * @route '/advisor/reports/{report}/download'
 */
download.url = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return download.definition.url
            .replace('{report}', parsedArgs.report.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ReportController::download
 * @see app/Http/Controllers/Advisor/ReportController.php:134
 * @route '/advisor/reports/{report}/download'
 */
download.get = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ReportController::download
 * @see app/Http/Controllers/Advisor/ReportController.php:134
 * @route '/advisor/reports/{report}/download'
 */
download.head = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ReportController::download
 * @see app/Http/Controllers/Advisor/ReportController.php:134
 * @route '/advisor/reports/{report}/download'
 */
    const downloadForm = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: download.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ReportController::download
 * @see app/Http/Controllers/Advisor/ReportController.php:134
 * @route '/advisor/reports/{report}/download'
 */
        downloadForm.get = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ReportController::download
 * @see app/Http/Controllers/Advisor/ReportController.php:134
 * @route '/advisor/reports/{report}/download'
 */
        downloadForm.head = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    download.form = downloadForm
/**
* @see \App\Http\Controllers\Advisor\ReportController::pptx
 * @see app/Http/Controllers/Advisor/ReportController.php:139
 * @route '/advisor/reports/{report}/pptx'
 */
export const pptx = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pptx.url(args, options),
    method: 'get',
})

pptx.definition = {
    methods: ["get","head"],
    url: '/advisor/reports/{report}/pptx',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ReportController::pptx
 * @see app/Http/Controllers/Advisor/ReportController.php:139
 * @route '/advisor/reports/{report}/pptx'
 */
pptx.url = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return pptx.definition.url
            .replace('{report}', parsedArgs.report.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ReportController::pptx
 * @see app/Http/Controllers/Advisor/ReportController.php:139
 * @route '/advisor/reports/{report}/pptx'
 */
pptx.get = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pptx.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ReportController::pptx
 * @see app/Http/Controllers/Advisor/ReportController.php:139
 * @route '/advisor/reports/{report}/pptx'
 */
pptx.head = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pptx.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ReportController::pptx
 * @see app/Http/Controllers/Advisor/ReportController.php:139
 * @route '/advisor/reports/{report}/pptx'
 */
    const pptxForm = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: pptx.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ReportController::pptx
 * @see app/Http/Controllers/Advisor/ReportController.php:139
 * @route '/advisor/reports/{report}/pptx'
 */
        pptxForm.get = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pptx.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ReportController::pptx
 * @see app/Http/Controllers/Advisor/ReportController.php:139
 * @route '/advisor/reports/{report}/pptx'
 */
        pptxForm.head = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: pptx.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    pptx.form = pptxForm
/**
* @see \App\Http\Controllers\Advisor\ReportController::review
 * @see app/Http/Controllers/Advisor/ReportController.php:202
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
 * @see app/Http/Controllers/Advisor/ReportController.php:202
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
 * @see app/Http/Controllers/Advisor/ReportController.php:202
 * @route '/advisor/reports/{report}/review'
 */
review.patch = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: review.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\ReportController::review
 * @see app/Http/Controllers/Advisor/ReportController.php:202
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
 * @see app/Http/Controllers/Advisor/ReportController.php:202
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
const reports = {
    download: Object.assign(download, download),
pptx: Object.assign(pptx, pptx),
review: Object.assign(review, review),
sections: Object.assign(sections, sections),
}

export default reports