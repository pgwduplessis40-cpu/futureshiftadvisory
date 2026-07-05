import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\AdvisorApi\WriteController::meetingNote
 * @see app/Http/Controllers/AdvisorApi/WriteController.php:20
 * @route '/api/advisor/v1/clients/{client}/meeting-notes'
 */
export const meetingNote = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: meetingNote.url(args, options),
    method: 'post',
})

meetingNote.definition = {
    methods: ["post"],
    url: '/api/advisor/v1/clients/{client}/meeting-notes',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AdvisorApi\WriteController::meetingNote
 * @see app/Http/Controllers/AdvisorApi/WriteController.php:20
 * @route '/api/advisor/v1/clients/{client}/meeting-notes'
 */
meetingNote.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return meetingNote.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdvisorApi\WriteController::meetingNote
 * @see app/Http/Controllers/AdvisorApi/WriteController.php:20
 * @route '/api/advisor/v1/clients/{client}/meeting-notes'
 */
meetingNote.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: meetingNote.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AdvisorApi\WriteController::meetingNote
 * @see app/Http/Controllers/AdvisorApi/WriteController.php:20
 * @route '/api/advisor/v1/clients/{client}/meeting-notes'
 */
    const meetingNoteForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: meetingNote.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AdvisorApi\WriteController::meetingNote
 * @see app/Http/Controllers/AdvisorApi/WriteController.php:20
 * @route '/api/advisor/v1/clients/{client}/meeting-notes'
 */
        meetingNoteForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: meetingNote.url(args, options),
            method: 'post',
        })
    
    meetingNote.form = meetingNoteForm
/**
* @see \App\Http\Controllers\AdvisorApi\WriteController::action
 * @see app/Http/Controllers/AdvisorApi/WriteController.php:56
 * @route '/api/advisor/v1/clients/{client}/actions'
 */
export const action = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: action.url(args, options),
    method: 'post',
})

action.definition = {
    methods: ["post"],
    url: '/api/advisor/v1/clients/{client}/actions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AdvisorApi\WriteController::action
 * @see app/Http/Controllers/AdvisorApi/WriteController.php:56
 * @route '/api/advisor/v1/clients/{client}/actions'
 */
action.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return action.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AdvisorApi\WriteController::action
 * @see app/Http/Controllers/AdvisorApi/WriteController.php:56
 * @route '/api/advisor/v1/clients/{client}/actions'
 */
action.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: action.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AdvisorApi\WriteController::action
 * @see app/Http/Controllers/AdvisorApi/WriteController.php:56
 * @route '/api/advisor/v1/clients/{client}/actions'
 */
    const actionForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: action.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AdvisorApi\WriteController::action
 * @see app/Http/Controllers/AdvisorApi/WriteController.php:56
 * @route '/api/advisor/v1/clients/{client}/actions'
 */
        actionForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: action.url(args, options),
            method: 'post',
        })
    
    action.form = actionForm
const WriteController = { meetingNote, action }

export default WriteController