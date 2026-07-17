import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::index
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:29
 * @route '/admin/learning-updates'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/learning-updates',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::index
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:29
 * @route '/admin/learning-updates'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::index
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:29
 * @route '/admin/learning-updates'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::index
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:29
 * @route '/admin/learning-updates'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\LearningUpdateController::index
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:29
 * @route '/admin/learning-updates'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\LearningUpdateController::index
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:29
 * @route '/admin/learning-updates'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\LearningUpdateController::index
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:29
 * @route '/admin/learning-updates'
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
* @see \App\Http\Controllers\Admin\LearningUpdateController::rerun
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:61
 * @route '/admin/learning-updates/rerun'
 */
export const rerun = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: rerun.url(options),
    method: 'post',
})

rerun.definition = {
    methods: ["post"],
    url: '/admin/learning-updates/rerun',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::rerun
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:61
 * @route '/admin/learning-updates/rerun'
 */
rerun.url = (options?: RouteQueryOptions) => {
    return rerun.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::rerun
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:61
 * @route '/admin/learning-updates/rerun'
 */
rerun.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: rerun.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\LearningUpdateController::rerun
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:61
 * @route '/admin/learning-updates/rerun'
 */
    const rerunForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: rerun.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\LearningUpdateController::rerun
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:61
 * @route '/admin/learning-updates/rerun'
 */
        rerunForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: rerun.url(options),
            method: 'post',
        })
    
    rerun.form = rerunForm
/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::decide
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:40
 * @route '/admin/learning-updates/{learningUpdate}/decision'
 */
export const decide = (args: { learningUpdate: string | { id: string } } | [learningUpdate: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: decide.url(args, options),
    method: 'patch',
})

decide.definition = {
    methods: ["patch"],
    url: '/admin/learning-updates/{learningUpdate}/decision',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::decide
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:40
 * @route '/admin/learning-updates/{learningUpdate}/decision'
 */
decide.url = (args: { learningUpdate: string | { id: string } } | [learningUpdate: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { learningUpdate: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { learningUpdate: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    learningUpdate: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        learningUpdate: typeof args.learningUpdate === 'object'
                ? args.learningUpdate.id
                : args.learningUpdate,
                }

    return decide.definition.url
            .replace('{learningUpdate}', parsedArgs.learningUpdate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::decide
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:40
 * @route '/admin/learning-updates/{learningUpdate}/decision'
 */
decide.patch = (args: { learningUpdate: string | { id: string } } | [learningUpdate: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: decide.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\LearningUpdateController::decide
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:40
 * @route '/admin/learning-updates/{learningUpdate}/decision'
 */
    const decideForm = (args: { learningUpdate: string | { id: string } } | [learningUpdate: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: decide.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\LearningUpdateController::decide
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:40
 * @route '/admin/learning-updates/{learningUpdate}/decision'
 */
        decideForm.patch = (args: { learningUpdate: string | { id: string } } | [learningUpdate: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: decide.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    decide.form = decideForm
/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::reviewImpact
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:73
 * @route '/admin/learning-update-implementations/{learningUpdateImplementation}/review'
 */
export const reviewImpact = (args: { learningUpdateImplementation: string | { id: string } } | [learningUpdateImplementation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reviewImpact.url(args, options),
    method: 'patch',
})

reviewImpact.definition = {
    methods: ["patch"],
    url: '/admin/learning-update-implementations/{learningUpdateImplementation}/review',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::reviewImpact
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:73
 * @route '/admin/learning-update-implementations/{learningUpdateImplementation}/review'
 */
reviewImpact.url = (args: { learningUpdateImplementation: string | { id: string } } | [learningUpdateImplementation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { learningUpdateImplementation: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { learningUpdateImplementation: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    learningUpdateImplementation: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        learningUpdateImplementation: typeof args.learningUpdateImplementation === 'object'
                ? args.learningUpdateImplementation.id
                : args.learningUpdateImplementation,
                }

    return reviewImpact.definition.url
            .replace('{learningUpdateImplementation}', parsedArgs.learningUpdateImplementation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::reviewImpact
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:73
 * @route '/admin/learning-update-implementations/{learningUpdateImplementation}/review'
 */
reviewImpact.patch = (args: { learningUpdateImplementation: string | { id: string } } | [learningUpdateImplementation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reviewImpact.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\LearningUpdateController::reviewImpact
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:73
 * @route '/admin/learning-update-implementations/{learningUpdateImplementation}/review'
 */
    const reviewImpactForm = (args: { learningUpdateImplementation: string | { id: string } } | [learningUpdateImplementation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: reviewImpact.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\LearningUpdateController::reviewImpact
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:73
 * @route '/admin/learning-update-implementations/{learningUpdateImplementation}/review'
 */
        reviewImpactForm.patch = (args: { learningUpdateImplementation: string | { id: string } } | [learningUpdateImplementation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: reviewImpact.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    reviewImpact.form = reviewImpactForm
/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::rollback
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:93
 * @route '/admin/learning-update-implementations/{learningUpdateImplementation}/rollback'
 */
export const rollback = (args: { learningUpdateImplementation: string | { id: string } } | [learningUpdateImplementation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: rollback.url(args, options),
    method: 'patch',
})

rollback.definition = {
    methods: ["patch"],
    url: '/admin/learning-update-implementations/{learningUpdateImplementation}/rollback',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::rollback
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:93
 * @route '/admin/learning-update-implementations/{learningUpdateImplementation}/rollback'
 */
rollback.url = (args: { learningUpdateImplementation: string | { id: string } } | [learningUpdateImplementation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { learningUpdateImplementation: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { learningUpdateImplementation: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    learningUpdateImplementation: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        learningUpdateImplementation: typeof args.learningUpdateImplementation === 'object'
                ? args.learningUpdateImplementation.id
                : args.learningUpdateImplementation,
                }

    return rollback.definition.url
            .replace('{learningUpdateImplementation}', parsedArgs.learningUpdateImplementation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::rollback
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:93
 * @route '/admin/learning-update-implementations/{learningUpdateImplementation}/rollback'
 */
rollback.patch = (args: { learningUpdateImplementation: string | { id: string } } | [learningUpdateImplementation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: rollback.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\LearningUpdateController::rollback
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:93
 * @route '/admin/learning-update-implementations/{learningUpdateImplementation}/rollback'
 */
    const rollbackForm = (args: { learningUpdateImplementation: string | { id: string } } | [learningUpdateImplementation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: rollback.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\LearningUpdateController::rollback
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:93
 * @route '/admin/learning-update-implementations/{learningUpdateImplementation}/rollback'
 */
        rollbackForm.patch = (args: { learningUpdateImplementation: string | { id: string } } | [learningUpdateImplementation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: rollback.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    rollback.form = rollbackForm
const LearningUpdateController = { index, rerun, decide, reviewImpact, rollback }

export default LearningUpdateController