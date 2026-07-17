import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::store
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:40
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/survey-assignments'
 */
export const store = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/survey-assignments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::store
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:40
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/survey-assignments'
 */
store.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { entrepreneurProfile: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { entrepreneurProfile: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                }

    return store.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::store
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:40
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/survey-assignments'
 */
store.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::store
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:40
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/survey-assignments'
 */
    const storeForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::store
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:40
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/survey-assignments'
 */
        storeForm.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
const surveyAssignments = {
    store: Object.assign(store, store),
}

export default surveyAssignments