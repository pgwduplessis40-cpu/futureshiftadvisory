import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\SurveyController::index
 * @see app/Http/Controllers/Admin/SurveyController.php:29
 * @route '/admin/surveys'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/surveys',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\SurveyController::index
 * @see app/Http/Controllers/Admin/SurveyController.php:29
 * @route '/admin/surveys'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SurveyController::index
 * @see app/Http/Controllers/Admin/SurveyController.php:29
 * @route '/admin/surveys'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\SurveyController::index
 * @see app/Http/Controllers/Admin/SurveyController.php:29
 * @route '/admin/surveys'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\SurveyController::index
 * @see app/Http/Controllers/Admin/SurveyController.php:29
 * @route '/admin/surveys'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\SurveyController::index
 * @see app/Http/Controllers/Admin/SurveyController.php:29
 * @route '/admin/surveys'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\SurveyController::index
 * @see app/Http/Controllers/Admin/SurveyController.php:29
 * @route '/admin/surveys'
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
* @see \App\Http\Controllers\Admin\SurveyController::store
 * @see app/Http/Controllers/Admin/SurveyController.php:64
 * @route '/admin/surveys'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/surveys',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\SurveyController::store
 * @see app/Http/Controllers/Admin/SurveyController.php:64
 * @route '/admin/surveys'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SurveyController::store
 * @see app/Http/Controllers/Admin/SurveyController.php:64
 * @route '/admin/surveys'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\SurveyController::store
 * @see app/Http/Controllers/Admin/SurveyController.php:64
 * @route '/admin/surveys'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\SurveyController::store
 * @see app/Http/Controllers/Admin/SurveyController.php:64
 * @route '/admin/surveys'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\SurveyController::edit
 * @see app/Http/Controllers/Admin/SurveyController.php:97
 * @route '/admin/surveys/{survey}/edit'
 */
export const edit = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/surveys/{survey}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\SurveyController::edit
 * @see app/Http/Controllers/Admin/SurveyController.php:97
 * @route '/admin/surveys/{survey}/edit'
 */
