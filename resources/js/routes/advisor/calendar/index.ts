import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
import meetings from './meetings'
/**
* @see \App\Http\Controllers\Advisor\CalendarController::index
 * @see app/Http/Controllers/Advisor/CalendarController.php:25
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
 * @see app/Http/Controllers/Advisor/CalendarController.php:25
 * @route '/advisor/calendar'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\CalendarController::index
 * @see app/Http/Controllers/Advisor/CalendarController.php:25
 * @route '/advisor/calendar'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\CalendarController::index
 * @see app/Http/Controllers/Advisor/CalendarController.php:25
 * @route '/advisor/calendar'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\CalendarController::index
 * @see app/Http/Controllers/Advisor/CalendarController.php:25
 * @route '/advisor/calendar'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\CalendarController::index
 * @see app/Http/Controllers/Advisor/CalendarController.php:25
 * @route '/advisor/calendar'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\CalendarController::index
 * @see app/Http/Controllers/Advisor/CalendarController.php:25
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
const calendar = {
    index: Object.assign(index, index),
meetings: Object.assign(meetings, meetings),
}

export default calendar