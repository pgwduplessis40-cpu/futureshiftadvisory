import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::cancel
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:57
 * @route '/advisor/survey-assignments/{surveyAssignment}/cancel'
 */
export const cancel = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: cancel.url(args, options),
    method: 'patch',
})

cancel.definition = {
    methods: ["patch"],
    url: '/advisor/survey-assignments/{surveyAssignment}/cancel',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::cancel
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:57
 * @route '/advisor/survey-assignments/{surveyAssignment}/cancel'
 */
cancel.url = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { surveyAssignment: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { surveyAssignment: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    surveyAssignment: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        surveyAssignment: typeof args.surveyAssignment === 'object'
                ? args.surveyAssignment.id
                : args.surveyAssignment,
                }

    return cancel.definition.url
            .replace('{surveyAssignment}', parsedArgs.surveyAssignment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::cancel
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:57
 * @route '/advisor/survey-assignments/{surveyAssignment}/cancel'
 */
cancel.patch = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: cancel.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::cancel
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:57
 * @route '/advisor/survey-assignments/{surveyAssignment}/cancel'
 */
    const cancelForm = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: cancel.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::cancel
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:57
 * @route '/advisor/survey-assignments/{surveyAssignment}/cancel'
 */
        cancelForm.patch = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: cancel.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    cancel.form = cancelForm
const surveyAssignments = {
    cancel: Object.assign(cancel, cancel),
}

export default surveyAssignments