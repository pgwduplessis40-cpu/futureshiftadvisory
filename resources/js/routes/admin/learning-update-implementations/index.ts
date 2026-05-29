import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::review
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:73
 * @route '/admin/learning-update-implementations/{learningUpdateImplementation}/review'
 */
export const review = (args: { learningUpdateImplementation: string | { id: string } } | [learningUpdateImplementation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: review.url(args, options),
    method: 'patch',
})

review.definition = {
    methods: ["patch"],
    url: '/admin/learning-update-implementations/{learningUpdateImplementation}/review',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::review
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:73
 * @route '/admin/learning-update-implementations/{learningUpdateImplementation}/review'
 */
review.url = (args: { learningUpdateImplementation: string | { id: string } } | [learningUpdateImplementation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return review.definition.url
            .replace('{learningUpdateImplementation}', parsedArgs.learningUpdateImplementation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\LearningUpdateController::review
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:73
 * @route '/admin/learning-update-implementations/{learningUpdateImplementation}/review'
 */
review.patch = (args: { learningUpdateImplementation: string | { id: string } } | [learningUpdateImplementation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: review.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Admin\LearningUpdateController::review
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:73
 * @route '/admin/learning-update-implementations/{learningUpdateImplementation}/review'
 */
    const reviewForm = (args: { learningUpdateImplementation: string | { id: string } } | [learningUpdateImplementation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: review.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\LearningUpdateController::review
 * @see app/Http/Controllers/Admin/LearningUpdateController.php:73
 * @route '/admin/learning-update-implementations/{learningUpdateImplementation}/review'
 */
        reviewForm.patch = (args: { learningUpdateImplementation: string | { id: string } } | [learningUpdateImplementation: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
const learningUpdateImplementations = {
    review: Object.assign(review, review),
rollback: Object.assign(rollback, rollback),
}

export default learningUpdateImplementations