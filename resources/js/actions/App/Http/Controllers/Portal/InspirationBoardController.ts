import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\InspirationBoardController::index
 * @see app/Http/Controllers/Portal/InspirationBoardController.php:23
 * @route '/portal/inspiration-board'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/portal/inspiration-board',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\InspirationBoardController::index
 * @see app/Http/Controllers/Portal/InspirationBoardController.php:23
 * @route '/portal/inspiration-board'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\InspirationBoardController::index
 * @see app/Http/Controllers/Portal/InspirationBoardController.php:23
 * @route '/portal/inspiration-board'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\InspirationBoardController::index
 * @see app/Http/Controllers/Portal/InspirationBoardController.php:23
 * @route '/portal/inspiration-board'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\InspirationBoardController::index
 * @see app/Http/Controllers/Portal/InspirationBoardController.php:23
 * @route '/portal/inspiration-board'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\InspirationBoardController::index
 * @see app/Http/Controllers/Portal/InspirationBoardController.php:23
 * @route '/portal/inspiration-board'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\InspirationBoardController::index
 * @see app/Http/Controllers/Portal/InspirationBoardController.php:23
 * @route '/portal/inspiration-board'
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
* @see \App\Http\Controllers\Portal\InspirationBoardController::image
 * @see app/Http/Controllers/Portal/InspirationBoardController.php:32
 * @route '/portal/inspiration-board/{boardPost}/image'
 */
export const image = (args: { boardPost: string | number | { id: string | number } } | [boardPost: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: image.url(args, options),
    method: 'get',
})

image.definition = {
    methods: ["get","head"],
    url: '/portal/inspiration-board/{boardPost}/image',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\InspirationBoardController::image
 * @see app/Http/Controllers/Portal/InspirationBoardController.php:32
 * @route '/portal/inspiration-board/{boardPost}/image'
 */
image.url = (args: { boardPost: string | number | { id: string | number } } | [boardPost: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { boardPost: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { boardPost: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    boardPost: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        boardPost: typeof args.boardPost === 'object'
                ? args.boardPost.id
                : args.boardPost,
                }

    return image.definition.url
            .replace('{boardPost}', parsedArgs.boardPost.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\InspirationBoardController::image
 * @see app/Http/Controllers/Portal/InspirationBoardController.php:32
 * @route '/portal/inspiration-board/{boardPost}/image'
 */
image.get = (args: { boardPost: string | number | { id: string | number } } | [boardPost: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: image.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Portal\InspirationBoardController::image
 * @see app/Http/Controllers/Portal/InspirationBoardController.php:32
 * @route '/portal/inspiration-board/{boardPost}/image'
 */
image.head = (args: { boardPost: string | number | { id: string | number } } | [boardPost: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: image.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Portal\InspirationBoardController::image
 * @see app/Http/Controllers/Portal/InspirationBoardController.php:32
 * @route '/portal/inspiration-board/{boardPost}/image'
 */
    const imageForm = (args: { boardPost: string | number | { id: string | number } } | [boardPost: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: image.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Portal\InspirationBoardController::image
 * @see app/Http/Controllers/Portal/InspirationBoardController.php:32
 * @route '/portal/inspiration-board/{boardPost}/image'
 */
        imageForm.get = (args: { boardPost: string | number | { id: string | number } } | [boardPost: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: image.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Portal\InspirationBoardController::image
 * @see app/Http/Controllers/Portal/InspirationBoardController.php:32
 * @route '/portal/inspiration-board/{boardPost}/image'
 */
        imageForm.head = (args: { boardPost: string | number | { id: string | number } } | [boardPost: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: image.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    image.form = imageForm
const InspirationBoardController = { index, image }

export default InspirationBoardController