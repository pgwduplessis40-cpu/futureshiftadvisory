import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ReportController::store
 * @see app/Http/Controllers/Advisor/ReportController.php:237
 * @route '/advisor/reports/{report}/sections/{reportSection}/comments'
 */
export const store = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/reports/{report}/sections/{reportSection}/comments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ReportController::store
 * @see app/Http/Controllers/Advisor/ReportController.php:237
 * @route '/advisor/reports/{report}/sections/{reportSection}/comments'
 */
store.url = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    report: args[0],
                    reportSection: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        report: typeof args.report === 'object'
                ? args.report.id
                : args.report,
                                reportSection: typeof args.reportSection === 'object'
                ? args.reportSection.id
                : args.reportSection,
                }

    return store.definition.url
            .replace('{report}', parsedArgs.report.toString())
            .replace('{reportSection}', parsedArgs.reportSection.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ReportController::store
 * @see app/Http/Controllers/Advisor/ReportController.php:237
 * @route '/advisor/reports/{report}/sections/{reportSection}/comments'
 */
store.post = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ReportController::store
 * @see app/Http/Controllers/Advisor/ReportController.php:237
 * @route '/advisor/reports/{report}/sections/{reportSection}/comments'
 */
    const storeForm = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ReportController::store
 * @see app/Http/Controllers/Advisor/ReportController.php:237
 * @route '/advisor/reports/{report}/sections/{reportSection}/comments'
 */
        storeForm.post = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
const comments = {
    store: Object.assign(store, store),
}

export default comments