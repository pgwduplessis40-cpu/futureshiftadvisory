import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/portal/calendar'
 */
const CalendarController9fbd95225f575cb58f4f4b347a44d932 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CalendarController9fbd95225f575cb58f4f4b347a44d932.url(options),
    method: 'get',
})

CalendarController9fbd95225f575cb58f4f4b347a44d932.definition = {
    methods: ["get","head"],
    url: '/portal/calendar',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/portal/calendar'
 */
CalendarController9fbd95225f575cb58f4f4b347a44d932.url = (options?: RouteQueryOptions) => {
    return CalendarController9fbd95225f575cb58f4f4b347a44d932.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/portal/calendar'
 */
CalendarController9fbd95225f575cb58f4f4b347a44d932.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CalendarController9fbd95225f575cb58f4f4b347a44d932.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/portal/calendar'
 */
CalendarController9fbd95225f575cb58f4f4b347a44d932.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: CalendarController9fbd95225f575cb58f4f4b347a44d932.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/portal/calendar'
 */
    const CalendarController9fbd95225f575cb58f4f4b347a44d932Form = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: CalendarController9fbd95225f575cb58f4f4b347a44d932.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/portal/calendar'
 */
        CalendarController9fbd95225f575cb58f4f4b347a44d932Form.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: CalendarController9fbd95225f575cb58f4f4b347a44d932.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/portal/calendar'
 */
        CalendarController9fbd95225f575cb58f4f4b347a44d932Form.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: CalendarController9fbd95225f575cb58f4f4b347a44d932.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    CalendarController9fbd95225f575cb58f4f4b347a44d932.form = CalendarController9fbd95225f575cb58f4f4b347a44d932Form
    /**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/calendar'
 */
const CalendarControllerb779617412a951269ff402230939ace7 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CalendarControllerb779617412a951269ff402230939ace7.url(options),
    method: 'get',
})

CalendarControllerb779617412a951269ff402230939ace7.definition = {
    methods: ["get","head"],
    url: '/calendar',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/calendar'
 */
CalendarControllerb779617412a951269ff402230939ace7.url = (options?: RouteQueryOptions) => {
    return CalendarControllerb779617412a951269ff402230939ace7.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/calendar'
 */
CalendarControllerb779617412a951269ff402230939ace7.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: CalendarControllerb779617412a951269ff402230939ace7.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/calendar'
 */
CalendarControllerb779617412a951269ff402230939ace7.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: CalendarControllerb779617412a951269ff402230939ace7.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/calendar'
 */
    const CalendarControllerb779617412a951269ff402230939ace7Form = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: CalendarControllerb779617412a951269ff402230939ace7.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/calendar'
 */
        CalendarControllerb779617412a951269ff402230939ace7Form.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: CalendarControllerb779617412a951269ff402230939ace7.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:39
 * @route '/calendar'
 */
        CalendarControllerb779617412a951269ff402230939ace7Form.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: CalendarControllerb779617412a951269ff402230939ace7.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    CalendarControllerb779617412a951269ff402230939ace7.form = CalendarControllerb779617412a951269ff402230939ace7Form

/**
* Multiple routes resolve to \App\Http\Controllers\CalendarController::CalendarController, so this export is a
* dictionary keyed by URI rather than a callable. Call a specific route with `CalendarController['<uri>'](...)`,
* or import the route by name from your generated `routes/` directory.
*/
const CalendarController = {
    '/portal/calendar': CalendarController9fbd95225f575cb58f4f4b347a44d932,
    '/calendar': CalendarControllerb779617412a951269ff402230939ace7,
}

export default CalendarController