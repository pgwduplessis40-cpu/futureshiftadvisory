import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\ReportController::store
 * @see app/Http/Controllers/Advisor/ReportController.php:37
 * @route '/advisor/clients/{client}/reports'
 */
export const store = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/clients/{client}/reports',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ReportController::store
 * @see app/Http/Controllers/Advisor/ReportController.php:37
 * @route '/advisor/clients/{client}/reports'
 */
store.url = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { client: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { client: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    client: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        client: typeof args.client === 'object'
                ? args.client.id
                : args.client,
                }

    return store.definition.url
            .replace('{client}', parsedArgs.client.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ReportController::store
 * @see app/Http/Controllers/Advisor/ReportController.php:37
 * @route '/advisor/clients/{client}/reports'
 */
store.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ReportController::store
 * @see app/Http/Controllers/Advisor/ReportController.php:37
 * @route '/advisor/clients/{client}/reports'
 */
    const storeForm = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ReportController::store
 * @see app/Http/Controllers/Advisor/ReportController.php:37
 * @route '/advisor/clients/{client}/reports'
 */
        storeForm.post = (args: { client: string | { id: string } } | [client: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Advisor\ReportController::download
 * @see app/Http/Controllers/Advisor/ReportController.php:134
 * @route '/advisor/reports/{report}/download'
 */
export const download = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})

download.definition = {
    methods: ["get","head"],
    url: '/advisor/reports/{report}/download',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ReportController::download
 * @see app/Http/Controllers/Advisor/ReportController.php:134
 * @route '/advisor/reports/{report}/download'
 */
download.url = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { report: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { report: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    report: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        report: typeof args.report === 'object'
                ? args.report.id
                : args.report,
                }

    return download.definition.url
            .replace('{report}', parsedArgs.report.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ReportController::download
 * @see app/Http/Controllers/Advisor/ReportController.php:134
 * @route '/advisor/reports/{report}/download'
 */
download.get = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: download.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ReportController::download
 * @see app/Http/Controllers/Advisor/ReportController.php:134
 * @route '/advisor/reports/{report}/download'
 */
download.head = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: download.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ReportController::download
 * @see app/Http/Controllers/Advisor/ReportController.php:134
 * @route '/advisor/reports/{report}/download'
 */
    const downloadForm = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: download.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ReportController::download
 * @see app/Http/Controllers/Advisor/ReportController.php:134
 * @route '/advisor/reports/{report}/download'
 */
        downloadForm.get = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: download.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ReportController::download
 * @see app/Http/Controllers/Advisor/ReportController.php:134
 * @route '/advisor/reports/{report}/download'
 */
        downloadForm.head = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Advisor\ReportController::downloadPptx
 * @see app/Http/Controllers/Advisor/ReportController.php:139
 * @route '/advisor/reports/{report}/pptx'
 */
export const downloadPptx = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: downloadPptx.url(args, options),
    method: 'get',
})

downloadPptx.definition = {
    methods: ["get","head"],
    url: '/advisor/reports/{report}/pptx',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\ReportController::downloadPptx
 * @see app/Http/Controllers/Advisor/ReportController.php:139
 * @route '/advisor/reports/{report}/pptx'
 */
downloadPptx.url = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { report: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { report: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    report: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        report: typeof args.report === 'object'
                ? args.report.id
                : args.report,
                }

    return downloadPptx.definition.url
            .replace('{report}', parsedArgs.report.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ReportController::downloadPptx
 * @see app/Http/Controllers/Advisor/ReportController.php:139
 * @route '/advisor/reports/{report}/pptx'
 */
downloadPptx.get = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: downloadPptx.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\ReportController::downloadPptx
 * @see app/Http/Controllers/Advisor/ReportController.php:139
 * @route '/advisor/reports/{report}/pptx'
 */
downloadPptx.head = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: downloadPptx.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\ReportController::downloadPptx
 * @see app/Http/Controllers/Advisor/ReportController.php:139
 * @route '/advisor/reports/{report}/pptx'
 */
    const downloadPptxForm = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: downloadPptx.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\ReportController::downloadPptx
 * @see app/Http/Controllers/Advisor/ReportController.php:139
 * @route '/advisor/reports/{report}/pptx'
 */
        downloadPptxForm.get = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: downloadPptx.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\ReportController::downloadPptx
 * @see app/Http/Controllers/Advisor/ReportController.php:139
 * @route '/advisor/reports/{report}/pptx'
 */
        downloadPptxForm.head = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: downloadPptx.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    downloadPptx.form = downloadPptxForm
/**
* @see \App\Http\Controllers\Advisor\ReportController::review
 * @see app/Http/Controllers/Advisor/ReportController.php:202
 * @route '/advisor/reports/{report}/review'
 */
export const review = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: review.url(args, options),
    method: 'patch',
})

review.definition = {
    methods: ["patch"],
    url: '/advisor/reports/{report}/review',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\ReportController::review
 * @see app/Http/Controllers/Advisor/ReportController.php:202
 * @route '/advisor/reports/{report}/review'
 */
review.url = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { report: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { report: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    report: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        report: typeof args.report === 'object'
                ? args.report.id
                : args.report,
                }

    return review.definition.url
            .replace('{report}', parsedArgs.report.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ReportController::review
 * @see app/Http/Controllers/Advisor/ReportController.php:202
 * @route '/advisor/reports/{report}/review'
 */
review.patch = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: review.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\ReportController::review
 * @see app/Http/Controllers/Advisor/ReportController.php:202
 * @route '/advisor/reports/{report}/review'
 */
    const reviewForm = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: review.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ReportController::review
 * @see app/Http/Controllers/Advisor/ReportController.php:202
 * @route '/advisor/reports/{report}/review'
 */
        reviewForm.patch = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: review.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    review.form = reviewForm
/**
* @see \App\Http\Controllers\Advisor\ReportController::release
 * @see app/Http/Controllers/Advisor/ReportController.php:221
 * @route '/advisor/reports/{report}/release'
 */
export const release = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: release.url(args, options),
    method: 'patch',
})

release.definition = {
    methods: ["patch"],
    url: '/advisor/reports/{report}/release',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\ReportController::release
 * @see app/Http/Controllers/Advisor/ReportController.php:221
 * @route '/advisor/reports/{report}/release'
 */
release.url = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { report: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { report: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    report: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        report: typeof args.report === 'object'
                ? args.report.id
                : args.report,
                }

    return release.definition.url
            .replace('{report}', parsedArgs.report.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ReportController::release
 * @see app/Http/Controllers/Advisor/ReportController.php:221
 * @route '/advisor/reports/{report}/release'
 */
release.patch = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: release.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\ReportController::release
 * @see app/Http/Controllers/Advisor/ReportController.php:221
 * @route '/advisor/reports/{report}/release'
 */
    const releaseForm = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: release.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ReportController::release
 * @see app/Http/Controllers/Advisor/ReportController.php:221
 * @route '/advisor/reports/{report}/release'
 */
        releaseForm.patch = (args: { report: string | { id: string } } | [report: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: release.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    release.form = releaseForm
/**
* @see \App\Http\Controllers\Advisor\ReportController::updateSection
 * @see app/Http/Controllers/Advisor/ReportController.php:246
 * @route '/advisor/reports/{report}/sections/{reportSection}'
 */
export const updateSection = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateSection.url(args, options),
    method: 'patch',
})

updateSection.definition = {
    methods: ["patch"],
    url: '/advisor/reports/{report}/sections/{reportSection}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\ReportController::updateSection
 * @see app/Http/Controllers/Advisor/ReportController.php:246
 * @route '/advisor/reports/{report}/sections/{reportSection}'
 */
updateSection.url = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    report: args[0],
                    reportSection: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        report: typeof args.report === 'object'
                ? args.report.id
                : args.report,
                                reportSection: typeof args.reportSection === 'object'
                ? args.reportSection.id
                : args.reportSection,
                }

    return updateSection.definition.url
            .replace('{report}', parsedArgs.report.toString())
            .replace('{reportSection}', parsedArgs.reportSection.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ReportController::updateSection
 * @see app/Http/Controllers/Advisor/ReportController.php:246
 * @route '/advisor/reports/{report}/sections/{reportSection}'
 */
updateSection.patch = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateSection.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\ReportController::updateSection
 * @see app/Http/Controllers/Advisor/ReportController.php:246
 * @route '/advisor/reports/{report}/sections/{reportSection}'
 */
    const updateSectionForm = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateSection.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ReportController::updateSection
 * @see app/Http/Controllers/Advisor/ReportController.php:246
 * @route '/advisor/reports/{report}/sections/{reportSection}'
 */
        updateSectionForm.patch = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateSection.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateSection.form = updateSectionForm
/**
* @see \App\Http\Controllers\Advisor\ReportController::commentSection
 * @see app/Http/Controllers/Advisor/ReportController.php:282
 * @route '/advisor/reports/{report}/sections/{reportSection}/comments'
 */
export const commentSection = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: commentSection.url(args, options),
    method: 'post',
})

commentSection.definition = {
    methods: ["post"],
    url: '/advisor/reports/{report}/sections/{reportSection}/comments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\ReportController::commentSection
 * @see app/Http/Controllers/Advisor/ReportController.php:282
 * @route '/advisor/reports/{report}/sections/{reportSection}/comments'
 */
commentSection.url = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    report: args[0],
                    reportSection: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        report: typeof args.report === 'object'
                ? args.report.id
                : args.report,
                                reportSection: typeof args.reportSection === 'object'
                ? args.reportSection.id
                : args.reportSection,
                }

    return commentSection.definition.url
            .replace('{report}', parsedArgs.report.toString())
            .replace('{reportSection}', parsedArgs.reportSection.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\ReportController::commentSection
 * @see app/Http/Controllers/Advisor/ReportController.php:282
 * @route '/advisor/reports/{report}/sections/{reportSection}/comments'
 */
commentSection.post = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: commentSection.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\ReportController::commentSection
 * @see app/Http/Controllers/Advisor/ReportController.php:282
 * @route '/advisor/reports/{report}/sections/{reportSection}/comments'
 */
    const commentSectionForm = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: commentSection.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\ReportController::commentSection
 * @see app/Http/Controllers/Advisor/ReportController.php:282
 * @route '/advisor/reports/{report}/sections/{reportSection}/comments'
 */
        commentSectionForm.post = (args: { report: string | { id: string }, reportSection: string | { id: string } } | [report: string | { id: string }, reportSection: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: commentSection.url(args, options),
            method: 'post',
        })
    
    commentSection.form = commentSectionForm
const ReportController = { store, download, downloadPptx, review, release, updateSection, commentSection }

export default ReportController