import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\TermsController::activate
 * @see app/Http/Controllers/Admin/TermsController.php:330
 * @route '/admin/terms/enforcement/activate'
 */
export const activate = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: activate.url(options),
    method: 'post',
})

activate.definition = {
    methods: ["post"],
    url: '/admin/terms/enforcement/activate',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\TermsController::activate
 * @see app/Http/Controllers/Admin/TermsController.php:330
 * @route '/admin/terms/enforcement/activate'
 */
activate.url = (options?: RouteQueryOptions) => {
    return activate.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\TermsController::activate
 * @see app/Http/Controllers/Admin/TermsController.php:330
 * @route '/admin/terms/enforcement/activate'
 */
activate.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: activate.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\TermsController::activate
 * @see app/Http/Controllers/Admin/TermsController.php:330
 * @route '/admin/terms/enforcement/activate'
 */
    const activateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: activate.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\TermsController::activate
 * @see app/Http/Controllers/Admin/TermsController.php:330
 * @route '/admin/terms/enforcement/activate'
 */
        activateForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: activate.url(options),
            method: 'post',
        })

    activate.form = activateForm
const enforcement = {
    activate: Object.assign(activate, activate),
}

export default enforcement