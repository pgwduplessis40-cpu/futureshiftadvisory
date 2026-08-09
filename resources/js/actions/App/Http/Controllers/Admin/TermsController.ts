import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\TermsController::index
 * @see app/Http/Controllers/Admin/TermsController.php:42
 * @route '/admin/terms'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/terms',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\TermsController::index
 * @see app/Http/Controllers/Admin/TermsController.php:42
 * @route '/admin/terms'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\TermsController::index
 * @see app/Http/Controllers/Admin/TermsController.php:42
 * @route '/admin/terms'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\TermsController::index
 * @see app/Http/Controllers/Admin/TermsController.php:42
 * @route '/admin/terms'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\TermsController::index
 * @see app/Http/Controllers/Admin/TermsController.php:42
 * @route '/admin/terms'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\TermsController::index
 * @see app/Http/Controllers/Admin/TermsController.php:42
 * @route '/admin/terms'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\TermsController::index
 * @see app/Http/Controllers/Admin/TermsController.php:42
 * @route '/admin/terms'
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
* @see \App\Http\Controllers\Admin\TermsController::store
 * @see app/Http/Controllers/Admin/TermsController.php:59
 * @route '/admin/terms'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/terms',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\TermsController::store
 * @see app/Http/Controllers/Admin/TermsController.php:59
 * @route '/admin/terms'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\TermsController::store
 * @see app/Http/Controllers/Admin/TermsController.php:59
 * @route '/admin/terms'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\TermsController::store
 * @see app/Http/Controllers/Admin/TermsController.php:59
 * @route '/admin/terms'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\TermsController::store
 * @see app/Http/Controllers/Admin/TermsController.php:59
 * @route '/admin/terms'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\TermsController::edit
 * @see app/Http/Controllers/Admin/TermsController.php:95
 * @route '/admin/terms/{termsVersion}/edit'
 */
export const edit = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/admin/terms/{termsVersion}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\TermsController::edit
 * @see app/Http/Controllers/Admin/TermsController.php:95
 * @route '/admin/terms/{termsVersion}/edit'
 */
