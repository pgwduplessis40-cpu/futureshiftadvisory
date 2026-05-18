import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Public\ContactController::create
 * @see app/Http/Controllers/Public/ContactController.php:21
 * @route '/contact'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/contact',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Public\ContactController::create
 * @see app/Http/Controllers/Public/ContactController.php:21
 * @route '/contact'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Public\ContactController::create
 * @see app/Http/Controllers/Public/ContactController.php:21
 * @route '/contact'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Public\ContactController::create
 * @see app/Http/Controllers/Public/ContactController.php:21
 * @route '/contact'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Public\ContactController::create
 * @see app/Http/Controllers/Public/ContactController.php:21
 * @route '/contact'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Public\ContactController::create
 * @see app/Http/Controllers/Public/ContactController.php:21
 * @route '/contact'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Public\ContactController::create
 * @see app/Http/Controllers/Public/ContactController.php:21
 * @route '/contact'
 */
        createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    create.form = createForm
/**
* @see \App\Http\Controllers\Public\ContactController::store
 * @see app/Http/Controllers/Public/ContactController.php:28
 * @route '/contact'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/contact',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Public\ContactController::store
 * @see app/Http/Controllers/Public/ContactController.php:28
 * @route '/contact'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Public\ContactController::store
 * @see app/Http/Controllers/Public/ContactController.php:28
 * @route '/contact'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Public\ContactController::store
 * @see app/Http/Controllers/Public/ContactController.php:28
 * @route '/contact'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Public\ContactController::store
 * @see app/Http/Controllers/Public/ContactController.php:28
 * @route '/contact'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Public\ContactController::thanks
 * @see app/Http/Controllers/Public/ContactController.php:60
 * @route '/contact/thanks'
 */
export const thanks = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: thanks.url(options),
    method: 'get',
})

thanks.definition = {
    methods: ["get","head"],
    url: '/contact/thanks',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Public\ContactController::thanks
 * @see app/Http/Controllers/Public/ContactController.php:60
 * @route '/contact/thanks'
 */
thanks.url = (options?: RouteQueryOptions) => {
    return thanks.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Public\ContactController::thanks
 * @see app/Http/Controllers/Public/ContactController.php:60
 * @route '/contact/thanks'
 */
thanks.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: thanks.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Public\ContactController::thanks
 * @see app/Http/Controllers/Public/ContactController.php:60
 * @route '/contact/thanks'
 */
thanks.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: thanks.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Public\ContactController::thanks
 * @see app/Http/Controllers/Public/ContactController.php:60
 * @route '/contact/thanks'
 */
    const thanksForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: thanks.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Public\ContactController::thanks
 * @see app/Http/Controllers/Public/ContactController.php:60
 * @route '/contact/thanks'
 */
        thanksForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: thanks.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Public\ContactController::thanks
 * @see app/Http/Controllers/Public/ContactController.php:60
 * @route '/contact/thanks'
 */
        thanksForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: thanks.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    thanks.form = thanksForm
const ContactController = { create, store, thanks }

export default ContactController