import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\MethodologyController::index
 * @see app/Http/Controllers/Advisor/MethodologyController.php:20
 * @route '/advisor/knowledge/methodologies'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/knowledge/methodologies',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\MethodologyController::index
 * @see app/Http/Controllers/Advisor/MethodologyController.php:20
 * @route '/advisor/knowledge/methodologies'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\MethodologyController::index
 * @see app/Http/Controllers/Advisor/MethodologyController.php:20
 * @route '/advisor/knowledge/methodologies'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\MethodologyController::index
 * @see app/Http/Controllers/Advisor/MethodologyController.php:20
 * @route '/advisor/knowledge/methodologies'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\MethodologyController::index
 * @see app/Http/Controllers/Advisor/MethodologyController.php:20
 * @route '/advisor/knowledge/methodologies'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\MethodologyController::index
 * @see app/Http/Controllers/Advisor/MethodologyController.php:20
 * @route '/advisor/knowledge/methodologies'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\MethodologyController::index
 * @see app/Http/Controllers/Advisor/MethodologyController.php:20
 * @route '/advisor/knowledge/methodologies'
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
* @see \App\Http\Controllers\Advisor\MethodologyController::show
 * @see app/Http/Controllers/Advisor/MethodologyController.php:57
 * @route '/advisor/knowledge/methodologies/{methodology}'
 */
export const show = (args: { methodology: string | number } | [methodology: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/knowledge/methodologies/{methodology}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\MethodologyController::show
 * @see app/Http/Controllers/Advisor/MethodologyController.php:57
 * @route '/advisor/knowledge/methodologies/{methodology}'
 */
show.url = (args: { methodology: string | number } | [methodology: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { methodology: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    methodology: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        methodology: args.methodology,
                }

    return show.definition.url
            .replace('{methodology}', parsedArgs.methodology.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\MethodologyController::show
 * @see app/Http/Controllers/Advisor/MethodologyController.php:57
 * @route '/advisor/knowledge/methodologies/{methodology}'
 */
show.get = (args: { methodology: string | number } | [methodology: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\MethodologyController::show
 * @see app/Http/Controllers/Advisor/MethodologyController.php:57
 * @route '/advisor/knowledge/methodologies/{methodology}'
 */
show.head = (args: { methodology: string | number } | [methodology: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\MethodologyController::show
 * @see app/Http/Controllers/Advisor/MethodologyController.php:57
 * @route '/advisor/knowledge/methodologies/{methodology}'
 */
    const showForm = (args: { methodology: string | number } | [methodology: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\MethodologyController::show
 * @see app/Http/Controllers/Advisor/MethodologyController.php:57
 * @route '/advisor/knowledge/methodologies/{methodology}'
 */
        showForm.get = (args: { methodology: string | number } | [methodology: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\MethodologyController::show
 * @see app/Http/Controllers/Advisor/MethodologyController.php:57
 * @route '/advisor/knowledge/methodologies/{methodology}'
 */
        showForm.head = (args: { methodology: string | number } | [methodology: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
const MethodologyController = { index, show }

export default MethodologyController