import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\ProfileController::cancel
* @see app/Http/Controllers/Settings/ProfileController.php:108
* @route '/settings/profile/idea-validation/cancel'
*/
export const cancel = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(options),
    method: 'post',
})

cancel.definition = {
    methods: ["post"],
    url: '/settings/profile/idea-validation/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\ProfileController::cancel
* @see app/Http/Controllers/Settings/ProfileController.php:108
* @route '/settings/profile/idea-validation/cancel'
*/
cancel.url = (options?: RouteQueryOptions) => {
    return cancel.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\ProfileController::cancel
* @see app/Http/Controllers/Settings/ProfileController.php:108
* @route '/settings/profile/idea-validation/cancel'
*/
cancel.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\ProfileController::cancel
* @see app/Http/Controllers/Settings/ProfileController.php:108
* @route '/settings/profile/idea-validation/cancel'
*/
const cancelForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: cancel.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\ProfileController::cancel
* @see app/Http/Controllers/Settings/ProfileController.php:108
* @route '/settings/profile/idea-validation/cancel'
*/
cancelForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: cancel.url(options),
    method: 'post',
})

cancel.form = cancelForm

const ideaValidation = {
    cancel: Object.assign(cancel, cancel),
}

export default ideaValidation
