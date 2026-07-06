import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\OutcomeFollowUpController::show
 * @see app/Http/Controllers/Portal/OutcomeFollowUpController.php:19
 * @route '/portal/outcome-follow-ups/{outcomeFollowUp}'
 */
export const show = (args: { outcomeFollowUp: string | { id: string } } | [outcomeFollowUp: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/outcome-follow-ups/{outcomeFollowUp}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\OutcomeFollowUpController::show
 * @see app/Http/Controllers/Portal/OutcomeFollowUpController.php:19
 * @route '/portal/outcome-follow-ups/{outcomeFollowUp}'
 */
show.url = (args: { outcomeFollowUp: string | { id: string } } | [outcomeFollowUp: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { outcomeFollowUp: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { outcomeFollowUp: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    outcomeFollowUp: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        outcomeFollowUp: typeof args.outcomeFollowUp === 'object'
                ? args.outcomeFollowUp.id
                : args.outcomeFollowUp,
                }

    return show.definition.url
            .replace('{outcomeFollowUp}', parsedArgs.outcomeFollowUp.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\OutcomeFollowUpController::show
 * @see app/Http/Controllers/Portal/OutcomeFollowUpController.php:19
 * @route '/portal/outcome-follow-ups/{outcomeFollowUp}'
 */
show.get = (args: { outcomeFollowUp: string | { id: string } } | [outcomeFollowUp: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\OutcomeFollowUpController::show
 * @see app/Http/Controllers/Portal/OutcomeFollowUpController.php:19
 * @route '/portal/outcome-follow-ups/{outcomeFollowUp}'
 */
show.head = (args: { outcomeFollowUp: string | { id: string } } | [outcomeFollowUp: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\OutcomeFollowUpController::show
 * @see app/Http/Controllers/Portal/OutcomeFollowUpController.php:19
 * @route '/portal/outcome-follow-ups/{outcomeFollowUp}'
 */
    const showForm = (args: { outcomeFollowUp: string | { id: string } } | [outcomeFollowUp: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\OutcomeFollowUpController::show
 * @see app/Http/Controllers/Portal/OutcomeFollowUpController.php:19
 * @route '/portal/outcome-follow-ups/{outcomeFollowUp}'
 */
        showForm.get = (args: { outcomeFollowUp: string | { id: string } } | [outcomeFollowUp: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\OutcomeFollowUpController::show
 * @see app/Http/Controllers/Portal/OutcomeFollowUpController.php:19
 * @route '/portal/outcome-follow-ups/{outcomeFollowUp}'
 */
        showForm.head = (args: { outcomeFollowUp: string | { id: string } } | [outcomeFollowUp: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Portal\OutcomeFollowUpController::submit
 * @see app/Http/Controllers/Portal/OutcomeFollowUpController.php:37
 * @route '/portal/outcome-follow-ups/{outcomeFollowUp}'
 */
export const submit = (args: { outcomeFollowUp: string | { id: string } } | [outcomeFollowUp: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(args, options),
    method: 'post',
})

submit.definition = {
    methods: ["post"],
    url: '/portal/outcome-follow-ups/{outcomeFollowUp}',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\OutcomeFollowUpController::submit
 * @see app/Http/Controllers/Portal/OutcomeFollowUpController.php:37
 * @route '/portal/outcome-follow-ups/{outcomeFollowUp}'
 */
submit.url = (args: { outcomeFollowUp: string | { id: string } } | [outcomeFollowUp: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { outcomeFollowUp: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { outcomeFollowUp: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    outcomeFollowUp: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        outcomeFollowUp: typeof args.outcomeFollowUp === 'object'
                ? args.outcomeFollowUp.id
                : args.outcomeFollowUp,
                }

    return submit.definition.url
            .replace('{outcomeFollowUp}', parsedArgs.outcomeFollowUp.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\OutcomeFollowUpController::submit
 * @see app/Http/Controllers/Portal/OutcomeFollowUpController.php:37
 * @route '/portal/outcome-follow-ups/{outcomeFollowUp}'
 */
submit.post = (args: { outcomeFollowUp: string | { id: string } } | [outcomeFollowUp: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: submit.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Portal\OutcomeFollowUpController::submit
 * @see app/Http/Controllers/Portal/OutcomeFollowUpController.php:37
 * @route '/portal/outcome-follow-ups/{outcomeFollowUp}'
 */
    const submitForm = (args: { outcomeFollowUp: string | { id: string } } | [outcomeFollowUp: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: submit.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Portal\OutcomeFollowUpController::submit
 * @see app/Http/Controllers/Portal/OutcomeFollowUpController.php:37
 * @route '/portal/outcome-follow-ups/{outcomeFollowUp}'
 */
        submitForm.post = (args: { outcomeFollowUp: string | { id: string } } | [outcomeFollowUp: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: submit.url(args, options),
            method: 'post',
        })

    submit.form = submitForm
const outcomeFollowUps = {
    show: Object.assign(show, show),
submit: Object.assign(submit, submit),
}

export default outcomeFollowUps