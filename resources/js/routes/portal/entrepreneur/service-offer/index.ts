import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Portal\EntrepreneurServiceOfferController::show
* @see app/Http/Controllers/Portal/EntrepreneurServiceOfferController.php:19
* @route '/portal/entrepreneur/service-offer'
*/
export const show = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/portal/entrepreneur/service-offer',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurServiceOfferController::show
* @see app/Http/Controllers/Portal/EntrepreneurServiceOfferController.php:19
* @route '/portal/entrepreneur/service-offer'
*/
show.url = (options?: RouteQueryOptions) => {
    return show.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurServiceOfferController::show
* @see app/Http/Controllers/Portal/EntrepreneurServiceOfferController.php:19
* @route '/portal/entrepreneur/service-offer'
*/
show.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurServiceOfferController::show
* @see app/Http/Controllers/Portal/EntrepreneurServiceOfferController.php:19
* @route '/portal/entrepreneur/service-offer'
*/
show.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurServiceOfferController::show
* @see app/Http/Controllers/Portal/EntrepreneurServiceOfferController.php:19
* @route '/portal/entrepreneur/service-offer'
*/
const showForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurServiceOfferController::show
* @see app/Http/Controllers/Portal/EntrepreneurServiceOfferController.php:19
* @route '/portal/entrepreneur/service-offer'
*/
showForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurServiceOfferController::show
* @see app/Http/Controllers/Portal/EntrepreneurServiceOfferController.php:19
* @route '/portal/entrepreneur/service-offer'
*/
showForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

/**
* @see \App\Http\Controllers\Portal\EntrepreneurServiceOfferController::store
* @see app/Http/Controllers/Portal/EntrepreneurServiceOfferController.php:36
* @route '/portal/entrepreneur/service-offer'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/portal/entrepreneur/service-offer',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Portal\EntrepreneurServiceOfferController::store
* @see app/Http/Controllers/Portal/EntrepreneurServiceOfferController.php:36
* @route '/portal/entrepreneur/service-offer'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Portal\EntrepreneurServiceOfferController::store
* @see app/Http/Controllers/Portal/EntrepreneurServiceOfferController.php:36
* @route '/portal/entrepreneur/service-offer'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurServiceOfferController::store
* @see app/Http/Controllers/Portal/EntrepreneurServiceOfferController.php:36
* @route '/portal/entrepreneur/service-offer'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Portal\EntrepreneurServiceOfferController::store
* @see app/Http/Controllers/Portal/EntrepreneurServiceOfferController.php:36
* @route '/portal/entrepreneur/service-offer'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

const serviceOffer = {
    show: Object.assign(show, show),
    store: Object.assign(store, store),
}

export default serviceOffer
