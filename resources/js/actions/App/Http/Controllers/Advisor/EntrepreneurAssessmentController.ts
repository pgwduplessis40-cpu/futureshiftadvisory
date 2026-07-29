import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:19
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}'
 */
export const show = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:19
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}'
 */
show.url = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    entrepreneurProfile: args[0],
                    planAssessment: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        entrepreneurProfile: typeof args.entrepreneurProfile === 'object'
                ? args.entrepreneurProfile.id
                : args.entrepreneurProfile,
                                planAssessment: typeof args.planAssessment === 'object'
                ? args.planAssessment.id
                : args.planAssessment,
                }

    return show.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{planAssessment}', parsedArgs.planAssessment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:19
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}'
 */
show.get = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:19
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}'
 */
show.head = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:19
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}'
 */
    const showForm = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:19
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}'
 */
        showForm.get = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:19
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}'
 */
        showForm.head = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    show.form = showForm
const EntrepreneurAssessmentController = { show }

export default EntrepreneurAssessmentController