edit.url = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { survey: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { survey: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    survey: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        survey: typeof args.survey === 'object'
                ? args.survey.id
                : args.survey,
                }

    return edit.definition.url
            .replace('{survey}', parsedArgs.survey.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SurveyController::edit
 * @see app/Http/Controllers/Admin/SurveyController.php:97
 * @route '/admin/surveys/{survey}/edit'
 */
edit.get = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\SurveyController::edit
 * @see app/Http/Controllers/Admin/SurveyController.php:97
 * @route '/admin/surveys/{survey}/edit'
 */
edit.head = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\SurveyController::edit
 * @see app/Http/Controllers/Admin/SurveyController.php:97
 * @route '/admin/surveys/{survey}/edit'
 */
    const editForm = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: edit.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\SurveyController::edit
 * @see app/Http/Controllers/Admin/SurveyController.php:97
 * @route '/admin/surveys/{survey}/edit'
 */
        editForm.get = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\SurveyController::edit
 * @see app/Http/Controllers/Admin/SurveyController.php:97
 * @route '/admin/surveys/{survey}/edit'
 */
        editForm.head = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Admin\SurveyController::update
 * @see app/Http/Controllers/Admin/SurveyController.php:112
 * @route '/admin/surveys/{survey}'
 */
export const update = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/admin/surveys/{survey}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Admin\SurveyController::update
 * @see app/Http/Controllers/Admin/SurveyController.php:112
 * @route '/admin/surveys/{survey}'
 */
update.url = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { survey: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { survey: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    survey: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        survey: typeof args.survey === 'object'
                ? args.survey.id
                : args.survey,
                }

    return update.definition.url
            .replace('{survey}', parsedArgs.survey.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SurveyController::update
 * @see app/Http/Controllers/Admin/SurveyController.php:112
 * @route '/admin/surveys/{survey}'
 */
update.put = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Admin\SurveyController::update
 * @see app/Http/Controllers/Admin/SurveyController.php:112
 * @route '/admin/surveys/{survey}'
 */
    const updateForm = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\SurveyController::update
 * @see app/Http/Controllers/Admin/SurveyController.php:112
 * @route '/admin/surveys/{survey}'
 */
        updateForm.put = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Admin\SurveyController::publish
 * @see app/Http/Controllers/Admin/SurveyController.php:173
 * @route '/admin/surveys/{survey}/publish'
 */
export const publish = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
})

publish.definition = {
    methods: ["post"],
    url: '/admin/surveys/{survey}/publish',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\SurveyController::publish
 * @see app/Http/Controllers/Admin/SurveyController.php:173
 * @route '/admin/surveys/{survey}/publish'
 */
publish.url = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { survey: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { survey: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    survey: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        survey: typeof args.survey === 'object'
                ? args.survey.id
                : args.survey,
                }

    return publish.definition.url
            .replace('{survey}', parsedArgs.survey.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SurveyController::publish
 * @see app/Http/Controllers/Admin/SurveyController.php:173
 * @route '/admin/surveys/{survey}/publish'
 */
publish.post = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\SurveyController::publish
 * @see app/Http/Controllers/Admin/SurveyController.php:173
 * @route '/admin/surveys/{survey}/publish'
 */
    const publishForm = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: publish.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\SurveyController::publish
 * @see app/Http/Controllers/Admin/SurveyController.php:173
 * @route '/admin/surveys/{survey}/publish'
 */
        publishForm.post = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: publish.url(args, options),
            method: 'post',
        })

    publish.form = publishForm
/**
* @see \App\Http\Controllers\Admin\SurveyController::archive
 * @see app/Http/Controllers/Admin/SurveyController.php:195
 * @route '/admin/surveys/{survey}/archive'
 */
export const archive = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: archive.url(args, options),
    method: 'post',
})

archive.definition = {
    methods: ["post"],
    url: '/admin/surveys/{survey}/archive',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\SurveyController::archive
 * @see app/Http/Controllers/Admin/SurveyController.php:195
 * @route '/admin/surveys/{survey}/archive'
 */
archive.url = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { survey: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { survey: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    survey: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        survey: typeof args.survey === 'object'
                ? args.survey.id
                : args.survey,
                }

    return archive.definition.url
            .replace('{survey}', parsedArgs.survey.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SurveyController::archive
 * @see app/Http/Controllers/Admin/SurveyController.php:195
 * @route '/admin/surveys/{survey}/archive'
 */
archive.post = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: archive.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\SurveyController::archive
 * @see app/Http/Controllers/Admin/SurveyController.php:195
 * @route '/admin/surveys/{survey}/archive'
 */
    const archiveForm = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: archive.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\SurveyController::archive
 * @see app/Http/Controllers/Admin/SurveyController.php:195
 * @route '/admin/surveys/{survey}/archive'
 */
        archiveForm.post = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: archive.url(args, options),
            method: 'post',
        })

    archive.form = archiveForm
/**
* @see \App\Http\Controllers\Admin\SurveyController::results
 * @see app/Http/Controllers/Admin/SurveyController.php:212
 * @route '/admin/surveys/{survey}/results'
 */
export const results = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: results.url(args, options),
    method: 'get',
})

results.definition = {
    methods: ["get","head"],
    url: '/admin/surveys/{survey}/results',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\SurveyController::results
 * @see app/Http/Controllers/Admin/SurveyController.php:212
 * @route '/admin/surveys/{survey}/results'
 */
results.url = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { survey: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { survey: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    survey: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        survey: typeof args.survey === 'object'
                ? args.survey.id
                : args.survey,
                }

    return results.definition.url
            .replace('{survey}', parsedArgs.survey.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\SurveyController::results
 * @see app/Http/Controllers/Admin/SurveyController.php:212
 * @route '/admin/surveys/{survey}/results'
 */
results.get = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: results.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\SurveyController::results
 * @see app/Http/Controllers/Admin/SurveyController.php:212
 * @route '/admin/surveys/{survey}/results'
 */
results.head = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: results.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\SurveyController::results
 * @see app/Http/Controllers/Admin/SurveyController.php:212
 * @route '/admin/surveys/{survey}/results'
 */
    const resultsForm = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: results.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\SurveyController::results
 * @see app/Http/Controllers/Admin/SurveyController.php:212
 * @route '/admin/surveys/{survey}/results'
 */
        resultsForm.get = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: results.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\SurveyController::results
 * @see app/Http/Controllers/Admin/SurveyController.php:212
 * @route '/admin/surveys/{survey}/results'
 */
        resultsForm.head = (args: { survey: string | { id: string } } | [survey: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: results.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    results.form = resultsForm
const surveys = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
edit: Object.assign(edit, edit),
update: Object.assign(update, update),
publish: Object.assign(publish, publish),
archive: Object.assign(archive, archive),
results: Object.assign(results, results),
}

export default surveys