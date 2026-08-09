import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\PartnerAgreementController::index
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:21
 * @route '/admin/partner-agreement'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/partner-agreement',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\PartnerAgreementController::index
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:21
 * @route '/admin/partner-agreement'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PartnerAgreementController::index
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:21
 * @route '/admin/partner-agreement'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\PartnerAgreementController::index
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:21
 * @route '/admin/partner-agreement'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\PartnerAgreementController::index
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:21
 * @route '/admin/partner-agreement'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\PartnerAgreementController::index
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:21
 * @route '/admin/partner-agreement'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\PartnerAgreementController::index
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:21
 * @route '/admin/partner-agreement'
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
* @see \App\Http\Controllers\Admin\PartnerAgreementController::update
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:32
 * @route '/admin/partner-agreement'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/admin/partner-agreement',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\PartnerAgreementController::update
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:32
 * @route '/admin/partner-agreement'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PartnerAgreementController::update
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:32
 * @route '/admin/partner-agreement'
 */
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\PartnerAgreementController::update
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:32
 * @route '/admin/partner-agreement'
 */
    const updateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\PartnerAgreementController::update
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:32
 * @route '/admin/partner-agreement'
 */
        updateForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    update.form = updateForm
/**
* @see \App\Http\Controllers\Admin\PartnerAgreementController::reset
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:68
 * @route '/admin/partner-agreement/reset'
 */
export const reset = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reset.url(options),
    method: 'patch',
})

reset.definition = {
    methods: ["patch"],
    url: '/admin/partner-agreement/reset',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\PartnerAgreementController::reset
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:68
 * @route '/admin/partner-agreement/reset'
 */
reset.url = (options?: RouteQueryOptions) => {
    return reset.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\PartnerAgreementController::reset
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:68
 * @route '/admin/partner-agreement/reset'
 */
reset.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reset.url(options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\PartnerAgreementController::reset
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:68
 * @route '/admin/partner-agreement/reset'
 */
    const resetForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reset.url({
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\PartnerAgreementController::reset
 * @see app/Http/Controllers/Admin/PartnerAgreementController.php:68
 * @route '/admin/partner-agreement/reset'
 */
        resetForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reset.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    reset.form = resetForm
const PartnerAgreementController = { index, update, reset }

export default PartnerAgreementController