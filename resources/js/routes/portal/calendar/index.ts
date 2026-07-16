import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
import leavePeriods from './leave-periods'
/**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:51
 * @route '/portal/calendar'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/portal/calendar',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:51
 * @route '/portal/calendar'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:51
 * @route '/portal/calendar'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:51
 * @route '/portal/calendar'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:51
 * @route '/portal/calendar'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:51
 * @route '/portal/calendar'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\CalendarController::__invoke
 * @see app/Http/Controllers/CalendarController.php:51
 * @route '/portal/calendar'
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
const calendar = {
    index: Object.assign(index, index),
leavePeriods: Object.assign(leavePeriods, leavePeriods),
}

export default calendar