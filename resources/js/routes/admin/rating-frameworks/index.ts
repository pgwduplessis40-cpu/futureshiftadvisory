import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import drafts from './drafts'
/**
* @see \App\Http\Controllers\Admin\RatingFrameworkController::index
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:23
 * @route '/admin/rating-frameworks'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/rating-frameworks',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\RatingFrameworkController::index
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:23
 * @route '/admin/rating-frameworks'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\RatingFrameworkController::index
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:23
 * @route '/admin/rating-frameworks'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\RatingFrameworkController::index
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:23
 * @route '/admin/rating-frameworks'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\RatingFrameworkController::index
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:23
 * @route '/admin/rating-frameworks'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\RatingFrameworkController::index
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:23
 * @route '/admin/rating-frameworks'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\RatingFrameworkController::index
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:23
 * @route '/admin/rating-frameworks'
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
* @see \App\Http\Controllers\Admin\RatingFrameworkController::publish
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:60
 * @route '/admin/rating-frameworks/{ratingFramework}/publish'
 */
export const publish = (args: { ratingFramework: string | { id: string } } | [ratingFramework: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
})

publish.definition = {
    methods: ["post"],
    url: '/admin/rating-frameworks/{ratingFramework}/publish',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\RatingFrameworkController::publish
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:60
 * @route '/admin/rating-frameworks/{ratingFramework}/publish'
 */
publish.url = (args: { ratingFramework: string | { id: string } } | [ratingFramework: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ratingFramework: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { ratingFramework: args.id }
        }

    if (Array.isArray(args)) {
        args = {
                    ratingFramework: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        ratingFramework: typeof args.ratingFramework === 'object'
                ? args.ratingFramework.id
                : args.ratingFramework,
                }

    return publish.definition.url
            .replace('{ratingFramework}', parsedArgs.ratingFramework.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\RatingFrameworkController::publish
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:60
 * @route '/admin/rating-frameworks/{ratingFramework}/publish'
 */
publish.post = (args: { ratingFramework: string | { id: string } } | [ratingFramework: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\RatingFrameworkController::publish
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:60
 * @route '/admin/rating-frameworks/{ratingFramework}/publish'
 */
    const publishForm = (args: { ratingFramework: string | { id: string } } | [ratingFramework: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: publish.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\RatingFrameworkController::publish
 * @see app/Http/Controllers/Admin/RatingFrameworkController.php:60
 * @route '/admin/rating-frameworks/{ratingFramework}/publish'
 */
        publishForm.post = (args: { ratingFramework: string | { id: string } } | [ratingFramework: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: publish.url(args, options),
            method: 'post',
        })

    publish.form = publishForm
const ratingFrameworks = {
    index: Object.assign(index, index),
drafts: Object.assign(drafts, drafts),
publish: Object.assign(publish, publish),
}

export default ratingFrameworks