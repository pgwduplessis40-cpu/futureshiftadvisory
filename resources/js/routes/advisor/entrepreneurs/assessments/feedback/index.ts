import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::update
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:140
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/feedback'
 */
export const update = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/feedback',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::update
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:140
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/feedback'
 */
update.url = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace('{planAssessment}', parsedArgs.planAssessment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::update
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:140
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/feedback'
 */
update.patch = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::update
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:140
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/feedback'
 */
    const updateForm = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\EntrepreneurActionController::update
 * @see app/Http/Controllers/Advisor/EntrepreneurActionController.php:140
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/assessments/{planAssessment}/feedback'
 */
        updateForm.patch = (args: { entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } } | [entrepreneurProfile: string | { id: string }, planAssessment: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    update.form = updateForm
const feedback = {
    update: Object.assign(update, update),
}

export default feedback