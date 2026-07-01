import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\CalendarController::index
 * @see app/Http/Controllers/Advisor/CalendarController.php:24
 * @route '/advisor/calendar'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/calendar',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\CalendarController::index
 * @see app/Http/Controllers/Advisor/CalendarController.php:24
 * @route '/advisor/calendar'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\CalendarController::index
 * @see app/Http/Controllers/Advisor/CalendarController.php:24
 * @route '/advisor/calendar'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\CalendarController::index
 * @see app/Http/Controllers/Advisor/CalendarController.php:24
 * @route '/advisor/calendar'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\CalendarController::index
 * @see app/Http/Controllers/Advisor/CalendarController.php:24
 * @route '/advisor/calendar'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\CalendarController::index
 * @see app/Http/Controllers/Advisor/CalendarController.php:24
 * @route '/advisor/calendar'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\CalendarController::index
 * @see app/Http/Controllers/Advisor/CalendarController.php:24
 * @route '/advisor/calendar'
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
* @see \App\Http\Controllers\Advisor\CalendarController::store
 * @see app/Http/Controllers/Advisor/CalendarController.php:84
 * @route '/advisor/calendar/meetings'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/calendar/meetings',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\CalendarController::store
 * @see app/Http/Controllers/Advisor/CalendarController.php:84
 * @route '/advisor/calendar/meetings'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\CalendarController::store
 * @see app/Http/Controllers/Advisor/CalendarController.php:84
 * @route '/advisor/calendar/meetings'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\CalendarController::store
 * @see app/Http/Controllers/Advisor/CalendarController.php:84
 * @route '/advisor/calendar/meetings'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\CalendarController::store
 * @see app/Http/Controllers/Advisor/CalendarController.php:84
 * @route '/advisor/calendar/meetings'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Advisor\CalendarController::update
 * @see app/Http/Controllers/Advisor/CalendarController.php:99
 * @route '/advisor/calendar/meetings/{meeting}'
 */
export const update = (args: { meeting: string | { id: string } } | [meeting: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/advisor/calendar/meetings/{meeting}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\CalendarController::update
 * @see app/Http/Controllers/Advisor/CalendarController.php:99
 * @route '/advisor/calendar/meetings/{meeting}'
 */
update.url = (args: { meeting: string | { id: string } } | [meeting: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { meeting: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { meeting: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    meeting: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        meeting: typeof args.meeting === 'object'
                ? args.meeting.id
                : args.meeting,
                }

    return update.definition.url
            .replace('{meeting}', parsedArgs.meeting.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\CalendarController::update
 * @see app/Http/Controllers/Advisor/CalendarController.php:99
 * @route '/advisor/calendar/meetings/{meeting}'
 */
update.patch = (args: { meeting: string | { id: string } } | [meeting: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\CalendarController::update
 * @see app/Http/Controllers/Advisor/CalendarController.php:99
 * @route '/advisor/calendar/meetings/{meeting}'
 */
    const updateForm = (args: { meeting: string | { id: string } } | [meeting: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\CalendarController::update
 * @see app/Http/Controllers/Advisor/CalendarController.php:99
 * @route '/advisor/calendar/meetings/{meeting}'
 */
        updateForm.patch = (args: { meeting: string | { id: string } } | [meeting: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    update.form = updateForm
/**
* @see \App\Http\Controllers\Advisor\CalendarController::cancel
 * @see app/Http/Controllers/Advisor/CalendarController.php:115
 * @route '/advisor/calendar/meetings/{meeting}'
 */
export const cancel = (args: { meeting: string | { id: string } } | [meeting: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancel.url(args, options),
    method: 'delete',
})

cancel.definition = {
    methods: ["delete"],
    url: '/advisor/calendar/meetings/{meeting}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Advisor\CalendarController::cancel
 * @see app/Http/Controllers/Advisor/CalendarController.php:115
 * @route '/advisor/calendar/meetings/{meeting}'
 */
cancel.url = (args: { meeting: string | { id: string } } | [meeting: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { meeting: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { meeting: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    meeting: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        meeting: typeof args.meeting === 'object'
                ? args.meeting.id
                : args.meeting,
                }

    return cancel.definition.url
            .replace('{meeting}', parsedArgs.meeting.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\CalendarController::cancel
 * @see app/Http/Controllers/Advisor/CalendarController.php:115
 * @route '/advisor/calendar/meetings/{meeting}'
 */
cancel.delete = (args: { meeting: string | { id: string } } | [meeting: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancel.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Advisor\CalendarController::cancel
 * @see app/Http/Controllers/Advisor/CalendarController.php:115
 * @route '/advisor/calendar/meetings/{meeting}'
 */
    const cancelForm = (args: { meeting: string | { id: string } } | [meeting: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: cancel.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\CalendarController::cancel
 * @see app/Http/Controllers/Advisor/CalendarController.php:115
 * @route '/advisor/calendar/meetings/{meeting}'
 */
        cancelForm.delete = (args: { meeting: string | { id: string } } | [meeting: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: cancel.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    cancel.form = cancelForm
const CalendarController = { index, store, update, cancel }

export default CalendarController