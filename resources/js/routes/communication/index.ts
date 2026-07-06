import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\CommunicationController::edit
 * @see app/Http/Controllers/Settings/CommunicationController.php:18
 * @route '/settings/communication'
 */
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/communication',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\CommunicationController::edit
 * @see app/Http/Controllers/Settings/CommunicationController.php:18
 * @route '/settings/communication'
 */
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\CommunicationController::edit
 * @see app/Http/Controllers/Settings/CommunicationController.php:18
 * @route '/settings/communication'
 */
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Settings\CommunicationController::edit
 * @see app/Http/Controllers/Settings/CommunicationController.php:18
 * @route '/settings/communication'
 */
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Settings\CommunicationController::edit
 * @see app/Http/Controllers/Settings/CommunicationController.php:18
 * @route '/settings/communication'
 */
    const editForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: edit.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Settings\CommunicationController::edit
 * @see app/Http/Controllers/Settings/CommunicationController.php:18
 * @route '/settings/communication'
 */
        editForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Settings\CommunicationController::edit
 * @see app/Http/Controllers/Settings/CommunicationController.php:18
 * @route '/settings/communication'
 */
        editForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    edit.form = editForm
/**
* @see \App\Http\Controllers\Settings\CommunicationController::update
 * @see app/Http/Controllers/Settings/CommunicationController.php:41
 * @route '/settings/communication'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/settings/communication',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Settings\CommunicationController::update
 * @see app/Http/Controllers/Settings/CommunicationController.php:41
 * @route '/settings/communication'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\CommunicationController::update
 * @see app/Http/Controllers/Settings/CommunicationController.php:41
 * @route '/settings/communication'
 */
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Settings\CommunicationController::update
 * @see app/Http/Controllers/Settings/CommunicationController.php:41
 * @route '/settings/communication'
 */
    const updateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Settings\CommunicationController::update
 * @see app/Http/Controllers/Settings/CommunicationController.php:41
 * @route '/settings/communication'
 */
        updateForm.put = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const communication = {
    edit: Object.assign(edit, edit),
update: Object.assign(update, update),
}

export default communication