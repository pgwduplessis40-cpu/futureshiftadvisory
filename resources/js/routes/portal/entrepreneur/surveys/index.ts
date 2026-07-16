import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::index
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:24
 * @route '/portal/entrepreneur/surveys'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur/surveys',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::index
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:24
 * @route '/portal/entrepreneur/surveys'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::index
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:24
 * @route '/portal/entrepreneur/surveys'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::index
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:24
 * @route '/portal/entrepreneur/surveys'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::index
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:24
 * @route '/portal/entrepreneur/surveys'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::index
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:24
 * @route '/portal/entrepreneur/surveys'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::index
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:24
 * @route '/portal/entrepreneur/surveys'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::show
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:44
 * @route '/portal/entrepreneur/surveys/{surveyAssignment}'
 */
export const show = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur/surveys/{surveyAssignment}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::show
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:44
 * @route '/portal/entrepreneur/surveys/{surveyAssignment}'
 */
show.url = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{surveyAssignment}', parsedArgs.surveyAssignment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::show
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:44
 * @route '/portal/entrepreneur/surveys/{surveyAssignment}'
 */
show.get = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::show
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:44
 * @route '/portal/entrepreneur/surveys/{surveyAssignment}'
 */
show.head = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::show
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:44
 * @route '/portal/entrepreneur/surveys/{surveyAssignment}'
 */
    const showForm = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::show
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:44
 * @route '/portal/entrepreneur/surveys/{surveyAssignment}'
 */
        showForm.get = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::show
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:44
 * @route '/portal/entrepreneur/surveys/{surveyAssignment}'
 */
        showForm.head = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::submit
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:56
 * @route '/portal/entrepreneur/surveys/{surveyAssignment}'
 */
export const submit = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(args, options),
    method: 'post',
})

submit.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/surveys/{surveyAssignment}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::submit
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:56
 * @route '/portal/entrepreneur/surveys/{surveyAssignment}'
 */
submit.url = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return submit.definition.url
            .replace('{surveyAssignment}', parsedArgs.surveyAssignment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::submit
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:56
 * @route '/portal/entrepreneur/surveys/{surveyAssignment}'
 */
submit.post = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::submit
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:56
 * @route '/portal/entrepreneur/surveys/{surveyAssignment}'
 */
    const submitForm = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: submit.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\EntrepreneurSurveyController::submit
 * @see app/Http/Controllers/Portal/EntrepreneurSurveyController.php:56
 * @route '/portal/entrepreneur/surveys/{surveyAssignment}'
 */
        submitForm.post = (args: { surveyAssignment: string | { id: string } } | [surveyAssignment: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: submit.url(args, options),
            method: 'post',
        })
    
    submit.form = submitForm
const surveys = {
    index: Object.assign(index, index),
show: Object.assign(show, show),
submit: Object.assign(submit, submit),
}

export default surveys