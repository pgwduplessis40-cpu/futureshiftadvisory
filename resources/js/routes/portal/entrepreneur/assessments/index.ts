import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Portal/EntrepreneurAssessmentController.php:20
 * @route '/portal/entrepreneur/assessments/{planAssessment}'
 */
export const show = (args: { planAssessment: string | { id: string } } | [planAssessment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur/assessments/{planAssessment}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Portal/EntrepreneurAssessmentController.php:20
 * @route '/portal/entrepreneur/assessments/{planAssessment}'
 */
show.url = (args: { planAssessment: string | { id: string } } | [planAssessment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planAssessment: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { planAssessment: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    planAssessment: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        planAssessment: typeof args.planAssessment === 'object'
                ? args.planAssessment.id
                : args.planAssessment,
                }

    return show.definition.url
            .replace('{planAssessment}', parsedArgs.planAssessment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Portal/EntrepreneurAssessmentController.php:20
 * @route '/portal/entrepreneur/assessments/{planAssessment}'
 */
show.get = (args: { planAssessment: string | { id: string } } | [planAssessment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Portal/EntrepreneurAssessmentController.php:20
 * @route '/portal/entrepreneur/assessments/{planAssessment}'
 */
show.head = (args: { planAssessment: string | { id: string } } | [planAssessment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Portal/EntrepreneurAssessmentController.php:20
 * @route '/portal/entrepreneur/assessments/{planAssessment}'
 */
    const showForm = (args: { planAssessment: string | { id: string } } | [planAssessment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Portal/EntrepreneurAssessmentController.php:20
 * @route '/portal/entrepreneur/assessments/{planAssessment}'
 */
        showForm.get = (args: { planAssessment: string | { id: string } } | [planAssessment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Portal/EntrepreneurAssessmentController.php:20
 * @route '/portal/entrepreneur/assessments/{planAssessment}'
 */
        showForm.head = (args: { planAssessment: string | { id: string } } | [planAssessment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    show.form = showForm
const assessments = {
    show: Object.assign(show, show),
}

export default assessments
