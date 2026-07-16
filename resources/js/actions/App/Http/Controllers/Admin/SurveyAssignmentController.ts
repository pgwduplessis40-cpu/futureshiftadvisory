import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::storeForClient
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:23
 * @route '/advisor/clients/{client}/survey-assignments'
 */
export const storeForClient = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeForClient.url(args, options),
    method: 'post',
})

storeForClient.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/survey-assignments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::storeForClient
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:23
 * @route '/advisor/clients/{client}/survey-assignments'
 */
storeForClient.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { client: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                }

    return storeForClient.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::storeForClient
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:23
 * @route '/advisor/clients/{client}/survey-assignments'
 */
storeForClient.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeForClient.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::storeForClient
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:23
 * @route '/advisor/clients/{client}/survey-assignments'
 */
    const storeForClientForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: storeForClient.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::storeForClient
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:23
 * @route '/advisor/clients/{client}/survey-assignments'
 */
        storeForClientForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: storeForClient.url(args, options),
            method: 'post',
        })
    
    storeForClient.form = storeForClientForm
/**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::storeForEntrepreneur
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:40
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/survey-assignments'
 */
export const storeForEntrepreneur = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeForEntrepreneur.url(args, options),
    method: 'post',
})

storeForEntrepreneur.definition = {
    methods: ["post"],
    url: '/advisor/entrepreneurs/{entrepreneurProfile}/survey-assignments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::storeForEntrepreneur
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:40
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/survey-assignments'
 */
storeForEntrepreneur.url = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return storeForEntrepreneur.definition.url
            .replace('{entrepreneurProfile}', parsedArgs.entrepreneurProfile.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::storeForEntrepreneur
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:40
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/survey-assignments'
 */
storeForEntrepreneur.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeForEntrepreneur.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::storeForEntrepreneur
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:40
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/survey-assignments'
 */
    const storeForEntrepreneurForm = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: storeForEntrepreneur.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\SurveyAssignmentController::storeForEntrepreneur
 * @see app/Http/Controllers/Admin/SurveyAssignmentController.php:40
 * @route '/advisor/entrepreneurs/{entrepreneurProfile}/survey-assignments'
 */
        storeForEntrepreneurForm.post = (args: { entrepreneurProfile: string | { id: string } } | [entrepreneurProfile: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: storeForEntrepreneur.url(args, options),
            method: 'post',
        })
    
    storeForEntrepreneur.form = storeForEntrepreneurForm
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
const SurveyAssignmentController = { storeForClient, storeForEntrepreneur, cancel }

export default SurveyAssignmentController