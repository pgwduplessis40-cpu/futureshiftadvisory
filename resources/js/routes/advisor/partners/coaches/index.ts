import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::index
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:60
 * @route '/advisor/partners/coaches'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/partners/coaches',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::index
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:60
 * @route '/advisor/partners/coaches'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::index
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:60
 * @route '/advisor/partners/coaches'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::index
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:60
 * @route '/advisor/partners/coaches'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::index
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:60
 * @route '/advisor/partners/coaches'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::index
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:60
 * @route '/advisor/partners/coaches'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::index
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:60
 * @route '/advisor/partners/coaches'
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
* @see \App\Http\Controllers\Advisor\PartnerPanelController::create
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:65
 * @route '/advisor/partners/coaches/invite'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/advisor/partners/coaches/invite',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::create
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:65
 * @route '/advisor/partners/coaches/invite'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::create
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:65
 * @route '/advisor/partners/coaches/invite'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::create
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:65
 * @route '/advisor/partners/coaches/invite'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::create
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:65
 * @route '/advisor/partners/coaches/invite'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::create
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:65
 * @route '/advisor/partners/coaches/invite'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::create
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:65
 * @route '/advisor/partners/coaches/invite'
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
* @see \App\Http\Controllers\Advisor\PartnerPanelController::store
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:70
 * @route '/advisor/partners/coaches/invite'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/partners/coaches/invite',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::store
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:70
 * @route '/advisor/partners/coaches/invite'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::store
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:70
 * @route '/advisor/partners/coaches/invite'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::store
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:70
 * @route '/advisor/partners/coaches/invite'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::store
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:70
 * @route '/advisor/partners/coaches/invite'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
const coaches = {
    index: Object.assign(index, index),
create: Object.assign(create, create),
store: Object.assign(store, store),
}

export default coaches