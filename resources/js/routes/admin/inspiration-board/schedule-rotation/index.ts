import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::cancel
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:133
 * @route '/admin/inspiration-board/schedule-rotation/{rotationSchedule}'
 */
export const cancel = (args: { rotationSchedule: string | { id: string } } | [rotationSchedule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancel.url(args, options),
    method: 'delete',
})

cancel.definition = {
    methods: ["delete"],
    url: '/admin/inspiration-board/schedule-rotation/{rotationSchedule}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::cancel
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:133
 * @route '/admin/inspiration-board/schedule-rotation/{rotationSchedule}'
 */
cancel.url = (args: { rotationSchedule: string | { id: string } } | [rotationSchedule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rotationSchedule: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { rotationSchedule: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    rotationSchedule: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rotationSchedule: typeof args.rotationSchedule === 'object'
                ? args.rotationSchedule.id
                : args.rotationSchedule,
                }

    return cancel.definition.url
            .replace('{rotationSchedule}', parsedArgs.rotationSchedule.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\InspirationBoardController::cancel
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:133
 * @route '/admin/inspiration-board/schedule-rotation/{rotationSchedule}'
 */
cancel.delete = (args: { rotationSchedule: string | { id: string } } | [rotationSchedule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: cancel.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::cancel
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:133
 * @route '/admin/inspiration-board/schedule-rotation/{rotationSchedule}'
 */
    const cancelForm = (args: { rotationSchedule: string | { id: string } } | [rotationSchedule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: cancel.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\InspirationBoardController::cancel
 * @see app/Http/Controllers/Admin/InspirationBoardController.php:133
 * @route '/admin/inspiration-board/schedule-rotation/{rotationSchedule}'
 */
        cancelForm.delete = (args: { rotationSchedule: string | { id: string } } | [rotationSchedule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: cancel.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    cancel.form = cancelForm
const scheduleRotation = {
    cancel: Object.assign(cancel, cancel),
}

export default scheduleRotation