import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::index
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:45
 * @route '/advisor/partners/brokers'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/advisor/partners/brokers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::index
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:45
 * @route '/advisor/partners/brokers'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::index
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:45
 * @route '/advisor/partners/brokers'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::index
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:45
 * @route '/advisor/partners/brokers'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::index
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:45
 * @route '/advisor/partners/brokers'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::index
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:45
 * @route '/advisor/partners/brokers'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::index
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:45
 * @route '/advisor/partners/brokers'
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
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:50
 * @route '/advisor/partners/brokers/invite'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/advisor/partners/brokers/invite',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::create
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:50
 * @route '/advisor/partners/brokers/invite'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::create
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:50
 * @route '/advisor/partners/brokers/invite'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::create
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:50
 * @route '/advisor/partners/brokers/invite'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::create
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:50
 * @route '/advisor/partners/brokers/invite'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::create
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:50
 * @route '/advisor/partners/brokers/invite'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::create
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:50
 * @route '/advisor/partners/brokers/invite'
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
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:55
 * @route '/advisor/partners/brokers/invite'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/advisor/partners/brokers/invite',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::store
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:55
 * @route '/advisor/partners/brokers/invite'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::store
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:55
 * @route '/advisor/partners/brokers/invite'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::store
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:55
 * @route '/advisor/partners/brokers/invite'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Advisor\PartnerPanelController::store
 * @see app/Http/Controllers/Advisor/PartnerPanelController.php:55
 * @route '/advisor/partners/brokers/invite'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })

    store.form = storeForm
const brokers = {
    index: Object.assign(index, index),
create: Object.assign(create, create),
store: Object.assign(store, store),
}

export default brokers