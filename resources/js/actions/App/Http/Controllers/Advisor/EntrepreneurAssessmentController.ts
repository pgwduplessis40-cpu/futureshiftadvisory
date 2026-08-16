import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:29
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
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:29
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
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:29
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}'
 */
show.get = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:29
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}'
 */
show.head = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:29
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}'
 */
    const showForm = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:29
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}'
 */
        showForm.get = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::show
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:29
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
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::planPreview
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:100
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/plan-preview'
 */
export const planPreview = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: planPreview.url(args, options),
    method: 'get',
})

planPreview.definition = {
    methods: ["get","head"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/plan-preview',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::planPreview
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:100
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/plan-preview'
 */
planPreview.url = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions) => {
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

    return planPreview.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{planAssessment}', parsedArgs.planAssessment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::planPreview
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:100
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/plan-preview'
 */
planPreview.get = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: planPreview.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::planPreview
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:100
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/plan-preview'
 */
planPreview.head = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: planPreview.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::planPreview
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:100
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/plan-preview'
 */
    const planPreviewForm = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: planPreview.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::planPreview
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:100
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/plan-preview'
 */
        planPreviewForm.get = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: planPreview.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurAssessmentController::planPreview
 * @see app/Http/Controllers/Advisor/EntrepreneurAssessmentController.php:100
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/plan-preview'
 */
        planPreviewForm.head = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: planPreview.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    planPreview.form = planPreviewForm
const EntrepreneurAssessmentController = { show, planPreview }

export default EntrepreneurAssessmentController