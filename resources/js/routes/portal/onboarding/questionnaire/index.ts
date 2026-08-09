import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\OnboardingController::draft
 * @see app/Http/Controllers/Portal/OnboardingController.php:108
 * @route '/portal/onboarding/questionnaire/draft'
 */
export const draft = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: draft.url(options),
    method: 'post',
})

draft.definition = {
    methods: ["post"],
    url: '/portal/onboarding/questionnaire/draft',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\OnboardingController::draft
 * @see app/Http/Controllers/Portal/OnboardingController.php:108
 * @route '/portal/onboarding/questionnaire/draft'
 */
draft.url = (options?: RouteQueryOptions) => {
    return draft.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\OnboardingController::draft
 * @see app/Http/Controllers/Portal/OnboardingController.php:108
 * @route '/portal/onboarding/questionnaire/draft'
 */
draft.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: draft.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\OnboardingController::draft
 * @see app/Http/Controllers/Portal/OnboardingController.php:108
 * @route '/portal/onboarding/questionnaire/draft'
 */
    const draftForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: draft.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\OnboardingController::draft
 * @see app/Http/Controllers/Portal/OnboardingController.php:108
 * @route '/portal/onboarding/questionnaire/draft'
 */
        draftForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: draft.url(options),
            method: 'post',
        })

    draft.form = draftForm
const questionnaire = {
    draft: Object.assign(draft, draft),
}

export default questionnaire