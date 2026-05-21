import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::index
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:31
 * @route '/admin/questionnaires'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/questionnaires',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::index
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:31
 * @route '/admin/questionnaires'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::index
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:31
 * @route '/admin/questionnaires'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::index
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:31
 * @route '/admin/questionnaires'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\QuestionnaireController::index
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:31
 * @route '/admin/questionnaires'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\QuestionnaireController::index
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:31
 * @route '/admin/questionnaires'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\QuestionnaireController::index
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:31
 * @route '/admin/questionnaires'
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
* @see \App\Http\Controllers\Admin\QuestionnaireController::store
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:45
 * @route '/admin/questionnaires'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/questionnaires',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::store
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:45
 * @route '/admin/questionnaires'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::store
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:45
 * @route '/admin/questionnaires'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\QuestionnaireController::store
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:45
 * @route '/admin/questionnaires'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\QuestionnaireController::store
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:45
 * @route '/admin/questionnaires'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::edit
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:79
 * @route '/admin/questionnaires/{questionnaire}/edit'
 */
export const edit = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/questionnaires/{questionnaire}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::edit
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:79
 * @route '/admin/questionnaires/{questionnaire}/edit'
 */
edit.url = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { questionnaire: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { questionnaire: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    questionnaire: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        questionnaire: typeof args.questionnaire === 'object'
                ? args.questionnaire.id
                : args.questionnaire,
                }

    return edit.definition.url
            .replace('{questionnaire}', parsedArgs.questionnaire.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::edit
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:79
 * @route '/admin/questionnaires/{questionnaire}/edit'
 */
edit.get = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::edit
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:79
 * @route '/admin/questionnaires/{questionnaire}/edit'
 */
edit.head = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\QuestionnaireController::edit
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:79
 * @route '/admin/questionnaires/{questionnaire}/edit'
 */
    const editForm = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: edit.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\QuestionnaireController::edit
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:79
 * @route '/admin/questionnaires/{questionnaire}/edit'
 */
        editForm.get = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\QuestionnaireController::edit
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:79
 * @route '/admin/questionnaires/{questionnaire}/edit'
 */
        editForm.head = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    edit.form = editForm
/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::update
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:90
 * @route '/admin/questionnaires/{questionnaire}'
 */
export const update = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/admin/questionnaires/{questionnaire}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::update
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:90
 * @route '/admin/questionnaires/{questionnaire}'
 */
update.url = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { questionnaire: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { questionnaire: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    questionnaire: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        questionnaire: typeof args.questionnaire === 'object'
                ? args.questionnaire.id
                : args.questionnaire,
                }

    return update.definition.url
            .replace('{questionnaire}', parsedArgs.questionnaire.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::update
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:90
 * @route '/admin/questionnaires/{questionnaire}'
 */
update.put = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Admin\QuestionnaireController::update
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:90
 * @route '/admin/questionnaires/{questionnaire}'
 */
    const updateForm = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\QuestionnaireController::update
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:90
 * @route '/admin/questionnaires/{questionnaire}'
 */
        updateForm.put = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })

    update.form = updateForm
/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::preview
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:175
 * @route '/admin/questionnaires/{questionnaire}/preview'
 */
export const preview = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})

preview.definition = {
    methods: ["get","head"],
    url: '/admin/questionnaires/{questionnaire}/preview',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::preview
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:175
 * @route '/admin/questionnaires/{questionnaire}/preview'
 */
preview.url = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { questionnaire: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { questionnaire: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    questionnaire: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        questionnaire: typeof args.questionnaire === 'object'
                ? args.questionnaire.id
                : args.questionnaire,
                }

    return preview.definition.url
            .replace('{questionnaire}', parsedArgs.questionnaire.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::preview
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:175
 * @route '/admin/questionnaires/{questionnaire}/preview'
 */
preview.get = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::preview
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:175
 * @route '/admin/questionnaires/{questionnaire}/preview'
 */
preview.head = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: preview.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\QuestionnaireController::preview
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:175
 * @route '/admin/questionnaires/{questionnaire}/preview'
 */
    const previewForm = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: preview.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\QuestionnaireController::preview
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:175
 * @route '/admin/questionnaires/{questionnaire}/preview'
 */
        previewForm.get = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: preview.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\QuestionnaireController::preview
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:175
 * @route '/admin/questionnaires/{questionnaire}/preview'
 */
        previewForm.head = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: preview.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    preview.form = previewForm
/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::publish
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:184
 * @route '/admin/questionnaires/{questionnaire}/publish'
 */
export const publish = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
})

publish.definition = {
    methods: ["post"],
    url: '/admin/questionnaires/{questionnaire}/publish',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::publish
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:184
 * @route '/admin/questionnaires/{questionnaire}/publish'
 */
publish.url = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { questionnaire: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { questionnaire: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    questionnaire: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        questionnaire: typeof args.questionnaire === 'object'
                ? args.questionnaire.id
                : args.questionnaire,
                }

    return publish.definition.url
            .replace('{questionnaire}', parsedArgs.questionnaire.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\QuestionnaireController::publish
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:184
 * @route '/admin/questionnaires/{questionnaire}/publish'
 */
publish.post = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\QuestionnaireController::publish
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:184
 * @route '/admin/questionnaires/{questionnaire}/publish'
 */
    const publishForm = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: publish.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\QuestionnaireController::publish
 * @see app/Http/Controllers/Admin/QuestionnaireController.php:184
 * @route '/admin/questionnaires/{questionnaire}/publish'
 */
        publishForm.post = (args: { questionnaire: string | number | { id: string | number } } | [questionnaire: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: publish.url(args, options),
            method: 'post',
        })

    publish.form = publishForm
const questionnaires = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
edit: Object.assign(edit, edit),
update: Object.assign(update, update),
preview: Object.assign(preview, preview),
publish: Object.assign(publish, publish),
}

export default questionnaires