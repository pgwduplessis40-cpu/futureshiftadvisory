import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Public\AboutController::__invoke
 * @see app/Http/Controllers/Public/AboutController.php:13
 * @route '/about'
 */
const AboutController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AboutController.url(options),
    method: 'get',
})

AboutController.definition = {
    methods: ["get","head"],
    url: '/about',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Public\AboutController::__invoke
 * @see app/Http/Controllers/Public/AboutController.php:13
 * @route '/about'
 */
AboutController.url = (options?: RouteQueryOptions) => {
    return AboutController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Public\AboutController::__invoke
 * @see app/Http/Controllers/Public/AboutController.php:13
 * @route '/about'
 */
AboutController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: AboutController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Public\AboutController::__invoke
 * @see app/Http/Controllers/Public/AboutController.php:13
 * @route '/about'
 */
AboutController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: AboutController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Public\AboutController::__invoke
 * @see app/Http/Controllers/Public/AboutController.php:13
 * @route '/about'
 */
    const AboutControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: AboutController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Public\AboutController::__invoke
 * @see app/Http/Controllers/Public/AboutController.php:13
 * @route '/about'
 */
        AboutControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: AboutController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Public\AboutController::__invoke
 * @see app/Http/Controllers/Public/AboutController.php:13
 * @route '/about'
 */
        AboutControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: AboutController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    AboutController.form = AboutControllerForm
export default AboutController