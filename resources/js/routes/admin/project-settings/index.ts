import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::index
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:22
 * @route '/admin/project-settings'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/project-settings',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::index
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:22
 * @route '/admin/project-settings'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::index
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:22
 * @route '/admin/project-settings'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::index
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:22
 * @route '/admin/project-settings'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::index
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:22
 * @route '/admin/project-settings'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::index
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:22
 * @route '/admin/project-settings'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::index
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:22
 * @route '/admin/project-settings'
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
* @see \App\Http\Controllers\Admin\ProjectSettingsController::update
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:35
 * @route '/admin/project-settings'
 */
export const update = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/admin/project-settings',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::update
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:35
 * @route '/admin/project-settings'
 */
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::update
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:35
 * @route '/admin/project-settings'
 */
update.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::update
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:35
 * @route '/admin/project-settings'
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
* @see \App\Http\Controllers\Admin\ProjectSettingsController::update
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:35
 * @route '/admin/project-settings'
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
* @see \App\Http\Controllers\Admin\ProjectSettingsController::reset
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:79
 * @route '/admin/project-settings/reset'
 */
export const reset = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reset.url(options),
    method: 'patch',
})

reset.definition = {
    methods: ["patch"],
    url: '/admin/project-settings/reset',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::reset
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:79
 * @route '/admin/project-settings/reset'
 */
reset.url = (options?: RouteQueryOptions) => {
    return reset.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::reset
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:79
 * @route '/admin/project-settings/reset'
 */
reset.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reset.url(options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::reset
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:79
 * @route '/admin/project-settings/reset'
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
* @see \App\Http\Controllers\Admin\ProjectSettingsController::reset
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:79
 * @route '/admin/project-settings/reset'
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
/**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::testEmail
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:91
 * @route '/admin/project-settings/test-email'
 */
export const testEmail = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: testEmail.url(options),
    method: 'post',
})

testEmail.definition = {
    methods: ["post"],
    url: '/admin/project-settings/test-email',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::testEmail
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:91
 * @route '/admin/project-settings/test-email'
 */
testEmail.url = (options?: RouteQueryOptions) => {
    return testEmail.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::testEmail
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:91
 * @route '/admin/project-settings/test-email'
 */
testEmail.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: testEmail.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::testEmail
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:91
 * @route '/admin/project-settings/test-email'
 */
    const testEmailForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: testEmail.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\ProjectSettingsController::testEmail
 * @see app/Http/Controllers/Admin/ProjectSettingsController.php:91
 * @route '/admin/project-settings/test-email'
 */
        testEmailForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: testEmail.url(options),
            method: 'post',
        })
    
    testEmail.form = testEmailForm
const projectSettings = {
    index: Object.assign(index, index),
update: Object.assign(update, update),
reset: Object.assign(reset, reset),
testEmail: Object.assign(testEmail, testEmail),
}

export default projectSettings