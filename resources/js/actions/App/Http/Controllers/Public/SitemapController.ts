import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Public\SitemapController::__invoke
 * @see app/Http/Controllers/Public/SitemapController.php:15
 * @route '/sitemap.xml'
 */
const SitemapController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: SitemapController.url(options),
    method: 'get',
})

SitemapController.definition = {
    methods: ["get","head"],
    url: '/sitemap.xml',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Public\SitemapController::__invoke
 * @see app/Http/Controllers/Public/SitemapController.php:15
 * @route '/sitemap.xml'
 */
SitemapController.url = (options?: RouteQueryOptions) => {
    return SitemapController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Public\SitemapController::__invoke
 * @see app/Http/Controllers/Public/SitemapController.php:15
 * @route '/sitemap.xml'
 */
SitemapController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: SitemapController.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Public\SitemapController::__invoke
 * @see app/Http/Controllers/Public/SitemapController.php:15
 * @route '/sitemap.xml'
 */
SitemapController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: SitemapController.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Public\SitemapController::__invoke
 * @see app/Http/Controllers/Public/SitemapController.php:15
 * @route '/sitemap.xml'
 */
    const SitemapControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: SitemapController.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Public\SitemapController::__invoke
 * @see app/Http/Controllers/Public/SitemapController.php:15
 * @route '/sitemap.xml'
 */
        SitemapControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: SitemapController.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Public\SitemapController::__invoke
 * @see app/Http/Controllers/Public/SitemapController.php:15
 * @route '/sitemap.xml'
 */
        SitemapControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: SitemapController.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    SitemapController.form = SitemapControllerForm
export default SitemapController