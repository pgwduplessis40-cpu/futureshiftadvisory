import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\TemplateController::index
 * @see app/Http/Controllers/Advisor/TemplateController.php:24
 * @route '/advisor/templates'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/templates',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\TemplateController::index
 * @see app/Http/Controllers/Advisor/TemplateController.php:24
 * @route '/advisor/templates'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\TemplateController::index
 * @see app/Http/Controllers/Advisor/TemplateController.php:24
 * @route '/advisor/templates'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\TemplateController::index
 * @see app/Http/Controllers/Advisor/TemplateController.php:24
 * @route '/advisor/templates'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\TemplateController::index
 * @see app/Http/Controllers/Advisor/TemplateController.php:24
 * @route '/advisor/templates'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\TemplateController::index
 * @see app/Http/Controllers/Advisor/TemplateController.php:24
 * @route '/advisor/templates'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\TemplateController::index
 * @see app/Http/Controllers/Advisor/TemplateController.php:24
 * @route '/advisor/templates'
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
* @see \App\Http\Controllers\Advisor\TemplateController::store
 * @see app/Http/Controllers/Advisor/TemplateController.php:61
 * @route '/advisor/templates'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/templates',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\TemplateController::store
 * @see app/Http/Controllers/Advisor/TemplateController.php:61
 * @route '/advisor/templates'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\TemplateController::store
 * @see app/Http/Controllers/Advisor/TemplateController.php:61
 * @route '/advisor/templates'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\TemplateController::store
 * @see app/Http/Controllers/Advisor/TemplateController.php:61
 * @route '/advisor/templates'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\TemplateController::store
 * @see app/Http/Controllers/Advisor/TemplateController.php:61
 * @route '/advisor/templates'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Advisor\TemplateController::show
 * @see app/Http/Controllers/Advisor/TemplateController.php:89
 * @route '/advisor/templates/{template}'
 */
export const show = (args: { template: string | { id: string } } | [template: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/advisor/templates/{template}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\TemplateController::show
 * @see app/Http/Controllers/Advisor/TemplateController.php:89
 * @route '/advisor/templates/{template}'
 */
show.url = (args: { template: string | { id: string } } | [template: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { template: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { template: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    template: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        template: typeof args.template === 'object'
                ? args.template.id
                : args.template,
                }

    return show.definition.url
            .replace('{template}', parsedArgs.template.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\TemplateController::show
 * @see app/Http/Controllers/Advisor/TemplateController.php:89
 * @route '/advisor/templates/{template}'
 */
show.get = (args: { template: string | { id: string } } | [template: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\TemplateController::show
 * @see app/Http/Controllers/Advisor/TemplateController.php:89
 * @route '/advisor/templates/{template}'
 */
show.head = (args: { template: string | { id: string } } | [template: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\TemplateController::show
 * @see app/Http/Controllers/Advisor/TemplateController.php:89
 * @route '/advisor/templates/{template}'
 */
    const showForm = (args: { template: string | { id: string } } | [template: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\TemplateController::show
 * @see app/Http/Controllers/Advisor/TemplateController.php:89
 * @route '/advisor/templates/{template}'
 */
        showForm.get = (args: { template: string | { id: string } } | [template: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\TemplateController::show
 * @see app/Http/Controllers/Advisor/TemplateController.php:89
 * @route '/advisor/templates/{template}'
 */
        showForm.head = (args: { template: string | { id: string } } | [template: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
/**
* @see \App\Http\Controllers\Advisor\TemplateController::update
 * @see app/Http/Controllers/Advisor/TemplateController.php:106
 * @route '/advisor/templates/{template}'
 */
export const update = (args: { template: string | { id: string } } | [template: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/advisor/templates/{template}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Advisor\TemplateController::update
 * @see app/Http/Controllers/Advisor/TemplateController.php:106
 * @route '/advisor/templates/{template}'
 */
update.url = (args: { template: string | { id: string } } | [template: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { template: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { template: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    template: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        template: typeof args.template === 'object'
                ? args.template.id
                : args.template,
                }

    return update.definition.url
            .replace('{template}', parsedArgs.template.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\TemplateController::update
 * @see app/Http/Controllers/Advisor/TemplateController.php:106
 * @route '/advisor/templates/{template}'
 */
update.patch = (args: { template: string | { id: string } } | [template: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Advisor\TemplateController::update
 * @see app/Http/Controllers/Advisor/TemplateController.php:106
 * @route '/advisor/templates/{template}'
 */
    const updateForm = (args: { template: string | { id: string } } | [template: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\TemplateController::update
 * @see app/Http/Controllers/Advisor/TemplateController.php:106
 * @route '/advisor/templates/{template}'
 */
        updateForm.patch = (args: { template: string | { id: string } } | [template: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
const templates = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
show: Object.assign(show, show),
update: Object.assign(update, update),
}

export default templates