import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import contact50a660 from './contact'
/**
* @see \App\Http\Controllers\Public\ServicesController::__invoke
 * @see app/Http/Controllers/Public/ServicesController.php:14
 * @route '/services'
 */
export const services = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: services.url(options),
    method: 'get',
})

services.definition = {
    methods: ["get","head"],
    url: '/services',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Public\ServicesController::__invoke
 * @see app/Http/Controllers/Public/ServicesController.php:14
 * @route '/services'
 */
services.url = (options?: RouteQueryOptions) => {
    return services.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Public\ServicesController::__invoke
 * @see app/Http/Controllers/Public/ServicesController.php:14
 * @route '/services'
 */
services.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: services.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Public\ServicesController::__invoke
 * @see app/Http/Controllers/Public/ServicesController.php:14
 * @route '/services'
 */
services.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: services.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Public\ServicesController::__invoke
 * @see app/Http/Controllers/Public/ServicesController.php:14
 * @route '/services'
 */
    const servicesForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: services.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Public\ServicesController::__invoke
 * @see app/Http/Controllers/Public/ServicesController.php:14
 * @route '/services'
 */
        servicesForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: services.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Public\ServicesController::__invoke
 * @see app/Http/Controllers/Public/ServicesController.php:14
 * @route '/services'
 */
        servicesForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: services.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    services.form = servicesForm
/**
* @see \App\Http\Controllers\Public\AboutController::__invoke
 * @see app/Http/Controllers/Public/AboutController.php:13
 * @route '/about'
 */
export const about = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: about.url(options),
    method: 'get',
})

about.definition = {
    methods: ["get","head"],
    url: '/about',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Public\AboutController::__invoke
 * @see app/Http/Controllers/Public/AboutController.php:13
 * @route '/about'
 */
about.url = (options?: RouteQueryOptions) => {
    return about.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Public\AboutController::__invoke
 * @see app/Http/Controllers/Public/AboutController.php:13
 * @route '/about'
 */
about.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: about.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Public\AboutController::__invoke
 * @see app/Http/Controllers/Public/AboutController.php:13
 * @route '/about'
 */
about.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: about.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Public\AboutController::__invoke
 * @see app/Http/Controllers/Public/AboutController.php:13
 * @route '/about'
 */
    const aboutForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: about.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Public\AboutController::__invoke
 * @see app/Http/Controllers/Public/AboutController.php:13
 * @route '/about'
 */
        aboutForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: about.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Public\AboutController::__invoke
 * @see app/Http/Controllers/Public/AboutController.php:13
 * @route '/about'
 */
        aboutForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: about.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    about.form = aboutForm
/**
* @see \App\Http\Controllers\Public\FaqController::__invoke
 * @see app/Http/Controllers/Public/FaqController.php:14
 * @route '/faq'
 */
export const faq = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: faq.url(options),
    method: 'get',
})

faq.definition = {
    methods: ["get","head"],
    url: '/faq',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Public\FaqController::__invoke
 * @see app/Http/Controllers/Public/FaqController.php:14
 * @route '/faq'
 */
faq.url = (options?: RouteQueryOptions) => {
    return faq.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Public\FaqController::__invoke
 * @see app/Http/Controllers/Public/FaqController.php:14
 * @route '/faq'
 */
faq.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: faq.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Public\FaqController::__invoke
 * @see app/Http/Controllers/Public/FaqController.php:14
 * @route '/faq'
 */
faq.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: faq.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Public\FaqController::__invoke
 * @see app/Http/Controllers/Public/FaqController.php:14
 * @route '/faq'
 */
    const faqForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: faq.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Public\FaqController::__invoke
 * @see app/Http/Controllers/Public/FaqController.php:14
 * @route '/faq'
 */
        faqForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: faq.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Public\FaqController::__invoke
 * @see app/Http/Controllers/Public/FaqController.php:14
 * @route '/faq'
 */
        faqForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: faq.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    faq.form = faqForm
/**
* @see \App\Http\Controllers\Public\ContactController::contact
 * @see app/Http/Controllers/Public/ContactController.php:21
 * @route '/contact'
 */
export const contact = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: contact.url(options),
    method: 'get',
})

contact.definition = {
    methods: ["get","head"],
    url: '/contact',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Public\ContactController::contact
 * @see app/Http/Controllers/Public/ContactController.php:21
 * @route '/contact'
 */
contact.url = (options?: RouteQueryOptions) => {
    return contact.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Public\ContactController::contact
 * @see app/Http/Controllers/Public/ContactController.php:21
 * @route '/contact'
 */
contact.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: contact.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Public\ContactController::contact
 * @see app/Http/Controllers/Public/ContactController.php:21
 * @route '/contact'
 */
contact.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: contact.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Public\ContactController::contact
 * @see app/Http/Controllers/Public/ContactController.php:21
 * @route '/contact'
 */
    const contactForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: contact.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Public\ContactController::contact
 * @see app/Http/Controllers/Public/ContactController.php:21
 * @route '/contact'
 */
        contactForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: contact.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Public\ContactController::contact
 * @see app/Http/Controllers/Public/ContactController.php:21
 * @route '/contact'
 */
        contactForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: contact.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    contact.form = contactForm
/**
* @see \App\Http\Controllers\Public\SitemapController::__invoke
 * @see app/Http/Controllers/Public/SitemapController.php:15
 * @route '/sitemap.xml'
 */
export const sitemap = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sitemap.url(options),
    method: 'get',
})

sitemap.definition = {
    methods: ["get","head"],
    url: '/sitemap.xml',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Public\SitemapController::__invoke
 * @see app/Http/Controllers/Public/SitemapController.php:15
 * @route '/sitemap.xml'
 */
sitemap.url = (options?: RouteQueryOptions) => {
    return sitemap.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Public\SitemapController::__invoke
 * @see app/Http/Controllers/Public/SitemapController.php:15
 * @route '/sitemap.xml'
 */
sitemap.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sitemap.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Public\SitemapController::__invoke
 * @see app/Http/Controllers/Public/SitemapController.php:15
 * @route '/sitemap.xml'
 */
sitemap.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: sitemap.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Public\SitemapController::__invoke
 * @see app/Http/Controllers/Public/SitemapController.php:15
 * @route '/sitemap.xml'
 */
    const sitemapForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: sitemap.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Public\SitemapController::__invoke
 * @see app/Http/Controllers/Public/SitemapController.php:15
 * @route '/sitemap.xml'
 */
        sitemapForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: sitemap.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Public\SitemapController::__invoke
 * @see app/Http/Controllers/Public/SitemapController.php:15
 * @route '/sitemap.xml'
 */
        sitemapForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: sitemap.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })

    sitemap.form = sitemapForm
const publicMethod = {
    services: Object.assign(services, services),
about: Object.assign(about, about),
faq: Object.assign(faq, faq),
contact: Object.assign(contact, contact50a660),
sitemap: Object.assign(sitemap, sitemap),
}

export default publicMethod