import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
import comments from './comments'
/**
* @see \App\Http\Controllers\Advisor\ReportController::update
 * @see app/Http/Controllers/Advisor/ReportController.php:221
 * @route '/advisor/reports/{report}/sections/{reportSection}'
 */
export const update = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/advisor/reports/{report}/sections/{reportSection}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\ReportController::update
 * @see app/Http/Controllers/Advisor/ReportController.php:221
 * @route '/advisor/reports/{report}/sections/{reportSection}'
 */
update.url = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{report}', parsedArgs.report.toString())
            .replace('{reportSection}', parsedArgs.reportSection.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ReportController::update
 * @see app/Http/Controllers/Advisor/ReportController.php:221
 * @route '/advisor/reports/{report}/sections/{reportSection}'
 */
update.patch = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\ReportController::update
 * @see app/Http/Controllers/Advisor/ReportController.php:221
 * @route '/advisor/reports/{report}/sections/{reportSection}'
 */
    const updateForm = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ReportController::update
 * @see app/Http/Controllers/Advisor/ReportController.php:221
 * @route '/advisor/reports/{report}/sections/{reportSection}'
 */
        updateForm.patch = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    update.form = updateForm
const sections = {
    update: Object.assign(update, update),
comments: Object.assign(comments, comments),
}

export default sections