edit.url = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { termsVersion: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { termsVersion: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    termsVersion: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        termsVersion: typeof args.termsVersion === 'object'
                ? args.termsVersion.id
                : args.termsVersion,
                }

    return edit.definition.url
            .replace('{termsVersion}', parsedArgs.termsVersion.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\TermsController::edit
 * @see app/Http/Controllers/Admin/TermsController.php:95
 * @route '/admin/terms/{termsVersion}/edit'
 */
edit.get = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\TermsController::edit
 * @see app/Http/Controllers/Admin/TermsController.php:95
 * @route '/admin/terms/{termsVersion}/edit'
 */
edit.head = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\TermsController::edit
 * @see app/Http/Controllers/Admin/TermsController.php:95
 * @route '/admin/terms/{termsVersion}/edit'
 */
    const editForm = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: edit.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\TermsController::edit
 * @see app/Http/Controllers/Admin/TermsController.php:95
 * @route '/admin/terms/{termsVersion}/edit'
 */
        editForm.get = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: edit.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\TermsController::edit
 * @see app/Http/Controllers/Admin/TermsController.php:95
 * @route '/admin/terms/{termsVersion}/edit'
 */
        editForm.head = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Admin\TermsController::update
 * @see app/Http/Controllers/Admin/TermsController.php:104
 * @route '/admin/terms/{termsVersion}'
 */
export const update = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/admin/terms/{termsVersion}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Admin\TermsController::update
 * @see app/Http/Controllers/Admin/TermsController.php:104
 * @route '/admin/terms/{termsVersion}'
 */
update.url = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { termsVersion: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { termsVersion: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    termsVersion: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        termsVersion: typeof args.termsVersion === 'object'
                ? args.termsVersion.id
                : args.termsVersion,
                }

    return update.definition.url
            .replace('{termsVersion}', parsedArgs.termsVersion.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\TermsController::update
 * @see app/Http/Controllers/Admin/TermsController.php:104
 * @route '/admin/terms/{termsVersion}'
 */
update.put = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Admin\TermsController::update
 * @see app/Http/Controllers/Admin/TermsController.php:104
 * @route '/admin/terms/{termsVersion}'
 */
    const updateForm = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\TermsController::update
 * @see app/Http/Controllers/Admin/TermsController.php:104
 * @route '/admin/terms/{termsVersion}'
 */
        updateForm.put = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Admin\TermsController::preview
 * @see app/Http/Controllers/Admin/TermsController.php:153
 * @route '/admin/terms/{termsVersion}/preview'
 */
export const preview = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})

preview.definition = {
    methods: ["get","head"],
    url: '/admin/terms/{termsVersion}/preview',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\TermsController::preview
 * @see app/Http/Controllers/Admin/TermsController.php:153
 * @route '/admin/terms/{termsVersion}/preview'
 */
preview.url = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { termsVersion: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { termsVersion: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    termsVersion: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        termsVersion: typeof args.termsVersion === 'object'
                ? args.termsVersion.id
                : args.termsVersion,
                }

    return preview.definition.url
            .replace('{termsVersion}', parsedArgs.termsVersion.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\TermsController::preview
 * @see app/Http/Controllers/Admin/TermsController.php:153
 * @route '/admin/terms/{termsVersion}/preview'
 */
preview.get = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: preview.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\TermsController::preview
 * @see app/Http/Controllers/Admin/TermsController.php:153
 * @route '/admin/terms/{termsVersion}/preview'
 */
preview.head = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: preview.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\TermsController::preview
 * @see app/Http/Controllers/Admin/TermsController.php:153
 * @route '/admin/terms/{termsVersion}/preview'
 */
    const previewForm = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: preview.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\TermsController::preview
 * @see app/Http/Controllers/Admin/TermsController.php:153
 * @route '/admin/terms/{termsVersion}/preview'
 */
        previewForm.get = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: preview.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\TermsController::preview
 * @see app/Http/Controllers/Admin/TermsController.php:153
 * @route '/admin/terms/{termsVersion}/preview'
 */
        previewForm.head = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Admin\TermsController::download
 * @see app/Http/Controllers/Admin/TermsController.php:162
 * @route '/admin/terms/{termsVersion}/download'
 */
export const download = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/admin/terms/{termsVersion}/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\TermsController::download
 * @see app/Http/Controllers/Admin/TermsController.php:162
 * @route '/admin/terms/{termsVersion}/download'
 */
download.url = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { termsVersion: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { termsVersion: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    termsVersion: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        termsVersion: typeof args.termsVersion === 'object'
                ? args.termsVersion.id
                : args.termsVersion,
                }

    return download.definition.url
            .replace('{termsVersion}', parsedArgs.termsVersion.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\TermsController::download
 * @see app/Http/Controllers/Admin/TermsController.php:162
 * @route '/admin/terms/{termsVersion}/download'
 */
download.get = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\TermsController::download
 * @see app/Http/Controllers/Admin/TermsController.php:162
 * @route '/admin/terms/{termsVersion}/download'
 */
download.head = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\TermsController::download
 * @see app/Http/Controllers/Admin/TermsController.php:162
 * @route '/admin/terms/{termsVersion}/download'
 */
    const downloadForm = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: download.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\TermsController::download
 * @see app/Http/Controllers/Admin/TermsController.php:162
 * @route '/admin/terms/{termsVersion}/download'
 */
        downloadForm.get = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\TermsController::download
 * @see app/Http/Controllers/Admin/TermsController.php:162
 * @route '/admin/terms/{termsVersion}/download'
 */
        downloadForm.head = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    download.form = downloadForm
/**
* @see \App\Http\Controllers\Admin\TermsController::uploadSourceFile
 * @see app/Http/Controllers/Admin/TermsController.php:191
 * @route '/admin/terms/{termsVersion}/source-file'
 */
export const uploadSourceFile = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadSourceFile.url(args, options),
    method: 'post',
})

uploadSourceFile.definition = {
    methods: ["post"],
    url: '/admin/terms/{termsVersion}/source-file',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\TermsController::uploadSourceFile
 * @see app/Http/Controllers/Admin/TermsController.php:191
 * @route '/admin/terms/{termsVersion}/source-file'
 */
uploadSourceFile.url = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { termsVersion: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { termsVersion: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    termsVersion: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        termsVersion: typeof args.termsVersion === 'object'
                ? args.termsVersion.id
                : args.termsVersion,
                }

    return uploadSourceFile.definition.url
            .replace('{termsVersion}', parsedArgs.termsVersion.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\TermsController::uploadSourceFile
 * @see app/Http/Controllers/Admin/TermsController.php:191
 * @route '/admin/terms/{termsVersion}/source-file'
 */
uploadSourceFile.post = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadSourceFile.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\TermsController::uploadSourceFile
 * @see app/Http/Controllers/Admin/TermsController.php:191
 * @route '/admin/terms/{termsVersion}/source-file'
 */
    const uploadSourceFileForm = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: uploadSourceFile.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\TermsController::uploadSourceFile
 * @see app/Http/Controllers/Admin/TermsController.php:191
 * @route '/admin/terms/{termsVersion}/source-file'
 */
        uploadSourceFileForm.post = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: uploadSourceFile.url(args, options),
            method: 'post',
        })

    uploadSourceFile.form = uploadSourceFileForm
/**
* @see \App\Http\Controllers\Admin\TermsController::downloadSourceFile
 * @see app/Http/Controllers/Admin/TermsController.php:236
 * @route '/admin/terms/{termsVersion}/source-file/download'
 */
export const downloadSourceFile = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: downloadSourceFile.url(args, options),
    method: 'get',
})

downloadSourceFile.definition = {
    methods: ["get","head"],
    url: '/admin/terms/{termsVersion}/source-file/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\TermsController::downloadSourceFile
 * @see app/Http/Controllers/Admin/TermsController.php:236
 * @route '/admin/terms/{termsVersion}/source-file/download'
 */
downloadSourceFile.url = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { termsVersion: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { termsVersion: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    termsVersion: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        termsVersion: typeof args.termsVersion === 'object'
                ? args.termsVersion.id
                : args.termsVersion,
                }

    return downloadSourceFile.definition.url
            .replace('{termsVersion}', parsedArgs.termsVersion.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\TermsController::downloadSourceFile
 * @see app/Http/Controllers/Admin/TermsController.php:236
 * @route '/admin/terms/{termsVersion}/source-file/download'
 */
downloadSourceFile.get = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: downloadSourceFile.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\TermsController::downloadSourceFile
 * @see app/Http/Controllers/Admin/TermsController.php:236
 * @route '/admin/terms/{termsVersion}/source-file/download'
 */
downloadSourceFile.head = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: downloadSourceFile.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\TermsController::downloadSourceFile
 * @see app/Http/Controllers/Admin/TermsController.php:236
 * @route '/admin/terms/{termsVersion}/source-file/download'
 */
    const downloadSourceFileForm = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: downloadSourceFile.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\TermsController::downloadSourceFile
 * @see app/Http/Controllers/Admin/TermsController.php:236
 * @route '/admin/terms/{termsVersion}/source-file/download'
 */
        downloadSourceFileForm.get = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: downloadSourceFile.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\TermsController::downloadSourceFile
 * @see app/Http/Controllers/Admin/TermsController.php:236
 * @route '/admin/terms/{termsVersion}/source-file/download'
 */
        downloadSourceFileForm.head = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: downloadSourceFile.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    downloadSourceFile.form = downloadSourceFileForm
/**
* @see \App\Http\Controllers\Admin\TermsController::confirmPublish
 * @see app/Http/Controllers/Admin/TermsController.php:268
 * @route '/admin/terms/{termsVersion}/publish'
 */
export const confirmPublish = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: confirmPublish.url(args, options),
    method: 'get',
})

confirmPublish.definition = {
    methods: ["get","head"],
    url: '/admin/terms/{termsVersion}/publish',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\TermsController::confirmPublish
 * @see app/Http/Controllers/Admin/TermsController.php:268
 * @route '/admin/terms/{termsVersion}/publish'
 */
confirmPublish.url = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { termsVersion: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { termsVersion: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    termsVersion: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        termsVersion: typeof args.termsVersion === 'object'
                ? args.termsVersion.id
                : args.termsVersion,
                }

    return confirmPublish.definition.url
            .replace('{termsVersion}', parsedArgs.termsVersion.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\TermsController::confirmPublish
 * @see app/Http/Controllers/Admin/TermsController.php:268
 * @route '/admin/terms/{termsVersion}/publish'
 */
confirmPublish.get = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: confirmPublish.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\TermsController::confirmPublish
 * @see app/Http/Controllers/Admin/TermsController.php:268
 * @route '/admin/terms/{termsVersion}/publish'
 */
confirmPublish.head = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: confirmPublish.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\TermsController::confirmPublish
 * @see app/Http/Controllers/Admin/TermsController.php:268
 * @route '/admin/terms/{termsVersion}/publish'
 */
    const confirmPublishForm = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: confirmPublish.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\TermsController::confirmPublish
 * @see app/Http/Controllers/Admin/TermsController.php:268
 * @route '/admin/terms/{termsVersion}/publish'
 */
        confirmPublishForm.get = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: confirmPublish.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\TermsController::confirmPublish
 * @see app/Http/Controllers/Admin/TermsController.php:268
 * @route '/admin/terms/{termsVersion}/publish'
 */
        confirmPublishForm.head = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: confirmPublish.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    confirmPublish.form = confirmPublishForm
/**
* @see \App\Http\Controllers\Admin\TermsController::publish
 * @see app/Http/Controllers/Admin/TermsController.php:277
 * @route '/admin/terms/{termsVersion}/publish'
 */
export const publish = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
})

publish.definition = {
    methods: ["post"],
    url: '/admin/terms/{termsVersion}/publish',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\TermsController::publish
 * @see app/Http/Controllers/Admin/TermsController.php:277
 * @route '/admin/terms/{termsVersion}/publish'
 */
publish.url = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { termsVersion: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { termsVersion: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    termsVersion: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        termsVersion: typeof args.termsVersion === 'object'
                ? args.termsVersion.id
                : args.termsVersion,
                }

    return publish.definition.url
            .replace('{termsVersion}', parsedArgs.termsVersion.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\TermsController::publish
 * @see app/Http/Controllers/Admin/TermsController.php:277
 * @route '/admin/terms/{termsVersion}/publish'
 */
publish.post = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\TermsController::publish
 * @see app/Http/Controllers/Admin/TermsController.php:277
 * @route '/admin/terms/{termsVersion}/publish'
 */
    const publishForm = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: publish.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\TermsController::publish
 * @see app/Http/Controllers/Admin/TermsController.php:277
 * @route '/admin/terms/{termsVersion}/publish'
 */
        publishForm.post = (args: { termsVersion: string | { id: string } } | [termsVersion: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: publish.url(args, options),
            method: 'post',
        })

    publish.form = publishForm
/**
* @see \App\Http\Controllers\Admin\TermsController::activateEnforcement
 * @see app/Http/Controllers/Admin/TermsController.php:335
 * @route '/admin/terms/enforcement/activate'
 */
export const activateEnforcement = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: activateEnforcement.url(options),
    method: 'post',
})

activateEnforcement.definition = {
    methods: ["post"],
    url: '/admin/terms/enforcement/activate',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\TermsController::activateEnforcement
 * @see app/Http/Controllers/Admin/TermsController.php:335
 * @route '/admin/terms/enforcement/activate'
 */
activateEnforcement.url = (options?: RouteQueryOptions) => {
    return activateEnforcement.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\TermsController::activateEnforcement
 * @see app/Http/Controllers/Admin/TermsController.php:335
 * @route '/admin/terms/enforcement/activate'
 */
activateEnforcement.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: activateEnforcement.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\TermsController::activateEnforcement
 * @see app/Http/Controllers/Admin/TermsController.php:335
 * @route '/admin/terms/enforcement/activate'
 */
    const activateEnforcementForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: activateEnforcement.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\TermsController::activateEnforcement
 * @see app/Http/Controllers/Admin/TermsController.php:335
 * @route '/admin/terms/enforcement/activate'
 */
        activateEnforcementForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: activateEnforcement.url(options),
            method: 'post',
        })

    activateEnforcement.form = activateEnforcementForm
const TermsController = { index, store, edit, update, preview, download, uploadSourceFile, downloadSourceFile, confirmPublish, publish, activateEnforcement }

export default TermsController